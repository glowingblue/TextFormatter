<?php

/**
* @package   s9e\Toolkit
* @copyright Copyright (c) 2010-2011 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\Toolkit\TextFormatter\Plugins;

use Exception,
    InvalidArgumentException,
	RuntimeException,
    s9e\Toolkit\TextFormatter\ConfigBuilder,
    s9e\Toolkit\TextFormatter\PluginConfig;

/**
* NOTE: does not support duplicate named captures
*/
class GenericConfig extends PluginConfig
{
	/**
	* @var array Associative array of regexps. The keys are the corresponding tag names
	*/
	protected $regexp = array();

	/**
	* Add a generic replacement
	*
	* @param  string $regexp
	* @param  string $template
	* @return string           The name of the tag
	*/
	public function addReplacement($regexp, $template)
	{
		$valid = false;

		try
		{
			$valid = @preg_match_all($regexp, '', $m);
		}
		catch (Exception $e)
		{
		}

		if ($valid === false)
		{
			throw new InvalidArgumentException('Invalid regexp');
		}

		/**
		* Tag options, will store attributes and template
		*/
		$tagOptions = array(
			'template' => $template
		);

		/**
		* Parse the regexp, and generate an attribute for every named capture
		*/
		$regexpInfo = ConfigBuilder::parseRegexp($regexp);

		$attrs = array();

		foreach ($regexpInfo['tokens'] as $tok)
		{
			if ($tok['type'] === 'capturingSubpatternStart'
			 && isset($tok['name']))
			{
				$attrName = $tok['name'];

				if (isset($tagOptions['attrs'][$attrName]))
				{
					throw new RuntimeException('Duplicate named subpatterns are not allowed');
				}

				$lpos = $tok['pos'];
				$rpos = $regexpInfo['tokens'][$tok['endToken']]['pos']
				      + $regexpInfo['tokens'][$tok['endToken']]['len'];

				$attrRegexp = $regexpInfo['delimiter']
				            . '^' . substr($regexpInfo['regexp'], $lpos, 1 + $rpos - $lpos) . '$'
				            . $regexpInfo['delimiter']
				            . $regexpInfo['modifiers']
				            . 'D';

				$tagOptions['attrs'][$attrName] = array(
					'type'       => 'regexp',
					'regexp'     => $attrRegexp,
					'isRequired' => true
				);
			}
		}

		/**
		* Generate a tag name based on the regexp
		*/
		$tagName = 'G' . strtr(dechex(crc32($regexp)), 'abcdef', 'ABCDEF');

		/**
		* Create the tag
		*/
		$this->cb->addTag($tagName, $tagOptions);

		/**
		* Finally, record the replacement
		*/
		$this->regexp[$tagName] = $regexp;

		return $tagName;
	}

	public function getConfig()
	{
		if (empty($this->regexp))
		{
			return false;
		}

		return array('regexp' => $this->regexp);
	}
}