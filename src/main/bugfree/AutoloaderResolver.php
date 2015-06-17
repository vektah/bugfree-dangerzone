<?php

namespace bugfree;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use bugfree\config\Config;

/**
 * Assumes that an autoloader has been registered and uses get_class() to determine if a class is available. Namespaces
 * are assumed to be directories beneath basedir.
 */
class AutoloaderResolver implements Resolver
{
    /** @var Config */
    private $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
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
        $name = implode('\\', array_filter(explode('\\', $name), 'strlen'));
        if ($this->classExistsCaseSensitive($name) || interface_exists($name) || trait_exists($name)) {
            return true;
        }

        return false;
    }
}
