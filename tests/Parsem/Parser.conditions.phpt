<?php

/**
 * TEST: Test parsing conditions
 * @testCase
 */

declare(strict_types=1);

use Matronator\Parsem\Parser;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

class ParserConditionsTest extends TestCase
{
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

    public function testNestedIfElse()
    {
        $string = "Hello<% if false %> Amazing<% else %> Cruel<% if true %> World!<% endif %><% endif %>";
        $expected = "Hello Cruel World!";

        $string2 = "Hello<% if true %> Amazing<% else %> Cruel<% if false %> World!<% endif %><% endif %>";
        $expected2 = "Hello Amazing";

        $parsed = Parser::parseString($string, []);
        $parsed2 = Parser::parseString($string2, []);
        Assert::equal($expected, $parsed, '(false) Nested if-else block is parsed.');
        Assert::equal($expected2, $parsed2, '(true) Nested if-else block is parsed.');
    }

    public function testDoubleNestedIfElseElse()
    {
        $string = "Hello<% if false %> Amazing<% else %> Cruel<% if false %> World!<% else %> Universe!<% endif %><% endif %>";
        $expected = "Hello Cruel Universe!";

        $string2 = "Hello<% if true %> Amazing<% else %> Cruel<% if false %> World!<% else %> Universe!<% endif %><% endif %>";
        $expected2 = "Hello Amazing";

        $string3 = "Hello<% if false %> Amazing<% else %> Cruel<% if true %> World!<% else %> Universe!<% endif %><% endif %>";
        $expected3 = "Hello Cruel World!";

        $parsed = Parser::parseString($string, []);
        $parsed2 = Parser::parseString($string2, []);
        $parsed3 = Parser::parseString($string3, []);
        Assert::equal($expected, $parsed, '(false false) Double nested if-else block is parsed.');
        Assert::equal($expected2, $parsed2, '(true false) Double nested if-else block is parsed.');
        Assert::equal($expected3, $parsed3, '(false true) Double nested if-else block is parsed.');
    }

    public function testDoubleNestedIfElseElseElse()
    {
        $string = "Hello<% if false %> Amazing<% else %> Cruel<% if false %> World<% else %> Universe<% endif %><% if false %>!<% else %>?<% endif %><% endif %>";
        $expected = "Hello Cruel Universe?";

        $string2 = "Hello<% if false %> Amazing<% else %> Cruel<% if true %> World<% else %> Universe<% endif %><% if false %>!<% else %>?<% endif %><% endif %>";

        $expected2 = "Hello Cruel World?";

        $string3 = "Hello<% if false %> Amazing<% else %> Cruel<% if true %> World!<% else %> Universe<% if false %> Milky Way!<% else %> Andromeda!<% endif %><% endif %><% endif %>";
        $expected3 = "Hello Cruel World!";

        $string4 = "Hello<% if false %> Amazing<% else %> Cruel<% if false %> World!<% else %> Universe<% if true %> Milky Way!<% else %> Andromeda!<% endif %><% endif %><% endif %>";
        $expected4 = "Hello Cruel Universe Milky Way!";

        $parsed = Parser::parseString($string, []);
        $parsed2 = Parser::parseString($string2, []);
        $parsed3 = Parser::parseString($string3, []);
        $parsed4 = Parser::parseString($string4, []);
        Assert::equal($expected, $parsed, '(false false false) Double nested if-else block is parsed.');
        Assert::equal($expected2, $parsed2, '(false true false) Double nested if-else block is parsed.');
        Assert::equal($expected3, $parsed3, '(false true false) Double nested if-else block is parsed.');
        Assert::equal($expected4, $parsed4, '(false false true) Double nested if-else block is parsed.');
    }

    public function testEmptyIfBlockAndEmptyElseBlock()
    {
        $string = "Hello<% if false %><% else %> Cruel<% endif %> World!";
        $expected = "Hello Cruel World!";

        $string2 = "Hello<% if true %> Amazing<% else %><% endif %> World!";
        $expected2 = "Hello Amazing World!";

        $string3 = "Hello<% if true %><% else %> Cruel<% endif %> World!";
        $expected3 = "Hello World!";

        $string4 = "Hello<% if false %> Amazing<% else %><% endif %> World!";
        $expected4 = "Hello World!";

        $parsed = Parser::parseString($string, []);
        $parsed2 = Parser::parseString($string2, []);
        $parsed3 = Parser::parseString($string3, []);
        $parsed4 = Parser::parseString($string4, []);
        Assert::equal($expected, $parsed, 'Empty if block with false is parsed.');
        Assert::equal($expected2, $parsed2, 'Empty else block with true is parsed.');
        Assert::equal($expected3, $parsed3, 'Empty if block with true is parsed.');
        Assert::equal($expected4, $parsed4, 'Empty else block with false is parsed.');
    }

    public function testNegatedCondition()
    {
        $string = "Hello<% if !false %> Amazing<% endif %> World!";
        $expected = "Hello Amazing World!";
        $string2 = "Hello<% if !true %> Amazing<% else %> Cruel<% endif %> World!";
        $expected2 = "Hello Cruel World!";

        $parsed = Parser::parseString($string, []);
        $parsed2 = Parser::parseString($string2, []);
        Assert::equal($expected, $parsed, 'Negated false is true.');
        Assert::equal($expected2, $parsed2, 'Negated true is false -> else shown.');
    }

    public function testNegatedArgument()
    {
        $string = 'Hello<% if !$foo %> Amazing<% endif %> World!';
        $expected = "Hello Amazing World!";
        $string2 = 'Hello<% if !$foo %> Amazing<% else %> Cruel<% endif %> World!';
        $expected2 = "Hello Cruel World!";

        $parsed = Parser::parseString($string, ['foo' => null]);
        $parsed2 = Parser::parseString($string2, ['foo' => true]);
        Assert::equal($expected, $parsed, 'Negated false is true.');
        Assert::equal($expected2, $parsed2, 'Negated true is false -> else shown.');
    }

    public function testNegation()
    {
        $template1 = '<% if $true %>not negated<% endif %>';
        $expected1 = 'not negated';
        $template2 = '<% if !$false %>negated<% endif %>';
        $expected2 = 'negated';
        $template3 = '<% if !$true %>negated<% endif %>';
        $expected3 = '';
        $template4 = '<% if true %>literal<% endif %>';
        $expected4 = 'literal';

        $parsed1 = Parser::parseString($template1, ['true' => true]);
        $parsed2 = Parser::parseString($template2, ['false' => false]);
        $parsed3 = Parser::parseString($template3, ['true' => true]);
        $parsed4 = Parser::parseString($template4);
        Assert::equal($expected1, $parsed1, 'Negation of true is false.');
        Assert::equal($expected2, $parsed2, 'Negation of false is true.');
        Assert::equal($expected3, $parsed3, 'Negation of true is false.');
        Assert::equal($expected4, $parsed4, 'Parsed literal.');
    }

    public function testNestedConditions2()
    {
        $string = <<<'EOT'
        <% if $mintable === true %>
        (define-public (mint (amount uint) (recipient principal))
        (begin
        <% if !$allowMintToAll %>
            (asserts! (is-eq tx-sender CONTRACT_OWNER) ERR_OWNER_ONLY)
        <% endif %>
            (ft-mint? <% name|kebabCase %> amount recipient)
        )
        )
        <% endif %>
        EOT;

        $expected1 = <<<'EOT'
        (define-public (mint (amount uint) (recipient principal))
        (begin
            (asserts! (is-eq tx-sender CONTRACT_OWNER) ERR_OWNER_ONLY)
            (ft-mint? hello-world amount recipient)
        )
        )

        EOT;

        $expected3 = <<<'EOT'
        (define-public (mint (amount uint) (recipient principal))
        (begin
            (ft-mint? hello-world amount recipient)
        )
        )

        EOT;

        $args = ['mintable' => true, 'allowMintToAll' => false, 'name' => 'HelloWorld'];
        $args2 = ['mintable' => false, 'allowMintToAll' => true, 'name' => 'HelloWorld'];
        $args3 = ['mintable' => true, 'allowMintToAll' => true, 'name' => 'HelloWorld'];

        $parsed1 = Parser::parseString($string, $args);
        $parsed2 = Parser::parseString($string, $args2);
        $parsed3 = Parser::parseString($string, $args3);

        Assert::equal($expected1, $parsed1, '1: Condition applied correctly to default value.');
        Assert::equal('', $parsed2, '2: Condition applied correctly to default value.');
        Assert::equal($expected3, $parsed3, '3: Condition applied correctly to default value.');
    }
}

(new ParserConditionsTest())->run();
