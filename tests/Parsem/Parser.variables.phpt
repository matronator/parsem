<?php

/**
 * TEST: Test parsing variables
 * @testCase
 */

declare(strict_types=1);

use Matronator\Parsem\Parser;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

class ParserVariablesTest extends TestCase
{
    public function getNonStringValues()
    {
        return [ [1], [true], [null], [-30], [0], [['sumting']], [(object) ['idk' => 'lol']], [1.23456789]];
    }

    /**
     * @dataProvider getNonStringValues
     */
    public function testParseNonStringValue($arg)
    {
        Assert::same($arg, Parser::parseString($arg), 'Parse non-string value');
    }

    public function testParseStringTemplate()
    {
        $string = 'test <% mate|pow:3 %> and with a <%filter|strtoupper%>';
        $args = ['mate' => 2, 'filter' => 'lol'];

        $parsed = Parser::parseString($string, $args);

        Assert::notContains('<%', $parsed, 'All template parameters are parsed.');
        Assert::notContains('%>', $parsed, 'All template parameters are parsed.');
        Assert::equal('test 8 and with a LOL', $parsed, 'Parses correctly.');
    }

    public function testFilterWithArguments()
    {
        $string = '<% var|substr:1,3 %>';
        $args = ['var' => 'abcdef'];

        $parsed = Parser::parseString($string, $args);

        Assert::equal('bcd', $parsed, 'Filter with arguments parsed correctly.');
    }

    public function testDefaultFilter()
    {
        $string = '<% var|pascalCase %>';
        $args = ['var' => 'hello world'];

        $parsed = Parser::parseString($string, $args);

        Assert::equal('HelloWorld', $parsed, 'Default filter parsed correctly.');
    }

    public function testDefaultValue()
    {
        $string = 'Hello <% var="world" %>!';
        $args = [];

        $parsed = Parser::parseString($string, $args);

        Assert::equal('Hello world!', $parsed, 'Default value parsed correctly.');
    }

    public function testDefaultValueTypes()
    {
        $string = 'Hello <% var="world" %><% var2=1 %><% var3=true %><% var4=null %>';
        $args = [];

        $parsed = Parser::parseString($string, $args);

        Assert::equal('Hello world11', $parsed, 'Default values parsed correctly.');
    }

    public function testEmptyDefaultValue()
    {
        $string = 'Hello <% var= %>!';
        $string2 = 'Hello <% var=|upper %>!';

        $parsed = Parser::parseString($string, [], false);
        $parsed2 = Parser::parseString($string2, [], false);

        Assert::equal('Hello !', $parsed, 'Empty default value parsed correctly.');
        Assert::equal('Hello !', $parsed2, 'Empty default value with filter parsed correctly.');
    }

    public function testIgnoreDefaultValue()
    {
        $string = 'Hello <% var="world" %>!';
        $args = ['var' => 'mate'];

        $parsed = Parser::parseString($string, $args);

        Assert::equal('Hello mate!', $parsed, 'Argument correctly overwrote default value.');
    }

    public function testDefaultValueWithFilter()
    {
        $string = 'Hello <% var="world"|truncate:2 %>!';
        $string2 = 'Hello <% var="world"|truncate:2,"" %>!';
        $string3 = 'Hello <% var=""|truncate:2 %>!';
        $args = [];

        $parsed = Parser::parseString($string, $args);
        Assert::equal('Hello wo...!', $parsed, 'Filter applied correctly to default value.');

        $parsed = Parser::parseString($string2, $args);
        Assert::equal('Hello wo!', $parsed, 'Filter applied correctly to default value.');

        $parsed = Parser::parseString($string3, $args);
        Assert::equal('Hello !', $parsed, 'Filter applied correctly to empty default value.');
    }
}

(new ParserVariablesTest())->run();
