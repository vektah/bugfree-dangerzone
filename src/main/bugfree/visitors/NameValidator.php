<?php

namespace bugfree\visitors;


use bugfree\ErrorType;
use bugfree\Resolver;
use bugfree\Result;
use bugfree\UseTracker;
use bugfree\fix\RemoveLineFix;
use bugfree\fix\StrReplaceFix;
use bugfree\fix\SwapLineFix;
use bugfree\helper\UseStatementOrganizer;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Name;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use vektah\parser_combinator\language\php\annotation\ConstLookup;
use vektah\parser_combinator\language\php\annotation\DoctrineAnnotation;
use vektah\parser_combinator\language\php\annotation\NonDoctrineAnnotation;
use vektah\parser_combinator\language\php\annotation\PhpAnnotationParser;

/**
 * Fairly similar to PHP Parser's NameResolver except:
 *  - throws up warnings and errors when things smell a little fishy rather then parse errors.
 *  - Uses a resolver to work out if the given use is valid within your project, ideally by using your PSR-0 autoloader.
 */
class NameValidator extends NodeVisitorAbstract
{
    /** @var Resolver */
    private $resolver = null;

    /** @var string the current namespace, '\' for no namespace */
    private $namespace = '';

    private $namespaceLine = 1;

    /** @var UseTracker[] UseTrackers keyed on alias */
    private $aliases = [];

    /** @var UseUse[] */
    private $useStatements = [];

    /** @var Result */
    private $result;

    /** @var PhpAnnotationParser */
    private $annotationParser;

    /** @var Node[] $node */
    private $nodeStack = [];

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
        $this->annotationParser = new PhpAnnotationParser();
    }

    /**
     * @param string $classname
     *
     * @return boolean returns true if $namespace is the namespace for $classname
     */
    private function inThisNamespace($classname) {
        $namespaceParts = array_values(array_filter(explode('\\', $this->namespace), 'strlen'));
        $classParts = array_values(array_filter(explode('\\', $classname), 'strlen'));

        if (count($namespaceParts) !== count($classParts) - 1) {
            return false;
        }

        foreach ($namespaceParts as $index => $value) {
            if ($classParts[$index] !== $value) {
                return false;
            }
        }

        return true;
    }

    private function cannonicalize($classname) {
        return implode('\\', array_filter(explode('\\', $classname), 'strlen'));
    }

    private function getQualifiedName(Name $type) {
        $parts = $type->parts;

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
        return $this->cannonicalize($qualifiedName);
    }

    /**
     * @param int $line    The statement that this class was referenced in for error generation.
     * @param Name $type    The class to resolve.
     * @param boolean $in_comment           If the type was found in a comment
     */
    private function resolveType($line, Name $type, $in_comment = false)
    {
        $qualifiedName = null;
        $parts = $type->parts;
        $autofix = $this->result->getConfig()->autoFix;

        if (in_array($parts[0], ['self', 'static', 'parent', '$this'])) {
            return;
        }

        $qualifiedName = $this->getQualifiedName($type);

        $unqualified_error = false;
        if (!$type->isUnqualified() && count(array_filter($parts, 'strlen')) !== 1) {
            $unqualified_error = true;
            // See if we can shorten any long (but valid) names.
            if ($autofix) {
                $reason = "Use of qualified type names is discouraged {$type->getLine()} $type => {$type->getLast()}";
                // If this use has already been included then this can be shortened easily
                if (isset($this->aliases[$type->getLast()]) && $this->aliases[$type->getLast()] === $qualifiedName) {
                    $this->result->fix(new StrReplaceFix($type->getLine(), $reason, $type->__toString(), $type->getLast()));
                    $unqualified_error = false;
                } elseif ($this->inThisNamespace($qualifiedName) && $this->resolver->isValid($qualifiedName)) {
                    // If its a relative path in this namespace
                    $this->result->fix(new StrReplaceFix($type->getLine(), $reason, $type->__toString(), $type->getLast()));
                    $unqualified_error = false;
                }
            }
        }

        // Now that we know the qualified name lets make sure its valid.
        if (!$this->resolver->isValid($qualifiedName)) {
            if ($in_comment) {
                $level = ErrorType::UNABLE_TO_RESOLVE_TYPE_IN_COMMENT;
            } else {
                $level = ErrorType::UNABLE_TO_RESOLVE_TYPE;
            }

            $this->result->error(
                $level,
                $line,
                "Type '$qualifiedName' could not be resolved."
            );
        }

        if ($unqualified_error) {
            if ($in_comment) {
                $level = ErrorType::USE_OF_UNQUALIFIED_TYPE_IN_COMMENT;
            } else {
                $level = ErrorType::USE_OF_UNQUALIFIED_TYPE;
            }

            $this->result->error(
                $level,
                $line,
                "Use of qualified type names is discouraged."
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
     * @param Node[] $nodes Array of nodes
     *
     * @return null|Node[] Array of nodes
     */
    public function beforeTraverse(array $nodes)
    {
        $this->namespace = '';
        $this->namespaceLine = 1;
    }

    /**
     * @return Class_
     */
    public function getCurrentClass() {
        foreach ($this->nodeStack as $frame) {
            if ($frame instanceof Class_) {
                return $frame;
            }
        }

        return false;
    }

    public function inClass($className) {
        if ($currentClass = $this->getCurrentClass()) {
            $qualifiedName = $this->getQualifiedName($this->nodeFromString($currentClass->name, $currentClass->getLine()));
            if ($qualifiedName === $className) {
                return true;
            }

            if ($currentClass->extends) {
                $qualifiedName = $this->getQualifiedName($currentClass->extends);

                if (!class_exists($qualifiedName)) {
                    return false;
                }

                $reflection_class = new \ReflectionClass($qualifiedName);

                if ($className === $reflection_class->getName()) {
                    return true;
                }

                while ($reflection_class = $reflection_class->getParentClass()) {
                    if ($className === $reflection_class->getName()) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function resolveClass($class) {
        if ($class instanceof Name) {
            if ($class->parts == ['self']) {
                $currentClass = $this->getCurrentClass();

                if (!$currentClass) {
                    return null;
                }

                return $this->getQualifiedName($this->nodeFromString($currentClass->name, $class->getLine()));
            }

            if ($class->parts == ['parent']) {
                $currentClass = $this->getCurrentClass();

                if (!$currentClass) {
                    return null;
                }

                if (!$currentClass->extends) {
                    $this->result->error(
                        ErrorType::UNABLE_TO_RESOLVE_TYPE,
                        $class->getLine(),
                        "Cannot resolve parent of $currentClass as it does not extend anything"
                    );
                    return null;
                }

                return $this->getQualifiedName($currentClass->extends);
            }

            // Could be any derived class, dont bother.
            if ($class->parts == ['static']) {
                return null;
            }

            return $this->getQualifiedName($class);
        }

        return null;
    }

    private function validateMethodAccess($line, $class, $method) {
        if ($class = $this->resolveClass($class)) {
            if (!$this->inClass($class) && class_exists($class) && method_exists($class, $method)) {
                $reflection_class = new \ReflectionClass($class);

                if ($reflection_method = $reflection_class->getMethod($method)) {
                    if (!$reflection_method->isPublic()) {
                        $this->result->error(
                            ErrorType::ACCESS_LEVEL,
                            $line,
                            "Cannot call $class::$method, $method is not accessible"
                        );
                    }
                }
            }
        }
    }

    private function validateMethodExists($line, $class, $method) {
        if (!$this->result->getConfig()->isEnabled(ErrorType::METHOD_EXISTS)) {
            return;
        }
        if ($class = $this->resolveClass($class)) {
            if (!class_exists($class)) {
                return;
            }

            $reflection_class = new \ReflectionClass($class);

            if (!$reflection_class->getMethod($method)) {
                if (!method_exists($class, $method)) {
                    $this->result->error(
                        ErrorType::METHOD_EXISTS,
                        $line,
                        "Cannot call $class::$method, $method does not exist"
                    );
                }
            }
        }
    }

    /**
     * Called when entering a node.
     *
     * Return value semantics:
     *  * null:      $node stays as-is
     *  * otherwise: $node is set to the return value
     *
     * @param Node $node
     *
     * @return null|Node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            $this->namespace = '\\' . $node->name;
            $this->namespaceLine = $node->getLine();
        } elseif ($node instanceof Use_) {
            $use_count = 0;
            foreach ($node->uses as $use) {
                if ($use instanceof UseUse) {
                    if (!$this->resolver->isValid("\\{$use->name}")) {
                        $this->result->error(
                            ErrorType::UNABLE_TO_RESOLVE_USE,
                            $use->getLine(),
                            "Use '\\{$use->name}' could not be resolved"
                        );
                    }

                    if (isset($this->aliases[$use->alias])) {
                        $line = $this->aliases[$use->alias]->getNode()->getLine();
                        $this->result->error(
                            ErrorType::DUPLICATE_ALIAS,
                            $use->getLine(),
                            "Alias '{$use->alias}' is already in use on line $line"
                        );
                    }

                    $this->aliases[$use->alias] = new UseTracker($use->alias, (string)$use->name, $use);
                    $this->useStatements[] = $use;

                } else {
                    // I don't know if this error can ever be generated, as it should be a parse error...
                    $this->result->error(
                        ErrorType::MALFORMED_USE,
                        $use->getLine(),
                        "Malformed use statement"
                    );
                    return;
                }
                $use_count++;
            }
            if ($use_count > 1) {
                $this->result->error(
                    ErrorType::MULTI_STATEMENT_USE,
                    $node->getLine(),
                    "Multiple uses in one statement is discouraged"
                );
            }
        } else {
            if ($node instanceof New_) {
                $this->validateMethodAccess($node->getLine(), $node->class, '__construct');
            }

            if ($node instanceof StaticCall) {
                if ($node->name !== '__construct') {
                    $this->validateMethodExists($node->getLine(), $node->class, $node->name);
                }
                $this->validateMethodAccess($node->getLine(), $node->class, $node->name);
            }

            if (isset($node->class) && $node->class instanceof Name) {
                $this->resolveType($node->getLine(), $node->class);
            }

            if (isset($node->traits)) {
                foreach ($node->traits as $trait) {
                    $this->resolveType($node->getLine(), $trait);
                }
            }

            if (isset($node->implements)) {
                foreach ($node->implements as $implements) {
                    $this->resolveType($node->getLine(), $implements);
                }
            }

            if (isset($node->extends)) {
                if ($node->extends instanceof Name) {
                    $this->resolveType($node->getLine(), $node->extends);
                } else {
                    foreach ($node->extends as $extends) {
                        $this->resolveType($node->getLine(), $extends);
                    }
                }
            }

            if (isset($node->type) && $node->type instanceof Name) {
                $this->resolveType($node->getLine(), $node->type);
            }

            if ($node instanceof ClassMethod or
                $node instanceof Function_ or
                $node instanceof Property or
                $node instanceof Class_ or
                $node instanceof Variable) {

                /** @var $docblock Doc */
                if ($docblock = $node->getDocComment()) {
                    $annotations = $this->annotationParser->parseString($docblock->getText());

                    foreach ($annotations as $annotation) {
                        if ($annotation instanceof DoctrineAnnotation) {
                            $this->resolveDoctrineComment($docblock->getLine() - 1, $annotation);
                        } elseif ($annotation instanceof NonDoctrineAnnotation) {
                            $this->resolveNonDoctrineComment($docblock->getLine() - 1, $annotation);
                        }

                    }
                }
            }

            // eclipse can only handle inline type hints in a normal comment block
            if ($node instanceof Variable) {
                $comments = $node->getAttribute('comments');

                if (is_array($comments)) {
                    $phpVariableRegex = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
                    // regex for matching /* @var $variable Class */
                    $eclipseInlineTypeHintRegex = '~^/\\* @var \\$' . $phpVariableRegex . ' (\\\\?' . $phpVariableRegex . '(\\\\' . $phpVariableRegex . ')*) \\*/$~';

                    foreach ($comments as $comment) {
                        /* @var $comment Comment */
                        $matches = [];
                        $match = preg_match($eclipseInlineTypeHintRegex, $comment->getText(), $matches);

                        if ($match === 1) {
                            $className = $matches[1];
                            $this->resolveAnnotatedType($comment->getLine(), $className);
                        }
                    }
                }
            }
        }
        $this->nodeStack[] = $node;
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->nodeStack);
    }


    private function resolveDoctrineComment($line, DoctrineAnnotation $annotation)
    {
        if (!in_array($annotation->name, ['Annotation', 'Target'])) {
            $this->resolveAnnotatedType($line + $annotation->line, $annotation->name);
        }

        foreach ($annotation->arguments as $value) {
            $this->resolveDoctrineCommentArgument($line, $value);
        }
    }

    private function resolveDoctrineCommentArgument($line, $argument)
    {
        if (is_array($argument)) {
            foreach ($argument as $value) {
                $this->resolveDoctrineCommentArgument($line, $value);
            }
        }

        if ($argument instanceof DoctrineAnnotation) {
            $this->resolveDoctrineComment($line, $argument);
        }

        if ($argument instanceof ConstLookup) {
            if ($argument->class) {
                $this->resolveAnnotatedType($line + $argument->line, $argument->class);
            }
        }
    }

    private function resolveNonDoctrineComment($line, NonDoctrineAnnotation $annotation)
    {
        $line = $line + $annotation->line;
        $autoFix = $this->result->getConfig()->autoFix;

        if (in_array($annotation->name, ['var', 'param', 'return', 'method', 'property', 'throws'])) {
            $word = preg_split('~[\s\[]+~', $annotation->value);
            if (count($word) === 0 || !$word[0]) {
                $this->result->error(ErrorType::UNABLE_TO_RESOLVE_TYPE_IN_COMMENT, $line, "No type specified, please specify a type");
            } elseif (count($word) === 1) {
                $this->resolveAnnotatedType($line, $word[0]);
            } elseif (count($word) > 1) {
                // Types are backwards here eg @var $foo Type
                if (strlen($word[0]) >= 1 && $word[0][0] === '$' && $word[0] !== '$this') {
                    $this->resolveAnnotatedType($line, $word[1]);
                } else {
                    $this->resolveAnnotatedType($line, $word[0]);
                }
            }

        }

        $tagTypos = [
            'returns' => 'return',
            'throw' => 'throws',
            'params' => 'param',
            'param[in]' => 'param',
            'param[out]' => 'param',
        ];

        foreach ($tagTypos as $typo => $fix) {
            if ($annotation->name === $typo) {
                $reason = "@$typo should be @$fix";

                if ($autoFix) {
                    $this->result->fix(new StrReplaceFix($line, $reason, $typo, $fix));
                } else {
                    $this->result->error(ErrorType::COMMON_TYPOS, $line, $reason);
                }
            }
        }
    }

    /**
     * resolves a type that was found in a docblock annotation.
     *
     * @param int $line
     * @param array $token
     */
    private function resolveAnnotatedType($line, $token)
    {
        $typeFixes = [
            'numeric' => 'int|string|float',
            'numeric-string' => 'string',
            'numberic' => 'int|string|float',
            'number' => 'int|string|float',
            'amount' => 'int|string|float',
            'strung' => 'string',
            'assoc' => 'array',
            'assoc-array' => 'array',
            'hash' => 'array',
            'date' => '\DateTime',
        ];

        foreach (explode('|', $token) as $typePart) {
            if (substr($typePart, strlen($typePart) - 2) == '[]') {
                $typePart = substr($typePart, 0, strlen($typePart) - 2);
            }
            if (!isset(self::$ignored_types[strtolower($typePart)])) {
                $all_ok = true;
                $type = $this->nodeFromString($typePart, $line);
                $qualifiedName = $this->getQualifiedName($type);
                if (!$this->resolver->isValid($qualifiedName)) {
                    foreach ($typeFixes as $typo => $replacement) {
                        if (strtolower($typePart) === $typo) {
                            $all_ok = false;
                            $reason = "$typo is not a valid type. Please see http://www.phpdoc.org/docs/latest/for-users/phpdoc/types.html for a list of valid types.";

                            if ($this->result->getConfig()->autoFix) {
                                $this->result->fix(new StrReplaceFix($line, $reason, $typo, $replacement));
                            } else {
                                $this->result->error(ErrorType::COMMON_TYPOS, $line, $reason);
                            }
                        }
                    }
                }

                if ($all_ok) {
                    $this->resolveType($line, $this->nodeFromString($typePart, $line), true);
                }
            }
        }
    }

    private function nodeFromString($str, $line)
    {
        if (strlen($str) > 0 && $str[0] == '\\') {
            $node = new FullyQualified($str);
        } else {
            $node = new Name($str);
        }

        $node->setLine($line);

        return $node;
    }

    /**
     * Called once after traversal.
     *
     * Return value semantics:
     *  * null:      $nodes stays as-is
     *  * otherwise: $nodes is set to the return value
     *
     * @param Node[] $nodes Array of nodes
     *
     * @return null|Node[] Array of nodes
     */
    public function afterTraverse(array $nodes)
    {
        if ($this->namespace == '') {
            $this->result->error(
                ErrorType::MISSING_NAMESPACE,
                null,
                'Every source file should have a namespace'
            );
        }

        $autoFix = $this->result->getConfig()->autoFix;

        $useStatementOrganizer = new UseStatementOrganizer($this->useStatements);
        $lineNumberMovements = [];

        if (!$useStatementOrganizer->areOrganized()) {
            $errorMsg = "Uses are not organized";
            if ($autoFix) {
                $lineSwaps = $useStatementOrganizer->getLineSwaps();
                $lineNumberMovements = $useStatementOrganizer->getLineNumberMovements();

                foreach ($lineSwaps as $currentLine => $newLine) {
                    $fix = new SwapLineFix($currentLine, $newLine, $errorMsg);

                    $this->result->fix($fix);
                }
            } else {
                $lineSwaps = $useStatementOrganizer->getLineSwaps();
                foreach ($lineSwaps as $currentLine => $newLine) {
                    $this->result->error(ErrorType::DISORGANIZED_USES, $currentLine, $errorMsg . ": this use should be on $newLine");
                }
            }
        }

        $unusedFixes = [];

        foreach ($this->aliases as $use) {
            $errorMsg = '';
            $name = $use->getName();

            if ($use->getUseCount() == 0) {
                $errorMsg = "Use '$name' is not being used";
            } elseif (str_replace("$this->namespace\\", '', "\\$name") === $use->getAlias()) {
                $errorMsg = "Use '$name' is automatically included as it is in the same namespace";
            }

            if ($errorMsg) {
                if ($autoFix) {
                    $line = $use->getNode()->getLine();

                    // if organization occurred the line may have moved
                    if (isset($lineNumberMovements[$line])) {
                        $line = $lineNumberMovements[$line];
                    }

                    $fix = new RemoveLineFix($line, $errorMsg);
                    $unusedFixes[$line] = $fix;
                } else {
                    $this->result->error(ErrorType::UNUSED_USE, $use->getNode()->getLine(), $errorMsg);
                }
            }
        }

        if ($autoFix) {
            // removing must be done in reverse order
            krsort($unusedFixes);

            foreach ($unusedFixes as $fix) {
                $this->result->fix($fix);
            }
        }
    }
}
