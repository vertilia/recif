# recif: rule engine comme il faut

Rule engine parser that translates conditions from rule file in json or yaml format to native code.

## Description

When businesses express new or updated rulesets, they may be either coded by programmers into the existing information system, or they may be declared in a special dialect that the system may parse and evaluate. In this case programmer's intervention is not needed, but the parsing and evaluation time may be significant. Each time the system needs to evaluate conditions from a ruleset for specific context (for example, define product segment) the system needs to parse the rule file, process conditions starting at the top level until the matching rule found, all this recursively calling methods for each operation mentioned in a ruleset.

With recif, when rulesets are updated in a rule file we call the converter that translates the rules into a native code. That code represents the ruleset conditions from the file. All operations are already translated to native constructs like OR, AND etc, so the execution of the full ruleset is optimal. To use that class inside your project instantiate the object and call its `evaluate()` method passing the context as its parameter. `evaluate()` will return `true` (or corresponding value) if match found, or `false` if passed context does not match any condition (yes, you may provide your own return value).

## Example 1

Single condition. Input context is a literal numeric value that is evaluated and returns `true` if it is greater than `10`:

```json
{
  "gt": [{"cx":""}, 10]
}
```

## Example 2

Multiple conditions combined with `AND` operator. Input context is a literal numeric value. Returns `true` if context value is in the range `0..100`:

```json
{
  "and": [
    {"ge": [{"cx":""}, 0]},
    {"le": [{"cx":""}, 100]}
  ]
}
```

## Example 3

Complex conditions: context is an associative array with `country` and `currency` entries which must match the following matrix:

|country|currency|
|-------|--------|
|`DE`   |`EUR`   |
|`ES`   |`EUR`   |
|`FR`   |`EUR`   |
|`IT`   |`EUR`   |
|`US`   |`USD`   |

If condition matches it will return continent name for the context (`North America` or `Europe`) instead of simple `true` value.

```json
{
  "or": [
    {
      "and": [
        {"in": [{"cx":"country"}, {"_": ["DE", "ES", "FR", "IT"]}]},
        {"eq": [{"cx":"currency"}, "EUR"]}
      ],
      "return": "Europe"
    },
    {
      "and": [
        {"eq": [{"cx":"country"}, "US"]},
        {"eq": [{"cx":"currency"}, "USD"]}
      ],
      "return": "North America"
    }
  ]
}
```

Note: to represent an array you need to use a special `{"_": ...}` inline operator.

## Installation

Require with `composer`:

```
$ composer require vertilia/recif
```

## Command line usage

After installation with `composer`, `recif` binary is available as `vendor/bin/recif`.

```
$ vendor/bin/recif
Usage: recif [options] <ruleset.json >Ruleset.php
Options:
  -C  COMMENT add comments text, should not contain open / close comment sequences (default: not set)
  -n  NAMESPACE use namespace (default: not set)
  -s  generate static evaluate() method (default: not set)
  -c  CLASS use class name (default: Ruleset)
  -e  CLASS extends class (default: not set)
  -i  INTERFACE implements interfaces, comma separated list (default: not set)
  -x  TYPE define context parameter type (default: not set)
  -r  TYPE define method return type (default: not set)
  -S  VALUE return on success default value (default: true)
  -F  VALUE return on fail default value (default: false)
  -d  declare static_types (default: not set)
  -5  generate php5-compatible code
  -y  input stream in YAML format (needs yaml PECL extension)
```

Simplest ruleset: evaluate the `true` statement:

```
echo 'true' | recif
```

Same level: evaluate that `1 > 0`:

```
echo '{"gt":[1,0]}' | recif
```

Do something meaningful: check context, add namespace to generated class and specify return type of its `evaluate()` method:

```
echo '{"gt":[{"cx":""},0]}' | recif -n MyNamespace\\Level2 -r bool
```

## Syntax of rule files

Rule file contains a tree of conditions starting with a root condition.

Each condition is an object with a single required entry containing operation name as key and arguments as value. An optional `return` entry may contain returned value for the case if operation matches, otherwise the default `true` will be returned.

Operators like `or` must contain an array of arguments. Unary operators like `not` operate on a single value, not an array. See [Operations reference]() for the full list of available operations.

Each argument may have one of the following forms:

- literal value or array (normally used to compare the value with context field), like `true` or `"US"`;
- object (representing context or another operation), like `{"cx":"currency"}` or `{"eq": [{"cx":"country"}, "US"]}` or `{"_": ["DE", "ES", "FR", "IT"]}`.

Returned value represents the value that is returned to the outer operation when the corresponding condition evaluates to `true`. It may be declared as any type. If value is an object, it may represent context or one of its properties, like `{"cx":""}` or  `{"cx":"currency.iso_name"}`. In this case the value of this element or property will be returned.

## Operations reference

Examples below are given in json notation, meanings are given as php code.

### Context operator

`cx` - context value (argument: empty string if context is literal, dot-separated list of keys if array)

Example: `{"cx":""}`,
`{"cx":"url.path"}`

Meaning: `$context`,
`$context['url']['path']`

### Comparison operators

`eq` - equal

Example: `{"eq": [{"cx":""}, 0]}`

Meaning: `$context == 0`

`===` - identical (equal and same type)

Example: `{"===": [{"cx":""}, '15h']}`

Meaning: `$context === '15h'`

`ne` - not equal

Example: `{"ne": [{"cx":""}, 0]}`

Meaning: `$context != 0`

`!==` - not identical (not equal or different type)

Example: `{"!==": [{"cx":""}, '15h']}`

Meaning: `$context !== '15h'`

`lt` - less than

Example: `{"lt": [{"cx":""}, 0]}`

Meaning: `$context < 0`

`le` - less or equal

Example: `{"le": [{"cx":""}, 0]}`

Meaning: `$context <= 0`

`gt` - greater than

Example: `{"gt": [{"cx":""}, 0]}`

Meaning: `$context > 0`

`ge` - greater or equal

Example: `{"ge": [{"cx":""}, 0]}`

Meaning: `$context >= 0`

### Logical operators

`or` - or (two or more arguments)

Example: `{"or": [{"eq": [{"cx":""}, 0]}, {"eq": [{"cx":""}, 10]}]}`

Meaning: `($context == 0) or ($context == 10)`

`and` - and (two or more arguments)

Example: `{"and": [{"gt": [{"cx":""}, 0]}, {"lt": [{"cx":""}, 10]}]}`

Meaning: `($context > 0) and ($context < 10)`

`not` - not (unary operator, single argument)

Example: `{"not": {"lt": [{"cx":""}, 0]}}`

Meaning: `! ($context < 0)`

### Math operations

`mod` - modulo (arguments: value, modulo)

Example: `{"mod": [{"cx":""}, 10]}`

Meaning: `$context % 10`

`rnd` - random integer number within range (arguments: min, max)

Example: `{"rnd": [1, 10]}`

Meaning: `rand(1, 10)`

### Array operations

`in` - element exists in array (arguments: element, array)

Example: `{"in": [{"cx":""}, {"_": [1, 2, 3, 5, 8, 13]}]}`

Meaning: `in_array($context, [1, 2, 3, 5, 8, 13])`

`inx` - get element from hash map by index (arguments: index, hash map)

Example: `{"inx": [{"cx":""}, {"_": ["FR": "Europe", "US": "North America"]}]}`,
`{"inx": [{"cx":""}, {"_": ["US": "North America", "default": "Europe"]}]}`

Meaning: `(["FR" => "Europe", "US" => "North America"])[$context]`,
`(["US" => "North America"])[$context] ?? "Europe"`

### String operations

`sub` - needle substring exists in haystack string (arguments: needle, haystack). Search in Unicode case-insensitive mode.

Example: `{"sub": ["word", {"cx":""}]}`

Meaning: `mb_stripos($context, "word") !== false`

`re` - string matches regular expression (arguments: regex, text).

Example: `{"re": ["/^\w+$/u", {"cx":""}]}`

Meaning: `preg_match('/^\w+$/u', $context)`

`spf` - formatted string a.k.a. sprintf (arguments: format string, zero or more values).

Example: `{"spf": ["%.2f", {"cx":""}]}`

Meaning: `sprintf('%.2f', $context)`

### Inline operator

`_` (underscore) - uses argument value as is (argument of any type).

Example: `{"_": true}`, `{"_": ["a": "b"]}`, `{"_": [1, {"_": []}, 3]}`

Meaning: `true`, `['a' => 'b']`, `[1, [], 3]`

### Native function call

`fn` - calls native function with provided params (arguments: function name, param1, ...).

Example: `{"fn": ["Currencies::getRateForCountry", {"cx":"country"}]}`

Meaning: `Currencies::getRateForCountry($context['country'])`

## Code examples

### Example 1

Rule file:

```json
{
  "gt": [{"cx":""}, 10]
}
```

Generated code:

```php
<?php

class Ruleset
{
    public function evaluate($context)
    {
        // return on success
        $success = true;

        // rules
        if ($context > 10) {
            return $success;
        }

        // not found
        return false;
    }
}
```

For the following examples the generated code will be created inside the `Ruleset::evaluate()` method, like in the example above, so only the corresponding `// rules` part will be presented.

### Example 2

Rule file:

```json
{
  "and": [
    {"ge": [{"cx":""}, 0]},
    {"le": [{"cx":""}, 100]}
  ]
}
```

Generated code:

```php
// rules
if (
    ($context >= 0)
    and ($context <= 100)
) {
    return $success;
}
```

### Example 3

Rule file:

```json
{
  "or": [
    {
      "and": [
        {"eq": [{"cx":"country"}, "US"]},
        {"eq": [{"cx":"currency"}, "USD"]}
      ],
      "return": "North America"
    },
    {
      "and": [
        {"in": [{"cx":"country"}, {"_": ["DE", "ES", "FR", "IT"]}]},
        {"eq": [{"cx":"currency"}, "EUR"]}
      ],
      "return": "Europe"
    }
  ]
}
```

Generated code:

```php
// rules
if (
    (
        (
            ($context['country'] ?? null) === "US"
            and ($context['currency'] ?? null) === "USD"
        )
        and is_string($success = "North America")
    ) or (
        (
            in_array($context['country'] ?? null, ["DE", "ES", "FR", "IT"])
            and ($context['currency'] ?? null) === "EUR"
        )
        and is_string($success = "Europe")
    )
) {
    return $success;
}
```

**REMARK** In `php-5` compatibility mode, the `($context['currency'] ?? null)` and similar constructs will be replaced by `isset($context['currency']) and $context['currency']` constructs.
