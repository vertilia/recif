# recif: rule engine comme il faut

Rule engine parser that translates conditions from rule file in json or yaml format to native code.

## Description

When businesses express new or updated rulesets, they may be either coded by programmers into the existing information system, or they may be declared in a special dialect that will be understood by the system. In this case programmer's intervention is not needed, but each time the system needs to evaluate the conditions from a ruleset for specific context (for example, define product segment) the system needs to parse the rule file, process conditions starting at the top level until the matching rule found, all this recursively calling methods for each operation mentioned in a ruleset.

With recif, when rulesets are updated in a rule file we call the converter that translates the rules into a native code. That code represents the ruleset conditions from the file. All operations are already translated to native constructs like OR, AND etc, so the execution of the full ruleset is optimal. To use that class inside your project instantiate the object and call its `evaluate()` method passing the context object as a parameter. `evaluate()` will return the corresponding value or `false` if passed context does not match any condition.

## Example 1

The simplest form: single condition. Input context is a literal numeric value that is evaluated and returns `true` if it is greater than `10`:

```json
{
  "gt": [{"cx":""}, 10]
}
```

## Example 2

Multiple conditions combined with `AND` operation. Input context is a literal numeric value. Returns `true` if context value is in the range `0..100`:

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
|`US`   |`USD`   |
|`DE`   |`EUR`   |
|`ES`   |`EUR`   |
|`FR`   |`EUR`   |
|`IT`   |`EUR`   |

If condition matches it will return continent name for the context (`North America` or `Europe`) instead of simple `true` value.

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
        {"in": [{"cx":"country"}, ["DE", "ES", "FR", "IT"]]},
        {"eq": [{"cx":"currency"}, "EUR"]}
      ],
      "return": "Europe"
    }
  ]
}
```

## Syntax of rule files

Rule file contains a tree of conditions starting with a root condition.

Each condition is an object with a single required entry containing operator name and arguments and optional `return` entry containing returned value.

Operators like `or` must contain an array of arguments. Unary operators like `not` may operate on a single value, not an array. See [Operators reference]() for the full list of operators.

Each argument may have one of the following forms:

- literal value or array (normally used to compare the value with context field), like `"US"` or `["DE", "ES", "FR", "IT"]`;
- object (representing context or another operation), like `{"cx":"currency"}` or `{"eq": [{"cx":"country"}, "US"]}`.

Returned value represents a value that is returned to the outer operation when the corresponding condition evaluates to `true`. It may be declared as any type. If value is an object, it may represent context or one of its properties, like `{"cx":""}` or  `{"cx":"currency.iso_name"}`. In this case the value of this element or property will be returned.

## Operators reference

Meanings below are given as php code.

### Context operator

`cx` - context value (argument: empty string if context is literal, dot-separated list of keys if array)

Example: `{"cx":""}`, `{"cx":"url.path"}`

Meaning: `$context`, `$context['url']['path']`

### Comparison operators

`eq` - equal

Example: `{"eq": [{"cx":""}, 0]}`

Meaning: `$context == 0`

`ne` - not equal

Example: `{"eq": [{"cx":""}, 0]}`

Meaning: `$context != 0`

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

### Array operations

`in` - element exists in array (arguments: element, array)

Example: `{"in": [{"cx":""}, [1, 2, 3, 5, 8, 13]]}`

Meaning: `in_array($context, [1, 2, 3, 5, 8, 13])`

### String operations

`sub` - needle substring exists in haystack string (arguments: haystack, needle). Search in Unicode case-insensitive mode.

Example: `{"sub": [{"cx":""}, "word"]}`

Meaning: `mb_stripos($context, "word") !== false`

`re` - string matches regular expression (arguments: string, regex). Unicode mode enabled.

Example: `{"re": [{"cx":""}, "[0-9]+"]}`

Meaning: `preg_match("/[0-9]+/u", $context)`

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
        {"in": [{"cx":"country"}, ["DE", "ES", "FR", "IT"]]},
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

**REMARK** In `php-5` compatibility mode the `($context['currency'] ?? null)` and similar constructs will be replaced by `isset($context['currency']) and $context['currency']` constructs.
