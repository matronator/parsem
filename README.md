# Pars'Em Template Engine

![Pars'Em logo](.github/parsem-logo.png)

Simple lightweight templating engine made in PHP.

Enhance your files with variables, conditional blocks and PHP functions as filters. Create re-usable templates by adding variable `<% placeholder %>`'s anywhere in your file and have the content change dynamically based on the arguments you provide.

<!-- @import "[TOC]" {cmd="toc" depthFrom=2 depthTo=6 orderedList=false} -->

<!-- code_chunk_output -->

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
  - [Templates Syntax Highlighting for VS Code](#templates-syntax-highlighting-for-vs-code)
- [Usage](#usage)
  - [Template syntax](#template-syntax)
    - [Conditions](#conditions)
    - [Variables](#variables)
      - [Default values](#default-values)
    - [Filters](#filters)
  - [Built-in filters](#built-in-filters)
  - [Use in code](#use-in-code)
    - [Parse string or file](#parse-string-or-file)
    - [Methods](#methods)
      - [`Parser::parseString`](#parserparsestring)
      - [`Parser::parse`](#parserparse)

<!-- /code_chunk_output -->

## Features

- Parse string templates to string
  - Replace variable placeholders with provided arguments
  - Apply filter functions to variables
    - Use [built-in filters](#built-in-filters) or provide custom functions
  - Use `<% if %>` blocks to conditionally parse the template
    - Use `<% else %>` blocks to provide an alternative content if the condition is not met
- Parse template files to string
  - Parse the entire file as a string
- Provide a custom regex pattern to parse functions to use a custom syntax
- Get all variables from a template

## Requirements

- PHP >= 8.2
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

To get syntax highlighting for template files (highlight `<% variable|placeholders %>` and `<% if %><% else %><% endif %>` even inside strings), you can download the [MTRGen Templates Syntax Highlighting](https://marketplace.visualstudio.com/items?itemName=matronator.mtrgen-yaml-templates) extension for VS Code.

## Usage

### Template syntax

#### Conditions

You can use conditions in your templates by using the `<% if %>` and `<% endif %>` tags. The condition must be a valid PHP expression that will be evaluated and if it returns `true`, the content between the tags will be included in the final output.

You can also use the `<% else %>` tag to provide an alternative content if the condition is not met.

To use a variable provided in the arguments array in a condition, you must use the `$` sign before the variable name, like this: `<% if $variable == 'value' %>`. The `$` sign is used to differentiate between the template variable and a keyword such as `true` or `null`.

##### Example:

```yaml
some:
  key
  <% if $variable == 'value' %>
  with value
  <% else %>
  without value
  <% endif %>
```

If you provide an argument `['variable' => 'value']`, the final output will be this:

```yaml
some:
  key
  with value
```

And if you provide an argument `['variable' => 'other value']`, the final output will be this:

```yaml
some:
  key
  without value
```

#### Variables

Variables are wrapped in `<%` and `%>` with optional space on either side (both `<%nospace%>` and `<% space %>` are valid) and the name must be an alphanumeric string with optional underscore/s (this regex `[a-zA-Z0-9_]+?`).

##### Default values

Variables can optionally have a default value that will be used if no argument is provided for that variable during parsing. You can specify a default value like this: `<% variable='Default' %>`

If you're going to use filters, the default value comes before the filter, ie.: `<% variable='Default'|filter %>`

If default value is empty (ie. `<% var= %>`), it will be treated as null.

#### Filters

You can optionally provide filter to a variable by placing the pipe symbol `|` right after the variable name and the filter right after that (no space around the `|` pipe symbol), like this: `<% variable|filter %>`.

The filter can be any PHP function with the variable used as the function's argument.

##### Example:

> If we have `<% foo|strtoupper %>` in the template and we provide an argument `['foo' => 'hello world']`, the final (parsed) output will be this: `HELLO WORLD`.

Filters can also have additional arguments apart from the variable itself. To pass additional arguments to a filter, write it like this: `<% var|filter:'arg','arg2',20,true %>`. Each argument after the colon is separated by a comma and can have any scalar type as a value.

The first argument will always the variable on which we're declaring the filter, with any other arguments passed after that.

##### Example:

> If we have `<% foo|substr:1,3 %>` and provide an argument `['foo' => 'abcdef']`, the filter will get called like this using the arguments provided: `substr('abcdef', 1, 3)`. And the final parsed output will thus be this: `bcd`.

*So far you can specify only one filter per variable declaration, but that will probably change in the future.*

### Built-in filters

There are a few built-in filters that you can use:

`upper` - Converts the variable to uppercase

`lower` - Converts the variable to lowercase

`upperFirst` - Converts the first character of the variable to uppercase

`lowerFirst` - Converts the first character of the variable to lowercase

`first` - Returns the first character of the variable

`last` - Returns the last character of the variable

`camelCase` - Converts the variable to camelCase

`snakeCase` - Converts the variable to snake_case

`kebabCase` - Converts the variable to kebab-case

`pascalCase` - Converts the variable to PascalCase

`titleCase` - Converts the variable to Title Case

`length` - Returns the length of the variable

`reverse` - Reverses the variable

`random` - Returns a random character from the variable

`truncate` - Truncates the variable to the specified length

### Use in code

#### Parse string or file

There are two main functions that will be of most interest to you: `parseString` and `parse`. Both are static functions and are used like this:

```php
use Matronator\Parsem\Parser;

// parseString()
echo Parser::parseString('some <%text%>.', ['text' => 'value']);
// Output: some value.

// parse()
$arguments = [
    'variableName' => 'value',
    'key' => 'other value',
];
$parsedFile = Parser::parse('filename.yaml', $arguments);
echo $parsedFile;
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
 * @param bool $strict [optional] If set to `true`, the function will throw an exception if a variable is not found in the `$arguments` array. If set to `false` null will be used.
 * @param string|null $pattern [optional] You can provide custom regex with two matching groups (for the variable name and for the filter) to use custom template syntax instead of the default one `<% name|filter %>`
 * @throws RuntimeException If a variable is not found in the `$arguments` array and `$strict` is set to `true`
 */
Parser::parseString(mixed $string, array $arguments = [], bool $strict = true, ?string $pattern = null): mixed
```

##### `Parser::parse`

```php
/**
 * @param string $filename Path to the file to parse
 * @see Parser::parseString() for rest of the parameter descriptions
 */
Parser::parse(string $filename, array $arguments = [], bool $strict = true, ?string $pattern = null): string
```
