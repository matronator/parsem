# Pars'Em Template Engine

![Pars'Em logo](.github/parsem-logo.png)

Simple lightweight templating engine made for (primarily) JSON, YAML and NEON templates.

Enhance your JSON/YAML/NEON files with variables and PHP functions as filters. Create re-usable templates by adding variable `<% placeholder %>`'s anywhere in your file and have the content change dynamically based on the arguments you provide.

<!-- @import "[TOC]" {cmd="toc" depthFrom=2 depthTo=6 orderedList=false} -->

<!-- code_chunk_output -->

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
  - [Templates Syntax Highlighting for VS Code](#templates-syntax-highlighting-for-vs-code)
- [Usage](#usage)
  - [Template syntax](#template-syntax)
    - [Variables](#variables)
    - [Filters](#filters)
      - [Example:](#example)
  - [Use in code](#use-in-code)
    - [Parse string or file](#parse-string-or-file)
    - [Methods](#methods)
      - [`Parser::parseString`](#parserparsestring)
      - [`Parser::parseFile`](#parserparsefile)
      - [`Parser::parseFileToString`](#parserparsefiletostring)
    - [Using custom parser](#using-custom-parser)

<!-- /code_chunk_output -->

## Features

- Parse string templates to string
  - Replace variable placeholders with provided arguments
  - Apply filter functions to variables
- Parse template files to string
  - Parse the entire file as a string regardless of extension
- Provide a custom regex pattern to parse functions to use a custom syntax
- Convert JSON, YAML and NEON files to a PHP object
- Convert any file type to a PHP object by providing a custom parsing function
- Get all variable placeholders from a string
- Validate a template file against the [mtrgen-template-schema](https://files.matronator.com/public/mtrgen/latest/mtrgen-template-schema.json)

## Requirements

- PHP >= 7.4
- Composer

## Installation

Install with Composer:

```
composer require matronator/parsem
```

And then just add the dependency to your PHP file/s:

```php
use Matronator\Parsem\Parser;
```

### Templates Syntax Highlighting for VS Code

To get syntax highlighting for template files (highlight `<% variable|placeholders %>` even inside strings), you can download the [MTRGen Templates Syntax Highlighting](https://marketplace.visualstudio.com/items?itemName=matronator.mtrgen-yaml-templates) extension for VS Code.

## Usage

### Template syntax

#### Variables

Variables are wrapped in `<%` and `%>` with optional space on either side (both `<%nospace%>` and `<% space %>` are valid) and the name must be an alphanumeric string with optional underscore/s (this regex `[a-zA-Z0-9_]+?`).

#### Filters

You can optionally provide filter to a variable by placing the pipe symbol `|` right after the variable name and the filter right after that (no space around the `|` pipe symbol), like this: `<% variable|filter %>`.

The filter can be any PHP function with the variable used as the function's argument.

##### Example:

> If we have `<% foo|strtoupper %>` in the template and we provide an argument `['foo' => 'hello world']`, the final (parsed) output will be this: `HELLO WORLD`.

*So far you can specify only one filter per variable declaration, but that will probably change in the future.*

### Use in code

#### Parse string or file

There are three main functions that will be of most interest to you: `parseString`, `parseFile` and `parseFileToString`. Both are static functions and are used like this:

```php
use Matronator\Parsem\Parser;

echo Parser::parseString('some <%text%>.', ['text' => 'value']);
// Output: some value.

$arguments = [
    'variableName' => 'value',
    'key' => 'other value',
];
$object = Parser::parseFile('filename.yaml', $arguments);
// Output: The YAML file converted to an object with all
// variable placeholders replaced by the provided arguments.

echo Parser::parseFileToString('filename.yaml', $arguments);
// Output: Will print the parsed contents of the file as string.
```

#### Methods

##### `Parser::parseString`

```php
/**
 * Parses a string, replacing all template variables with the corresponding values passed in `$arguments`.
 * @return mixed The parsed string or the original `$string` value if it's not string
 * @param mixed $string String to parse. If not provided with a string, the function will return this value
 * @param array $arguments Array of arguments to find and replace while parsing `['key' => 'value']`
 * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
 */
Parser::parseString(mixed $string, array $arguments = [], ?string $pattern = null): mixed
```

##### `Parser::parseFile`

```php
/**
 * @see Parser::parseString() for parameter descriptions
 */
Parser::parseFile(string $filename, array $arguments = [], ?string $pattern = null): object
```

##### `Parser::parseFileToString`

```php
/**
 * @see Parser::parseString() for parameter descriptions
 */
Parser::parseFileToString(string $filename, array $arguments = [], ?string $pattern = null): string
```

#### Using custom parser

You can parse any file type to object, not only JSON/YAML/NEON, by providing a custom parser function as a callback to `Parser::customParse()` function.

```php
use Matronator\Parsem\Parser;

// Parse XML file
$object = Parser::customParse('filename.xml', function($contents) {
    return simplexml_load_string($contents);
});
```
