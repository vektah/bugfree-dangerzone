<?php
namespace bugfree\helper;


use bugfree\exception\DecodingException;
use bugfree\exception\EncodingException;

class Json
{
    /**
     * Encode a value into a json string
     *
     * @param mixed $value
     * @throws EncodingException
     * @return string
     */
    public static function encode($value)
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($err = json_last_error()) {
            throw new EncodingException($err);
        }
        return $json;
    }

    /**
     * Encode a value into a json string with pretty whitespace
     *
     * @param mixed $value
     * @throws EncodingException
     * @return string
     */
    public static function pretty($value)
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($err = json_last_error()) {
            throw new EncodingException($err);
        }
        return $json;
    }

    /**
     * Decode a json string into an array
     *
     * @param string $json
     * @throws DecodingException
     * @return array
     */
    public static function decode($json)
    {
        $data = json_decode($json, true, 512, JSON_UNESCAPED_UNICODE);
        if ($err = json_last_error()) {
            throw new DecodingException($err, $json);
        }
        return $data;
    }
}
