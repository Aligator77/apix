<?php
namespace Apix\View;

use Apix\View\ViewModel;
use Apix\View\Template as Template;

abstract class Template
{

	/**
	 * Default template engine name.
	 * @var  string
	 */
	public static $engine = 'Apix\View\Template\Mustache';

	/**
	 * The name of the template layout.
     * @var string
	 */
	protected $layout = 'default';

	/**
	 * Renders the model view into the template layout.
	 *
	 * @param  ViewModel  $view
	 * @abstract
	 */
	abstract public function render(ViewModel $view);

    /**
     * Sets the template engine object.
     *
     * Returns the object instance, as it is its own template engine
     *
     * @return Template
     */
	static final public function setEngine($name=null)
	{
		$class = __NAMESPACE__ . '\\Template';
		$class .= null === $name ? : '\\' . $name;
		if(!class_exists($class)) {
			throw new \RuntimeException(
				sprintf('Template class "%s" does not exist.', $class)
			);
		}
		Template::$engine = $class;
	}

    /**
     * Returns the template engine object.
     *
     * Returns the object instance, as it is its own template engine
     *
     * @return Template
     */
	static final public function getEngine($name=null)
	{
		if(null !== $name) {
			Template::setEngine($name);
		}

		return new Template::$engine;
	}

	/**
	 * Sets the name of the template layout.
	 *
	 * @param  string $layout
	 */
	public function setLayout($layout)
	{
		$this->layout = $layout;
	}

}