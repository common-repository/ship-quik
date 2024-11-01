<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\Shared;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use \ShipQuik\Shared\ShipQuik_API;

use DateTime;
use Ramsey\Uuid\Uuid;

class ShipQuik_Helper
{
    private static $_instance = null;

    protected $parent = null;

    protected $config = null;

    public function __construct($parent)
    {
        $this->parent = $parent;
        $this->config  = $parent->config;
    }

    public static function searchArrayValueByKey($search, array $array)
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array)) as $key => $value) {
            if ($search === $key) {
                return $value;
            }
        }
        return false;
    }

    public static function deleteShippingMethodFiles($files)
    {
        $files = glob("$files", GLOB_BRACE);

        if (count($files) > 0) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    public static function getDateTime($date, $time)
    {
        return new DateTime($date . ' ' . $time);
    }

    public static function getSuppliersConfiguration($parent, $data)
    {
        $result = false;

        $params = [];

        $params['Baseurl'] = $parent->config->getValueByKey('endpoints')['getSuppliersConfiguration'];
        $params['Data'] = $data;

        $curl = new ShipQuik_API($parent, $params, false);
        $result = $curl->runCall();

        if ($result == 'renew_token') {
            $parent->config->reNewAccessToken();

            $result = $curl->runCall();
        }

        if ($result) {
            return $result;
        }

        return false;
    }

    public static function mb_unserialize($string)
    {
        $string2 = preg_replace_callback(
            '!s:(\d+):"(.*?)";!s',
            function ($element) {
                $len = strlen($element[2]);
                $result = "s:$len:\"{$element[2]}\";";

                return $result;
            },
            $string
        );

        return unserialize($string2);
    }

    public static function encodeKey($data)
    {
        $result = base64_encode(json_encode($data));

        return $result;
    }

    public static function decodeKey($data)
    {
        $result = json_decode(base64_decode($data));

        return $result;
    }

    public static function instance($parent)
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($parent);
        }
        return self::$_instance;
    } // End instance()

    public function __clone()
    {
        trigger_error('La clonación de este objeto no está permitida', E_USER_ERROR);
    }
}
