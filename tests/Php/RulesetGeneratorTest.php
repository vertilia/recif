<?php

namespace Recif\Platform\Php;

class RulesetGeneratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider rulesetsOptionsProvider
     */
    public function testOptions($options, $pattern)
    {
        $rc = new RulesetGenerator(true, $options);
        $code = $rc->generate();
        $this->assertRegExp($pattern, $code);
    }

    public function rulesetsOptionsProvider()
    {
        return [
            // options
            [['declareStrictTypes' => true], '/^<\?php\s+declare\(strict_types=1\);/'],
            [['namespace' => 'Test'], '/^<\?php\s+namespace\s+Test;/'],
            [['className' => 'Class1'], '/^<\?php\s+class\s+Class1\s/'],
            [['extends' => 'Class2'], '/^<\?php\s+class\s+\w+\s+extends\s+Class2\s/'],
            [['implements' => 'Interface2'], '/^<\?php\s+class\s+\w+\s+implements\s+Interface2\s/'],
            [
                ['extends' => 'Class2', 'implements' => 'Interface2'],
                '/^<\?php\s+class\s+\w+\s+extends\s+Class2\s+implements\s+Interface2\s/',
            ],
            [['contextType' => 'ContextType'], '/\sevaluate\s*\(ContextType\s+\$context\)/'],
            [['returnType' => 'boolean'], '/\sevaluate\s*\(\$context\)\s*:\s*boolean\s*\{/'],
            [['returnOnSuccess' => 'false'], '/\$success\s*=\s*false;/'],
            [['returnOnFail' => 'null'], '/return\s+null;/'],
            [
                ['declareStrictTypes' => true, 'php5' => true],
                '/^<\?php\s+class\s+\b/',
            ],
            [
                ['returnType' => 'boolean', 'php5' => true],
                '/\sevaluate\s*\(\$context\)\s*\{/',
            ],

        ];
    }

    /**
     * @dataProvider rulesetsWithoutContextProvider
     */
    public function testWithoutContext($ruleset, $pattern, $options = null)
    {
        $rc = new RulesetGenerator($ruleset, $options);
        $code = $rc->generate();
        $this->assertRegExp($pattern, $code);
    }

    public function rulesetsWithoutContextProvider()
    {
        return [
            // scalars and null
            [true, '/\(true\)/i'],
            [false, '/\(false\)/i'],
            [null, '/\(null\)/i'],
            [1, '/\(1\)/'],
            [1.5, '/\(1\.5\)/'],
            [-1.5, '/\(-1\.5\)/'],
            ['string', '/\(\'string\'\)/'],
            ["o'string", "/\('o\\\'string'\)/"],
            ["o\tstring", "/\('o\tstring'\)/"],
            ['o\tstring', "/\('o\\\\tstring'\)/"],
            ['o\\string', "/\('o\\\\string'\)/"],
            ['Вася', "/\('Вася'\)/"],

            // single operations

            // context
            [['cx' => ''], '/\$context/'],
            [['cx' => 'country'], '/\$context\[\'country\'\]/'],
            [['cx' => 'country'], '/isset\(\$context\[\'country\'\]\)/', ['php5' => true]],
            [['cx' => 'countries.0'], '/\$context\[\'countries\']\[0\]/'],
            [['cx' => "country.o\\name"], '/\$context\[\'country\']\[\'o\\\\name\'\]/'],

            // comparison
            [['eq' => [1, 2]], '/\(\(?1\)?\s?==\s?\(?2\)?\)/'],
            [['ne' => [1, 2]], '/\(\(?1\)?\s?!=\s?\(?2\)?\)/'],
            [['lt' => [1, 2]], '/\(\(?1\)?\s?<\s?\(?2\)?\)/'],
            [['le' => [1, 2]], '/\(\(?1\)?\s?<=\s?\(?2\)?\)/'],
            [['gt' => [1, 2]], '/\(\(?1\)?\s?>\s?\(?2\)?\)/'],
            [['ge' => [1, 2]], '/\(\(?1\)?\s?>=\s?\(?2\)?\)/'],

            // logical
            [['or' => [0, 1, 2]], '/\(\(?0\)?\s?or\s?\(?1\)?\s?or\s?\(?2\)?\)/'],
            [['and' => [0, 1, 2]], '/\(\(?0\)?\s?and\s?\(?1\)?\s?and\s?\(?2\)?\)/'],
            [['not' => false], '/\(!\(?false\)?\)/'],

            // math
            [['mod' => [121, 100]], '/\b121\b.*%.*\b100\b/'],
            [['rnd' => [4000, 4999]], '/\b4000\b.*,.*\b4999\b/'],

            // arrays
            [['in' => ['a', ['a', 'b', 'c']]], "/\(\\\\?in_array\(['\"]a['\"],\s?\[[abc,'\" ]+\]\)\)/"],

            // strings
            [
                ['sub' => ['sentence with words', 'word']],
                "/\(\\\\?strpos\(['\"][a-z ]+['\"],\s?['\"]word['\"]\)\s?\!==\s?false\)/"
            ],
            [
                ['re' => ['121352', '/^\d+$/']],
                "/\(\\\\?preg_match\(['\"][^'\"]+['\"],\s?['\"]121352['\"]\)\)/"
            ],

            // flatten
            [['_' => true], '/\bif\s*\(\s*true\s*\)/'],
            [['_' => 'string'], '/\bif\s*\(.*string.*\)/'],
            [['_' => ['a', 'b']], "/'a'\s*,\s*'b'/"],
            [['_' => ['a', 'b' => 'c']], "/'a'\s*,\s*'b'\s*=>\s*'c'/"],

            // multiple operations

            // comparison
            [['eq' => [0, ['not' => 1]]], '/\(\(?0\)?\s?==\s?\(?!1\)?\)/'],
            [
                ['and' => [true, ['not' => false]]],
                '/\(\(?true\)?\s?and\s?\(?!\(?false\)?\)?\)/'
            ],

            // flatten
            [['_' => [1, ['_' => []], 3]], "/\b1\s*,\s*(\[\s*\]|array\(\s*\))\s*,\s*3\b/"],
            [['_' => ['a' => ['_' => ['b' => 'c']]]], "/'a'\s*=>.*'b'\s*=>\s*'c'/"],

            // nafive function call
            [
                ['fn' => ['Currencies::getRateForCountryAndTime', ['cx' => 'country'], ['fn' => ['time']]]],
                '/Currencies::getRateForCountryAndTime\s*\(.*\$context.*,\s*\btime\s*\(\s*\)\s*\)/'
            ],

            // examples
            [
                \json_decode('{"gt": [{"cx":""}, 10]}', true),
                '/\(\(?\$context\)?\s?>\s?\(?10\)?\)/',
            ],
            [
                \json_decode(
                    '{
                        "and": [
                        {"ge": [{"cx":""}, 0]},
                        {"le": [{"cx":""}, 100]}
                        ]
                    }',
                    true
                ),
                '/\(\(?\$context\)?\s?<=\s?\(?100\)?\)/',
            ],
            [
                \json_decode(
                    '{
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
                    }',
                    true
                ),
                '/North America/',
            ]
        ];
    }

    /**
     * @dataProvider rulesetsWithContextProvider
     */
    public function testWithContext($ruleset, $context, $expected, $options, $classname)
    {
        // produce the code
        $rc = new RulesetGenerator($ruleset, $options);
        $code = $rc->generate();
        $this->assertStringStartsWith('<?php', $code);

        // write code to the temp file
        $code_file = \tempnam(\sys_get_temp_dir(), 'test');
        $this->assertGreaterThan(0, \file_put_contents($code_file, $code));

        // include the temp file (this will define the class)
        $this->assertTrue(!\class_exists($classname));
        include $code_file;
        $this->assertTrue(\class_exists($classname));

        // run the test with specified context
        $ruleset = new $classname();
        $this->assertTrue($expected === $ruleset->evaluate($context), $code);

        // remove test file
        \unlink($code_file);
    }

    public function rulesetsWithContextProvider()
    {
        $example1 = \json_decode(<<<EOJ1
{
  "gt": [{"cx":""}, 10]
}
EOJ1
, true);

        $example2 = \json_decode(<<<EOJ2
{
  "and": [
    {"ge": [{"cx":""}, 0]},
    {"le": [{"cx":""}, 100]}
  ]
}
EOJ2
, true);

        $example3 = \json_decode(<<<EOJ3
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
EOJ3
, true);

        return [
            [true, null, true, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [
                true,
                null,
                true,
                ['namespace' => 'MyTest', 'className' => 'Ruleset'.__LINE__], '\MyTest\Ruleset'.__LINE__
            ],
            [
                ['lt' => [['fn' => ['strtotime', 'yesterday']], ['cx' => '']]],
                time(),
                true,
                ['namespace' => 'MyTest', 'className' => 'Ruleset'.__LINE__], '\MyTest\Ruleset'.__LINE__
            ],

            // returns
            [
                [
                    'lt' => [['fn' => ['strtotime', 'yesterday']], ['cx' => '']],
                    'return' => ['fn' => ['strtotime', 'tomorrow']]
                ],
                \strtotime('today'),
                \strtotime('tomorrow'),
                ['namespace' => 'MyTest', 'className' => 'Ruleset'.__LINE__], '\MyTest\Ruleset'.__LINE__
            ],
            [
                [
                    'in' => [['fn' => ['\strtolower', ['cx' => '']]], ['fr', 'de', 'it']],
                    'return' => ['_' => ['EUR', 1]]
                ],
                'FR',
                ['EUR', 1],
                ['namespace' => 'MyTest', 'className' => 'Ruleset'.__LINE__], '\MyTest\Ruleset'.__LINE__
            ],
            [
                [
                    '_' => true,
                    'return' => ['cx' => 'country']
                ],
                ['country' => 'FR'],
                'FR',
                ['namespace' => 'MyTest', 'className' => 'Ruleset'.__LINE__], '\MyTest\Ruleset'.__LINE__
            ],

            // example 1
            [$example1, -1, false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example1, 0, false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example1, 1, false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example1, 10, false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example1, 11, true, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example1, 'string', false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example1, [], true, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__], // @WTF?
            [$example1, (object)[], false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],

            // example 2
            [$example2, -1, false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example2, 0, true, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example2, 1, true, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example2, 100, true, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example2, 101, false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example2, 'string', true, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example2, [], false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
            [$example2, (object)[], true, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__], // @WTF?

            // example 3
            [
                $example3,
                ['country' => 'US', 'currency' => 'USD'],
                'North America',
                ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__
            ],
            [
                $example3,
                ['country' => 'FR', 'currency' => 'EUR'],
                'Europe',
                ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__
            ],
            [
                $example3,
                ['country' => 'FR', 'currency' => 'USD'],
                false,
                ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__
            ],
            [$example3, [], false, ['className' => 'Ruleset'.__LINE__], 'Ruleset'.__LINE__],
        ];
    }
}
