<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->get('sendmail', 'EmailController::sendmail');
$routes->get('documents', 'Documents::index');

service('auth')->routes($routes);

$routes->group("api", ["namespace" => "App\Controllers"], function ($routes) {
$routes->get("invalid-access", "AuthController::accessDenied");

// Post
$routes->post("register", "AuthController::register");

//post
$routes->post("login", "AuthController::login");

//Filtered routes login required for accessibility
//Get
$routes->get("profile", "AuthController::profile", ["filter" => "apiauth"]);

//Post
$routes->post("logout", "AuthController::logout", ["filter" => "apiauth"]);
$routes->post("uploadlogo", "AuthController::uploadLogo", ["filter" => "apiauth"]);
$routes->post("updateprofile", "AuthController::updateProfile", ["filter" => "apiauth"]);

//resource entries and retrievals
//get
$routes->get('retrievals','Retrievals::index', ["filter" => "apiauth"]);
$routes->get('categories/', 'Category::index', ["filter" => "apiauth"]);
$routes->get('entries/edit/(:num)', 'Entries::edit/$1', ["filter" => "apiauth"]);
$routes->get('retrievals/(:num)', 'Retrievals::getItem/$1',  ["filter" => "apiauth"]);
$routes->get('retrievals/history', 'Retrievals::history',  ["filter" => "apiauth"]);
$routes->get('stock/', 'Retrievals::getStock',  ["filter" => "apiauth"]);
$routes->get('retrievals/debts', 'Retrievals::getDebts',  ["filter" => "apiauth"]);
$routes->get('retrievals/sales', 'Retrievals::getSales',  ["filter" => "apiauth"]);
$routes->get('retrievals/statistics', 'Retrievals::statistics',  ["filter" => "apiauth"]);

//post
$routes->post('entries/addstock', 'Entries::createStock');
$routes->post('addstock/', 'Entries::addStock',  ["filter" => "apiauth"]);
$routes->post('entries/updateItem', 'Entries::update',  ["filter" => "apiauth"]);
$routes->post('entries/debts', 'Entries::createDebt',  ["filter" => "apiauth"]);
$routes->post('entries/paydebt', 'Entries::payDebt',  ["filter" => "apiauth"]);
$routes->post('entries/sales', 'Entries::saleStock',  ["filter" => "apiauth"]);
$routes->post('entries/cancelsale', 'Entries::cancelSale',  ["filter" => "apiauth"]);
$routes->post('entries/deleteItem/(:num)', 'Entries::delete/$1',  ["filter" => "apiauth"]);
});
