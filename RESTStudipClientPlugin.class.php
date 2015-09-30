<?php
require 'bootstrap.php';

/**
 * RESTStudipClientPlugin.class.php
 *
 * ...
 *
 * @author      Philipp Danner <philipp@danner-web.de>
 * @version 0.1a
 */

class RESTStudipClientPlugin extends StudIPPlugin implements RESTAPIPlugin {

    /**
     * Load REST Routes
     * 
     * @return \extendedNews REST Routes
     */
    public function getRouteMaps() {

        // Autoload models if required
        $this->setupAutoload();

        // Load all routes
        foreach (glob(__DIR__ . '/routes/*') as $filename) {
            require_once $filename;
            $classname = basename($filename, '.php');
            $routes[] = new $classname;
        }
        return $routes;
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