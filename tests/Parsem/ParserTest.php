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
}

(new ParserTest())->run();
