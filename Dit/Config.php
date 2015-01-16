<?php
/**
 * Dit Configuration class
 * @author  Tuan Duong <duongthaso@gmail.com>
 * @package Dit
 */
namespace Dit;
class Config 
{
    private static $config = array();
    /**
     * Load configuration
     */
    public static function load()
    {
        $configFile = dirname (dirname(__FILE__)) . '/config.php';
        if (!file_exists($configFile)) {
            throw new \Dit\Exception('Config file ' . $configFile . ' is not existed.');
        }
        include_once($configFile);
        self::$config = $config;
    }

    /**
     * Get configuration from section
     * Return all config if $section is ''
     * @var array
     */
    public static function get($section = '')
    {
        if ($section == '') {
            return self::$config;
        }
        $sectors = explode (".", $section);
        $config = self::$config;
        foreach ($sectors as $sector) {
            if (!isset($config[$sector])) {
                return null;
            }
            $config = $config[$sector];
        }
        return $config;
    }
}