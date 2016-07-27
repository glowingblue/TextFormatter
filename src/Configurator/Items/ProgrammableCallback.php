<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2016 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Configurator\Items;

use InvalidArgumentException;
use s9e\TextFormatter\Configurator\ConfigProvider;
use s9e\TextFormatter\Configurator\Helpers\ConfigHelper;
use s9e\TextFormatter\Configurator\JavaScript\Code;
use s9e\TextFormatter\Configurator\JavaScript\FunctionProvider;

class ProgrammableCallback implements ConfigProvider
{
	/**
	* @var callback Callback
	*/
	protected $callback;

	/**
	* @var string JavaScript source code for this callback
	*/
	protected $js = 'returnFalse';

	/**
	* @var array List of params to be passed to the callback
	*/
	protected $params = [];

	/**
	* @var array Variables associated with this instance
	*/
	protected $vars = [];

	/**
	* @param callable $callback
	*/
	public function __construct($callback)
	{
		if (!is_callable($callback))
		{
			throw new InvalidArgumentException(__METHOD__ . '() expects a callback');
		}

		$this->callback = $this->normalizeCallback($callback);
		$this->autoloadJS();
	}

	/**
	* Add a parameter by value
	*
	* @param  mixed $paramValue
	* @return self
	*/
	public function addParameterByValue($paramValue)
	{
		$this->params[] = $paramValue;

		return $this;
	}

	/**
	* Add a parameter by name
	*
	* The value will be dynamically generated by the caller
	*
	* @param  string $paramName
	* @return self
	*/
	public function addParameterByName($paramName)
	{
		if (array_key_exists($paramName, $this->params))
		{
			throw new InvalidArgumentException("Parameter '" . $paramName . "' already exists");
		}

		$this->params[$paramName] = null;

		return $this;
	}

	/**
	* Get this object's callback
	*
	* @return callback
	*/
	public function getCallback()
	{
		return $this->callback;
	}

	/**
	* Get this callback's JavaScript
	*
	* @return string
	*/
	public function getJS()
	{
		return $this->js;
	}

	/**
	* Get this object's variables
	*
	* @return array
	*/
	public function getVars()
	{
		return $this->vars;
	}

	/**
	* Remove all the parameters
	*
	* @return self
	*/
	public function resetParameters()
	{
		$this->params = [];

		return $this;
	}

	/**
	* Set this callback's JavaScript
	*
	* @param  string $js JavaScript source code for this callback
	* @return self
	*/
	public function setJS($js)
	{
		$this->js = $js;

		return $this;
	}

	/**
	* Set or overwrite one of this callback's variable
	*
	* @param  string $name  Variable name
	* @param  string $value Variable value
	* @return self
	*/
	public function setVar($name, $value)
	{
		$this->vars[$name] = $value;

		return $this;
	}

	/**
	* Set all of this callback's variables at once
	*
	* @param  array $vars Associative array of values
	* @return self
	*/
	public function setVars(array $vars)
	{
		$this->vars = $vars;

		return $this;
	}

	/**
	* {@inheritdoc}
	*/
	public function asConfig()
	{
		$config = ['callback' => $this->callback];

		foreach ($this->params as $k => $v)
		{
			if (is_numeric($k))
			{
				// By value
				$config['params'][] = $v;
			}
			elseif (isset($this->vars[$k]))
			{
				// By name, but the value is readily available in $this->vars
				$config['params'][] = $this->vars[$k];
			}
			else
			{
				// By name
				$config['params'][$k] = null;
			}
		}

		if (isset($config['params']))
		{
			$config['params'] = ConfigHelper::toArray($config['params'], true, true);
		}

		// Add the callback's JavaScript representation
		$config['js'] = new Code($this->js);

		return $config;
	}

	/**
	* Try to load the JavaScript source for this callback
	*
	* @return void
	*/
	protected function autoloadJS()
	{
		if (!is_string($this->callback))
		{
			return;
		}

		try
		{
			$this->js = FunctionProvider::get($this->callback);
		}
		catch (InvalidArgumentException $e)
		{
			// Do nothing
		}
	}

	/**
	* Normalize a callback's representation
	*
	* @param  callable $callback
	* @return callable
	*/
	protected function normalizeCallback($callback)
	{
		// Normalize ['foo', 'bar'] to 'foo::bar'
		if (is_array($callback) && is_string($callback[0]))
		{
			$callback = $callback[0] . '::' . $callback[1];
		}

		// Normalize '\\foo' to 'foo' and '\\foo::bar' to 'foo::bar'
		if (is_string($callback))
		{
			$callback = ltrim($callback, '\\');
		}

		return $callback;
	}
}