<?php

/**
 * This a custom autoloader for typo3
 *
 * @package   typo3
 * @copyright Copyright (c) 2021 Peter Dias
 * @license   http://opensource.org/licenses/mit-license The MIT License
 */

spl_autoload_register(function ($class) {
    if (strpos($class, 'TYPO3\\CMS\\Core') === 0) {
        $filename = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 15)) . '.php';
        $fullpath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

        if (is_file($fullpath)) {
            require_once $fullpath;
        }
    }
});
