<?php

namespace bugfree;


class Bugfree
{
    /** @var string */
    private $name;

    /** @var string[] */
    private $errors = [];

    /** @var string[] */
    private $warnings = [];

    /** @var Resolver */
    private $resolver = null;

    private $uses = [];

    /**
     * @param string   $name
     * @param string   $source the source code to analyze
     * @param Resolver $resolver resolver to use when checking use statements.
     */
    public function __construct($name, $source, Resolver $resolver)
    {
        $this->name = $name;
        $this->resolver = $resolver;
        $parser = new \PHPParser_Parser(new \PHPParser_Lexer());

        $source = $parser->parse($source);

        // Top level nodes in a source file should always be a namespace.
        if (count($source) != 1 || !$source[0] instanceof \PHPParser_Node_Stmt_Namespace) {
            $this->error(null, 'Every source file should have a namespace');
            $file_namespace = '\\';
            $statements = $source;
        } else {
            $file_namespace = $source[0]->name;
            $statements = $source[0]->stmts;
        }


        foreach ($statements as $statement) {
            if ($statement instanceof \PHPParser_Node_Stmt_Use) {
                $this->parseUse($statement);
            }
        }
    }

    private function parseUse(\PHPParser_Node_Stmt_Use $use)
    {
        $use_count = 0;
        foreach ($use->uses as $use) {
            if ($use instanceof \PHPParser_Node_Stmt_UseUse) {
                if (!$this->resolver->isValid("\\$use->name")) {
                    $this->error($use, "Use '\\{$use->name}' cannot be resolved");
                }
//                print_r($use->name);
//                print_r($use->alias);
            } else {
                // I don't know if this error can ever be generated, as it should be a parse error...
                $this->error($use, "Malformed use statement");
                return;
            }
            $use_count++;
        }
        if ($use_count > 1) {
            $this->warning($use, "Multiple uses in one statement is discouraged");
        }
    }

    /**
     * @param \PHPParser_Node_Stmt $statement
     * @param string               $message
     */
    private function error($statement, $message)
    {
        $locator = $this->name;
        if ($statement) {
            $locator .= ":{$statement->getLine()}";
        }
        $this->errors[] = "$locator $message";
    }

    private function warning(\PHPParser_Node_Stmt $statement, $message)
    {
        $locator = $this->name;
        if ($statement) {
            $locator .= ":{$statement->getLine()}";
        }
        $this->warnings[] = "$locator $message";
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getWarnings()
    {
        return $this->warnings;
    }
}
