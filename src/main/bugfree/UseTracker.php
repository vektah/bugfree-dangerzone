<?php

namespace bugfree;


class UseTracker
{
    /** @var string $name the fully qualified class or namespace */
    private $name;

    /** @var string $alias the alias that this uses, usually the last part of the namespace, unless explicity defined */
    private $alias;

    /** @var int a counter for the number of times this use has been used. */
    private $useCount = 0;

    public function __construct($alias, $name)
    {
        $this->alias = $alias;
        $this->name = $name;
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
     * @return int the number of times this use is referenced in code
     */
    public function getUseCount()
    {
        return $this->useCount;
    }
}
