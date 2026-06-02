<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\GroupModel;
use CodeIgniter\Shield\Models\PermissionModel;
use CodeIgniter\Shield\Models\UserModel;
use App\Models\Business;

class CreateAdmin extends BaseCommand
{
    protected $group = 'Setup';
    protected $name = 'admin:create';
    protected $description = 'Create the initial superadmin account for the application.';
    protected $usage = 'admin:create [options]';
    protected $options = [
        'username' => 'The admin username',
        'email'    => 'The admin email address',
        'password' => 'The admin password',
        'group'    => 'The role/group to assign (default: superadmin)',
        'force'    => 'Create the account even when users already exist',
    ];

    public function run(array $params = [])
    {
        $username = CLI::getOption('username') ?: env('ADMIN_USERNAME') ?: getenv('ADMIN_USERNAME');
        $email = CLI::getOption('email') ?: env('ADMIN_EMAIL') ?: getenv('ADMIN_EMAIL');
        $password = CLI::getOption('password') ?: env('ADMIN_PASSWORD') ?: getenv('ADMIN_PASSWORD');
        $group = CLI::getOption('group') ?: env('ADMIN_GROUP') ?: getenv('ADMIN_GROUP') ?: 'superadmin';
        $force = CLI::getOption('force');
        if ($force === null) {
            $force = env('ADMIN_FORCE');
        }
        if ($force === null) {
            $force = getenv('ADMIN_FORCE');
        }
        $force = filter_var($force, FILTER_VALIDATE_BOOLEAN);

        if (empty($username)) {
            $username = CLI::prompt('Admin username');
        }
        if (empty($email)) {
            $email = CLI::prompt('Admin email');
        }
        if (empty($password)) {
            $password = CLI::prompt('Admin password', null, 'required');
        }

        $username = trim($username);
        $email = trim($email);
        $password = trim($password);
        $group = trim($group) ?: 'superadmin';

        if (empty($username) || empty($email) || empty($password)) {
            CLI::error('Username, email, and password are all required.');
            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            CLI::error('A valid email address is required.');
            return;
        }

        if (strlen($password) < 8) {
            CLI::error('Password must be at least 8 characters long.');
            return;
        }

        $userModel = new UserModel();
        $existingUsers = $userModel->builder()->countAllResults(false);

        $existingUserByUsername = $userModel->where('username', $username)->first();
        $existingUserByEmail    = $userModel->findByCredentials(['email' => $email]);
        $existingUser = $existingUserByUsername ?? $existingUserByEmail;
        $isUpdate = $existingUser !== null;

        if ($existingUserByUsername !== null && $existingUserByEmail !== null && $existingUserByUsername->id !== $existingUserByEmail->id) {
            CLI::error('A user already exists with that username and another account already exists with that email. Resolve the conflict before retrying.');
            return;
        }

        if ($existingUser !== null && ! $force) {
            CLI::error('A user already exists with that username or email. Use --force to update the existing admin account.');
            return;
        }

        if ($existingUsers > 0 && ! $force && $existingUser === null) {
            CLI::error('This command is for initial bootstrap only. A user already exists. Use --force to override.');
            return;
        }

        $userData = [
            'id'       => $existingUser?->id,
            'username' => $username,
            'email'    => $email,
            'password' => $password,
            'active'   => true,
        ];

        $userEntity = new User($userData);

        if (! $userModel->save($userEntity)) {
            CLI::error('Failed to create admin account.');
            foreach ($userModel->errors() as $error) {
                CLI::error($error);
            }
            return;
        }

        $userId = $existingUser?->id ?: $userModel->getInsertID();
        $groupModel = new GroupModel();
        $permissionModel = new PermissionModel();

        $groupName = strtolower($group);
        $groupExists = $groupModel->where('user_id', $userId)->where('group', $groupName)->first();

        if ($groupExists === null) {
            $groupInsert = $groupModel->insert([
                'user_id' => $userId,
                'group'   => $groupName,
            ]);

            if ($groupInsert === false) {
                CLI::error('Failed to assign group to the admin account.');
                return;
            }
        }

        $permissionExists = $permissionModel->where('user_id', $userId)->where('permission', 'admin.access')->first();

        if ($permissionExists === null) {
            $permissionModel->insert([
                'user_id'    => $userId,
                'permission' => 'admin.access',
            ]);
        }

        // Create or update default business profile
        $businessModel = new Business();
        $businessData = [
            'busId'        => $userId,
            'busName'      => env('BUSINESS_NAME') ?: 'My Business',
            'busLocation'  => env('BUSINESS_LOCATION') ?: 'Uganda',
            'busBuilding'  => env('BUSINESS_BUILDING') ?: '',
            'busNumberShop' => env('BUSINESS_SHOP_NUMBER') ?: '',
            'busContactOne' => env('BUSINESS_CONTACT') ?: '',
            'busContactTwo' => env('BUSINESS_CONTACT_ALT') ?: '',
            'busEmail'     => $email,
            'busOwner'     => $username,
            'appSettings'  => json_encode([
                'theme'                => 'light',
                'currency'             => 'UGX',
                'minWholesaleOrder'    => 0,
                'autoPriceDetermination' => false,
                'lowLevelProducts'     => 10,
                'lowLevelMaterials'    => 5,
                'notificationFrequency' => 'daily',
                'navbarColor'          => '#2c3e50',
                'sidebarColor'         => '#34495e',
                'taxRate'              => 0,
                'allowDebtSales'       => true,
            ]),
        ];

        $existingBusiness = $businessModel->where('busId', $userId)->first();

        if ($existingBusiness !== null) {
            $businessInsert = $businessModel->update($existingBusiness['profileId'], $businessData);
        } else {
            $businessInsert = $businessModel->insert($businessData);
        }

        if ($businessInsert === false) {
            CLI::error('Failed to create or update default business profile.');
            return;
        }

        if ($isUpdate) {
            CLI::write('Superadmin account updated successfully.', 'green');
        } else {
            CLI::write('Superadmin created successfully.', 'green');
        }
        CLI::write('Username: ' . $username);
        CLI::write('Email: ' . $email);
        CLI::write('Group: ' . $group);
        CLI::write('Business Profile: Created or updated (My Business)', 'green');
        CLI::write('Important: remove any temporary setup route from app/Config/Routes.php after running this command.');
    }
}
