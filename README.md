# Pars'Em

![Pars'Em logo](.github/parsem-logo.png)

Simple lightweight templating engine made for (primarily) JSON, YAML and NEON templates.

Enhance your JSON/YAML/NEON files with variables and PHP functions as filters. Create re-usable templates by adding variable `<% placeholder %>`'s anywhere in your file and have the content change dynamically based on the arguments you provide.

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

## Usage

### Template syntax

#### Variables

Variables are wrapped in `<%` and `%>` with optional space on either side (both `<%nospace%>` and `<% space %>` styles are valid) and the name must be an alphanumeric string with optional underscore/s (this regex `[a-zA-Z0-9_]+?`).

#### Filters

You can optionally provide a filter to a variable by placing a pipe `|` right after the variable name and the filter right after the symbol (no space around the `|` pipe symbol), like this: `<% variable|filter %>`.

The filter can be any PHP function with the variable used as the function's argument. After parsing, the return value of that function called with the provided argument will be printed.

##### Example:

> If we have `<% foo|strtoupper %>` in the template and we provide an argument `['foo' => 'hello world']`, the final (parsed) output will be this: `HELLO WORLD`.

*So far you can specify only one filter on one variable declaration, but that will probably change in the future.*

### Use in code

There are two main functions that will be of most interest to you: `parse` and `parseFile`. Both are static functions and are used like this:

```php
use Matronator\Parsem\Parser;

echo Parser::parse('some <%text%>.', ['text' => 'value']);
// Output: some value.

$arguments = [
    'variableName' => 'value',
    'key' => 'other value',
];
$object = Parser::parseFile('filename.yaml', $arguments);
// Output: The YAML file parsed to an PHP object with all
//   variable placeholders replaced by the provided arguments.
```
