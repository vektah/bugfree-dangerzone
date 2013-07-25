<?php

namespace bugfree\fix;


class SwapLineFix extends AbstractFix
{
    /** @var int */
    private $destinationLine;

    public function __construct($sourceLine, $destinationLine, $reason)
    {
        parent::__construct($sourceLine, $reason);
        $this->destinationLine = $destinationLine;
    }

    public function run(array &$fileLines)
    {
        $sourceIndex = $this->getLine() - 1;
        $destinationIndex = $this->destinationLine - 1;

        $sourceLine = $fileLines[$sourceIndex];
        $destinationLine = $fileLines[$destinationIndex];

        $fileLines[$sourceIndex] = $destinationLine;
        $fileLines[$destinationIndex] = $sourceLine;
    }
}
