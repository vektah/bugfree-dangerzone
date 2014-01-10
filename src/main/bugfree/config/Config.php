<?php

namespace bugfree\config;

use vektah\common\json\Json;

class Config
{
    /** @var EmitLevel */
    public $emitLevel;

    /** @var boolean if true quick fixes will be made like removing unused uses. */
    public $autoFix = false;

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
        $file = Json::decode(file_get_contents($filename));

        $config = new Config();
        $config->emitLevel = new EmitLevel($file['emitLevel']);

        return $config;
    }

    public function save($filename)
    {
        $file = Json::pretty(['emitLevel' => $this->emitLevel]);

        file_put_contents($filename, $file);
    }
}
