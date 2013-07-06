<?php

namespace bugfree;

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

    /** @var Resolver */
    private $resolver = null;

    /** @var UseTracker[] UseTrackers keyed on alias */
    private $uses = [];

    private $namespace;

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
            $this->namespace = '\\';
            $statements = $source;
        } else {
            $this->namespace = '\\' . $source[0]->name;
            $statements = $source[0]->stmts;
        }

        foreach ($statements as $statement) {
            $this->parse($statement);
        }
    }

    private function parse(\PHPParser_Node $node)
    {
        switch(get_class($node)) {
            case 'PHPParser_Node_Stmt_Use':
                $this->parseUse($node);
                break;
            case 'PHPParser_Node_Stmt_Function':
                $this->parseFunction($node);
                break;
            case 'PHPParser_Node_Stmt_ClassMethod':
                $this->parseMethod($node);
                break;
            case 'PHPParser_Node_Stmt_Class':
                $this->parseClass($node);
                break;
        }
    }

    /**
     * Validate a use statement and create a tracker for it.
     *
     * @param \PHPParser_Node_Stmt_Use $use the use statement
     */
    private function parseUse(\PHPParser_Node_Stmt_Use $use)
    {
        $use_count = 0;
        foreach ($use->uses as $use) {
            if ($use instanceof \PHPParser_Node_Stmt_UseUse) {
                if (!$this->resolver->isValid("\\{$use->name}")) {
                    $this->error($use, "Use '\\{$use->name}' could not be resolved");
                } else {
                    $this->uses[$use->alias] = new UseTracker($use->alias, $use->name);
                }
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

    private function parseFunction(\PHPParser_Node_Stmt_Function $fn)
    {
        foreach ($fn->params as $param) {
            if ($param->type instanceof \PHPParser_Node_Name) {
                $this->resolveClass($fn, $param->type);
            }
        }
    }

    private function parseMethod(\PHPParser_Node_Stmt_ClassMethod $fn)
    {
        foreach ($fn->params as $param) {
            if ($param->type instanceof \PHPParser_Node_Name) {
                $this->resolveClass($fn, $param->type);
            }
        }
    }

    private function parseClass(\PHPParser_Node_Stmt_Class $class)
    {
        if ($class->implements) {
            foreach ($class->implements as $implements) {
                $this->resolveClass($class, $implements);
            }
        }

        if ($class->extends) {
            $this->resolveClass($class, $class->extends);
        }

        foreach($class->stmts as $child) {
            $this->parse($child);
        }
    }

    /**
     * @param \PHPParser_Node_Stmt $statement   The statement that this class was referenced in for error generation.
     * @param \PHPParser_Node_Name $type        The class to resolve.
     */
    private function resolveClass(\PHPParser_Node_Stmt $statement, \PHPParser_Node_Name $type)
    {
        $qualified_name = null;
        $parts = $type->parts;

        if (!$type->isUnqualified() && count($parts) !== 1) {
            $this->warning($statement, "Use of qualified type names is discouraged.");
        }

        if ($type->isFullyQualified()) {
            $qualified_name = "\\{$type->toString()}";
        } else {
            if (isset($this->uses[$parts[0]])) {
                $parts[0] = "\\" . $this->uses[$parts[0]]->getName();
            } else {
                $parts[0] = $this->namespace . "\\" . $parts[0];
            }

            $qualified_name = implode("\\", $parts);
        }

        // Now that we know the qualified name lets make sure its valid.
        if (!$this->resolver->isValid($qualified_name)) {
            $this->error($statement, "Type '$qualified_name' could not be resolved.");
        }

    }


    /**
     * Adds an error
     *
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

    /**
     * Adds a warning
     *
     * @param \PHPParser_Node_Stmt $statement
     * @param string $message
     */
    private function warning(\PHPParser_Node_Stmt $statement, $message)
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
