<?php

/*
* @package   s9e\TextFormatter
* @copyright Copyright (c) 2010-2014 The s9e Authors
* @license   http://www.opensource.org/licenses/mit-license.php The MIT License
*/
namespace s9e\TextFormatter\Configurator\RendererGenerators\PHP;

use RuntimeException;

class XPathConvertor
{
	protected $customXPath = [
		"contains('upperlowerdecim',substring(@type,1,5))"
			=> "strpos('upperlowerdecim',substr(\$node->getAttribute('type'),0,5))!==false",

		"substring('songWw',6-5*boolean(@songid),5)"
			=> "(\$node->hasAttribute('songid')?'songW':'w')",

		"400-360*boolean(@songid)"
			=> "(\$node->hasAttribute('songid')?40:400)"
	];

	public $useMultibyteStringFunctions = \false;

	public function convertCondition($expr)
	{
		$expr = \trim($expr);

		if (\preg_match('#^@([-\\w]+)$#', $expr, $m))
			return '$node->hasAttribute(' . \var_export($m[1], \true) . ')';

		if (\preg_match('#^not\\(@([-\\w]+)\\)$#', $expr, $m))
			return '!$node->hasAttribute(' . \var_export($m[1], \true) . ')';

		if (\preg_match('#^\\$(\\w+)$#', $expr, $m))
			return '!empty($this->params[' . \var_export($m[1], \true) . '])';

		if (\preg_match('#^not\\(\\$(\\w+)\\)$#', $expr, $m))
			return 'empty($this->params[' . \var_export($m[1], \true) . '])';

		if (!\preg_match('#[=<>]|\\bor\\b|\\band\\b|^[-\\w]+\\s*\\(#', $expr))
			$expr = 'boolean(' . $expr . ')';

		return $this->convertXPath($expr);
	}

	public function convertXPath($expr)
	{
		$expr = \trim($expr);

		if (isset($this->customXPath[$expr]))
			return $this->customXPath[$expr];

		if (\preg_match($this->getXPathRegexp(), $expr, $m))
		{
			$methodName = \null;
			foreach ($m as $k => $v)
			{
				if (\is_numeric($k) || $v === '' || !\method_exists($this, $k))
					continue;

				$methodName = $k;
				break;
			}

			if (isset($methodName))
			{
				$args = [$m[$methodName]];

				$i = 0;
				while (isset($m[$methodName . $i]))
				{
					$args[$i] = $m[$methodName . $i];
					++$i;
				}

				return \call_user_func_array([$this, $methodName], $args);
			}
		}

		if (!\preg_match('#[=<>]|\\bor\\b|\\band\\b|^[-\\w]+\\s*\\(#', $expr))
			$expr = 'string(' . $expr . ')';

		return '$this->xpath->evaluate(' . $this->exportXPath($expr) . ',$node)';
	}

	protected function attr($attrName)
	{
		return '$node->getAttribute(' . \var_export($attrName, \true) . ')';
	}

	protected function dot()
	{
		return '$node->textContent';
	}

	protected function param($paramName)
	{
		return '$this->params[' . \var_export($paramName, \true) . ']';
	}

	protected function string($string)
	{
		return \var_export(\substr($string, 1, -1), \true);
	}

	protected function lname()
	{
		return '$node->localName';
	}

	protected function name()
	{
		return '$node->nodeName';
	}

	protected function number($number)
	{
		return "'" . $number . "'";
	}

	protected function strlen($expr)
	{
		if ($expr === '')
			$expr = '.';

		$php = $this->convertXPath($expr);

		return ($this->useMultibyteStringFunctions)
			? 'mb_strlen(' . $php . ",'utf-8')"
			: "strlen(preg_replace('(.)us','.'," . $php . '))';
	}

	protected function contains($haystack, $needle)
	{
		return '(strpos(' . $this->convertXPath($haystack) . ',' . $this->convertXPath($needle) . ')!==false)';
	}

	protected function startswith($string, $substring)
	{
		return '(strpos(' . $this->convertXPath($string) . ',' . $this->convertXPath($substring) . ')===0)';
	}

	protected function not($expr)
	{
		return '!(' . $this->convertCondition($expr) . ')';
	}

	protected function notcontains($haystack, $needle)
	{
		return '(strpos(' . $this->convertXPath($haystack) . ',' . $this->convertXPath($needle) . ')===false)';
	}

	protected function substr($exprString, $exprPos, $exprLen = \null)
	{
		if (!$this->useMultibyteStringFunctions)
		{
			$expr = 'substring(' . $exprString . ',' . $exprPos;
			if (isset($exprLen))
				$expr .= ',' . $exprLen;
			$expr .= ')';

			return '$this->xpath->evaluate(' . $this->exportXPath($expr) . ',$node)';
		}

		$php = 'mb_substr(' . $this->convertXPath($exprString) . ',';

		if (\is_numeric($exprPos))
			$php .= \max(0, $exprPos - 1);
		else
			$php .= 'max(0,' . $this->convertXPath($exprPos) . '-1)';

		$php .= ',';

		if (isset($exprLen))
			if (\is_numeric($exprLen))
				if (\is_numeric($exprPos) && $exprPos < 1)
					$php .= \max(0, $exprPos + $exprLen - 1);
				else
					$php .= \max(0, $exprLen);
			else
				$php .= 'max(0,' . $this->convertXPath($exprLen) . ')';
		else
			$php .= 'null';

		$php .= ",'utf-8')";

		return $php;
	}

	protected function cmp($expr1, $operator, $expr2)
	{
		$operands  = [];
		$operators = [
			'='  => '===',
			'!=' => '!==',
			'>'  => '>',
			'>=' => '>=',
			'<'  => '<',
			'<=' => '<='
		];

		foreach ([$expr1, $expr2] as $expr)
			if (\is_numeric($expr))
			{
				$operators['=']  = '==';
				$operators['!='] = '!=';

				$operands[] = \ltrim($expr, '0');
			}
			else
				$operands[] = $this->convertXPath($expr);

		return \implode($operators[$operator], $operands);
	}

	protected function bool($expr1, $operator, $expr2)
	{
		$operators = [
			'and' => '&&',
			'or'  => '||'
		];

		return $this->convertCondition($expr1) . $operators[$operator] . $this->convertCondition($expr2);
	}

	protected function parens($expr)
	{
		return '(' . $this->convertXPath($expr) . ')';
	}

	protected function translate($str, $from, $to)
	{
		\preg_match_all('(.)su', \substr($from, 1, -1), $matches);
		$from = $matches[0];

		\preg_match_all('(.)su', \substr($to, 1, -1), $matches);
		$to = $matches[0];

		if (\count($to) > \count($from))
			$to = \array_slice($to, 0, \count($from));
		else
			while (\count($from) > \count($to))
				$to[] = '';

		$from = \array_unique($from);
		$to   = \array_intersect_key($to, $from);

		$php = 'strtr(' . $this->convertXPath($str) . ',';

		if ([1] === \array_unique(\array_map('strlen', $from))
		 && [1] === \array_unique(\array_map('strlen', $to)))
			$php .= \var_export(\implode('', $from), \true) . ',' . \var_export(\implode('', $to), \true);
		else
		{
			$php .= '[';

			$cnt = \count($from);
			for ($i = 0; $i < $cnt; ++$i)
			{
				if ($i)
					$php .= ',';

				$php .= \var_export($from[$i], \true) . '=>' . \var_export($to[$i], \true);
			}

			$php .= ']';
		}

		$php .= ')';

		return $php;
	}

	protected function math($expr1, $operator, $expr2)
	{
		if (!\is_numeric($expr1))
			$expr1 = $this->convertXPath($expr1);

		if (!\is_numeric($expr2))
			$expr2 = $this->convertXPath($expr2);

		return $expr1 . $operator . $expr2;
	}

	protected function exportXPath($expr)
	{
		$phpTokens = [];
		$pos = 0;
		$len = \strlen($expr);
		while ($pos < $len)
		{
			if ($expr[$pos] === "'" || $expr[$pos] === '"')
			{
				$nextPos = \strpos($expr, $expr[$pos], 1 + $pos);
				if ($nextPos === \false)
					throw new RuntimeException('Unterminated string literal in XPath expression ' . \var_export($expr, \true));

				$phpTokens[] = \var_export(\substr($expr, $pos, $nextPos + 1 - $pos), \true);

				$pos = $nextPos + 1;

				continue;
			}

			if ($expr[$pos] === '$' && \preg_match('/\\$(\\w+)/', $expr, $m, 0, $pos))
			{
				$phpTokens[] = '$this->getParamAsXPath(' . \var_export($m[1], \true) . ')';
				$pos += \strlen($m[0]);

				continue;
			}

			$spn = \strcspn($expr, '\'"$', $pos);
			if ($spn)
			{
				$phpTokens[] = \var_export(\substr($expr, $pos, $spn), \true);
				$pos += $spn;
			}
		}

		return \implode('.', $phpTokens);
	}

	protected function getXPathRegexp()
	{
		static $regexp;

		if (isset($regexp))
			return $regexp;

		$patterns = [
			'attr'      => ['@', '(?<attr0>[-\\w]+)'],
			'dot'       => '\\.',
			'name'      => 'name\\(\\)',
			'lname'     => 'local-name\\(\\)',
			'param'     => ['\\$', '(?<param0>\\w+)'],
			'string'    => '"[^"]*"|\'[^\']*\'',
			'number'    => ['-?', '\\d++'],
			'strlen'    => ['string-length', '\\(', '(?<strlen0>(?&value)?)', '\\)'],
			'contains'  => [
				'contains',
				'\\(',
				'(?<contains0>(?&value))',
				',',
				'(?<contains1>(?&value))',
				'\\)'
			],
			'translate' => [
				'translate',
				'\\(',
				'(?<translate0>(?&value))',
				',',
				'(?<translate1>(?&string))',
				',',
				'(?<translate2>(?&string))',
				'\\)'
			],
			'substr' => [
				'substring',
				'\\(',
				'(?<substr0>(?&value))',
				',',
				'(?<substr1>(?&value))',
				'(?:, (?<substr2>(?&value)))?',
				'\\)'
			],
			'startswith' => [
				'starts-with',
				'\\(',
				'(?<startswith0>(?&value))',
				',',
				'(?<startswith1>(?&value))',
				'\\)'
			]
		];

		if (\version_compare(\PCRE_VERSION, '8.13', '>='))
		{
			$patterns['math'] = [
				'(?<math0>(?&attr)|(?&number)|(?&param))',
				'(?<math1>[-+*])',
				'(?<math2>(?&math)|(?&math0))'
			];

			$patterns['notcontains'] = [
				'not',
				'\\(',
				'contains',
				'\\(',
				'(?<notcontains0>(?&value))',
				',',
				'(?<notcontains1>(?&value))',
				'\\)',
				'\\)'
			];
		}

		$exprs = [];

		$valueExprs = [];
		foreach ($patterns as $name => $pattern)
		{
			if (\is_array($pattern))
				$pattern = \implode(' ', $pattern);

			$valueExprs[] = '(?<' . $name . '>' . $pattern . ')';
		}
		$exprs[] = '(?<value>' . \implode('|', $valueExprs) . ')';

		$exprs[] = '(?<cmp>(?<cmp0>(?&value)) (?<cmp1>!?=) (?<cmp2>(?&value)))';

		$boolMatch = $parensMatch = '';
		if (\version_compare(\PCRE_VERSION, '8.13', '>='))
		{
			$boolMatch   = '(?&bool)|';
			$parensMatch = '|(?&parens)';

			$exprs[] = '(?<parens>\\( (?<parens0>(?&bool)|(?&cmp)) \\))';
		}

		$exprs[] = '(?<bool>(?<bool0>(?&cmp)|(?&not)|(?&value)' . $parensMatch . ') (?<bool1>and|or) (?<bool2>(?&cmp)|(?&not)|(?&value)|(?&bool)' . $parensMatch . '))';

		$exprs[] = '(?<not>not \\( (?<not0>' . $boolMatch . '(?&value)) \\))';

		$regexp = '#^(?:' . \implode('|', $exprs) . ')$#S';

		$regexp = \str_replace(' ', '\\s*', $regexp);

		return $regexp;
	}
}