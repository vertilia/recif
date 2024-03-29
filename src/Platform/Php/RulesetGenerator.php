<?php

declare(strict_types=1);

namespace Recif\Platform\Php;

use InvalidArgumentException;
use LengthException;
use OutOfBoundsException;
use Recif\IRulesetGenerator;

class RulesetGenerator implements IRulesetGenerator
{
    const DEFAULT_CLASS_NAME = 'Ruleset';

    // options
    protected bool $declare_strict_types = false;
    protected ?string $comment = null;
    protected ?string $namespace = null;
    protected bool $static = false;
    protected string $class_name = self::DEFAULT_CLASS_NAME;
    protected ?string $extends = null;
    protected ?string $implements = null;
    protected ?string $context_type = null;
    protected ?string $return_type = null;
    protected string $return_on_success = 'true';
    protected string $return_on_fail = 'false';
    protected bool $php5 = false;

    protected array $opt2prop = [
        'declareStrictTypes' => 'declare_strict_types',
        'comment' => 'comment',
        'namespace' => 'namespace',
        'static' => 'static',
        'className' => 'class_name',
        'extends' => 'extends',
        'implements' => 'implements',
        'contextType' => 'context_type',
        'returnType' => 'return_type',
        'returnOnSuccess' => 'return_on_success',
        'returnOnFail' => 'return_on_fail',
        'php5' => 'php5',
    ];

    protected array $context_refs = [];

    /** @var mixed $ruleset */
    protected $ruleset = true;

    // operations callbacks
    protected array $op_callbacks;

    /**
     * Instantiates RuleConvertor object, sets ruleset.
     *
     * @param mixed|null $ruleset
     * @param ?array $options list of options: {
     *  "namespace": "MyAppNamespace",
     *  "className": "MyRuleset",
     *  "extends": "MyClass",
     *  "implements": "MyInterface"
     * }
     */
    public function __construct($ruleset = null, ?array $options = null)
    {
        // options
        if ($options) {
            $this->addOptions($options);
        }

        // ruleset
        if (null !== $ruleset) {
            $this->setRules($ruleset);
        }

        // op callbacks
        $this->initCallbacks();
    }

    /**
     * @param mixed $ruleset
     * @return $this
     */
    public function setRules($ruleset): self
    {
        $this->ruleset = $ruleset;
        $this->context_refs = [];

        return $this;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function addOptions(array $options): self
    {
        foreach (array_intersect_key($this->opt2prop, $options) as $opt => $prop) {
            $this->$prop = $options[$opt];
        }

        if ($this->php5) {
            $this->declare_strict_types = false;
            $this->return_type = null;
        }

        return $this;
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

        $conditions = $this->elementToCode($this->ruleset);

        $code = preg_replace(
            [
                '/%DeclareStrictTypes%\s*/ui',
                '/%Comment%\s*/ui',
                '/%Namespace%\s*/ui',
                '/%Static%\s*/ui',
                '/%ClassName%/ui',
                '/\s*%Extends%/ui',
                '/%ContextType%\s*/ui',
                '/\s*%ReturnType%/ui',
                '/%ReturnOnSuccess%/ui',
                '/%ReturnOnFail%/ui',
            ],
            [
                $this->declare_strict_types ? "declare(strict_types=1);\n\n" : null,
                $this->comment ? "/* $this->comment */\n\n" : null,
                $this->namespace ? "namespace $this->namespace;\n\n" : null,
                $this->static ? "static " : null,
                $this->class_name,
                $extends_parts ? ' ' . implode(' ', $extends_parts) : null,
                $this->context_type ? "$this->context_type " : null,
                $this->return_type ? " : $this->return_type" : null,
                $this->return_on_success,
                $this->return_on_fail,
            ],
            file_get_contents(__DIR__ . '/fragments/main.frg')
        );

        // treat lines with code separately since they may contain symbols treated as pcre backreferences
        if ($this->context_refs) {
            $margin = preg_match('/^([ \t]*)%LocalVars%/uim', $code, $m) ? $m[1] : ' ';
            $code = str_ireplace(
                '%LocalVars%',
                "// local vars\n$margin" .
                implode(
                    "\n$margin",
                    array_column($this->context_refs, 'code')
                ),
                $code
            );
        } else {
            $code = preg_replace('/%LocalVars%\s*/ui', '', $code);
        }

        return str_ireplace('%Rules%', $conditions, $code);
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
                if ('' === trim($arg)) {
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
                    $var_name = isset($this->context_refs[$arg])
                        ? $this->context_refs[$arg]['var']
                        : ('$C_' . (count($this->context_refs) + 1));
                    $this->context_refs[$arg] = [
                        'var' => $var_name,
                        'code' => "$var_name = " .
                            ($this->php5
                                ? sprintf('isset($context%s) ? $context%s : null', $whole, $whole)
                                : sprintf('$context%s ?? null', $whole)
                            ) .
                            ';'
                    ];

                    return "$var_name";
                }
            },

            // comparison operators
            'eq' => function ($args) {
                return $this->op2Args('eq', '==', $args);
            },
            '===' => function ($args) {
                return $this->op2Args('===', '===', $args);
            },
            'ne' => function ($args) {
                return $this->op2Args('ne', '!=', $args);
            },
            '!==' => function ($args) {
                return $this->op2Args('!==', '!==', $args);
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
                    'rand(%s, %s)',
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
                    'in_array(%s, %s, true)',
                    $this->elementToCode($args[0]),
                    $this->elementToCode($args[1])
                );
            },
            'inx' => function ($args) {
                if (!is_array($args) or array_keys($args) != [0, 1]) {
                    throw new LengthException('"inx" operation must have 2 arguments');
                }
                if (!is_array($args[1])) {
                    throw new InvalidArgumentException('"inx" operation must have a map as second argument');
                }

                return sprintf(
                    '(function ($i, $m) {' .
                    'return array_key_exists($i, $m) ' .
                        '? $m[$i] ' .
                        ': (array_key_exists(\'default\', $m) ? $m[\'default\'] : false);' .
                    '})(%s, %s)',
                    $this->elementToCode($args[0]),
                    $this->elementToCode($args[1])
                );
            },

            // string operations
            'sub' => function ($args) {
                if (!is_array($args) or array_keys($args) != [0, 1]) {
                    throw new LengthException('"sub" operation must have 2 arguments (substring, text)');
                }
                return sprintf(
                    'mb_stripos(%s, %s) !== false',
                    $this->elementToCode($args[1]),
                    $this->elementToCode($args[0])
                );
            },
            're' => function ($args) {
                if (!is_array($args) or array_keys($args) != [0, 1]) {
                    throw new LengthException('"re" operation must have 2 arguments (regexp, text)');
                }
                if (!is_string($args[0])) {
                    throw new InvalidArgumentException('"re" operation must have a string as first argument');
                }
                return sprintf(
                    "preg_match(%s, %s)",
                    var_export($args[0], true),
                    $this->elementToCode($args[1])
                );
            },
            'spf' => function ($args) {
                if (!is_array($args) or empty($args) or !is_string($args[0])) {
                    throw new LengthException('"spf" operation must have format string as first argument');
                }

                // set format string from first arg
                $format = array_shift($args);

                // set sprintf params from the rest of args array
                $params = [];
                foreach ($args as $param) {
                    $params[] = ', ' . $this->elementToCode($param);
                }

                return sprintf('sprintf(%s%s)', var_export($format, true), implode('', $params));
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
                if (!is_array($args) or empty($args)) {
                    throw new LengthException('"fn" operation must have function name as first argument');
                }

                // set function name from first arg
                $fn = array_shift($args);

                // set function params from the rest of args array
                $params = [];
                foreach ($args as $param) {
                    $params[] = $this->elementToCode($param);
                }

                return sprintf(
                    '%s(%s)',
                    is_string($fn) ? $fn : $this->elementToCode($fn),
                    implode(', ', $params)
                );
            },
        ];
    }

    /**
     * sprintf pattern for argument (whether to use parenthesis around argument value)
     *
     * @param mixed $arg
     * @return string
     */
    protected function argFmt($arg): string
    {
        return (
            is_scalar($arg)
            or is_null($arg)
            or (is_array($arg) and (array_key_exists('cx', $arg) or array_key_exists('fn', $arg)))
        )
            ? '%s'
            : '(%s)';
    }

    /**
     * Operation with 2 arguments.
     *
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
        $left_fmt = $this->argFmt($args[0]);
        $right_fmt = $this->argFmt($args[1]);

        return sprintf(
            "$left_fmt %s $right_fmt",
            $this->elementToCode($args[0]),
            $code,
            $this->elementToCode($args[1])
        );
    }

    /**
     * Operation with 2 or more arguments.
     *
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
            $parts[] = sprintf($this->argFmt($arg), $this->elementToCode($arg));
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
            throw new LengthException(sprintf(
                'Operation must contain one opcode, %u present: %s',
                count($element),
                json_encode($element, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ));
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
                '(%s) and gettype($success = %s)',
                $this->op_callbacks[$op['op']]($op['args']),
                $this->elementToCode($op['return'])
            );
        } else {
            return $this->op_callbacks[$op['op']]($op['args']);
        }
    }
}
