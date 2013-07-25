<?php

namespace bugfree\fix;


class RemoveLineFix extends AbstractFix
{
    public function run(array &$fileLines)
    {
        array_splice($fileLines, $this->getLine() - 1, 1);
    }
}
