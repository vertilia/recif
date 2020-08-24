<?php

namespace Recif\Platform\Php;

class RuleConvertorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider rulesetsWithoutContextProvider
     */
    public function testWithoutContext($ruleset, $options, $pattern)
    {
        $rc = new RuleConvertor($ruleset, $options);
        $code = $rc->convert();
        $this->assertRegExp($pattern, $code);
    }

    public function rulesetsWithoutContextProvider()
    {
        return [
            // options
            [true, ['namespace' => 'Test'], '/namespace\s+Test;/'],
            [true, ['extends' => 'Class2'], '/\s+extends\s+Class2\b/'],
            [true, ['implements' => 'Interface2'], '/\s+implements\s+Interface2\b/'],
            [
                true,
                ['extends' => 'Class2', 'implements' => 'Interface2'],
                '/\s+extends\s+Class2\s+implements\s+Interface2\b/',
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
    public function testWithContext($ruleset, $options, $pattern)
    {
        $rc = new RuleConvertor($ruleset, $options);
        $code = $rc->convert();
        print_r($code);
        $this->assertRegExp($pattern, $code);
    }

    public function rulesetsWithContextProvider()
    {
        return [
            
        ];
    }
}
