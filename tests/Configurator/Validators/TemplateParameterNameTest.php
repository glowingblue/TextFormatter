<?php

namespace s9e\TextFormatter\Tests\Configurator\Validators;

use s9e\TextFormatter\Configurator\Validators\TemplateParameterName;
use s9e\TextFormatter\Tests\Test;

/**
* @covers s9e\TextFormatter\Configurator\Validators\TemplateParameterName
*/
class TemplateParameterNameTest extends Test
{
	/**
	* @testdox normalize() throws an InvalidArgumentException if the name is invalid
	*/
	public function testInvalid()
	{
		$this->expectException('InvalidArgumentException');
		$this->expectExceptionMessage("Invalid parameter name '*invalid*'");

		TemplateParameterName::normalize('*invalid*');
	}

	// Start of content generated by ../scripts/patchValidatorsTests.php
	/**
	* @testdox "foo" is normalized to "foo"
	*/
	public function testValid8C736521()
	{
		$this->assertTrue(TemplateParameterName::isValid("foo"));
		$this->assertSame("foo", TemplateParameterName::normalize("foo"));
	}

	/**
	* @testdox "Foo" is normalized to "Foo"
	*/
	public function testValidB43E23C1()
	{
		$this->assertTrue(TemplateParameterName::isValid("Foo"));
		$this->assertSame("Foo", TemplateParameterName::normalize("Foo"));
	}

	/**
	* @testdox "*" is invalid (name must start with a letter or an underscore)
	*/
	public function testInvalid9B9265B()
	{
		$this->assertFalse(TemplateParameterName::isValid("*"));
	}

	/**
	* @testdox "foo:bar" is invalid (name can only contain letters, digits, dashes and underscores)
	*/
	public function testInvalid81E2B6CF()
	{
		$this->assertFalse(TemplateParameterName::isValid("foo:bar"));
	}

	/**
	* @testdox "foo\n" is invalid (no newlines allowed)
	*/
	public function testInvalid7E3265A8()
	{
		$this->assertFalse(TemplateParameterName::isValid("foo\n"));
	}
	// End of content generated by ../scripts/patchValidatorsTests.php

	public static function getNames()
	{
		return [
			'foo'     => 'foo',
			'Foo'     => 'Foo',
			'*'       => 'Invalid: name must start with a letter or an underscore',
			'foo:bar' => 'Invalid: name can only contain letters, digits, dashes and underscores',
			"foo\n"   => 'Invalid: no newlines allowed'
		];
	}
}