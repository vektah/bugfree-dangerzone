<?php

namespace bugfree;

use bugfree\visitors\NameValidator;

/**
 * Parses a file for errors when constructed.
 *
 * The object left after construction time should be fairly lightweight in terms of memory usage, only containing
 * error/warning summary data.
 */
class Bugfree
{
    /** @var string */
    private $name;

    /** @var string[] */
    private $errors = [];

    /** @var string[] */
    private $warnings = [];

    /**
     * @param string   $name
     * @param string   $source the source code to analyze
     * @param Resolver $resolver resolver to use when checking use statements.
     */
    public function __construct($name, $source, Resolver $resolver)
    {
        $this->name = $name;
        $parser = new \PHPParser_Parser(new \PHPParser_Lexer());

        $traverser = new \PHPParser_NodeTraverser();
        $traverser->addVisitor(new NameValidator($this, $resolver));
        $traverser->traverse($parser->parse($source));
    }

    /**
     * Adds an error
     *
     * @param \PHPParser_Node $statement
     * @param string               $message
     */
    public function error($statement, $message)
    {
        $locator = $this->name;
        if ($statement) {
            $locator .= ":{$statement->getLine()}";
        }
        $this->errors[] = "$locator $message";
    }

    /**
     * Adds a warning
     *
     * @param \PHPParser_Node $statement
     * @param string $message
     */
    public function warning($statement, $message)
    {
        $locator = $this->name;
        if ($statement) {
            $locator .= ":{$statement->getLine()}";
        }
        $this->warnings[] = "$locator $message";
    }

    /**
     * @return string[] all of the errors in this file.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return string[] all of the warnings in this file.
     */
    public function getWarnings()
    {
        return $this->warnings;
    }
}
