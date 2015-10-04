<?php

/**
 * RESTStudipClientPlugin.class.php
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Philipp Danner <philipp@danner-web.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 * @package     StudipClient
 *
 * @version 1.0
 */

class RESTStudipClientPlugin extends StudIPPlugin implements RESTAPIPlugin {

    /**
     * Load REST Routes
     * 
     * @return \Studip Client REST Routes
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