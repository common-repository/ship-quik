<?php
/**
 * [autoloadClasses]
 *
 * @param $class_name classes
 */

if (!defined('ABSPATH') ) {
    exit;
}

function ship_quik_auto_load_classes($class)
{
    $dir = SHIP_QUIK_TEMPLIST_PATH;

    foreach (scandir($dir) as $file ) {
        if (preg_match("/.php$/i", $file)) {
            $filename = SHIP_QUIK_TEMPLIST_PATH . str_replace('\\', '/', str_replace('.php', '', $file)) . '.php';
            include_once $filename;
        }
    }
}

spl_autoload_register('ship_quik_auto_load_classes');
