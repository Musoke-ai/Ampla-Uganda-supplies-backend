<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use App\Models\Business;
use App\Models\RefreshToken;
use App\Services\BranchContextService;
use CodeIgniter\Shield\Models\GroupModel;
use CodeIgniter\Shield\Models\PermissionModel;

use DateTime;
// Get the User Provider ( UserModel by default )
// $users = auth()->getProvider();

class AuthController extends ResourceController {
    private $businessModel;
    private $refreshTokenModel;
    private $UserObject;
    private $Users;
    private $group;
    private $permission;
    private BranchContextService $branchContext;

    public function __construct() {
        $this->UserObject = new UserModel();
        $this->businessModel = new Business();
        $this->refreshTokenModel = new RefreshToken();
        $this->group =  new GroupModel();
        $this->permission =  new PermissionModel();
        $this->branchContext = service('branchContext');
    }

    private function defaultAppSettings(): array
    {
        return [
            'minWholesaleOrder' => 500,
            'currency' => 'UGX',
            'autoPriceDetermination' => false,
            'lowLevelProducts' => 10,
            'lowLevelMaterials' => 50,
            'notificationFrequency' => 'Weekly',
            'theme' => 'light',
            'navbarColor' => '#2f8f57',
            'sidebarColor' => '#f4faf6',
            'taxRate' => 0,
            'allowDebtSales' => true,
        ];
    }

    private function businessProfile(): ?array
    {
        $profiles = $this->businessModel->orderBy('profileId', 'ASC')->findAll(1);
        return $profiles[0] ?? null;
    }

    private function decodeAppSettings($settings): array
    {
        if (is_array($settings)) {
            return $this->normalizeAppSettings($settings);
        }

        if (!is_string($settings) || trim($settings) === '') {
            return $this->defaultAppSettings();
        }

        $decoded = json_decode($settings, true);
        if (!is_array($decoded)) {
            return $this->defaultAppSettings();
        }

        return $this->normalizeAppSettings($decoded);
    }

    private function cleanNumber($value, float $fallback, float $min = 0, float $max = 1000000): float
    {
        $number = is_numeric($value) ? (float) $value : $fallback;
        $number = max($min, min($max, $number));
        return floor($number) === $number ? (int) $number : $number;
    }

    private function cleanColor($value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)
            ? strtolower($value)
            : $fallback;
    }

    private function normalizeAppSettings(array $settings): array
    {
        $defaults = $this->defaultAppSettings();
        $notifications = ['Daily', 'Weekly', 'Monthly', 'Never'];
        $theme = ($settings['theme'] ?? $defaults['theme']) === 'dark' ? 'dark' : 'light';
        $currency = isset($settings['currency']) && is_string($settings['currency'])
            ? strtoupper(trim($settings['currency']))
            : $defaults['currency'];

        return [
            'minWholesaleOrder' => $this->cleanNumber($settings['minWholesaleOrder'] ?? $defaults['minWholesaleOrder'], $defaults['minWholesaleOrder']),
            'currency' => $currency !== '' ? substr($currency, 0, 12) : $defaults['currency'],
            'autoPriceDetermination' => filter_var($settings['autoPriceDetermination'] ?? $defaults['autoPriceDetermination'], FILTER_VALIDATE_BOOLEAN),
            'lowLevelProducts' => $this->cleanNumber($settings['lowLevelProducts'] ?? $defaults['lowLevelProducts'], $defaults['lowLevelProducts']),
            'lowLevelMaterials' => $this->cleanNumber($settings['lowLevelMaterials'] ?? $defaults['lowLevelMaterials'], $defaults['lowLevelMaterials']),
            'notificationFrequency' => in_array($settings['notificationFrequency'] ?? '', $notifications, true)
                ? $settings['notificationFrequency']
                : $defaults['notificationFrequency'],
            'theme' => $theme,
            'navbarColor' => $this->cleanColor($settings['navbarColor'] ?? $defaults['navbarColor'], $defaults['navbarColor']),
            'sidebarColor' => $this->cleanColor($settings['sidebarColor'] ?? $defaults['sidebarColor'], $defaults['sidebarColor']),
            'taxRate' => $this->cleanNumber($settings['taxRate'] ?? $defaults['taxRate'], $defaults['taxRate'], 0, 100),
            'allowDebtSales' => filter_var($settings['allowDebtSales'] ?? $defaults['allowDebtSales'], FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function formatProfileForResponse(array $profile): array
    {
        $profile['appSettingsConfigured'] = isset($profile['appSettings'])
            && is_string($profile['appSettings'])
            && trim($profile['appSettings']) !== '';
        $profile['appSettings'] = $this->decodeAppSettings($profile['appSettings'] ?? null);
        return $profile;
    }
    //post


    public function getUsers() {
        $users = $this->UserObject->findAll();

        if ( empty( $users ) ) {
            return "Users table empty";
        } else {
            $auth = \CodeIgniter\Config\Services::authorization(); // ✅ correct way
            $payload = [];
    
            foreach ($users as $user) {
                // Get the groups/roles for this user ID
                $roles = $user->getGroups();
                $permissions = $this->permission->getForUser($user);
    
                // Build a clean array representation
                $payload[] = array_merge(
                    $user->toArray(),     // the original user fields
                    ['roles' => $roles,
                    'permissions' => $permissions,
                    'email' => $user->email,
                    'branchScope' => $this->branchContext->getUserScope((int) $user->id),
                    
                    ]   // new roles and permissions fields
                  
                );
            }
        
            // return $this->respondCreated( $users);
            return $this->respondCreated( $payload );
        }
    }

    // Change password for a user by ID
function changeUserPassword()
{
$newPassword = $this->request->getVar( 'newPassword' );
$userId = $this->request->getVar( 'user_id' );
    // Find the user
    $user = $this->UserObject->find($userId);
    if (!$user) {
        $response = [
            'status' => false,
            'message' => 'No user exists with the supplied user id',
            'data' => ''
        ];
        return $this->respondCreated( $response );
    }
    // $user->setPassword($newPassword);
    // $user->save();
    // Update the password securely using Shield's Password Updater
$passwordService = service('passwords');
$user->password = $passwordService->hash($newPassword);

// Now use the model to save the updated user
$this->UserObject->save($user);
    $response = [
        'status' => true,
        'message' => 'User password reset successfull',
        'data' => ''
    ];
    return $this->respondCreated( $response );

    // Hash the new password using Shield's Password service
    // $passwordService = service('passwords');
    // $hashedPassword = $passwordService->hash($newPassword);

    // Update the user's password
    // $user->password_hash = $hashedPassword;
    // $this->UserObject->save($user);

}

public function deleteUser()
{
    if (!$this->branchContext->canUserSwitchBranches()) {
        return $this->failForbidden('Only branch admins can delete users.');
    }

    // Attempt to find the user by ID
    $userId = $this->request->getVar( 'user_id' );
    $user = $this->UserObject->find($userId);

    if (!$user) {
        return $this->failNotFound('User not found with the provided ID.');
    }

    // Attempt to delete the user
    if ($this->UserObject->delete($userId, true)) {
        return $this->respond([
            'status' => true,
            'message' => 'User deleted successfully.',
            'data' => ''
        ]);
    } else {
        return $this->failServerError('Failed to delete user. Please try again.');
    }
}

    public function updateUserRolesAndPermissions()
    {
        if (!$this->branchContext->canUserSwitchBranches()) {
            return $this->failForbidden('Only branch admins can update user access.');
        }

        // 1. Validation
        $rules = [
            'id'   => 'required|is_not_unique[users.id]',
            'roles'     => 'permit_empty|is_array',
            'permissions' => 'permit_empty|is_array',
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors());
        }

        // 2. Get and sanitize data from the request
        $userId = $this->request->getVar('id');
        $roles = array_map(fn($r) => strtolower(str_replace(' ', '', trim($r))), $this->request->getVar('roles') ?? []);
        $permissions = array_map(fn($p) => strtolower(str_replace(' ', '', trim($p))), $this->request->getVar('permissions') ?? []);
        $requestedBranchId = $this->branchContext->normalizeBranchId($this->request->getVar('branchId'));
        $currentScope = $this->branchContext->getUserScope((int) $userId);
        $canSwitchBranches = $this->branchContext->rolesCanSwitchBranches($roles);
        $assignedBranchId = $requestedBranchId ?? $currentScope['assigned_branch_id'] ?? $this->branchContext->getEffectiveBranchId();

        // 3. Start a database transaction to ensure atomicity
        $db = \Config\Database::connect();
        $db->transStart();

        // 4. Delete old roles and permissions for the user
        $this->group->where('user_id', $userId)->delete();
        $this->permission->where('user_id', $userId)->delete();

        // 5. Add new roles if any are provided
        if (!empty($roles)) {
            $batchRoles = [];
            foreach ($roles as $role) {
                $batchRoles[] = [
                    'user_id' => $userId,
                    'group'   => $role,
                ];
            }
            $this->group->insertBatch($batchRoles);
        }

        // 6. Add new permissions if any are provided
        if (!empty($permissions)) {
            $batchPermissions = [];
            foreach ($permissions as $permission) {
                $batchPermissions[] = [
                    'user_id'    => $userId,
                    'permission' => $permission,
                ];
            }
            $this->permission->insertBatch($batchPermissions);
        }

        $this->branchContext->assignUserToBranch(
            (int) $userId,
            $assignedBranchId,
            auth()->id() ? (int) auth()->id() : null,
            $canSwitchBranches
        );

        // 7. Complete the transaction
        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->failServerError('Could not update user roles and permissions.');
        }

        // 8. Return success response
        return $this->respond([
            'status'  => true,
            'message' => 'User roles and permissions updated successfully.',
        ]);
    }

    public function register() {
        if (!$this->branchContext->canUserSwitchBranches()) {
            return $this->failForbidden('Only branch admins can create users.');
        }

        // Defined validation rules for the required fields
$rules = [
    'username'  => 'required|alpha_numeric_space|min_length[3]',
    'email'     => 'required|valid_email',
    'password'  => 'required|min_length[8]',
    'roles'     => 'permit_empty|is_array', // Optional: ensure 'roles' is an array if submitted
];
        // Run the validation
if (! $this->validate($rules)) {
    // Validation failed, return with errors.
    // In a web context, you might redirect back:
    // return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
    
    // In an API context, you might do this:
    return $this->fail($this->validator->getErrors());
}

// Validation passed, you can now safely get the data
$validatedData = $this->validator->getValidated();

        $userEntityObject = new User( [
            'username' => $this->request->getVar( 'username' ),
            'email' => $this->request->getVar( 'email' ),
            'password' => $this->request->getVar( 'password' )
        ] );

        // $roles = $this->request->getVar( 'roles' )??[];
        // $permissions = $this->request->getVar( 'permissions' )??[];

        $roles = array_map(fn($r) => strtolower(str_replace(' ', '', trim($r))), $this->request->getVar('roles') ?? []);
        $permissions = array_map(fn($p) => strtolower(str_replace(' ', '', trim($p))), $this->request->getVar('permissions') ?? []);
        $requestedBranchId = $this->branchContext->normalizeBranchId($this->request->getVar('branchId'));
        $creatorBranchId = $this->branchContext->getEffectiveBranchId();
        $canSwitchBranches = $this->branchContext->rolesCanSwitchBranches($roles);
        $assignedBranchId = $requestedBranchId ?? $creatorBranchId;

        if (!$canSwitchBranches && $assignedBranchId === null) {
            return $this->fail('A branch must be selected before creating a branch-scoped user.');
        }

        $userSaved = $this->UserObject->save($userEntityObject);
       
        if ($userSaved) {
            
// Get the ID of the newly inserted user
$userId = $this->UserObject->getInsertID();

//Save user roles to group table in sheild
foreach ($roles as $role) {
    $this->group->insert([
        'user_id' => $userId,
        'group' => $role,
    ]);
}
//Save user permissions to permissions table in sheild
foreach ($permissions as $permission) {
     // Create new permission
     $this->permission->insert([
        'user_id'        => $userId,
        'permission' => $permission
    ]);
}
            $this->branchContext->assignUserToBranch(
                (int) $userId,
                $assignedBranchId,
                auth()->id() ? (int) auth()->id() : null,
                $canSwitchBranches
            );
            $response = [
                'status' => true,
                'message' => 'Account successfully created',
                'data' => [
                    'user_id' => $userId,
                    'branchScope' => $this->branchContext->getUserScope((int) $userId),
                ]
            ];
            return $this->respondCreated( $response );
        } else{
            $response = [
                'status' => false,
                'message' => 'An error occurred while creating the account',
                'data' => []
            ];
            return $this->respondCreated( $response );
        }

    }

    public function uploadLogo() {
        $userId = auth()->id();
        $logo = $this->request->getVar( 'logo' );
        if ( isset( $logo ) ) {
            $errors = array();
            $file_name = $logo[ 'name' ];
            $file_size = $logo[ 'size' ];
            $file_tmp = $logo[ 'tmp_name' ];
            $file_type = $logo[ 'type' ];
            $file_ext = strtolower( end( explode( '.', $logo[ 'name' ] ) ) );
            $file_path = 'uploads/logos/' . $file_name;

            $extensions = array( 'jpeg', 'jpg', 'png' );

            if ( in_array( $file_ext, $extensions ) === false ) {
                $errors[] = 'extension not allowed, please choose a JPEG or PNG file.';
            }

            if ( $file_size > 2097152 ) {
                $errors[] = 'File size must be exactly 2MB';
            }
            if ( empty( $errors ) ) {
                move_uploaded_file( $file_tmp, $file_path );
                $this->businessModel->set( 'busLogo', $file_path );
                $this->businessModel->where( 'busId', $userId );
                $logoUpdate = $this->businessModel->update();
                if ( $logoUpdate ) {
                    return $this->respondCreated( 'Success' );
                } else {
                    return $this->respondCreated( 'Data base error' );
                }
            } else {
                return $this->respondCreated( $errors );
            }
        }
    }

    // post

    public function login() {

        if ( auth()->loggedIn() ) {
            auth()->logout();
        }

        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required'
        ];

        if ( !$this->validate( $rules ) ) {
            $response = [
                'status' => false,
                'message' => $this->validator->getErrors(),
                'data' => []
            ];
        } else {
            //success
            $credentials = [
                'email' => $this->request->getVar( 'email' ),
                'password' => $this->request->getVar( 'password' )
            ];

            $loginAttempt = auth()->attempt( $credentials );

            if ( !$loginAttempt->isOk() ) {
                $response = [
                    'status' => false,
                    'message' => 'Invalid login details',
                    'data' => []
                ];
            } else {

                $userData = $this->UserObject->findById( auth()->id() );

                $refreshToken = $userData->generateAccessToken(env('REFRESH_TOKEN_NAME', 'ampla-uganda-refresh-token'));
                $data = generateRefreshToken();
                $accessToken = $data[ 'refreshToken' ];
                $hashedToken = $data[ 'hashedToken' ];
                $expiryDate = ( new DateTime() )->modify( '+2 minutes' )->format( 'Y-m-d H:i:s' );
                $access = $this->refreshTokenModel->save( [
                    'user_id' => auth()->id(),
                    'token' =>   $hashedToken,
                    'expiry_date' => $expiryDate
                ] );
                // Set the cookie parameters
                $cookieParams = [
                    'expires' => time() + 120, // 2 minutes expiry time
                    // 'expires' => time() + ( 86400 * 30 ), // 30 days expiry time
                    'path' => '/', // Available in the entire domain
                    // 'domain' => env('COOKIE_DOMAIN'), // Your domain
                    'secure' => true, // Only send over HTTPS
                    'httponly' => true, // Accessible only via HTTP protocol
                    'samesite' => 'Lax' // Helps mitigate CSRF attacks
                ];

                // Set the cookie
                setcookie( 'refreshToken', $refreshToken->raw_token, $cookieParams );
                $roles = ['admin'];

                $auth_token = $accessToken;
                $data = [
                    'subscription' => 'free',
                    'accessToken' =>  $auth_token,
                    'roles' => 'admin'
                ];

                $response = [
                    'status' => true,
                    'message' => 'User logged in successfully',
                    'data' => $data
                ];
            }
        }
        return $this->respondCreated( $response );
    }

    //Get profile

    public function profile() {
        $userId = auth()->id();
        // $businessprofile = $this->businessModel->where( 'busId', $userId )->findAll();
        $data = $this->businessProfile();
        if (!$data) {
            return $this->failNotFound('Business profile has not been configured.');
        }
        $data = $this->formatProfileForResponse($data);
        $data['branchScope'] = $this->branchContext->getUserScope($userId ? (int) $userId : null);
        return $this->respondCreated( $data );
    }

    public function settings()
    {
        if (!auth()->id()) {
            return $this->respond([
                'status' => false,
                'message' => 'Authentication failed. You must be logged in.',
                'data' => [],
            ], 401);
        }

        $profile = $this->businessProfile();
        if (!$profile) {
            return $this->failNotFound('Business profile has not been configured.');
        }

        return $this->respond([
            'status' => true,
            'message' => 'Settings loaded successfully.',
            'data' => [
                'settings' => $this->decodeAppSettings($profile['appSettings'] ?? null),
                'configured' => isset($profile['appSettings']) && is_string($profile['appSettings']) && trim($profile['appSettings']) !== '',
                'profileId' => $profile['profileId'],
            ],
        ]);
    }

    public function updateSettings()
    {
        if (!auth()->id()) {
            return $this->respond([
                'status' => false,
                'message' => 'Authentication failed. You must be logged in.',
                'data' => [],
            ], 401);
        }

        if (!$this->branchContext->canUserSwitchBranches()) {
            return $this->failForbidden('Only admins can update workspace settings.');
        }

        $payload = $this->request->getJSON(true) ?? [];
        $settings = $payload['settings'] ?? $this->request->getVar('settings');

        if (!is_array($settings)) {
            return $this->fail('A settings object is required.');
        }

        $profile = $this->businessProfile();
        if (!$profile) {
            return $this->failNotFound('Business profile has not been configured.');
        }

        $cleanSettings = $this->normalizeAppSettings($settings);
        $saved = $this->businessModel->update($profile['profileId'], [
            'appSettings' => json_encode($cleanSettings, JSON_UNESCAPED_SLASHES),
        ]);

        if (!$saved) {
            return $this->failServerError('Workspace settings could not be saved.');
        }

        return $this->respond([
            'status' => true,
            'message' => 'Workspace settings saved successfully.',
            'data' => [
                'settings' => $cleanSettings,
                'profile' => $this->formatProfileForResponse($this->businessModel->find($profile['profileId'])),
            ],
        ]);
    }

    //post

    // public function updateProfile() {
    //     $userId = auth()->id();
    //     $updateData = $this->request->getVar( 'businessProfile');
    //      if (empty($updateData)) {
    //             $response = [
    //                 'status' => false,
    //                 'error' => 'ItemsListEmpty',
    //                 'message' => 'Items list is empty add an item or items to make a complete transaction',
    //                 'data' => $updateData
    //             ];
    //             return $this->respond($response);
    //         }

    //     $profileUpdated = $this->businessModel->update('profileId', $updateData);

    //     if ( $profileUpdated ) {
    //         return $this->respondCreated(
    //             [
    //                 'status' => true,
    //                 'message' => 'Profile updated successfully',
    //                 'data' => [$profileUpdated]
    //             ]
    //         );
    //     } else {
    //         return $this->respondCreated(
    //             [
    //                 'status' => false,
    //                 'message' => 'Profile updated failed!',
    //                 'data' => []
    //             ]
    //         );
    //     }
    // }

public function updateProfile()
{
    // 1. Gatekeeper: Check for authentication
    if (!auth()->id()) {
        $response = [
            'status'  => false,
            'message' => 'Authentication failed. You must be logged in.',
            'data'    => []
        ];
        return $this->respond($response, 401); // 401 Unauthorized
    }

    if (!$this->branchContext->canUserSwitchBranches()) {
        return $this->failForbidden('Only admins can update the business profile.');
    }

    $updateData = $this->request->getVar('businessProfile');

    $requestedProfileId = is_array($updateData)
        ? ($updateData['profileId'] ?? null)
        : ($updateData->profileId ?? null);

    // 2. Validate input data and presence of profileId
    if (empty($updateData) || !$requestedProfileId) {
        $response = [
            'status'  => false,
            'message' => 'Required profile data or profileId is missing.',
            'data'    => []
        ];
        return $this->respond($response, 400); // 400 Bad Request
    }
    
    // 3. Prepare data for update
    $updateData = (array) $updateData;
    $profileId = $requestedProfileId;
    unset($updateData['profileId']);
    $allowedProfileFields = [
        'busName',
        'busLocation',
        'busBuilding',
        'busNumberShop',
        'busContactOne',
        'busContactTwo',
        'busEmail',
        'busOwner',
    ];
    $updateData = array_intersect_key($updateData, array_flip($allowedProfileFields));

    // Check if there are any fields left to update
    if (empty($updateData)) {
        $response = [
            'status'  => false,
            'message' => 'No fields were provided to update.',
            'data'    => []
        ];
        return $this->respond($response, 400); // 400 Bad Request
    }

    // 4. Attempt the update
    if ($this->businessModel->update($profileId, $updateData)) {
        // Success ✅
        $updatedProfile = $this->formatProfileForResponse($this->businessModel->find($profileId));
        $response = [
            'status'  => true,
            'message' => 'Profile updated successfully.',
            'data'    => $updatedProfile
        ];
        return $this->respond($response, 200); // 200 OK
    } 
    
    // 5. Handle update failure
    // First, check if the profile even exists
    if (!$this->businessModel->find($profileId)) {
        $response = [
            'status'  => false,
            'message' => "A business profile with the ID '{$profileId}' does not exist.",
            'data'    => []
        ];
        return $this->respond($response, 404); // 404 Not Found
    }

    // If it exists but still failed, it's a server error
    $response = [
        'status'  => false,
        'message' => 'The profile could not be updated due to a server error.',
        'data'    => []
    ];
    return $this->respond($response, 500); // 500 Internal Server Error
}

    public function accessDenied() {
        return $this->respondCreated( [
            'status' => false,
            'message' => 'Invalid access',
            'data' => []
        ] );
    }
}
