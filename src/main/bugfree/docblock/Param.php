<?php

namespace bugfree\docblock;


class Param
{
    private $type;
    private $name;
    private $description;

    /**
     * @param string $value a phpdocumentor or doxygen param line, not including the @param
     */
    public function __construct($value)
    {
        list($this->type, $this->name, $this->description) = preg_split('/\s+/', $value, 3) + ['', '', ''];

        // Sometimes people prefer to have the variable name first, so if it looks like a variable name then swap.
        if (count($this->type) > 1 && $this->type[0] == '$') {
            $tmp = $this->name;
            $this->name = $this->type;
            $this->type = $tmp;
        }
    }

    /**
     * @return string a description for this param
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @return string the name for this param
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string the type hinted for this param
     */
    public function getType()
    {
        return $this->type;
    }

}
