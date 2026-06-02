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

if (ENVIRONMENT !== 'production') {
    $routes->get('/test-email', 'EmailTest::send');
}
$routes->get('forgot-password', 'PasswordResetController::forgotPasswordForm');
$routes->post('forgot-password', 'PasswordResetController::sendResetLink');
$routes->get('reset-password', 'PasswordResetController::resetPasswordForm');
$routes->post('reset-password', 'PasswordResetController::handleReset');

/**
 * --------------------------------------------------------------------
 * Initial Setup Route
 * --------------------------------------------------------------------
 * IMPORTANT: In production, do not use a public setup route.
 * The initial administrator must be created with a secure CLI command.
 * Example:
 *   php spark admin:create --username=admin --email=admin@example.com --password=StrongPass123
 */
// $routes->get('setup/create-admin', 'SetupController::createAdmin');

$routes->get('/', 'Home::index');
if (ENVIRONMENT !== 'production') {
    $routes->get('sendmail', 'EmailController::sendmail');
}
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

    // AI agent
    // $routes->post('agent/test', 'AgentController::test');
    $routes->post('agent/chat', 'AgentController::chat', ['filter' => 'jwt']);
    $routes->post('agent/help', 'AgentController::help', ['filter' => 'jwt']);
    $routes->get('agent/briefing', 'AgentController::briefing', ['filter' => 'jwt']);
    $routes->get('agent/tools', 'AgentController::tools', ['filter' => 'jwt']);
    $routes->get('agent/drafts', 'AgentController::drafts', ['filter' => 'jwt']);
    $routes->get('agent/drafts/(:segment)', 'AgentController::draft/$1', ['filter' => 'jwt']);
    $routes->post('agent/drafts/confirm', 'AgentController::confirmDraft', ['filter' => 'jwt']);
    $routes->post('agent/drafts/cancel', 'AgentController::cancelDraft', ['filter' => 'jwt']);
    $routes->post('agent/drafts/execute', 'AgentController::executeDraft', ['filter' => 'jwt']);
    $routes->post('catalog/analyze-image', 'CatalogImageAnalysisController::analyze', ['filter' => 'jwt']);

    // Post
    $routes->get("getUsers", "AuthController::getUsers", ['filter' => 'jwt']);
    $routes->post("deleteUser", "AuthController::deleteUser", ['filter' => 'jwt']);
    $routes->post("register", "AuthController::register", ['filter' => 'jwt']);
    $routes->post("updateUserRoles", "AuthController::updateUserRolesAndPermissions", ['filter' => 'jwt']);
    $routes->post("changePassword", "AuthController::changeUserPassword", ['filter' => 'jwt']);
    $routes->get("staff/overview", "AuthController::staffOverview", ['filter' => 'jwt']);
    $routes->get("staff/(:num)/activity", "AuthController::staffActivity/$1", ['filter' => 'jwt']);
    $routes->post("staff/status", "AuthController::updateStaffStatus", ['filter' => 'jwt']);
    $routes->post("staff/documents", "AuthController::uploadStaffDocuments", ['filter' => 'jwt']);

    // RawMaterials
    $routes->get("rawmaterials", "RawMaterialsController::index", ['filter' => 'jwt']);
    $routes->post("addrawmaterial", "RawMaterialsController::addRawMaterial", ['filter' => 'jwt']);
    $routes->post("updaterawmaterial", "RawMaterialsController::update", ['filter' => 'jwt']);
    $routes->post("deleterawmaterial", "RawMaterialsController::delete", ['filter' => 'jwt']);
    $routes->get("rawmaterialcategories", "RawMaterialCategoriesController::index", ['filter' => 'jwt']);
    $routes->post("addrawmaterialcategory", "RawMaterialCategoriesController::create", ['filter' => 'jwt']);
    $routes->post("updaterawmaterialcategory", "RawMaterialCategoriesController::update", ['filter' => 'jwt']);
    $routes->post("deleterawmaterialcategory", "RawMaterialCategoriesController::delete", ['filter' => 'jwt']);

    $routes->get("getRawMaterialsLists", "RawMaterialsRegisterController::index", ['filter' => 'jwt']);
    $routes->post("createrawmateriallist", "RawMaterialsRegisterController::createList", ['filter' => 'jwt']);
    $routes->post("updateRawMaterialList", "RawMaterialsRegisterController::update", ['filter' => 'jwt']);
    $routes->post("deleteRawMaterialFromList", "RawMaterialsRegisterController::delete", ['filter' => 'jwt']);

    // Employees
    $routes->get("employees", "EmployeesController::index", ['filter' => 'jwt']);
    $routes->post("addemployee", "EmployeesController::addEmployee", ['filter' => 'jwt']);
    $routes->post("updateemployee", "EmployeesController::update", ['filter' => 'jwt']);
    $routes->post("deleteemployee", "EmployeesController::delete", ['filter' => 'jwt']);

    // Branches
    $routes->get("branches", "BranchesController::index", ['filter' => 'jwt']);
    $routes->post("addbranch", "BranchesController::create", ['filter' => 'jwt']);
    $routes->post("updatebranch", "BranchesController::update", ['filter' => 'jwt']);
    $routes->post("deletebranch", "BranchesController::delete", ['filter' => 'jwt']);
    $routes->post("switchbranch", "BranchesController::switchBranch", ['filter' => 'jwt']);

    $routes->get("employeedailylist", "EmployeeDailyList::index", ['filter' => 'jwt']);
    $routes->post("createemployeedailylist", "EmployeeDailyList::createList", ['filter' => 'jwt']);
    $routes->post("updateemployeedailylist", "EmployeeDailyList::update", ['filter' => 'jwt']);
    $routes->post("deleteemployeedailylist", "EmployeeDailyList::delete", ['filter' => 'jwt']);
    $routes->post("payemployee", "EmployeeDailyList::payEmployee", ['filter' => 'jwt']);

    // Expenses
    $routes->get("expenses", "ExpensesController::index", ['filter' => 'jwt']);
    $routes->post("addexpense", "ExpensesController::addExpense", ['filter' => 'jwt']);
    $routes->post("updateexpense", "ExpensesController::update", ['filter' => 'jwt']);
    $routes->post("deleteexpense", "ExpensesController::delete", ['filter' => 'jwt']);

    // Orders
    $routes->get("orders", "OrdersController::index", ['filter' => 'jwt']);
    $routes->post("addOrder", "OrdersController::create", ['filter' => 'jwt']);
    $routes->post("updateOrder", "OrdersController::update", ['filter' => 'jwt']);
    $routes->post("deleteOrder", "OrdersController::delete", ['filter' => 'jwt']);

    // Production batches
    $routes->get("production/batches", "ProductionBatchesController::index", ['filter' => 'jwt']);
    $routes->get("production/batches/(:num)", "ProductionBatchesController::show/$1", ['filter' => 'jwt']);
    $routes->post("production/batches/create", "ProductionBatchesController::create", ['filter' => 'jwt']);
    $routes->post("production/batches/update", "ProductionBatchesController::update", ['filter' => 'jwt']);
    $routes->post("production/batches/delete", "ProductionBatchesController::delete", ['filter' => 'jwt']);
    $routes->post("production/batches/materials", "ProductionBatchesController::addMaterial", ['filter' => 'jwt']);
    $routes->post("production/batches/materials/update", "ProductionBatchesController::updateMaterial", ['filter' => 'jwt']);
    $routes->post("production/batches/materials/delete", "ProductionBatchesController::deleteMaterial", ['filter' => 'jwt']);
    $routes->post("production/batches/labor", "ProductionBatchesController::addLabor", ['filter' => 'jwt']);
    $routes->post("production/batches/labor/update", "ProductionBatchesController::updateLabor", ['filter' => 'jwt']);
    $routes->post("production/batches/labor/delete", "ProductionBatchesController::deleteLabor", ['filter' => 'jwt']);
    $routes->post("production/batches/expenses", "ProductionBatchesController::addExpense", ['filter' => 'jwt']);
    $routes->post("production/batches/expenses/update", "ProductionBatchesController::updateExpense", ['filter' => 'jwt']);
    $routes->post("production/batches/expenses/delete", "ProductionBatchesController::deleteExpense", ['filter' => 'jwt']);
    $routes->post("production/batches/output", "ProductionBatchesController::postOutput", ['filter' => 'jwt']);
    $routes->post("production/batches/output/update", "ProductionBatchesController::updateOutput", ['filter' => 'jwt']);
    $routes->post("production/batches/output/delete", "ProductionBatchesController::deleteOutput", ['filter' => 'jwt']);
    $routes->post("production/batches/status", "ProductionBatchesController::updateStatus", ['filter' => 'jwt']);
    $routes->post("production/batches/quality", "ProductionBatchesController::updateQuality", ['filter' => 'jwt']);

    // Auth
    // $routes->post("login", "AuthController::login");
    $routes->post("login", "LoginController::jwtLogin");
    $routes->get('refreshtoken', 'LoginController::refreshToken');
    $routes->get('users', 'AuthController::getUsers', ['filter' => 'jwt']);

    // Notification Routes
    $routes->get("fetchNotifications", "Notifications::index", ['filter' => 'jwt']);
    $routes->post("updateNotification", "Notifications::update", ['filter' => 'jwt']);
    $routes->post("deleteNotification", "Notifications::delete", ['filter' => 'jwt']);

    // Filtered routes login required for accessibility
    $routes->get("profile", "AuthController::profile", ['filter' => 'jwt']);
    $routes->get("settings", "AuthController::settings", ['filter' => 'jwt']);

    $routes->post("uploadlogo", "AuthController::uploadLogo", ['filter' => 'jwt']);
    $routes->post("updateprofile", "AuthController::updateProfile", ['filter' => 'jwt']);
    $routes->post("settings", "AuthController::updateSettings", ['filter' => 'jwt']);
    $routes->post("logout", "LoginController::logout", ['filter' => 'jwt']);

    $routes->post("createsharedAccount", "UserAuthController::createSharedAccount", ['filter' => 'jwt']);

    $routes->post('addcustomer', 'Customers::create', ['filter' => 'jwt']);
    $routes->post('updatecustomer', 'Customers::update', ['filter' => 'jwt']);
    $routes->post('deletecustomer', 'Customers::delete', ['filter' => 'jwt']);

    // Resource entries and retrievals
    $routes->get('getcustomers', 'Customers::index', ['filter' => 'jwt']);
    $routes->get('retrievals', 'Retrievals::index', ['filter' => 'jwt']);
    $routes->get('categories/', 'Category::index', ['filter' => 'jwt']);
    $routes->get('entries/edit/(:num)', 'Entries::edit/$1', ['filter' => 'jwt']);
    $routes->get('retrievals/(:num)', 'Retrievals::getItem/$1', ['filter' => 'jwt']);
    $routes->get('retrievals/history', 'Retrievals::history', ['filter' => 'jwt']);
    $routes->get('stock/', 'Retrievals::getStock', ['filter' => 'jwt']);
    $routes->get('retrievals/debts', 'Retrievals::getDebts', ['filter' => 'jwt']);
    $routes->get('retrievals/sales', 'Retrievals::getSales', ['filter' => 'jwt']);
    $routes->get('retrievals/receipts', 'Retrievals::getReceipts', ['filter' => 'jwt']);
    $routes->get('retrievals/statistics', 'Retrievals::statistics', ['filter' => 'jwt']);

    // Backend-calculated reporting APIs
    $routes->get('reports/catalog', 'ReportsController::catalog', ['filter' => 'jwt']);
    $routes->get('reports/dashboard', 'ReportsController::dashboard', ['filter' => 'jwt']);
    $routes->get('reports/sales', 'ReportsController::sales', ['filter' => 'jwt']);
    $routes->get('reports/sales/product-profit', 'ReportsController::salesProductProfit', ['filter' => 'jwt']);
    $routes->get('reports/sales/paid-vs-credit', 'ReportsController::salesPaidVsCredit', ['filter' => 'jwt']);
    $routes->get('reports/finance/cash-book', 'ReportsController::cashBook', ['filter' => 'jwt']);
    $routes->get('reports/inventory', 'ReportsController::inventory', ['filter' => 'jwt']);
    $routes->get('reports/inventory/stock-movements', 'ReportsController::stockMovements', ['filter' => 'jwt']);
    $routes->get('reports/purchases', 'ReportsController::purchases', ['filter' => 'jwt']);
    $routes->get('reports/suppliers', 'ReportsController::suppliers', ['filter' => 'jwt']);
    $routes->get('reports/raw-materials', 'ReportsController::rawMaterials', ['filter' => 'jwt']);
    $routes->get('reports/production', 'ReportsController::production', ['filter' => 'jwt']);
    $routes->get('reports/expenses', 'ReportsController::expenses', ['filter' => 'jwt']);
    $routes->get('reports/customers', 'ReportsController::customers', ['filter' => 'jwt']);
    $routes->get('reports/staff', 'ReportsController::staff', ['filter' => 'jwt']);
    $routes->get('reports/audit', 'ReportsController::audit', ['filter' => 'jwt']);
    $routes->get('reports/alerts', 'ReportsController::alerts', ['filter' => 'jwt']);

    // Guided spreadsheet imports
    $routes->get('imports/history', 'ImportsController::history', ['filter' => 'jwt']);
    $routes->get('imports/mappings', 'ImportsController::mappings', ['filter' => 'jwt']);
    $routes->get('imports/(:num)', 'ImportsController::show/$1', ['filter' => 'jwt']);
    $routes->post('imports/upload', 'ImportsController::upload', ['filter' => 'jwt']);
    $routes->post('imports/(:num)/rows/(:num)', 'ImportsController::updateRow/$1/$2', ['filter' => 'jwt']);
    $routes->post('imports/(:num)/validate', 'ImportsController::validateBatch/$1', ['filter' => 'jwt']);
    $routes->post('imports/(:num)/confirm', 'ImportsController::confirm/$1', ['filter' => 'jwt']);

    // Post
    $routes->post('entries/addstock', 'Entries::createStock', ['filter' => 'jwt']);
    $routes->post('categories/create', 'Category::create', ['filter' => 'jwt']);
    $routes->post('categories/update', 'Category::update', ['filter' => 'jwt']);
    $routes->post('categories/delete/(:num)', 'Category::delete/$1', ['filter' => 'jwt']);
    $routes->post('categories/delete', 'Category::delete', ['filter' => 'jwt']);
    $routes->post('addstock/', 'Entries::addStock', ['filter' => 'jwt']);
    $routes->post('entries/updateItem', 'Entries::update', ['filter' => 'jwt']);
    $routes->post('entries/debts', 'Entries::createDebt', ['filter' => 'jwt']);
    $routes->post('entries/paydebt', 'Entries::payDebt', ['filter' => 'jwt']);
    $routes->post('entries/sales', 'Entries::saleStock', ['filter' => 'jwt']);
    $routes->post('entries/cancelsale', 'Entries::cancelSale', ['filter' => 'jwt']);
    $routes->post('entries/deleteItem/(:num)', 'Entries::delete/$1', ['filter' => 'jwt']);
});
