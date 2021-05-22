<?php

namespace Recif\Platform\Php;

use InvalidArgumentException;
use LengthException;
use OutOfBoundsException;
use Recif\IRulesetGenerator;

class RulesetGenerator implements IRulesetGenerator
{
    const DEFAULT_CLASS_NAME = 'Ruleset';

    // options
    protected ?bool $declare_strict_types;
    protected ?string $namespace;
    protected ?string $class_name;
    protected ?string $extends;
    protected ?string $implements;
    protected ?string $context_type;
    protected ?string $return_type;
    protected ?string $return_on_success;
    protected ?string $return_on_fail;
    protected ?string $php5;

    // ruleset
    protected $ruleset;

    // operations callbacks
    protected array $op_callbacks;

    /**
     * Instantiates RuleConvertor object, sets ruleset.
     *
     * @param mixed $ruleset
     * @param array|null $options list of options: {
     *  "namespace": "MyAppNamespace",
     *  "className": "MyRuleset",
     *  "extends": "MyClass",
     *  "implements": "MyInterface"
     * }
     */
    public function __construct($ruleset, array $options = null)
    {
        // options
        $this->declare_strict_types = $options['declareStrictTypes'] ?? null;
        $this->namespace = $options['namespace'] ?? null;
        $this->class_name = $options['className'] ?? self::DEFAULT_CLASS_NAME;
        $this->extends = $options['extends'] ?? null;
        $this->implements = $options['implements'] ?? null;
        $this->context_type = $options['contextType'] ?? null;
        $this->return_type = $options['returnType'] ?? null;
        $this->return_on_success = $options['returnOnSuccess'] ?? 'true';
        $this->return_on_fail = $options['returnOnFail'] ?? 'false';
        $this->php5 = $options['php5'] ?? null;

        if ($this->php5) {
            $this->declare_strict_types = null;
            $this->return_type = null;
        }

        // ruleset
        $this->ruleset = $ruleset;

        // op callbacks
        $this->initCallbacks();
    }

    /**
     * @return string
     */
    public function generate(): string
    {
        $extends_parts = [];

        if (isset($this->extends)) {
            $extends_parts[] = "extends $this->extends";
        }

        if (isset($this->implements)) {
            $extends_parts[] = "implements $this->implements";
        }

        return preg_replace(
            [
                '/%DeclareStrictTypes%\s*/ui',
                '/%Namespace%\s*/ui',
                '/%ClassName%/ui',
                '/\s*%Extends%/ui',
                '/%Rules%/ui',
                '/%ContextType%\s*/ui',
                '/\s*%ReturnType%/ui',
                '/%ReturnOnSuccess%/ui',
                '/%ReturnOnFail%/ui',
            ],
            [
                $this->declare_strict_types ? "declare(strict_types=1);\n\n" : null,
                $this->namespace ? "namespace $this->namespace;\n\n" : null,
                $this->class_name,
                $extends_parts ? ' ' . implode(' ', $extends_parts) : null,
                $this->elementToCode($this->ruleset),
                $this->context_type ? "$this->context_type " : null,
                $this->return_type ? " : $this->return_type" : null,
                $this->return_on_success,
                $this->return_on_fail,
            ],
            file_get_contents(__DIR__ . '/fragments/main.frg')
        );
    }

    /**
     * Initialises operations callbacks
     */
    protected function initCallbacks()
    {
        $this->op_callbacks = [
            // context operator
            'cx' => function ($arg) {
                if (!is_string($arg)) {
                    throw new InvalidArgumentException('Context argument must be a string');
                }
                if ($arg === '') {
                    return '$context';
                } else {
                    $parts = [];
                    foreach (explode('.', $arg) as $v) {
                        if (is_numeric($v)) {
                            $parts[] = "[$v]";
                        } elseif (strlen($tr = trim($v))) {
                            $parts[] = "['" . addcslashes($tr, "'\0\\") . "']";
                        }
                    }
                    if (empty($parts)) {
                        return '$context';
                    }
                    $whole = implode('', $parts);
                    return $this->php5
                        ? sprintf('isset($context%s) ? $context%s : null', $whole, $whole)
                        : sprintf('$context%s ?? null', $whole);
                }
            },

            // comparison operators
            'eq' => function ($args) {
                return $this->op2Args('eq', '==', $args);
            },
            'ne' => function ($args) {
                return $this->op2Args('ne', '!=', $args);
            },
            'lt' => function ($args) {
                return $this->op2Args('lt', '<', $args);
            },
            'le' => function ($args) {
                return $this->op2Args('le', '<=', $args);
            },
            'gt' => function ($args) {
                return $this->op2Args('gt', '>', $args);
            },
            'ge' => function ($args) {
                return $this->op2Args('ge', '>=', $args);
            },

            // logical operators
            'or' => function ($args) {
                return $this->op2PlusArgs('or', 'or', $args);
            },
            'and' => function ($args) {
                return $this->op2PlusArgs('and', 'and', $args);
            },
            'not' => function ($arg) {
                if (is_scalar($arg)) {
                    return '!' . var_export($arg, true);
                }
                $op = $this->parseOperation($arg);
                return '!(' . $this->operationToCode($op) . ')';
            },

            // math
            'mod' => function ($args) {
                return $this->op2Args('mod', '%', $args);
            },
            'rnd' => function ($args) {
                if (!is_array($args) or array_keys($args) != [0, 1]) {
                    throw new LengthException('"rnd" operation must have 2 arguments');
                }
                return sprintf(
                    '\\rand(%s, %s)',
                    $this->elementToCode($args[0]),
                    $this->elementToCode($args[1])
                );
            },

            // array operations
            'in' => function ($args) {
                if (!is_array($args) or array_keys($args) != [0, 1]) {
                    throw new LengthException('"in" operation must have 2 arguments');
                }
                if (!is_array($args[1])) {
                    throw new InvalidArgumentException('"in" operation must have an array as second argument');
                }
                return sprintf(
                    '\\in_array(%s, %s)',
                    $this->elementToCode($args[0]),
                    $this->elementToCode($args[1])
                );
            },

            // string operations
            'sub' => function ($args) {
                if (!is_array($args) or array_keys($args) != [0, 1]) {
                    throw new LengthException('"sub" operation must have 2 arguments (text, substring)');
                }
                return sprintf(
                    '\\mb_stripos(%s, %s) !== false',
                    $this->elementToCode($args[0]),
                    $this->elementToCode($args[1])
                );
            },
            're' => function ($args) {
                if (!is_array($args) or array_keys($args) != [0, 1]) {
                    throw new LengthException('"re" operation must have 2 arguments (text, regexp)');
                }
                if (!is_string($args[1])) {
                    throw new InvalidArgumentException('"re" operation must have a string as second argument');
                }
                return sprintf(
                    "\\preg_match(%s, %s)",
                    var_export($args[1], true),
                    $this->elementToCode($args[0])
                );
            },

            // inline operator
            '_' => function ($args) {
                if (is_array($args)) {
                    $params = [];
                    $params_flat = [];
                    $flat_array = true;
                    $i = 0;
                    foreach ($args as $k => $v) {
                        $el_code = $this->elementToCode($v);
                        $params[] = sprintf('%s=>%s', var_export($k, true), $el_code);
                        $params_flat[] = sprintf('%s', $el_code);
                        if (!is_int($k) or $k != $i) {
                            $flat_array = false;
                        }
                        $i++;
                    }
                    return sprintf('[%s]', implode(', ', $flat_array ? $params_flat : $params));
                } else {
                    return var_export($args, true);
                }
            },

            // native function call
            'fn' => function ($args) {
                if (!is_array($args) or count($args) < 1 or !is_string($args[0])) {
                    throw new LengthException(
                        '"fn" operation must have at least one argument (function name, [param1, [...]])'
                    );
                }

                // set function name from first arg
                $fn = array_shift($args);

                // set function params from the rest of args array
                $params = [];
                foreach ($args as $param) {
                    $params[] = $this->elementToCode($param);
                }

                return sprintf('%s(%s)', $fn, implode(', ', $params));
            },
        ];
    }

    /**
     * @param string $op
     * @param string $code
     * @param mixed $args
     * @return string
     */
    protected function op2Args(string $op, string $code, $args): string
    {
        if (!is_array($args) or array_keys($args) != [0, 1]) {
            throw new LengthException("\"$op\" operator must have 2 arguments");
        }
        $left_fmt = is_scalar($args[0]) ? '%s' : '(%s)';
        $right_fmt = is_scalar($args[1]) ? '%s' : '(%s)';
        return sprintf(
            "$left_fmt %s $right_fmt",
            $this->elementToCode($args[0]),
            $code,
            $this->elementToCode($args[1])
        );
    }

    /**
     * @param string $op
     * @param string $code
     * @param mixed $args
     * @return string
     */
    protected function op2PlusArgs(string $op, string $code, $args): string
    {
        if (!is_array($args) or count($args) < 2) {
            throw new LengthException("\"$op\" operator must have at least 2 arguments");
        }
        $parts = [];
        foreach ($args as $k => $arg) {
            if (!is_int($k)) {
                throw new InvalidArgumentException("\"$op\" operator must not have named keys, found key \"$k\"");
            }
            $fmt = is_scalar($arg) ? '%s' : '(%s)';
            $parts[] = sprintf("$fmt", $this->elementToCode($arg));
        }
        return implode(" $code ", $parts);
    }

    /**
     * @return array schema: [
     *  "op":"operation_name",
     *  "args":"operation_args",
     *  "return":"return_value"
     * ]
     * @throws InvalidArgumentException
     * @throws LengthException
     * @throws OutOfBoundsException
     */
    protected function parseOperation($element): array
    {
        if (!is_array($element)) {
            throw new InvalidArgumentException('Operation is not an array');
        }

        if (isset($element['return'])) {
            $return = $element['return'];
            unset($element['return']);
        } else {
            $return = null;
        }

        if (count($element) != 1) {
            throw new LengthException(sprintf('Operation must contain one opcode, %u present', count($element)));
        }

        $op = key($element);
        if (empty($this->op_callbacks[$op])) {
            throw new OutOfBoundsException("Unknown opcode: \"$op\"");
        }

        return [
            'op' => $op,
            'args' => current($element),
            'return' => $return,
        ];
    }

    /**
     * @param mixed $element
     * @return string
     */
    protected function elementToCode($element): string
    {
        if (is_scalar($element) or is_null($element)) {
            return var_export($element, true);
        } else {
            $op = $this->parseOperation($element);
            return $this->operationToCode($op);
        }
    }

    /**
     * @param array $op
     * @return string
     */
    protected function operationToCode(array $op): string
    {
        if (isset($op['return'])) {
            return sprintf(
                '(%s) and \gettype($success = %s)',
                $this->op_callbacks[$op['op']]($op['args']),
                $this->elementToCode($op['return'])
            );
        } else {
            return $this->op_callbacks[$op['op']]($op['args']);
        }
    }
}
