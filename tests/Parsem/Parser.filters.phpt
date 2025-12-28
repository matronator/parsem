<?php

/**
 * TEST: Test parsing filters
 * @testCase
 */

declare(strict_types=1);

use Matronator\Parsem\Parser;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

class ParserFiltersTest extends TestCase
{
    public function testPascalCaseFilter()
    {
        $string = '<% var|pascalCase %>';
        $args1 = ['var' => 'hello world'];
        $args2 = ['var' => 'hello-world'];
        $args3 = ['var' => 'hello_world. And HTML idk.'];

        $parsed = Parser::parseString($string, $args1);
        $parsed2 = Parser::parseString($string, $args2);
        $parsed3 = Parser::parseString($string, $args3);

        Assert::equal('HelloWorld', $parsed, 'Default filter parsed correctly.');
        Assert::equal('HelloWorld', $parsed2, 'Default filter parsed correctly.');
        Assert::equal('HelloWorld.AndHTMLIdk.', $parsed3, 'Default filter parsed correctly.');
    }

    public function testTitleCaseFilter()
    {
        $string = '<% var|titleCase %>';
        $args1 = ['var' => 'hello world'];
        $args2 = ['var' => 'hello-world'];
        $args3 = ['var' => 'hello_world. And HTML idk.'];

        $parsed = Parser::parseString($string, $args1);
        $parsed2 = Parser::parseString($string, $args2);
        $parsed3 = Parser::parseString($string, $args3);

        Assert::equal('Hello World', $parsed, 'Default filter parsed correctly.');
        Assert::equal('Hello-World', $parsed2, 'Default filter parsed correctly.');
        Assert::equal('Hello World. And HTML Idk.', $parsed3, 'Default filter parsed correctly.');
    }

    public function testTruncateFilter()
    {
        $string1 = '<% var|truncate:10 %>';
        $string2 = '<% var|truncate:10,"-" %>';
        $args1 = ['var' => 'hello world'];
        $args2 = ['var' => 'hello'];
        $args3 = ['var' => 'hello_world. And HTML idk.'];

        $parsed1 = Parser::parseString($string1, $args1);
        $parsed2 = Parser::parseString($string1, $args2);
        $parsed3 = Parser::parseString($string1, $args3);

        $parsed4 = Parser::parseString($string2, $args1);
        $parsed5 = Parser::parseString($string2, $args2);
        $parsed6 = Parser::parseString($string2, $args3);

        Assert::equal('hello worl...', $parsed1, 'Truncate filter parsed correctly.');
        Assert::equal('hello', $parsed2, 'Truncate filter parsed correctly.');
        Assert::equal('hello_worl...', $parsed3, 'Truncate filter parsed correctly.');
        Assert::equal('hello worl-', $parsed4, 'Truncate filter parsed correctly.');
        Assert::equal('hello', $parsed5, 'Truncate filter parsed correctly.');
        Assert::equal('hello_worl-', $parsed6, 'Truncate filter parsed correctly.');
    }
}

(new ParserFiltersTest())->run();
