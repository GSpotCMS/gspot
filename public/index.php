<?php
// Public entry point for production web servers
// Point your document root here and use URL rewriting

define('GSPOT_ROOT', dirname(__DIR__));
require GSPOT_ROOT . '/system/server.php';
