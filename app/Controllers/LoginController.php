<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Authentication\JWTManager;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Models\PermissionModel;
use App\Services\BranchContextService;

class LoginController extends ResourceController
{
    // use ResponseTrait;

    /**
     * Authenticate Existing User and Issue JWT.
     */

    private $userModel;
    private $permissionModel;
    private BranchContextService $branchContext;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->permissionModel = new PermissionModel();
        $this->branchContext = service('branchContext');
    }

    public function jwtLogin()
    {
        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        if (auth()->loggedIn()) {
            auth()->logout();
        }

        $rules = [
            "email" => "required|valid_email",
            "password" => "required"
        ];

        if (!$this->validate($rules)) {
            $response = [
                "status" => false,
                "message" => $this->validator->getErrors(),
                "data" => []
            ];
        } else {
            //success
            $credentials = [
                "email" => $this->request->getVar("email"),
                "password" => $this->request->getVar("password")
            ];

            // Check the credentials
            $result = $authenticator->check($credentials);

            if (!$result->isOk()) {
                $response = [
                    "status" => false,
                    "message" => "Invalid login details",
                    "data" => []
                ];
                return $this->respondCreated($response);
            } else {

                // Credentials match.
                // @TODO Record a successful login attempt

                $user = $result->extraInfo();
                $roles = $user->getGroups(); // returns array of group names
                $permissions = $this->permissionModel->getForUser($user); // returns array of permissions

                /** @var JWTManager $manager */
                $manager = service('jwtmanager');

                $payload = [
                    'user_id' => $user->id,
                    'email'   => $this->request->getVar("email"),
                ];

                $claims = [
                    'email'   => $this->request->getVar("email"),
                ];

                // Generate JWT and return to client
                $jwt = $manager->generateToken($user, $claims);
                $jwt2 = $manager->issue($payload, 43200);

                // Set the cookie parameters
                $cookieParams = [
                    'expires' => time() + 43200, // 12 hours expiry time
                    // 'expires' => time() + (86400 * 30), // 30 days expiry time
                    'path' => '/', // Available in the entire domain
                    // 'domain' => env('COOKIE_DOMAIN'), // Your domain
                    'secure' => true, // Only send over HTTPS
                    'httponly' => true, // Accessible only via HTTP protocol
                    'samesite' => 'Lax' // Helps mitigate CSRF attacks
                ];

                // Set the cookie
                setcookie('refreshToken',  $jwt2, $cookieParams);

                $data = [
                    'subscription' => 'free',
                    'accessToken' =>  $jwt,
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'user_id' => $user->id,
                    'branchScope' => $this->branchContext->getUserScope((int) $user->id),
                ];

                $response = [
                    "status" => true,
                    "message" => "User logged in successfully",
                    'data' => $data
                ];

                return $this->respondCreated($response);
            }
        }
    }

    public function refreshToken()
    {
        $response = [];
        $token = null;

        if (isset($_COOKIE['refreshToken'])) {
            $cookieValue = $_COOKIE['refreshToken'];
            $token = $cookieValue;

            /** @var JWTManager $manager */
            $manager = service('jwtmanager');

            //Verify JWT
            if ($manager->parse($token)) {
                $jwt = $manager->parse($token);

                $user_id = $jwt->user_id;

                // Retrieve the user by ID
                $user = $this->userModel->find($user_id);
                $roles = $user->getGroups(); // returns array of group names
                $permissions = $this->permissionModel->getForUser($user); // returns array of permissions
                // Generate JWT and return to client
                $accesToken = $manager->generateToken($user);

                $data = [
                    'subscription' => 'free',
                    'accessToken' =>  $accesToken,
                    'permissions' =>  $permissions,
                    'roles' => $roles,
                    'user_id' => $user->id,
                    'branchScope' => $this->branchContext->getUserScope((int) $user->id),
                ];

                $response = [
                    "status" => true,
                    "message" => "Token refreshed",
                    'data' => $data
                ];
                return $this->respondCreated($response);
            }
        } else {
            $response = [
                "status" => true,
                "message" => "No refresh token set",
                'data' => null
            ];

            return $this->respondCreated($response);
        }
    }

      //Post
      public function logout()
      {
        //   auth()->logout();
           // Set the cookie parameters
           $cookieParams = [
            'expires' => time() - 43200, // 1 day expiry time
            // 'expires' => time() + (86400 * 30), // 30 days expiry time
            'path' => '/', // Available in the entire domain
            // 'domain' => env('COOKIE_DOMAIN'), // Your domain
            'secure' => true, // Only send over HTTPS
            'httponly' => true, // Accessible only via HTTP protocol
            'samesite' => 'Lax' // Helps mitigate CSRF attacks
        ];

        // Set the cookie
        setcookie('refreshToken', '', $cookieParams);
          return $this->respondCreated(
              [
                  "status" => true,
                  "message" => "User looged out successfully",
                  "data" => null
              ]
          );
      }
}
