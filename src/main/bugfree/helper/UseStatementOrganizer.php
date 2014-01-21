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
    private $lineNumberMovements = null;

    /**
     * @param UseStatement[] $useStatements An array of use statements in the order they are defined
     */
    public function __construct(array $useStatements)
    {
        $this->useStatements = $useStatements;
        $this->organizedUseStatements = $this->getOrganizedUseStatements();
    }

    public static function compareUse(UseStatement $a, UseStatement $b) {
        $aParts = $a->name->parts;
        $aNumParts = count($aParts);
        $bParts = $b->name->parts;
        $bNumParts = count($bParts);
        $numParts = min($aNumParts, $bNumParts);

        $i = 0;

        while ($i < $numParts) {
            // sort \a\b\c\DClass before \a\b\c\d\EClass
            $isLastAPart = $i == ($aNumParts - 1);
            $isLastBPart = $i == ($bNumParts - 1);

            if ($isLastAPart && !$isLastBPart) {
                return -1;
            } elseif ($isLastBPart && !$isLastAPart) {
                return 1;
            }

            $aPart = $aParts[$i];
            $bPart = $bParts[$i];

            $comparison = strcasecmp($aPart, $bPart);

            // if the last part of both namespaces is the same, sort by no alias then alias
            if ($isLastAPart && $isLastBPart && $comparison === 0) {
                $aAlias = $a->alias;
                $bAlias = $b->alias;

                // alias is set to the last part of the namespace if not explicitly defined
                if ($aAlias === $aPart) {
                    return -1;
                } elseif ($bAlias === $bPart) {
                    return 1;
                }

                return strcmp($aAlias, $bAlias);
            }

            if ($comparison !== 0) {
                return $comparison;
            }

            $i++;
        }

        return 0;
    }

    /**
     * @return UseStatement[]
     */
    private function getOrganizedUseStatements()
    {
        // create a copy
        $useStatements = $this->useStatements;

        usort($useStatements, __CLASS__ . '::compareUse');

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
            $name = $useStatement->name;
            $namespace = $name->toString();
            $alias = $useStatement->alias;

            // alias defaults to the last part of the name
            if ($name->getLast() != $alias) {
                $namespace = $namespace . " as " . $alias;
            }

            $useStrings[] = $namespace;
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
        $lineNumberMapping = [];
        foreach ($currentLineNumbers as $currentLineNumber) {
            $lineNumberMapping[$currentLineNumber] = $currentLineNumber;
        }

        // start from the bottom
        for ($organizedIndex = count($organizedLineNumbers) - 1; $organizedIndex >= 0; $organizedIndex--) {
            $currentLineNumber = $currentLineNumbers[$organizedIndex];
            $organizedLineNumber = $organizedLineNumbers[$organizedIndex];
            $newLineNumber = array_search($organizedLineNumber, $lineNumberMapping);

            if ($currentLineNumber != $newLineNumber) {
                $lineNumberMapping = $this->swap($lineNumberMapping, $currentLineNumber, $newLineNumber);

                $lineSwaps[$currentLineNumber] = $newLineNumber;
            }
        }

        $this->lineNumberMovements = array_flip($lineNumberMapping);

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
     * number it should be moved to.
     *
     * @return array
     */
    public function getLineNumberMovements()
    {
        if (!$this->lineNumberMovements) {
            throw new \BadMethodCallException("You must call getLineSwaps() before calling this function");
        }

        return $this->lineNumberMovements;
    }
}
