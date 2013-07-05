<?php

namespace bugfree;

/**
 * Assumes that an autoloader has been registered and uses get_class() to determine if a class is available. Namespaces
 * are assumed to be directories beneath basedir.
 */
class AutoloaderResolver implements Resolver {
    private $basedir;

    function __construct($basedir)
    {
        $this->basedir = $basedir;
    }


    /**
     * Determines if a given string can resolve correctly.
     *
     * @param string $name A fully qualified class name or namespace name.
     *
     * @return boolean true if the object exists within the source tree, the libraries or PHP.
     */
    public function isValid($name)
    {
        if(class_exists($name)) {
            return true;
        }

        $filename = str_replace('\\', '/', $name);
        if(is_dir($this->basedir . "/" . $filename)) {
            return true;
        }

        return false;
    }
}