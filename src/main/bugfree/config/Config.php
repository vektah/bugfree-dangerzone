<?php

namespace bugfree\config;

use vektah\common\json\Json;

class Config
{
    private $basedir;

    /** @var string list of source roots. These should be in psr-0 format */
    private $autoload = ['\\' => 'src'];

    /** @var string Path to autoload file */
    private $bootstrap = 'vendor/autoload.php';

    private $configFilename = 'bugfree.json';

    /** @var boolean if true quick fixes will be made like removing unused uses. */
    public $autoFix = false;

    /** @var EmitLevel */
    public $emitLevel;

    public function __construct()
    {
        $this->emitLevel = new EmitLevel();
    }

    /**
     * @param string $filename
     *
     * @return Config
     */
    public static function load($filename)
    {
        if (!file_exists(stream_resolve_include_path($filename))) {
            $file = ['emitLevel' => []];
        } else {
            $file = Json::decode(file_get_contents($filename));
        }

        $config = new Config();
        $config->basedir = realpath(dirname($filename));
        $config->emitLevel = new EmitLevel($file['emitLevel']);

        $imports = ['bootstrap', 'autoload'];

        if (!isset($file['autoload'])) {
            $composer_filename = $config->basedir . '/composer.json';
            if (file_exists($composer_filename)) {
                $composer = Json::decode(file_get_contents($composer_filename));
                if (isset($composer['autoload']['psr-0'])) {
                    $file['autoload'] = $composer['autoload']['psr-0'];
                }
            }
        }

        foreach ($imports as $import) {
            if (isset($file[$import])) {
                $config->$import = $file[$import];
            }
        }

        return $config;
    }

    public function getAutoloaderPaths()
    {
        $paths = [];

        foreach ($this->autoload as $namespace => $dir) {
            if ($dir[0] !== '/') {
                $dir = $this->basedir . '/' . $dir;
            }
            $paths[$namespace] = $dir;
        }

        return $paths;
    }

    public function getBoostrapPath() {
        if ($this->bootstrap[0] !== '/') {
            return $this->basedir . '/' . $this->bootstrap;
        }

        return $this->bootstrap;
    }

    public function getConfigFilename() {
        if ($this->configFilename[0] !== '/') {
            return $this->configFilename . '/' . $this->configFilename;
        }

        return $this->configFilename;
    }

    public function save()
    {
        $file = Json::pretty([
            'autoload' => $this->autoload,
            'bootstrap' => $this->bootstrap,
            'emitLevel' => $this->emitLevel,
        ]);

        file_put_contents($this->configFilename, $file);
    }
}
