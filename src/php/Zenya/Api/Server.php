<?php

namespace Zenya\Api;

class Server extends Listener
{
    const VERSION = 'Sleepover/0.2.11';

    private $config = array();
    private $resources = array();

    public function __construct(array $resources=null, array $config=array())
    {

        $c = new Config($config);
        #$c->injet('server', $this);
        $this->config = $c->getConfig();

        // to be passed thru the constructor!!!
        $resources = array(
            
            // 'test' => array(
            //     'class_args'=>array('arg1'=>'value1', 'arg2'=>'string')
            // ),

            'resourceName' => array(
                'class_name' => 'Zenya\Api\Fixtures\BlankResource',
                'class_args' => array('arg1'=>'value1', 'arg2'=>'string')
            ),

            'someName' => array(
                'class_name' => 'Zenya\Api\Fixtures\BlankResource',
                #'class_args' => array('test')
            )

        );

        $this->config['resources'] = $resources;

        $this->request = new Request;
        
        $this->response = new Response(
            $this->request,
            $this->config['sign'],
            $this->config['debug']
        );

        set_error_handler(array('Zenya\Api\Exception', 'errorHandler'));
        register_shutdown_function(array('Zenya\Api\Exception', 'shutdownHandler'));
    }

    public function setRouting()
    {
        // Get path without the route prefix
        $path = preg_replace($this->config['route_prefix'], '', $this->request->getUri());

        $this->route = new Router(
            $this->config['routes'],
            array(
                'method'        => $this->request->getMethod(),
                'path'          => $path,
                'class_name'    => null,
                'class_args'    => &$this, // temp!
            )
        );
        $this->route->map($path, $this->request->getParams());
    }

    public function run()
    {
        $c = &$this->config;

        try {
    
            // Routing
            $this->setRouting();

            // add the resources
            foreach($c['resources']+$c['resources_default'] as $key => $values) {
                $this->addResource($key, $values);
            }

            // Set and sanittize the format of the response...
            $this->negotiateFormat($c['format_negotiation']);

            // if($c['format_negotiation']['http_accept']) {
            //     // $this->response->setHeader('Vary', 'Accept');
            // }

            // attach the early listeners @ pre-processing stage
            $this->addAllListeners('server', 'early');

            // Process with the requested resource
            $this->resource = new Resource($this->route);
            
            $this->results = $this->resource->call(
                $this->getResource(
                    $this->route->getControllerName()
                )
            );

        } catch (\Exception $e) {

            if( !in_array($this->route->getControllerName(), array_keys($this->getResources())) ) {
                $this->route->setControllerName('error');
                $this->results[] = $e->getMessage();
            } else {
                $this->results['error'] = $e->getMessage();
            }
            
            $this->response->setHttpCode(
                $e->getCode()>199 ? $e->getCode() : 500
            );

            // attach the listeners @ exception stage
            $this->addAllListeners('server', 'exception');
        }

        switch ($this->response->getHttpCode()) {
            case 401;
                // TODO
                #$this->response->setHeader('WWW-Authenticate',
                #    sprintf('%s realm="%s"', $this->config['auth']['type'], $this->config['org'])
                #);
            break;

            case 405:
                $this->response->setHeader('Allow',
                    implode(', ', array_keys($this->resource->actions)),
                    false // preserve existing
                );
        }

        $output = $this->response->generate(
                    $this->route->getControllerName(),
                    $this->results,
                    sprintf("%s/%s #%s", $this->config['org'], $this->config['version'], self::VERSION),
                    $this->config['rootNode']
                );

        // attach the late listeners @ post-processing stage
        $this->addAllListeners('server', 'late');

        return $this->route->getMethod() != 'HEAD' ? $output : null;
    }

    /**
     * Returns the output format from the request chain.
     * Options are:
     *  - [default] => value e.g. json
     *  - [controller_ext] => boolean
     *  - [request_chain] => such as $_REQUEST['format'] or false
     *  - [http_accept] => boolean
     * @param array $config
     * @return string
     */
    public function negotiateFormat(array $opts)
    {
        switch(true) {
            case $opts['controller_ext']
                && $extract = $this->extractExtension($this->route->getControllerName()):
                    $this->route->setControllerName($extract[0]);
                    $format = $extract[1];
            break;

            case false !== $opts['request_chain']
                && $format = $opts['request_chain']:
            break;

            case $opts['http_accept']
                && $format = $this->getFormatFromHttpAccept(
                        $this->request
                    ):
                $this->response->setHeader('Vary', 'Accept');
            break;

            default:
                $format = $opts['default'];
        }

        $this->response->setFormat($format, $opts['default']);
    }

    /**
     * Returns the output format from controller.
     *
     * @param string A string ending wiht an extension
     * @return string|false The output format
     */
    public function extractExtension($name)
    {
        $name = explode('.', $name);

        return count($name)>1 && end($name)!=null
            ? array($name[0], end($name))
            : false;
    }

    /**
     * Returns the output format from an HTTP Accept.
     *
     * @param Request $request
     * @return string The output format
     */
    public function getFormatFromHttpAccept(Request $request)
    {
        if ($request->hasHeader('HTTP_ACCEPT')) {
            $accept = $request->getHeader('HTTP_ACCEPT');
            
            switch (true) {
                // 'application/json'
                case (strstr($accept, '/json')):
                    $format ='json';
                break;

                // 'text/xml', 'application/xml'
                case (strstr($accept, '/xml')
                    && (!strstr($accept, 'html'))):
                    $format = 'xml';
            }
        }
        return isset($format) ? $format : false;
    }

    /**
     * Get the output/results.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }

    /**
     * Gets a ressource.
     *
     * @param   string $name A resource name
     * @return  string
     * @throws  \InvalidArgumentException    404
     */
    public function getResource($name)
    {
        if (!isset($this->resources[$name])) {
            throw new \InvalidArgumentException(sprintf("Invalid resource's name specified (%s).", $name), 404);
        }

        return $this->resources[$name];
    }

    /**
     * Adds a resource, sanitize, etc...
     *
     * @param string $name The resource name
     * @param array $resource the resource array
     * @return void
     */
    public function addResource($name, array $resource)
    {
        if (! isset($resource['class_name']) ) {
            $resource['class_name'] = '\stdClass';
            //throw new \InvalidArgumentException("todo: Resource missing a class name.", 500);
        }

        $class = new \stdClass;
        $class->name = $resource['class_name'];

        $class->args = isset($resource['class_args'])
            ? $resource['class_args']    // use provided
            : $this->route->class_args;  // use route's default

        $this->resources[$name] = $class;
    }

    /**
     * Gets all the resources.
     *
     * @return array An array of resources
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * Temp Debug
     */
    public static function d($mix)
    {
        echo '<pre>';
        print_r($mix);
        echo '</pre>';
    }

}