<?php
if (!function_exists('AliYunACMAutoload')) {
    function AliYunACMAutoload($className) {
        if (strpos($className, 'Dleno\\AliYunAcm') === 0) {
            $classPath = explode('\\', $className);
            unset($classPath[0], $classPath[1]);
            $filePath = realpath(dirname(__FILE__) . '/../src/' . implode('/', $classPath) . '.php');
            if (file_exists($filePath))
                require_once($filePath);
        }
    }
    spl_autoload_register('AliYunACMAutoload');
}

