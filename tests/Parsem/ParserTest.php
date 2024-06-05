<?php

declare(strict_types=1);

use Matronator\Parsem\Parser;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

class ParserTest extends TestCase
{
    public function getNonStringValues()
    {
        return [ [1], [true], [null], [-30], [0], [['sumting']], [(object) ['idk' => 'lol']], [1.23456789]];
    }

    /**
     * @dataProvider getNonStringValues
     * @testCase
     */
    public function testParseNonStringValue($arg)
    {
        Assert::same($arg, Parser::parseString($arg), 'Parse non-string value');
    }

    /** @testCase */
    public function testParseStringTemplate()
    {
        $string = 'test <% mate|pow:3 %> and with a <%filter|strtoupper%>';
        $args = ['mate' => 2, 'filter' => 'lol'];

        $parsed = Parser::parseString($string, $args);

        Assert::notContains('<%', $parsed, 'All template parameters are parsed.');
        Assert::notContains('%>', $parsed, 'All template parameters are parsed.');
        Assert::equal('test 8 and with a LOL', $parsed, 'Parses correctly.');
    }

    /** @testCase */
    public function testFilterWithArguments()
    {
        $string = '<% var|substr:1,3 %>';
        $args = ['var' => 'abcdef'];

        $parsed = Parser::parseString($string, $args);

        Assert::equal('bcd', $parsed, 'Filter with arguments parsed correctly.');
    }

    /** @testCase */
    public function testDefaultFilter()
    {
        $string = '<% var|pascalCase %>';
        $args = ['var' => 'hello world'];

        $parsed = Parser::parseString($string, $args);

        Assert::equal('HelloWorld', $parsed, 'Default filter parsed correctly.');
    }

    /** @testCase */
    public function testDefaultValue()
    {
        $string = 'Hello <% var="world" %>!';
        $args = [];

        $parsed = Parser::parseString($string, $args);

        Assert::equal('Hello world!', $parsed, 'Default value parsed correctly.');
    }

    /** @testCase */
    public function testIgnoreDefaultValue()
    {
        $string = 'Hello <% var="world" %>!';
        $args = ['var' => 'mate'];

        $parsed = Parser::parseString($string, $args);

        Assert::equal('Hello mate!', $parsed, 'Argument correctly overwrote default value.');
    }

    /** @testCase */
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

    /** @testCase */
    public function testSimpleCondition()
    {
        $string = '<% if $foo === true %><% bar %> <% endif %>World!';
        $args = ['foo' => true, 'bar' => 'Hello'];
        $args2 = ['foo' => false, 'bar' => 'Hello'];

        $parsed = Parser::parseString($string, $args);
        Assert::equal('Hello World!', $parsed, 'Condition applied correctly to default value.');

        $parsed = Parser::parseString($string, $args2);
        Assert::equal('World!', $parsed, 'Condition applied correctly to default value.');
    }

    /** @testCase */
    public function testNestedConditions()
    {
        $string = '<% if $foo === true %>Hello<% if $bar === true %> Cruel<% endif %><% endif %> World!';
        $args = ['foo' => true, 'bar' => true];
        $args2 = ['foo' => true, 'bar' => false];
        $args3 = ['foo' => false, 'bar' => true];
        $args4 = ['foo' => false, 'bar' => false];

        $parsed = Parser::parseString($string, $args);
        Assert::equal('Hello Cruel World!', $parsed, 'All are true');

        $parsed = Parser::parseString($string, $args2);
        Assert::equal('Hello World!', $parsed, 'True and false');

        $parsed = Parser::parseString($string, $args3);
        Assert::equal(' World!', $parsed, 'False and true');

        $parsed = Parser::parseString($string, $args4);
        Assert::equal(' World!', $parsed, 'All are false');
    }

    /** @testCase */
    public function testNestedNumericConditions()
    {
        $string = '<% if $foo === "asdf" %>Hello<% if $bar === 2 %> Cruel<% endif %><% endif %> World!';
        $args = ['foo' => 'asdf', 'bar' => 2];
        $args2 = ['foo' => 'asdf', 'bar' => 1];
        $args3 = ['foo' => 2, 'bar' => 2];
        $args4 = ['foo' => 2, 'bar' => 1];

        $parsed = Parser::parseString($string, $args);
        Assert::equal('Hello Cruel World!', $parsed, 'All are true');

        $parsed = Parser::parseString($string, $args2);
        Assert::equal('Hello World!', $parsed, 'True and false');

        $parsed = Parser::parseString($string, $args3);
        Assert::equal(' World!', $parsed, 'False and true');

        $parsed = Parser::parseString($string, $args4);
        Assert::equal(' World!', $parsed, 'All are false');
    }

    /** @testCase */
    public function testNewLines()
    {
        $string = <<<EOT
        Hello
        <% if false %>
        Amazing
        <% endif %>
        World!
        EOT;

        $expected = <<<EOT
        Hello
        World!
        EOT;

        $string2 = <<<EOT
        Hello
        <% if true %>
        Amazing
        <% endif %>
        World!
        EOT;

        $expected2 = <<<EOT
        Hello
        Amazing
        World!
        EOT;

        $parsed = Parser::parseString($string, []);
        $parsed2 = Parser::parseString($string2, []);
        Assert::equal($expected, $parsed, '(false) New lines are ignored.');
        Assert::equal($expected2, $parsed2, '(true) New lines are ignored.');
    }

    /** @testCase */
    public function testElseBlocks()
    {
        $string = "Hello<% if false %> Amazing<% else %> Cruel<% endif %> World!";

        $expected = "Hello Cruel World!";

        $string2 = "Hello<% if true %> Amazing<% else %> Cruel<% endif %> World!";

        $expected2 = "Hello Amazing World!";

        $parsed = Parser::parseString($string, []);
        $parsed2 = Parser::parseString($string2, []);
        Assert::equal($expected, $parsed, '(false) Else block is parsed.');
        Assert::equal($expected2, $parsed2, '(true) If block is parsed.');
    }
}

(new ParserTest())->run();
