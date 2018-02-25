<?php

/**
 * ======================================================================
 * LICENSE: This file is subject to the terms and conditions defined in *
 * file 'license.txt', which is part of this source code package.       *
 * ======================================================================
 */

if (defined('AAM_KEY') && !defined('AAM_MULTISITE')) {
    $config = require(dirname(__FILE__) . '/config.php');
    
    //define extension constant as it's version
    define('AAM_MULTISITE', $config['version']);

    //register activate and extension classes
    AAM_Autoloader::add('AAM_Multisite', $config['basedir'] . '/Multisite.php');
    
    if (version_compare(AAM_Core_API::version(), '5.0') === -1) {
        AAM_Core_Console::add(
                '[Multisite] extension requires AAM 5.0 or higher.', 'b'
        );
    }

    AAM_Multisite::bootstrap();
}