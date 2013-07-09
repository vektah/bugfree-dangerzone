<?php

namespace bugfree\docblock;


class AtReturn
{
    private $type;
    private $description;

    /**
     * @param string $value a phpdocumentor or doxygen param line, not including the @param
     */
    public function __construct($value)
    {
        list($this->type, $this->description) = preg_split('/\s+/', $value, 2) + ['', ''];
    }

    /**
     * @return string a description for this param
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string the type hinted for this param
     */
    public function getType()
    {
        return $this->type;
    }

    const _CLASS = __CLASS__;
}
