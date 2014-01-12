<?php

namespace bugfree;

/**
 * Assumes that an autoloader has been registered and uses get_class() to determine if a class is available. Namespaces
 * are assumed to be directories beneath basedir.
 */
class AutoloaderResolver implements Resolver
{
    private $basedir;

    public function __construct($basedir)
    {
        $this->basedir = $basedir;
    }

    private function classExistsCaseSensitive($class)
    {
        if ($class[0] == '\\') {
            $class = substr($class, 1);
        }
        return class_exists($class) && in_array($class, get_declared_classes());
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
        if ($this->classExistsCaseSensitive($name) || interface_exists($name) || trait_exists($name)) {
            return true;
        }

        $filename = str_replace('\\', '/', $name);
        if (is_dir($this->basedir . "/" . $filename)) {
            return true;
        }

        return false;
    }
}
