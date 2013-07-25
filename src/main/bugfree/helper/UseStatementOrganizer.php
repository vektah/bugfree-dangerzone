<?php

namespace bugfree\helper;


use PHPParser_Node_Stmt_UseUse as UseStatement;

/**
 * Checks if use statements are organized and determines what is required to
 * organize them.
 */
class UseStatementOrganizer
{
    /** @var UseStatement[] */
    private $useStatements = [];

    /** @var UseStatement[] */
    private $organizedUseStatements = [];

    /** @var array */
    private $lineNumberMapping = null;

    /**
     * @param UseStatement[] $useStatements An array of use statements in the order they are defined
     */
    public function __construct(array $useStatements)
    {
        $this->useStatements = $useStatements;
        $this->organizedUseStatements = $this->getOrganizedUseStatements();
    }

    /**
     * @return UseStatement[]
     */
    private function getOrganizedUseStatements()
    {
        $organizer = function (UseStatement $a, UseStatement $b) {
            $aParts = $a->name->parts;
            $aNumParts = count($aParts);
            $bParts = $b->name->parts;
            $bNumParts = count($bParts);
            $numParts = min($aNumParts, $bNumParts);

            $i = 0;

            while ($i < $numParts) {
                $aPart = $aParts[$i];
                $bPart = $bParts[$i];

                // sort \a\b\c\Class before \a\b\c\a\Class
                $isLastAPart = $i == ($aNumParts - 1);
                $isLastBPart = $i == ($bNumParts - 1);

                if ($isLastAPart && !$isLastBPart) {
                    return -1;
                } elseif ($isLastBPart && !$isLastAPart) {
                    return 1;
                }

                $comparison = strcasecmp($aPart, $bPart);

                if ($comparison !== 0) {
                    return $comparison;
                }

                $i++;
            }

            return 0;
        };

        // create a copy
        $useStatements = $this->useStatements;

        usort($useStatements, $organizer);

        return $useStatements;
    }

    /**
     * Returns true if the list of use statements are organized.
     *
     * @return boolean
     */
    public function areOrganized()
    {
        $current = $this->useStatementsToString($this->useStatements);
        $organized = $this->useStatementsToString($this->organizedUseStatements);

        return count(array_diff_assoc($current, $organized)) == 0;
    }

    /**
     * @param UseStatement[] $useStatements
     * @return array
     */
    private function useStatementsToString(array $useStatements)
    {
        $useStrings = [];

        foreach ($useStatements as $useStatement) {
            $useStrings[] = $useStatement->name->toString();
        }

        return $useStrings;
    }

    /**
     * Calculates any line swaps that need to take place in order for the use
     * statements to be organized.
     *
     * @return array hash of current line number to new line number
     */
    public function getLineSwaps()
    {
        $lineSwaps = [];
        $currentLineNumbers = $this->useStatementsToLineNumbers($this->useStatements);
        $organizedLineNumbers = $this->useStatementsToLineNumbers($this->organizedUseStatements);

        // keep track of the swaps
        $this->lineNumberMapping = [];
        foreach ($currentLineNumbers as $currentLineNumber) {
            $this->lineNumberMapping[$currentLineNumber] = $currentLineNumber;
        }

        // start from the bottom
        for ($organizedIndex = count($organizedLineNumbers) - 1; $organizedIndex >= 0; $organizedIndex--) {
            $currentLineNumber = $currentLineNumbers[$organizedIndex];
            $organizedLineNumber = $organizedLineNumbers[$organizedIndex];
            $newLineNumber = array_search($organizedLineNumber, $this->lineNumberMapping);

            if ($currentLineNumber != $newLineNumber) {
                $this->lineNumberMapping = $this->swap($this->lineNumberMapping, $currentLineNumber, $newLineNumber);

                $lineSwaps[$currentLineNumber] = $newLineNumber;
            }
        }

        return $lineSwaps;
    }

    /**
     * @param UseStatement[] $useStatements
     * @return array
     */
    private function useStatementsToLineNumbers(array $useStatements)
    {
        $useLines = [];

        foreach ($useStatements as $useStatement) {
            $useLines[] = $useStatement->getLine();
        }

        return $useLines;
    }

    /**
     * @param array $arr
     * @param mixed $currentKey
     * @param mixed $newKey
     * @return array
     */
    private function swap(array $arr, $currentKey, $newKey)
    {
        $currentValue = $arr[$currentKey];
        $newValue = $arr[$newKey];

        $arr[$currentKey] = $newValue;
        $arr[$newKey] = $currentValue;

        return $arr;
    }

    /**
     * Returns a hash with key of the current line number and value of the line
     * number it should be at.
     *
     * @return array
     */
    public function getLineNumberMapping()
    {
        if (!$this->lineNumberMapping) {
            throw \BadMethodCallException("You must call getLineSwaps() before calling this function");
        }

        return $this->lineNumberMapping;
    }
}
