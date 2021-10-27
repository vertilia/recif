<?php

namespace Recif;

interface IRulesetGenerator
{
    /**
     * Set rules for generation.
     *
     * @return $this
     */
    public function setRules($ruleset): self;

    /**
     * Returns generated code representing ruleset.
     *
     * @return string
     */
    public function generate(): string;
}
