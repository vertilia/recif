<?php

namespace Recif\Platform\Php;

class RulesetGeneratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider rulesetsWithoutContextProvider
     */
    public function testWithoutContext($ruleset, $options, $pattern)
    {
        $rc = new RulesetGenerator($ruleset, $options);
        $code = $rc->generate();
        $this->assertRegExp($pattern, $code);
    }

    public function rulesetsWithoutContextProvider()
    {
        return [
            // options
            [true, ['declareStrictTypes' => true], '/^<\?php\s+declare\(strict_types=1\);/'],
            [true, ['namespace' => 'Test'], '/^<\?php\s+namespace\s+Test;/'],
            [true, ['className' => 'Class1'], '/^<\?php\s+class\s+Class1\s/'],
            [true, ['extends' => 'Class2'], '/^<\?php\s+class\s+\w+\s+extends\s+Class2\s/'],
            [true, ['implements' => 'Interface2'], '/^<\?php\s+class\s+\w+\s+implements\s+Interface2\s/'],
            [
                true,
                ['extends' => 'Class2', 'implements' => 'Interface2'],
                '/^<\?php\s+class\s+\w+\s+extends\s+Class2\s+implements\s+Interface2\s/',
            ],
            [true, ['contextType' => 'ContextType'], '/\sevaluate\s*\(ContextType\s+\$context\)/'],
            [true, ['returnType' => 'boolean'], '/\sevaluate\s*\(\$context\)\s*:\s*boolean\s*\{/'],
            [true, ['returnOnSuccess' => 'false'], '/\$success\s*=\s*false;/'],
            [true, ['returnOnFail' => 'null'], '/return\s+null;/'],
            [
                true,
                ['declareStrictTypes' => true, 'php5' => true],
                '/^<\?php\s+class\s+\b/',
            ],
            [
                true,
                ['returnType' => 'boolean', 'php5' => true],
                '/\sevaluate\s*\(\$context\)\s*\{/',
            ],

            // scalars and null
            [true, null, '/\(true\)/'],
            [false, null, '/\(false\)/'],
            [null, null, '/\(null\)/'],
            [1, null, '/\(1\)/'],
            [1.5, null, '/\(1\.5\)/'],
            [-1.5, null, '/\(-1\.5\)/'],
            ['string', null, '/\(\'string\'\)/'],
            ["o'string", null, "/\('o\\\'string'\)/"],
            ["o\tstring", null, "/\('o\tstring'\)/"],
            ['o\tstring', null, "/\('o\\\\tstring'\)/"],
            ['o\\string', null, "/\('o\\\\string'\)/"],
            ['Вася', null, "/\('Вася'\)/"],

            // single operations
            [['cx' => ''], null, '/\$context/'],
            [['cx' => 'country'], null, '/\$context\[\'country\'\]/'],
            [['cx' => 'country'], ['php5' => true], '/isset\(\$context\[\'country\'\]\)/'],
            [['cx' => 'countries.0'], null, '/\$context\[\'countries\']\[0\]/'],
            [['cx' => "country.o\\name"], null, '/\$context\[\'country\']\[\'o\\\\name\'\]/'],

            [['eq' => [1, 2]], null, '/\(\(?1\)?\s?==\s?\(?2\)?\)/'],
            [['ne' => [1, 2]], null, '/\(\(?1\)?\s?!=\s?\(?2\)?\)/'],
            [['lt' => [1, 2]], null, '/\(\(?1\)?\s?<\s?\(?2\)?\)/'],
            [['le' => [1, 2]], null, '/\(\(?1\)?\s?<=\s?\(?2\)?\)/'],
            [['gt' => [1, 2]], null, '/\(\(?1\)?\s?>\s?\(?2\)?\)/'],
            [['ge' => [1, 2]], null, '/\(\(?1\)?\s?>=\s?\(?2\)?\)/'],
            
            [['or' => [0, 1, 2]], null, '/\(\(?0\)?\s?or\s?\(?1\)?\s?or\s?\(?2\)?\)/'],
            [['and' => [0, 1, 2]], null, '/\(\(?0\)?\s?and\s?\(?1\)?\s?and\s?\(?2\)?\)/'],
            [['not' => false], null, '/\(!\(?false\)?\)/'],
            
            [['in' => ['a', ['a', 'b', 'c']]], null, "/\(\\\\?in_array\(['\"]a['\"],\s?\[[abc,'\" ]+\]\)\)/"],
            
            [
                ['sub' => ['sentence with words', 'word']],
                null,
                "/\(\\\\?strpos\(['\"][a-z ]+['\"],\s?['\"]word['\"]\)\s?\!==\s?false\)/"
            ],
            [
                ['re' => ['121352', '^\d+$']],
                null,
                "/\(\\\\?preg_match\(['\"][^'\"]+['\"],\s?['\"]121352['\"]\)\)/"
            ],

            // multiple operations
            [['eq' => [0, ["not"=>1]]], null, '/\(\(?0\)?\s?==\s?\(?!1\)?\)/'],
            [
                ['and' => [true, ["not"=>false]]],
                null,
                '/\(\(?true\)?\s?and\s?\(?!\(?false\)?\)?\)/'
            ],

            // examples
            [
                \json_decode('{"gt": [{"cx":""}, 10]}', true),
                null,
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
                null,
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
                null,
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
        $this->assertTrue($expected === $ruleset->evaluate($context));

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
            [true, null, true, ['namespace' => 'MyTest', 'className' => 'Ruleset'.__LINE__], '\MyTest\Ruleset'.__LINE__],

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
