<?php

namespace Recif;

interface IRulesetGenerator
{
    /**
     * Returns generated code representing ruleset.
     *
     * @return string
     */
    public function generate(): string;
}
