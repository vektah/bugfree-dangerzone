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

    /** @var array maps from short class name to fully qualified names*/
    private $classmap = [];

    public function __construct(Config $config)
    {
        $this->config = $config;

        foreach ($this->config->getAutoloaderPaths() as $namespace => $dir) {
            $this->locateClasses($dir, $namespace);
        }
    }

    private function locateClasses($dir, $namespace) {
        $directory = new RecursiveDirectoryIterator($dir . '/' . str_replace('\\', '/', $namespace));
        $iterator = new RecursiveIteratorIterator($directory);
        $regex = new RegexIterator($iterator, '~^.*/[A-Z].*?\.[pP][hH][pP]$~', RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $filename) {
            $fully_qualified_name = str_replace("$dir/", '', $filename[0]);
            $fully_qualified_name = str_replace('/', '\\', $fully_qualified_name);
            $fully_qualified_name = preg_replace('~\\\\+~', '\\', $fully_qualified_name);
            $fully_qualified_name = preg_replace('~\.php$~', '', $fully_qualified_name);

            $short_class_name = $this->shortClassName($fully_qualified_name);

            if (!isset($this->classmap[$short_class_name])) {
                $this->classmap[$short_class_name] = [];
            }

            $this->classmap[$short_class_name][] = $fully_qualified_name;
        }
    }

    private function shortClassName($full_class_name)
    {
        return join('', array_slice(explode('\\', $full_class_name), -1));
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

    public function getPossibleClasses($qualified_name)
    {
        $short_name = $this->shortClassName($qualified_name);

        if (isset($this->classmap[$short_name])) {
            $sanitized = [];

            // Now make sure that it can actually be loaded before offering it as a candidate.
            foreach ($this->classmap[$short_name] as $class_name) {
                if ($this->isValid($class_name)) {
                    $sanitized[] = $class_name;
                }
            }

            return $sanitized;
        }

        return [];
    }
}
