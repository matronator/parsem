<?php

/**
 * TEST: Test parsing comments
 * @testCase
 */

declare(strict_types=1);

use Matronator\Parsem\Parser;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

class ParserCommentsTest extends TestCase
{
    public function testComments()
    {
        $string = 'Hello <# This is a comment #>World!';
        $string2 = 'Hello <#This is a comment#>World!';
        $expected = 'Hello World!';

        $parsed = Parser::parseString($string);
        $parsed2 = Parser::parseString($string2);
        Assert::equal($expected, $parsed, 'Comment is removed.');
        Assert::equal($expected, $parsed2, 'Comment without spaces is removed.');
    }

    public function testCommentsWithConditionsAndVariables()
    {
        $string = 'Hello <# This is a comment <% if $condition %> #>World!<# <% $variable %> <% endif %> #>';
        $expected = 'Hello World!';

        $parsed = Parser::parseString($string);
        $parsed2 = Parser::parseString($string, ['condition' => true, 'variable' => 'test']);
        Assert::equal($expected, $parsed, 'Comment with condition and variable is removed.');
        Assert::equal($expected, $parsed2, 'Comment with condition and variable is removed even with arguments.');
    }

    public function testMultilineComments()
    {
        $string = 'Hello <# This is a comment
        that spans multiple lines #>World!';
        $expected = 'Hello World!';

        $parsed = Parser::parseString($string);
        Assert::equal($expected, $parsed, 'Multiline comment is removed.');
    }

    public function testEmptyComments()
    {
        $string = 'Hello <# #>World!';
        $expected = 'Hello World!';

        $string2 = 'Hello <##>World!';
        $expected2 = 'Hello World!';

        $parsed = Parser::parseString($string);
        $parsed2 = Parser::parseString($string2);
        Assert::equal($expected, $parsed, 'Empty comment is removed.');
        Assert::equal($expected2, $parsed2, 'Empty comment with no spaces is removed.');
    }

    public function testNestedComments()
    {
        $string = 'Hello <# This is a comment <# that is nested #>World!';
        $expected = 'Hello World!';

        $parsed = Parser::parseString($string);
        Assert::equal($expected, $parsed, 'Nested comment is removed.');
    }
}

(new ParserCommentsTest())->run();
