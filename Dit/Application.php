<?php
/**
 * Dit Application
 * @author Tuan Duong <duongthaso@gmail.com>
 * @package  Dit
 */
namespace Dit;

class Application
{
    /**
     * Singleton instance
     * @var \Dit\Application
     */
    private static $instance = null;
    
    /**
     * Constructor
     */
    private function __construct()
    {

    }

    /**
     * Get \Dit\Application singleton instance
     * @return \Dit\Application
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new \Dit\Application();
        }
        return self::$instance;
    }

    /**
     * Initialize Dit Application
     */
    public function initialize()
    {
        \Dit\Config::load();
    }
}