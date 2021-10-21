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
        $this->assertMatchesRegularExpression($pattern, $code);
    }

    public function rulesetsOptionsProvider()
    {
        return [
            // options
            [['declareStrictTypes' => true], '/^<\?php\s+declare\(strict_types=1\);/'],
            [['namespace' => 'Test\\Ruleset'], '/^namespace\s+Test\\\\Ruleset;/m'],
            [['className' => 'Class1'], '/^class\s+Class1\b/m'],
            [['extends' => 'Class2'], '/^class\s+\w+\s+extends\s+Class2\b/m'],
            [['implements' => 'Interface2'], '/^class\s+\w+\s+implements\s+Interface2\b/m'],
            [
                ['extends' => 'Class2', 'implements' => 'Interface2'],
                '/^\bclass\s+\w+\s+extends\s+Class2\s+implements\s+Interface2\b/m',
            ],
            [['contextType' => 'ContextType'], '/function\s+evaluate\s*\(ContextType\s+\$context\)/'],
            [['returnType' => 'boolean'], '/function\s+evaluate\s*\(\$context\)\s*:\s*boolean\s*\{/'],
            [['returnOnSuccess' => 'false'], '/\$success\s*=\s*false;/'],
            [['returnOnFail' => 'null'], '/return\s+null;/'],
            // php5 mode incompatible with strict_types and return type directives
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
     * @dataProvider rulesetsWithContextProvider
     */
    public function testWithContext($ruleset, $context, $expected, $line)
    {
        $classname = "Ruleset$line";

        // produce the code
        $rc = new RulesetGenerator($ruleset, ['className' => $classname]);
        $code = $rc->generate();
        $this->assertStringStartsWith('<?php', $code);

        // write code to the temp file
        $code_file = tempnam(sys_get_temp_dir(), 'test');
        $this->assertGreaterThan(0, file_put_contents($code_file, $code));

        // include the temp file (this will define the class)
        $this->assertTrue(!class_exists($classname));
        include $code_file;
        $this->assertTrue(class_exists($classname));

        // run the test with specified context
        $ruleset = new $classname();
        $this->assertEquals($expected, $ruleset->evaluate($context), var_export($context, 1) . " -> $code");

        // remove test file
        unlink($code_file);
    }

    public function rulesetsWithContextProvider(): array
    {
        $example1 = json_decode(<<<EOJ1
{
  "gt": [{"cx":""}, 10]
}
EOJ1
        , true);

        $example2 = json_decode(<<<EOJ2
{
  "and": [
    {"ge": [{"cx":""}, 0]},
    {"le": [{"cx":""}, 100]}
  ]
}
EOJ2
        , true);

        $example3 = json_decode(<<<EOJ3
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
EOJ3
        , true);

        return [
            // scalars and null

            [true, true, true, __LINE__],
            [true, false, true, __LINE__],
            [true, null, true, __LINE__],
            [true, 1, true, __LINE__],
            [true, "Вася", true, __LINE__],
            [true, [], true, __LINE__],

            [false, true, false, __LINE__],
            [false, false, false, __LINE__],
            [false, null, false, __LINE__],
            [false, 1, false, __LINE__],
            [false, "Вася", false, __LINE__],
            [false, [], false, __LINE__],

            [null, true, false, __LINE__],
            [null, false, false, __LINE__],
            [null, null, false, __LINE__],
            [null, 1, false, __LINE__],
            [null, "Вася", false, __LINE__],
            [null, [], false, __LINE__],

            [1, true, true, __LINE__],
            [1, false, true, __LINE__],
            [1, null, true, __LINE__],
            [1, 1, true, __LINE__],
            [1, "Вася", true, __LINE__],
            [1, [], true, __LINE__],

            ["Вася", true, true, __LINE__],
            ["Вася", false, true, __LINE__],
            ["Вася", null, true, __LINE__],
            ["Вася", 1, true, __LINE__],
            ["Вася", "Вася", true, __LINE__],
            ["Вася", [], true, __LINE__],

            [['_' => []], true, false, __LINE__],
            [['_' => []], false, false, __LINE__],
            [['_' => []], null, false, __LINE__],
            [['_' => []], 1, false, __LINE__],
            [['_' => []], "Вася", false, __LINE__],
            [['_' => []], [], false, __LINE__],

            // strings

            ["string", null, true, __LINE__],
            [' ', null, true, __LINE__],
            ['0.0', null, true, __LINE__],
            ["0string", null, true, __LINE__],
            ['', null, false, __LINE__],
            ['0', null, false, __LINE__],

            // context

            [['cx' => ''], true, true, __LINE__],
            [['cx' => ''], false, false, __LINE__],
            [['cx' => ''], null, false, __LINE__],
            [['cx' => ''], 1, true, __LINE__],
            [['cx' => ''], "Вася", true, __LINE__],
            [['cx' => ''], [], false, __LINE__],

            [['cx' => 'country'], ['country' => 'FR'], true, __LINE__],
            [['cx' => 'country'], ['countrix' => 'FR'], false, __LINE__],
            [['cx' => 'country'], null, false, __LINE__],
            [['cx' => 'countries.0'], ['countries' => ['DE', 'FR', 'IT']], true, __LINE__],
            [['cx' => 'countries.0'], ['countries' => [1=>'DE', 'FR', 'IT']], false, __LINE__],
            [['cx' => 'countries.0'], null, false, __LINE__],
            [['cx' => 'country.o\\name'], ['country' => ['o\\name' => 'ON']], true, __LINE__],
            [['cx' => 'country.o\\name'], ['country' => ['o-name' => 'ON']], false, __LINE__],
            [['cx' => 'country.o\\name'], null, false, __LINE__],

            // comparisons

            [['eq' => [1, ['cx' => '']]], 2, false, __LINE__],
            [['eq' => [2, ['cx' => '']]], '02', true, __LINE__],
            [['eq' => [2, ['cx' => '']]], 2, true, __LINE__],
            [['===' => [1, ['cx' => '']]], 2, false, __LINE__],
            [['===' => [2, ['cx' => '']]], '2', false, __LINE__],
            [['===' => [2, ['cx' => '']]], 2, true, __LINE__],
            [['ne' => [1, ['cx' => '']]], 2, true, __LINE__],
            [['ne' => [2, ['cx' => '']]], '02', false, __LINE__],
            [['ne' => [2, ['cx' => '']]], 2, false, __LINE__],
            [['!==' => [1, ['cx' => '']]], 2, true, __LINE__],
            [['!==' => [2, ['cx' => '']]], '2', true, __LINE__],
            [['!==' => [2, ['cx' => '']]], 2, false, __LINE__],
            [['lt' => [1, ['cx' => '']]], 2, true, __LINE__],
            [['lt' => [2, ['cx' => '']]], 2, false, __LINE__],
            [['le' => [1, ['cx' => '']]], 2, true, __LINE__],
            [['le' => [2, ['cx' => '']]], 2, true, __LINE__],
            [['gt' => [1, ['cx' => '']]], 2, false, __LINE__],
            [['gt' => [2, ['cx' => '']]], 2, false, __LINE__],
            [['ge' => [1, ['cx' => '']]], 2, false, __LINE__],
            [['ge' => [2, ['cx' => '']]], 2, true, __LINE__],

            // complex comparisons

            [['eq' => [0, ['not' => 1]]], null, true, __LINE__],
            [['and' => [true, ['not' => false]]], null, true, __LINE__],

            // logical

            [['or' => [0, ['cx' => '']]], 0, false, __LINE__],
            [['or' => [0, ['cx' => '']]], 1, true, __LINE__],
            [['or' => [1, ['cx' => '']]], 0, true, __LINE__],
            [['or' => [1, ['cx' => '']]], 1, true, __LINE__],

            [['and' => [0, ['cx' => '']]], 0, false, __LINE__],
            [['and' => [0, ['cx' => '']]], 1, false, __LINE__],
            [['and' => [1, ['cx' => '']]], 0, false, __LINE__],
            [['and' => [1, ['cx' => '']]], 1, true, __LINE__],

            [['not' => ['cx' => '']], 1, false, __LINE__],
            [['not' => ['cx' => '']], 0, true, __LINE__],

            // math

            [['eq' => [21, ['mod' => [['cx' => ''], 100]]]], 120, false, __LINE__],
            [['eq' => [21, ['mod' => [['cx' => ''], 100]]]], 121, true, __LINE__],
            [['eq' => [21, ['mod' => [['cx' => ''], 100]]]], 122, false, __LINE__],
            [['lt' => [21, ['rnd' => [['cx' => 'lo'], ['cx' => 'hi']]]]], ['lo' => 22, 'hi' => 25], true, __LINE__],
            [['lt' => [21, ['rnd' => [['cx' => 'lo'], ['cx' => 'hi']]]]], ['lo' => 10, 'hi' => 20], false, __LINE__],

            // arrays

            [['in' => [['cx' => ''], ['_' => ['a', 'b', 'c']]]], 'a', true, __LINE__],
            [['in' => [['cx' => ''], ['_' => ['a', 'b', 'c']]]], 'x', false, __LINE__],

            // strings

            [['sub' => [['cx' => ''], 'медвед']], 'Превед, Медвед!', true, __LINE__],
            [['sub' => [['cx' => ''], 'МЕДВЕД']], 'Превед, Медвед!', true, __LINE__],
            [['sub' => [['cx' => ''], 'лошад']], 'Превед, Медвед!', false, __LINE__],
            [['re' => [['cx' => ''], '/^\d{5}$/']], '00000', true, __LINE__],
            [['re' => [['cx' => ''], '/^\d{5}$/']], '12345', true, __LINE__],
            [['re' => [['cx' => ''], '/^\d{5}$/']], ' 12345', false, __LINE__],
            [['re' => [['cx' => ''], '/^\d{5}$/']], '12345 ', false, __LINE__],
            [['re' => [['cx' => ''], '/^\d{5}$/']], '123', false, __LINE__],

            // inlines and returns

            [
                [
                    '_' => true,
                    'return' => ['_' => [1, ['_' => []], 3]]
                ],
                null,
                [1, [], 3],
                __LINE__
            ],
            [
                [
                    '_' => true,
                    'return' => ['_' => ['a' => ['_' => ['b' => 'c']]]]
                ],
                null,
                ['a' => ['b' => 'c']],
                __LINE__
            ],
            [
                [
                    'lt' => [['fn' => ['strtotime', 'yesterday']], ['cx' => '']],
                    'return' => ['fn' => ['strtotime', 'tomorrow']]
                ],
                strtotime('today'),
                strtotime('tomorrow'),
                __LINE__
            ],
            [
                [
                    'in' => [['fn' => ['\strtolower', ['cx' => '']]], ['_' => ['fr', 'de', 'it']]],
                    'return' => ['_' => ['EUR', 1]]
                ],
                'FR',
                ['EUR', 1],
                __LINE__
            ],
            [
                [
                    '_' => true,
                    'return' => ['cx' => 'country']
                ],
                ['country' => 'FR'],
                'FR',
                __LINE__
            ],

            // native function call

            [
                ['and' => [
                    ['lt' => [['fn' => ['strtotime', 'yesterday']], ['cx' => '']]],
                    ['gt' => [['fn' => ['strtotime', 'tomorrow']], ['cx' => '']]]
                ]],
                time(),
                true,
                __LINE__
            ],

            // example 1
            [$example1, -1, false, __LINE__],
            [$example1, 0, false, __LINE__],
            [$example1, 1, false, __LINE__],
            [$example1, 10, false, __LINE__],
            [$example1, 11, true, __LINE__],

            // example 2
            [$example2, -1, false, __LINE__],
            [$example2, 0, true, __LINE__],
            [$example2, 1, true, __LINE__],
            [$example2, 100, true, __LINE__],
            [$example2, 101, false, __LINE__],

            // example 3
            [$example3, ['country' => 'US', 'currency' => 'USD'], 'North America', __LINE__],
            [$example3, ['country' => 'FR', 'currency' => 'EUR'], 'Europe', __LINE__],
            [$example3, ['country' => 'FR', 'currency' => 'USD'], false, __LINE__],
            [$example3, [], false, __LINE__],
        ];
    }
}
