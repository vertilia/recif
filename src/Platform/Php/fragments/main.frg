<?php

%DeclareStrictTypes%

%Namespace%

class %ClassName% %Extends%
{
    public %Static% function evaluate(%ContextType% $context) %ReturnType%
    {
        // return on success
        $success = %ReturnOnSuccess%;

        %LocalVars%

        // rules
        if (%Rules%) {
            return $success;
        }

        // not found
        return %ReturnOnFail%;
    }
}
