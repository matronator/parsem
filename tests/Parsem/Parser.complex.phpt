<?php

/**
 * TEST: Test real-life complex example
 * @testCase
 */

declare(strict_types=1);

use Matronator\Parsem\Parser;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

class ParserComplexTest extends TestCase
{
    public function testComplexParsing()
    {
        $input = file_get_contents(__DIR__ . '/token.input.clar.mtr');

        $expected1 = file_get_contents(__DIR__ . '/token.expected.1.clar');
        $expected2 = file_get_contents(__DIR__ . '/token.expected.2.clar') . "\n\n";

        $args1 = [
            "name" => "asdads",
            "editableUri" => true,
            "userWallet" => "SP39DTEJFPPWA3295HEE5NXYGMM7GJ8MA0TQX379",
            "tokenName" => "asdads",
            "tokenSymbol" => "ASD",
            "tokenSupply" => 8,
            "tokenDecimals" => 3,
            "tokenUri" => "",
            "mintable" => false,
            "burnable" => false,
            "initialAmount" => 0,
            "allowMintToAll" => false,
        ];

        $args2 = [
            "name" => "asdads",
            "editableUri" => true,
            "userWallet" => "SP39DTEJFPPWA3295HEE5NXYGMM7GJ8MA0TQX379",
            "tokenName" => "asdads",
            "tokenSymbol" => "ASD",
            "tokenSupply" => 0,
            "tokenDecimals" => 3,
            "tokenUri" => "",
            "mintable" => true,
            "burnable" => false,
            "initialAmount" => 0,
            "allowMintToAll" => false,
            "mintFixedAmount" => false,
            "mintAmount" => 0,
        ];

        $parsed1 = Parser::parseString($input, $args1);
        $parsed2 = Parser::parseString($input, $args2);

        Assert::equal($expected1, $parsed1, 'Parses correctly.');
        Assert::equal($expected2, $parsed2, 'Parses correctly.');
    }
}

(new ParserComplexTest())->run();
