<?php

namespace bugfree;


use PhpParser\Node;

class UseTracker
{
    /** @var string $name the fully qualified class or namespace */
    private $name;

    /** @var string $alias the alias that this uses, usually the last part of the namespace, unless explicity defined */
    private $alias;

    /** @var Node */
    private $node;

    /** @var int a counter for the number of times this use has been used. */
    private $useCount = 0;

    public function __construct($alias, $name, Node $node)
    {
        $this->alias = $alias;
        $this->name = $name;
        $this->node = $node;
    }

    /**
     * @return string the alias for this use statement
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @return string the namespace for this use statement
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Node
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @return int the number of times this use is referenced in code
     */
    public function getUseCount()
    {
        return $this->useCount;
    }

    /**
     * Marks this use statement as being used in the source file.
     */
    public function markUsed()
    {
        $this->useCount++;
    }
}
