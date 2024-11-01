<?php
/**
Functions for Ship-Quik
 **/

namespace ShipQuik\Shared;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class ShipQuik_API
{
    private static $_instance = null;

    protected $_params = [];

    protected $parent = null;

    protected $config = null;

    protected $_is_post = true;

    public function __construct($parent = null, $params = [], $is_post = true)
    {
        $this->parent  = $parent;
        $this->_params  = $params;
        $this->_is_post = $is_post;

        $this->config   = $parent->config;
    }

    public function runCall($connecttimeout = 800, $timeout = 800)
    {
        $response = [];

        $headers = [
            'Content-Type' => 'application/json; charset=utf-8'
        ];

        $token = $this->config->getAccessToken();

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        if ($this->_is_post) {
            $response = wp_remote_post(
                $this->_params['Baseurl'], [
                'headers' => $headers,
                'body' => json_encode($this->_params['Data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]
            );
        } else {
            $response = wp_remote_get(
                $this->_params['Baseurl'], [
                'headers' => $headers,
                ]
            );
        }

        if (wp_remote_retrieve_response_code($response) == 401) {
            return "renew_token";
        }
        if (wp_remote_retrieve_response_code($response) != 200) {
            $message = json_encode($response, true);
            if ($message) {
                $this->parent->logger->info(
                    'API: Response: ' . wc_print_r($message, true),
                    array('source' => 'ship-quik')
                );
            }

            return false;
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public static function instance()
    {
        if (! isset(self::$_instance)) {
            $class = __CLASS__;
            self::$_instance = new $class();
        }
        return self::$_instance;
    }

    public function __clone()
    {
        trigger_error('La clonación de este objeto no está permitida', E_USER_ERROR);
    }
}
