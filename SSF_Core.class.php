<?php
require 'bootstrap.php';

/**
 * Api-testPlugin.class.php
 *
 * ...
 *
 * @author  Philipp Danner
 * @version 0.1a
 */

class SSF_Core extends StudIPPlugin implements RESTAPIPlugin {

    /**
     * Load REST Routes
     * 
     * @return \extendedNews REST Routes
     */
    public function getRouteMaps() {

        // Autoload models if required
        $this->setupAutoload();

        // Return CardMap
        require_once 'routes/SSFCoreMap.php';
        return new SSFCoreMap();
    }

    /**
     * Setup autoloader
     */
    public function setupAutoload() {
        if (class_exists("StudipAutoloader")) {
            StudipAutoloader::addAutoloadPath(__DIR__ . '/models');
        } else {
            spl_autoload_register(function ($class) {
                include_once __DIR__ . $class . '.php';
            });
        }
    }
}