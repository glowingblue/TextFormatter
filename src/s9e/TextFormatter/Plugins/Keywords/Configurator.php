<?php

/**
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2013 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Plugins\Keywords;

use InvalidArgumentException;
use s9e\TextFormatter\Configurator\Collections\NormalizedList;
use s9e\TextFormatter\Configurator\Helpers\RegexpBuilder;
use s9e\TextFormatter\Configurator\Items\Regexp;
use s9e\TextFormatter\Configurator\Traits\CollectionProxy;
use s9e\TextFormatter\Plugins\ConfiguratorBase;

class Configurator extends ConfiguratorBase
{
	use CollectionProxy;

	/**
	* @var string Name of the attribute used by this plugin
	*/
	protected $attrName = 'value';

	/**
	* @var NormalizedCollection List of [keyword => value]
	*/
	protected $collection;

	/**
	* @var string Name of the tag used by this plugin
	*/
	protected $tagName = 'KEYWORD';

	/**
	* {@inheritdoc}
	*/
	protected function setUp()
	{
		$this->collection = new NormalizedList;

		$this->configurator->tags->add($this->tagName)->attributes->add($this->attrName);
	}

	/**
	* {@inheritdoc}
	*/
	public function asConfig()
	{
		if (!count($this->collection))
		{
			return false;
		}

		$config = parent::asConfig();

		// Remove the collection from the config
		$keywords = $config['collection'];
		unset($config['collection']);

		// Sort keywords in order to keep keywords that start with the same characters together. We
		// also remove duplicates that would otherwise skew the length computation done below
		$keywords = array_unique($keywords);
		sort($keywords);

		// Group keywords by chunks of ~30KB to remain below PCRE's limit
		$groups   = [];
		$groupKey = 0;
		$groupLen = 0;
		foreach ($keywords as $keyword)
		{
			// NOTE: the value 4 is a guesstimate for the cost of each alternation
			$keywordLen  = 4 + strlen($keyword);
			$groupLen   += $keywordLen;

			if ($groupLen > 30000)
			{
				$groupLen = $keywordLen;
				++$groupKey;
			}

			$groups[$groupKey][] = $keyword;
		}

		foreach ($groups as $keywords)
		{
			$regexp = '/\\b' . RegexpBuilder::fromList($keywords) . '\\b/S';
			$config['regexps'][] = new Regexp($regexp, true);
		}

		return $config;
	}
}