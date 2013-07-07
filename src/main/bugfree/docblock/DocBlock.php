<?php
namespace bugfree\docblock;


class DocBlock
{
    private $annotations = [];

    /**
     * @param string $text build a new DocBlock from the given comment.
     */
    public function __construct($text)
    {
        if (substr(trim($text), 0, 3) != '/**') {
            return;
        }

        preg_match_all('/@(?<name>[a-zA-Z][a-zA-Z0-9]*)[\s]*(?<value>.*)/', $text, $matches);

        foreach ($matches['name'] as $i => $name) {
            $value = $matches['value'][$i];
            // One liner docblocks will get a trailing */.. lets trim it off now...
            $value = trim(explode('*/', $value)[0]);

            if ($name == 'param') {
                $value = new Param($value);
            }

            $this->annotations[$name][] = $value;
        }
    }

    /**
     * Gets an annotation from this DocBlock
     *
     * @param string $name the name to look for
     * @return array zero or more annotations with the given name
     */
    public function getAnnotations($name)
    {
        if (isset($this->annotations[$name])) {
            return $this->annotations[$name];
        }

        return null;
    }

    /**
     * @return Param[] An array of zero ore more Param annotations (@param)
     */
    public function getParams()
    {
        return $this->getAnnotations('param');
    }
}