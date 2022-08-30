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
        Assert::same($arg, Parser::parse($arg), 'Parse non-string value');
    }

    /** @testCase */
    public function testParseStringTemplate()
    {
        $string = 'test <% mate %> and with a <%filter|strtoupper%>';
        $args = ['mate' => 0, 'filter' => 'lol'];

        $parsed = Parser::parse($string, $args);

        Assert::notContains('<%', $parsed, 'All template parameters are parsed.');
        Assert::equal('test 0 and with a LOL', $parsed, 'Parses correctly.');
    }
}

(new ParserTest())->run();
