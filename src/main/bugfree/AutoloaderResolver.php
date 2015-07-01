<?php

namespace bugfree;

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
        if (class_exists($name) || interface_exists($name) || trait_exists($name)) {
            return true;
        }

        return false;
    }
}
