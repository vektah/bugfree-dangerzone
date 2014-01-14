<?php

namespace bugfree;


class Error
{
    /**
     * Workaround until php 5.5's class operator is implemented.
     *
     * @see http://www.php.net/manual/en/language.oop5.basic.php#language.oop5.basic.class.class
     */
    const _CLASS = __CLASS__;

    public $filename;
    public $message;
    public $line = 1;
    public $severity;

    public function __construct(array $data = [])
    {
        if (is_string($data)) {
            $this->message = $data;
            return;
        }

        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                throw new \InvalidArgumentException("$key does not exist");
            }

            $this->$key = $value;
        }
    }


    public function getFormatted()
    {
        $locator = $this->filename;

        if ($this->line) {
            $locator = "$locator:{$this->line}";
        }

        return "$locator {$this->message}";
    }

    public static function formatter($e)
    {
        if (is_string($e)) {
            return $e;
        }
        return $e->getFormatted();
    }
}
