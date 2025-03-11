<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->get('install', 'InstallController::index');
$routes->get('install/requirements', 'InstallController::requirements');
$routes->match(['get', 'post'], 'install/database', 'InstallController::database');
$routes->match(['get', 'post'], 'install/migrate', 'InstallController::migrate');
$routes->get('install/complete', 'InstallController::complete');
