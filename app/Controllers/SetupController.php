<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use App\Models\Business;
use App\Models\RefreshToken;
use CodeIgniter\Shield\Models\GroupModel;
use CodeIgniter\Shield\Models\PermissionModel;

use DateTime;

class SetupController extends ResourceController
{
    private $businessModel;
    private $refreshTokenModel;
    private $UserObject;
    private $group;
    private $permission;

    public function __construct()
    {
        $this->UserObject = new UserModel();
        $this->businessModel = new Business();
        $this->refreshTokenModel = new RefreshToken();
        $this->group =  new GroupModel();
        $this->permission =  new PermissionModel();
    }


     public function createAdmin() {

            // NOTE: This controller method is retained for legacy setup only.
            // In an enterprise deployment, use the CLI bootstrap command instead:
            //   php spark admin:create --username=admin --email=admin@example.com --password=StrongPass123
            // Do not expose this route in production.

            // 2. Hard-coded admin details.
        // IMPORTANT: Change the password in a production environment!
        $adminData = [
            'username' => 'amplaUganda',
            'email'    => 'amplauganda@gmail.com',
            'password' => 'Cake123Machine?', // <-- CHANGE THIS!
            //'active'   => 1, // Activate the user immediately
        ];

        $userEntityObject = new User($adminData);

        $role = 'superadmin';
        $permission = 'admin';

        $userSaved = $this->UserObject->save($userEntityObject);

        if ($userSaved) {
          echo"User created successfully";
// Get the ID of the newly inserted user/admin
$userId = $this->UserObject->getInsertID();

//Save admin role to group table in sheild

    $this->group->insert([
        'user_id' => $userId,
        'group' => $role,
    ]);
//Save admin permission to permissions table in sheild
     // Create new permission
     $this->permission->insert([
        'user_id'        => $userId,
        'permission' => $permission
    ]);
            $response = [
                'status' => true,
                'message' => 'Account successfully created',
                'data' => ''
            ];
           return print_r( $response );
        } else{
            $response = [
                'status' => false,
                'message' => 'An error occurred while creating the account',
                'data' => []
            ];
            return print_r( $response );
        }

    }











}




// namespace App\Controllers;

// use CodeIgniter\RESTful\ResourceController;
// use CodeIgniter\Shield\Entities\User;
// use CodeIgniter\Shield\Models\GroupModel;
// use CodeIgniter\Shield\Models\UserModel;

// class SetupController extends ResourceController
// {
//     private GroupModel $groupModel;
//     private UserModel $userProvider;

//     public function __construct()
//     {
//         $this->groupModel   = model(GroupModel::class);
//         $this->userProvider = auth()->getProvider();
//     }

    /**
     * Creates the initial superadmin user with hard-coded details.
     * This method should only be run once during the initial setup of the application.
     *
     * IMPORTANT: For security, you MUST remove the route to this method
     * in app/Config/Routes.php after the admin has been created.
     */
    // public function createAdmin()
    // {
    //     // 1. Check if a superadmin already exists. We only need to know if at least one exists.
    //     $existingAdmin = $this->groupModel->getUsersForGroup('superadmin', 1);
    //     if (! empty($existingAdmin)) {
    //         return $this->fail(
    //             'An admin user already exists. For security, a new one cannot be created via this setup script.',
    //             409 // HTTP 409 Conflict
    //         );
    //     }

        // 2. Hard-coded admin details.
        // IMPORTANT: Change the password in a production environment!
        // $adminData = [
        //     'username' => 'amplaUganda',
        //     'email'    => 'amplauganda@gmail.com',
        //     'password' => 'Cake123Machine?', // <-- CHANGE THIS!
        //     'active'   => 1, // Activate the user immediately
        // ];

//         try {
//             // 3. Create the User entity.
//             // The `email` and `password` fields will be caught by the
//             // UserModel's `afterInsert` hook to create the identity.
//             $user = new User($adminData);

//             // 4. Save the user.
//             $this->userProvider->save($user);

//             // 5. Retrieve the user we just created to get their ID.
//             $user = $this->userProvider->findById($this->userProvider->getInsertID());

//             // 6. Add the user to the 'superadmin' group
//             $user->addGroup('superadmin');

//             // 7. Success response
//             return $this->respondCreated([
//                 'status'  => true,
//                 'message' => 'Superadmin user created successfully.',
//                 'data'    => ['user_id'  => $user->id, 'username' => $user->username],
//             ]);
//         } catch (\Throwable $e) {
//             // Handle potential errors during user creation
//             return $this->failServerError($e->getMessage());
//         }
//     }
// }