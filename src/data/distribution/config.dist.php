<?php
// This is the Apix config.dist.php file
// -------------------------------------
//
// You should NOT edit this file! Instead, put any overrides into a local copy.
// The local file should only contain values which override values set in the
// distribution file. This eases the upgrade path when defaults are changed and
// new features are added. Beware this file is upgraded regularly.

namespace Apix;

// Define the DEBUG constant. Should be set to false in production.
if(!defined('DEBUG')) define('DEBUG', true);

$c = array(

    // The API version string allowing users to keep track of API changes. It is
    // defined as major.minor.maintenance[.build] where:
    //  - Major: Increase for each changes that may affect or not be compatible
    // with a previous version of the API. Bumping the major generally imply a
    // fresh new production deployment so the previous version can (and should)
    // be left intact for those that depend upon it.
    // - Minor: Increases each time there are new addition e.g. a new resource.
    // - Maintenance: Increases each time there are modifications to existing
    // resource entities and which don't break exisiting definitions.
    // - Build: Can be use for arbitrary naming such as 0.1.0.beta, 0.1.1.rc3
    // (third release candidate), 0.1.2.smoking-puma, 0.1.30.testing
    'api_version'       => '0.1.0.empty-dumpty',

    // The API realm name. Used in few places, most notably as part of the
    // version string in the header response. It is also used as part of some
    // authentication mechanisms e.g. Basic and Digest. Should always be a
    // generic/static string and cannot be used to define server instance. In
    // other words, DO NOT use $_SERVER['SERVER_NAME'] to set this option!
    'api_realm'         => 'api.domain.tld',

    // Define the name of the data output topmost node which contains the
    // various nodes generated by the response output. The signature and debug
    // nodes (may) also live within that node if enable further down below.
    'output_rootNode'   => 'apix',

    // The array of available data formats for input representation:
    // - POST: Body post data.
    // - JSON: Light text-based open standard designed for human-readable data
    // interchange.
    // - XML: Generic and standard markup language as defined by XML 1.0 schema.
    // Note that at this stage only UTF-8 is supported. Later more XML based
    // schema can be implemented if required by clients.
    'input_formats'     => array('post', 'json', 'xml'),

    // routing definitions
    'routing'           => array(

        // The regular expression representing the path prefix from the Request-
        // URI. Allows notably to retrieve the path without the route prefix,
        // handling variation in version numbering, Apache's mod_rewrite, etc...
        // Should match '/index.php/api/v1/entity/name?whatver...' which using
        // mod_rewrite could then translates into
        // 'http://api.domain.tld/v1/entity/name?whatver...'.
        'path_prefix'       => '/^(\/\w+\.\w+)?(\/api)?\/v(\d+)/i',

        // The array of available data formats for output representation:
        // - JSON: Light text-based open standard designed for human-readable
        // data interchange.
        // - XML: Generic and standard markup language as defined by the XML 1.0
        // specification. Again, other schema could be implemented if required.
        // - JSONP: Output JSON embeded within a javascript callback. Javascript
        // clients can set the callback name using the GET/POST variable named
        // 'callback' or default to the 'output_rootNode' value set above.
        // - HTML: Output an HTML bulleted list.
        // - PHP: Does not currently serialize the data as one would expect but
        // just dump the output array for now.
        'formats'           => array('json', 'xml', 'jsonp', 'html', 'php'),

        // Set the defaut output format to either JSON or XML. Note that JSON
        // encoding is by definition UTF-8 only. If a specific encoding is
        // required then XML should be used as the default format. In most
        // case, JSON is favored.
        'default_format'    => 'json',

        // Wether to enable the negotiation of output format from an HTTP accept
        // header. This is the expected and most RESTful way to set the
        // output format. http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
        'http_accept'       => true,

        // Wether to allow the output format to be set from the Request-URI
        // using a file extension such as '/controller.json/id'.
        // This is handy and common practice but fairly un-RESTful...
        // The extension overrides the http_accept negotiation.
        'controller_ext'    => true,

        // Forces the output format to the string provided and overrides the
        // format negotiation process. Set to false to disable. Can be use to
        // set the format from a request parameter, or any other arbitrary
        // methods, etc... Using REQUEST is considered un-RESTful but also can
        // be handy in many cases e.g. forms handling.
        'format_override'   => isset($_REQUEST['_format'])
                                ? $_REQUEST['_format']
                                : false,
    )

);

// Resources definitions
// ---------------------
// A resource definition is made of a 'Route path' (with or without named
// variable) poiting to a controller which are define as:
// - closure/lambda definitions (à la Sinatra): allowing fast prototyping.
// - class definition, e.g.
//      '/keywords/:keyword' => array(
//          'controller' => array(
//              'name' =>   'Apix\Stack\Controller\Keywords',
//                          a namespace\classname as a string)
//              'args' =>   array('classArg1'=>'value1', 'classArg2'=>'string')
//                          a __constructor variables as an array or null.
//          )
//      ),
// - redirect, e.g.
//      '/somewhere' => array(
//          'redirect'  =>  '/to/somewhere/else'
//      )
//
// See the server guide for more details on the subject.
$c['resources'] = array(

    // Handles GET /help/path/to/resource
    '/help/:path' => array(
        'redirect' => 'OPTIONS'
    ),

    // As per RFC2616 section 9.2
    // see http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.2
    '/*' => array(
        'redirect' => 'OPTIONS',
    )

);

// Service definitions
// -------------------
// The service defintions array is mostly use as a convenient container to
// define some generic/shared code...
$c['services'] = array(

    // Auth examples (see plugins definition)
    'auth' => function() use ($c) {
        $basic = false; // set to: False to use Digest, True to use Basic.
        if ($basic) {
            // Example implementing Plugins\Auth\Basic'
            // ----------------------------------------
            // The Basic Authentification mechanism is generally use with SSL.
            $adapter = new Plugins\Auth\Basic($c['api_realm']);
            $adapter->setToken(function(array $current) use ($c) {
                $users = Services::get('users');
                foreach ($users as $user) {
                    if ($current['username'] == $user['user']
                        && $current['password'] == $user['api_key']) {
                        return true;
                    }
                }
                return false;
            });
        } else {
            // Example implementing 'Plugins\Auth\Digest'
            // -------------------------------------------
            // The Digest Authentification mechanism is use to encrypt and salt
            // the user's credentials without the overhead of SSL.
            $adapter = new Plugins\Auth\Digest($c['api_realm']);
            $adapter->setToken(function(array $current) use ($c) {
                $users = Services::get('users');
                foreach ($users as $user) {
                if ($user['user'] == $current['username']
                    && $user['realm'] == $c['api_realm']) {
                        // Digest match againt this token!
                        return $user['api_key'];
                    }
                }
                return false;
            });
        }

        return $adapter;
    },

    // Returns a user array. This is used by the authentification plugins above.
    // TODO: JON to retrieve the users generic schema and data set from Magento.
    'users' => function() {
        // username:password:sharedSecret:role:realm
        return array(
            0 => array(
                'user' => 'franck', 'password' => 'pass', 'api_key' => '1234',
                'role' => 'admin', 'realm' => 'api.domain.tld'
            ),
            1 => array(
                'user' => 'test', 'password' => 'sesame', 'api_key' => '123abc',
                'role' => 'guest', 'realm' => 'api.domain.tld'
            )
        );
    }

);

// Plugins definitions
// -------------------
$c['plugins'] = array(

    // Add the entity signature as part of the response-body.
    'Apix\Plugins\OutputSign',

    // Add some debugging information within the response-body.
    // Should be set to false in production. This plugin affects cachability.
    'Apix\Plugins\OutputDebug' => array('enable' => DEBUG),

    // Validate, correct, and pretty-print XML and HTML outputs. Many options
    // are available (see Tidy::$options)
    'Apix\Plugins\Tidy',

    // Autentification (with basic ACL) plugin
    'Apix\Plugins\Auth' => array('adapter' => $c['services']['auth']),

    // Plugin to cache the output of the controllers. The full Request-URI acts
    // as the unique cache id.
    'Apix\Plugins\Cache' => array(
        'enable' => DEBUG,
    )
);

// Init is an associative array of specific PHP directives. They are
// recommended settings for most generic REST API server and should be set
// as required. There is most probably a performance penalty setting most of
// these at runtime so it is recommneded that most of these (if not all) be
// set directly in PHP.ini/vhost file on productions servers -- and then
// commented out. TODO: comparaison benchmark!?
$c['init'] = array(
    // Weter to display errors (should be set to false in production).
    'display_errors'            => DEBUG,

    // Enable or disable php error logging.
    'init_log_errors'           => true,

    // Path to the error log file.
    'error_log'                 => '/tmp/apix-server-errors.log',

    /////////////////////////////////////////////////////////////////////
    // Anything below should be set in PHP.ini on productions servers. //
    /////////////////////////////////////////////////////////////////////

    // Enable or disable html_errors
    'html_errors'               => false,

    // Whether to transparently compress outputs using GZIP.
    // Once enable, this will also add a 'Vary: Accept-Encoding' header.
    'zlib.output_compression'   => true,

    // Maximum amount of memory a script may consume.
    'memory_limit'              => '64M',

    // The timeout in seconds.
    // BEWARE web servers such as Apache have also their own timout settings
    // that may interfere. See your web server manual for specific details.
    'max_execution_time'        => 15,

    ////////////////////////////////////////////////////////////////////////
    // These below might not always get set depending on the environment. //
    // Consider setting these in PHP.ini on productions servers...        //
    ////////////////////////////////////////////////////////////////////////

    // Maximum amount of time each script may spend parsing request data.
    'post_max_size'             => '8M',

    // Maximum amount of time each script may spend parsing request data.
    'max_input_time'            => 30,

    // Maximum amount of GET/POST input variables.
    'max_input_vars'            => 100,

    // Maximum input variable nesting level.
    'max_input_nesting_level'   => 64,

    // Determines which super global are registered and in which order these
    // variables are then populated.
    'variables_order'           => 'GPS',
    'request_order'             => 'GP'
);

///////////////////////////////////////////////////////////////
// Anything below that point should not need to be modified. //
///////////////////////////////////////////////////////////////

$c['default'] = array(
    'services' => array(),
    'resources' => array(
        // The OPTIONS method represents a request for information about the
        // communication options available on the request/response chain
        // identified by the Request-URI. This method allows the client to
        // determine the options and/or requirements associated with a resource,
        // or the capabilities of a server, without implying a resource action
        // or initiating a resource retrieval.
        'OPTIONS' => array(
            'controller' => array(
                'name' => __NAMESPACE__ . '\Resource\Help',
                'args' => null
            ),
        ),
        // representing HTTP HEAD
        'HEAD' => array(
            'controller' => array(
                'name' => __NAMESPACE__ . '\Resource\Test',
                'args' => null
            ),
        ),
    )
);

$c['config_path'] = __DIR__;

return $c;
