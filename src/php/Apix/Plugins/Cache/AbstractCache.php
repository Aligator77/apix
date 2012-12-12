<?php
namespace Apix\Plugins\Cache;

abstract class AbstractCache implements Adapter
{
    protected $adapter;

    protected $options = array(
        'prefix_key' => 'apix-cache-key:', // prefix cache keys
        'prefix_tag' => 'apix-cache-tag:', // prefix cache tags
    );

    /**
     * Constructor.
     *
     * @param object|null $adapter Generally an object.
     * @param array       $options Array of options.
     */
    public function __construct($adapter=null, array $options=null)
    {
        $this->adapter = $adapter;

        if (null !== $options) {
            $this->options = $options+$this->options;
        }
    }

    /**
     * Returns a prefixed and sanitased cache id.
     *
     * @param  string $key The base key to prefix.
     * @return string
     */
    public function mapKey($key)
    {
        return $this->sanitise($this->options['prefix_key'] . $key);
    }

    /**
     * Returns a prefixed and sanitased cache tag.
     *
     * @param  string $tag The base tag to prefix.
     * @return string
     */
    public function mapTag($tag)
    {
        return $this->sanitise($this->options['prefix_tag'] . $tag);
    }

    /**
     * Returns a sanitased string keying/tagging purpose.
     *
     * @param  string $key The string to sanitise.
     * @return string
     */
    public function sanitise($key)
    {
        return $key;
        // return str_replace(array('/', '\\', ' '), '_', $key);
    }

}
