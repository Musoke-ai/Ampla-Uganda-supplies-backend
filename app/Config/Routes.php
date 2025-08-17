<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */


// $routes->get('change-password', 'PasswordController::index');
$routes->get('change-password', 'ChangePasswordController::index');
$routes->post('change-password', 'ChangePasswordController::update');

 // Load Shield default routes
// service('auth')->routes($routes, ['except' => ['register']]);

$routes->get('/test-email', 'EmailTest::send');
$routes->get('forgot-password', 'PasswordResetController::forgotPasswordForm');
$routes->post('forgot-password', 'PasswordResetController::sendResetLink');
$routes->get('reset-password', 'PasswordResetController::resetPasswordForm');
$routes->post('reset-password', 'PasswordResetController::handleReset');

/**
 * --------------------------------------------------------------------
 * Initial Setup Route
 * --------------------------------------------------------------------
 * IMPORTANT: This route is for one-time setup only.
 * You MUST remove or comment it out after setup for security.
 */
// $routes->get('setup/create-admin', 'SetupController::createAdmin');


$routes->get('/', 'Home::index');
$routes->get('sendmail', 'EmailController::sendmail');
$routes->get('documents', 'Documents::index');
$routes->get('signup', 'Login::signUp');
$routes->get('check', 'Login::checkToken');
$routes->get('makepayment', 'PaymentController::processPayment');
$routes->get('paymentstatus', 'PaymentController::paymentStatus');


service('auth')->routes($routes);

// $routes->get('login', 'ChangePasswordController::login');
// $routes->post('login', 'ChangePasswordController::attemptLogin');
// $routes->get('login/verify-magic-link', 'MagicLinkController::verify');

$routes->group("api", ["namespace" => "App\Controllers"], function ($routes) {
$routes->get("invalid-access", "AuthController::accessDenied");

// Post
$routes->get("getUsers", "AuthController::getUsers", ['filter' => 'jwt']);
$routes->post("deleteUser", "AuthController::deleteUser", ['filter' => 'jwt']);
$routes->post("register", "AuthController::register", ['filter' => 'jwt']);
$routes->post("updateUserRoles", "AuthController::updateUserRolesAndPermissions", ['filter' => 'jwt']);
$routes->post("changePassword", "AuthController::changeUserPassword", ['filter' => 'jwt']);

//RawMaterials
$routes->get("rawmaterials", "RawMaterialsController::index");
$routes->post("addrawmaterial", "RawMaterialsController::addRawMaterial");
$routes->post("updaterawmaterial", "RawMaterialsController::update");
$routes->post("deleterawmaterial", "RawMaterialsController::delete");

$routes->get("getRawMaterialsLists", "RawMaterialsRegisterController::index");
$routes->post("createrawmateriallist", "RawMaterialsRegisterController::createList");
$routes->post("updateRawMaterialList", "RawMaterialsRegisterController::update");
$routes->post("deleteRawMaterialFromList", "RawMaterialsRegisterController::delete");

//Employees
$routes->get("employees", "EmployeesController::index");
$routes->post("addemployee", "EmployeesController::addEmployee");
$routes->post("updateemployee", "EmployeesController::update");
$routes->post("deleteemployee", "EmployeesController::delete");

$routes->get("employeedailylist", "EmployeeDailyList::index");
$routes->post("createemployeedailylist", "EmployeeDailyList::createList");
$routes->post("updateemployeedailylist", "EmployeeDailyList::update");
$routes->post("deleteemployeedailylist", "EmployeeDailyList::delete");
$routes->post("payemployee", "EmployeeDailyList::payEmployee");

//Expenses
$routes->get("expenses", "ExpensesController::index");
$routes->post("addexpense", "ExpensesController::addExpense");
$routes->post("updateexpense", "ExpensesController::update");
$routes->post("deleteexpense", "ExpensesController::delete");

//Orders
$routes->get("orders", "OrdersController::index");
$routes->post("addOrder", "OrdersController::create");
$routes->post("updateOrder", "OrdersController::update");
$routes->post("deleteOrder", "OrdersController::delete");


//post
// $routes->post("login", "AuthController::login");
$routes->post("login", "LoginController::jwtLogin");
$routes->get('refreshtoken', 'LoginController::refreshToken');

$routes->get('users', 'LoginController::users', ['filter' => 'jwt']);

//Notification Routes
$routes->get("fetchNotifications", "Notifications::index");
$routes->get("updateNotification", "Notifications::update");
$routes->get("deleteNotification", "Notifications::delete");

//Filtered routes login required for accessibility
//Get
$routes->get("profile", "AuthController::profile", ['filter' => 'jwt']);

//Post
$routes->post("uploadlogo", "AuthController::uploadLogo", ['filter' => 'jwt']);
$routes->post("updateprofile", "AuthController::updateProfile", ['filter' => 'jwt']);
$routes->post("logout", "LoginController::logout", ['filter' => 'jwt']);

$routes->post("createsharedAccount", "UserAuthController::createSharedAccount", ['filter' => 'jwt']);

$routes->post('addcustomer', 'Customers::create', ['filter' => 'jwt']);
$routes->post('updatecustomer', 'Customers::update', ['filter' => 'jwt']);
$routes->post('deletecustomer', 'Customers::delete', ['filter' => 'jwt']);

//resource entries and retrievals
//get
$routes->get('getcustomers', 'Customers::index', ['filter' => 'jwt']);
$routes->get('retrievals','Retrievals::index', ['filter' => 'jwt']);
$routes->get('categories/', 'Category::index', ['filter' => 'jwt']);
$routes->get('entries/edit/(:num)', 'Entries::edit/$1', ['filter' => 'jwt']);
$routes->get('retrievals/(:num)', 'Retrievals::getItem/$1',  ['filter' => 'jwt']);
$routes->get('retrievals/history', 'Retrievals::history',  ['filter' => 'jwt']);
$routes->get('stock/', 'Retrievals::getStock',  ['filter' => 'jwt']);
$routes->get('retrievals/debts', 'Retrievals::getDebts',  ['filter' => 'jwt']);
$routes->get('retrievals/sales', 'Retrievals::getSales',  ['filter' => 'jwt']);
$routes->get('retrievals/statistics', 'Retrievals::statistics',  ['filter' => 'jwt']);

//post
$routes->post('entries/addstock', 'Entries::createStock', ['filter' => 'jwt']);
$routes->post('addstock/', 'Entries::addStock',  ['filter' => 'jwt']);
$routes->post('entries/updateItem', 'Entries::update',  ['filter' => 'jwt']);
$routes->post('entries/debts', 'Entries::createDebt',  ['filter' => 'jwt']);
$routes->post('entries/paydebt', 'Entries::payDebt',  ['filter' => 'jwt']);
$routes->post('entries/sales', 'Entries::saleStock',  ['filter' => 'jwt']);
$routes->post('entries/cancelsale', 'Entries::cancelSale',  ['filter' => 'jwt']);
$routes->post('entries/deleteItem/(:num)', 'Entries::delete/$1',  ['filter' => 'jwt']);
});