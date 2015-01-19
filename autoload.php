<?php
/**
 * Simple autoload function based on PSR-0 standard
 * @author Tuan Duong <duongthaso@gmail.com>
 * @package Dit
 */

/**
 * Autoload function
 * @param  string $className
 * @throws \Dit\Exception If classname does not exist
 */
function autoload($className)
{
    $className = ltrim($className, '\\');
    $fileName  = '';
    $namespace = '';
    if ($lastNsPos = strrpos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

    if (!file_exists($fileName)) {
        throw new \Dit\Exception('Class ' . $className . ' can not be loaded');
    }
    require $fileName;
}
spl_autoload_register('autoload');