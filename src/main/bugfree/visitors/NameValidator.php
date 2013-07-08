<?php

namespace bugfree\visitors;


use bugfree\Bugfree;
use bugfree\docblock\DocBlock;
use bugfree\ErrorType;
use bugfree\Resolver;
use bugfree\Result;
use bugfree\UseTracker;

/**
 * Fairly similar to PHP Parser's NameResolver except:
 *  - throws up warnings and errors when things smell a little fishy rather then parse errors.
 *  - Uses a resolver to work out if the given use is valid within your project, ideally by using your PSR-0 autoloader.
 */
class NameValidator extends \PHPParser_NodeVisitorAbstract
{
    /** @var Resolver */
    private $resolver = null;

    /** @var string the current namespace, '\' for no namespace */
    private $namespace = '\\';

    /** @var UseTracker[] UseTrackers keyed on alias */
    private $aliases = [];

    /** @var Bugfree */
    private $result;

    private static $ignored_types = [
        'string' => true,
        'integer' => true,
        'int' => true,
        'boolean' => true,
        'bool' => true,
        'float' => true,
        'double' => true,
        'object' => true,
        'mixed' => true,
        'array' => true,
        'resource' => true,
        'void' => true,
        'null' => true,
        'callback' => true,
        'false' => true,
        'true' => true,
        'self' => true,
        'callable' => true,
    ];

    /**
     * @param Result  $result     Instance of bugfree to log errors and warnings against, TODO: split concerns?
     * @param Resolver $resolver    A resolver to use when resolving classes.
     */
    public function __construct(Result $result, Resolver $resolver)
    {
        $this->result = $result;
        $this->resolver = $resolver;
    }

    /**
     * @param \PHPParser_Node $statement   The statement that this class was referenced in for error generation.
     * @param \PHPParser_Node_Name $type        The class to resolve.
     */
    private function resolveClass(\PHPParser_Node $statement, \PHPParser_Node_Name $type)
    {
        $qualifiedName = null;
        $parts = $type->parts;

        if (in_array($parts[0], ['self', 'static', 'parent'])) {
            return;
        }

        if (!$type->isUnqualified() && count($parts) !== 1) {
            $this->result->error(
                ErrorType::USE_OF_UNQUALIFIED_TYPE,
                $statement,
                "Use of qualified type names is discouraged."
            );
        }

        if ($type->isFullyQualified()) {
            $qualifiedName = "\\{$type->toString()}";
        } else {
            if (isset($this->aliases[$parts[0]])) {
                $use = $this->aliases[$parts[0]];
                $parts[0] = "\\" . $use->getName();
                $use->markUsed();
            } else {
                $parts[0] = $this->namespace . "\\" . $parts[0];
            }

            $qualifiedName = implode("\\", $parts);
        }

        // Now that we know the qualified name lets make sure its valid.
        if (!$this->resolver->isValid($qualifiedName)) {
            $this->result->error(
                ErrorType::UNABLE_TO_RESOLVE_TYPE,
                $statement,
                "Type '$qualifiedName' could not be resolved."
            );
        }

    }


    /**
     * Called once before traversal.
     *
     * Return value semantics:
     *  * null:      $nodes stays as-is
     *  * otherwise: $nodes is set to the return value
     *
     * @param \PHPParser_Node[] $nodes Array of nodes
     *
     * @return null|\PHPParser_Node[] Array of nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $this->namespace = '\\';
    }

    /**
     * Called when entering a node.
     *
     * Return value semantics:
     *  * null:      $node stays as-is
     *  * otherwise: $node is set to the return value
     *
     * @param \PHPParser_Node $node Node
     *
     * @return null|\PHPParser_Node Node
     */
    public function enterNode(\PHPParser_Node $node)
    {
        if ($node instanceof \PHPParser_Node_Stmt_Namespace) {
                $this->namespace = '\\' . $node->name;
        } elseif ($node instanceof \PHPParser_Node_Stmt_Use) {
            $use_count = 0;
            foreach ($node->uses as $use) {
                if ($use instanceof \PHPParser_Node_Stmt_UseUse) {
                    if (!$this->resolver->isValid("\\{$use->name}")) {
                        $this->result->error(
                            ErrorType::UNABLE_TO_RESOLVE_USE,
                            $use,
                            "Use '\\{$use->name}' could not be resolved"
                        );
                    }

                    if (isset($this->aliases[$use->alias])) {
                        $line = $this->aliases[$use->alias]->getNode()->getLine();
                        $this->result->error(
                            ErrorType::DUPLICATE_ALIAS,
                            $use,
                            "Alias '{$use->alias}' is already in use on line $line'"
                        );
                    }

                    $this->aliases[$use->alias] = new UseTracker($use->alias, $use->name, $use);

                } else {
                    // I don't know if this error can ever be generated, as it should be a parse error...
                    $this->result->error(
                        ErrorType::MALFORMED_USE,
                        $use,
                        "Malformed use statement"
                    );
                    return;
                }
                $use_count++;
            }
            if ($use_count > 1) {
                $this->result->error(
                    ErrorType::MULTI_STATEMENT_USE,
                    $node,
                    "Multiple uses in one statement is discouraged"
                );
            }
        } else {
            if (isset($node->class) && $node->class instanceof \PHPParser_Node_Name) {
                $this->resolveClass($node, $node->class);
            }

            if (isset($node->implements)) {
                foreach ($node->implements as $implements) {
                    $this->resolveClass($node, $implements);
                }
            }

            if (isset($node->extends) && $node->extends instanceof \PHPParser_Node_Name) {
                $this->resolveClass($node, $node->extends);
            }

            if (isset($node->type) && $node->type instanceof \PHPParser_Node_Name) {
                $this->resolveClass($node, $node->type);
            }


            if ($node instanceof \PHPParser_Node_Stmt_ClassMethod or $node instanceof \PHPParser_Node_Stmt_Function) {
                if ($docblock = $node->getDocComment()) {
                    $doc = new DocBlock($docblock->getText());

                    if (is_array($doc->getParams())) {
                        foreach ($doc->getParams() as $param) {
                            $type = $param->getType();
                            foreach (explode('|', $type) as $typePart) {
                                if (substr($type, strlen($typePart) - 2) == '[]') {
                                    $typePart = substr($typePart, 0, strlen($typePart) - 2);
                                }
                                if (!isset(self::$ignored_types[strtolower($typePart)])) {
                                    $this->resolveClass($node, $this->nodeFromString($typePart));
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    private function nodeFromString($str)
    {
        if ($str[0] == '\\') {
            return new \PHPParser_Node_Name_FullyQualified(substr($str, 1));
        } else {
            return new \PHPParser_Node_Name($str);
        }
    }

    /**
     * Called once after traversal.
     *
     * Return value semantics:
     *  * null:      $nodes stays as-is
     *  * otherwise: $nodes is set to the return value
     *
     * @param \PHPParser_Node[] $nodes Array of nodes
     *
     * @return null|\PHPParser_Node[] Array of nodes
     */
    public function afterTraverse(array $nodes)
    {
        if ($this->namespace == '\\') {
            $this->result->error(
                ErrorType::MISSING_NAMESPACE,
                null,
                'Every source file should have a namespace'
            );
        }

        foreach ($this->aliases as $use) {
            if ($use->getUseCount() == 0) {
                $this->result->error(
                    ErrorType::UNUSED_USE,
                    null,
                    "Use '{$use->getName()}' is not being used"
                );
            }
        }
    }
}
