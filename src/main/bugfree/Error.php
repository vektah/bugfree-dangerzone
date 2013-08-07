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

    public function getFormatted()
    {
        $locator = $this->filename;

        if ($this->line) {
            $locator = "$locator:{$this->line}";
        }

        return "$locator {$this->message}";
    }

    public static function formatter(Error $e)
    {
        return $e->getFormatted();
    }
}
