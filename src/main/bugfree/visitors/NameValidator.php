<?php

namespace bugfree\visitors;


use bugfree\Bugfree;
use bugfree\Resolver;
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
    private $bugfree;

    /**
     * @param Bugfree  $bugfree     Instance of bugfree to log errors and warnings against, TODO: split concerns?
     * @param Resolver $resolver    A resolver to use when resolving classes.
     */
    public function __construct(Bugfree $bugfree, Resolver $resolver)
    {
        $this->bugfree = $bugfree;
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
            $this->bugfree->warning($statement, "Use of qualified type names is discouraged.");
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
            $this->bugfree->error($statement, "Type '$qualifiedName' could not be resolved.");
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
                        $this->bugfree->error($use, "Use '\\{$use->name}' could not be resolved");
                    }

                    if (isset($this->aliases[$use->alias])) {
                        $line = $this->aliases[$use->alias]->getNode()->getLine();
                        $this->bugfree->error($use, "Alias '{$use->alias}' is already in use on line $line'");
                    }

                    $this->aliases[$use->alias] = new UseTracker($use->alias, $use->name, $use);

                } else {
                    // I don't know if this error can ever be generated, as it should be a parse error...
                    $this->bugfree->error($use, "Malformed use statement");
                    return;
                }
                $use_count++;
            }
            if ($use_count > 1) {
                $this->bugfree->warning($node, "Multiple uses in one statement is discouraged");
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
            $this->bugfree->error(null, 'Every source file should have a namespace');
        }

        foreach ($this->aliases as $use) {
            if ($use->getUseCount() == 0) {
                $this->bugfree->warning(null, "Use '{$use->getName()}' is not being used");
            }
        }
    }
}
