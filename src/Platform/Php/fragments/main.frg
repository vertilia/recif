<?php

%DeclareStrictTypes%

%Namespace%

class %ClassName% %Extends%
{
    public function evaluate(%ContextType% $context) %ReturnType%
    {
        // return on success
        $success = %ReturnOnSuccess%;

        // rules
        if (%Rules%) {
            return $success;
        }

        // not found
        return %ReturnOnFail%;
    }
}
