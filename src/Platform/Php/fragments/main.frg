<?php

%Namespace%

class %ClassName% %Extends%
{
    public function evaluate($context)
    {
        // return on success
        $success = true;

        // rules
        if (%Rules%) {
            return $success;
        }

        // not found
        return null;
    }
}
