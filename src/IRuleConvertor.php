<?php

namespace Recif;

interface IRuleConvertor
{
    /**
     * Instantiates RuleConvertor object, sets ruleset.
     *
     * @param mixed $ruleset
     * @param array $options list of options: {
     *  "namespace": "MyAppNamespace",
     *  "className": "MyRuleset",
     *  "extends": "MyClass",
     *  "implements": "MyInterface"
     * }
     * @return string
     */
    public function __construct($ruleset, array $options = null);

    /**
     * Returns generated code representing ruleset.
     *
     * @return string
     */
    public function convert(): string;
}
