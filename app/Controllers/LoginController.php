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
    private const LOGIN_IP_LIMIT = 20;
    private const LOGIN_EMAIL_LIMIT = 8;
    private const LOGIN_THROTTLE_SECONDS = 300;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->permissionModel = new PermissionModel();
        $this->branchContext = service('branchContext');
    }

    private function enforceLoginThrottle(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        $email = strtolower(trim((string) $this->request->getVar('email')));
        $ipAddress = (string) $this->request->getIPAddress();
        $emailKey = $email !== '' ? sha1($email) : 'missing-email';
        $ipKey = sha1($ipAddress);

        $throttler = service('throttler');
        $ipAllowed = $throttler->check(
            'login-ip-' . $ipKey,
            self::LOGIN_IP_LIMIT,
            self::LOGIN_THROTTLE_SECONDS
        );
        $emailAllowed = $throttler->check(
            'login-email-' . $emailKey,
            self::LOGIN_EMAIL_LIMIT,
            self::LOGIN_THROTTLE_SECONDS
        );

        if ($ipAllowed && $emailAllowed) {
            return null;
        }

        $this->response->setHeader('Retry-After', (string) self::LOGIN_THROTTLE_SECONDS);

        return $this->respond([
            'status' => false,
            'message' => 'Too many login attempts. For your security, sign-in is temporarily paused. Please wait 5 minutes and try again.',
            'data' => [
                'retryAfterSeconds' => self::LOGIN_THROTTLE_SECONDS,
            ],
        ], 429);
    }

    private function passwordIdentityFlags(int $userId): array
    {
        $identity = \Config\Database::connect()
            ->table('auth_identities')
            ->select('force_reset')
            ->where('user_id', $userId)
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->get()
            ->getRowArray();

        return [
            'forcePasswordReset' => (bool) ($identity['force_reset'] ?? false),
        ];
    }

    private function touchUserActivity(int $userId): void
    {
        $this->userModel->update($userId, [
            'last_active' => date('Y-m-d H:i:s'),
        ]);
    }

    public function jwtLogin()
    {
        $throttleResponse = $this->enforceLoginThrottle();
        if ($throttleResponse !== null) {
            return $throttleResponse;
        }

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
            return $this->respond([
                'status' => false,
                'message' => 'Enter your email address and password to continue.',
                'data' => [
                    'reasonCode' => 'VALIDATION_FAILED',
                ],
            ], 422);
        } else {
            //success
            $credentials = [
                "email" => $this->request->getVar("email"),
                "password" => $this->request->getVar("password")
            ];

            // Check the credentials
            $result = $authenticator->check($credentials);

            if (!$result->isOk()) {
                return $this->respond([
                    'status' => false,
                    'message' => "We couldn't sign you in. Check your email and password, then try again.",
                    'data' => [
                        'reasonCode' => 'INVALID_CREDENTIALS',
                    ],
                ], 401);
            } else {

                // Credentials match.
                // @TODO Record a successful login attempt

                $user = $result->extraInfo();
                if (!$user || !method_exists($user, 'getGroups')) {
                    return $this->respond([
                        'status' => false,
                        'message' => 'Login could not load your account. Please try again or contact an administrator.',
                        'data' => [],
                    ], 401);
                }

                if (method_exists($user, 'isBanned') && $user->isBanned()) {
                    return $this->respond([
                        'status' => false,
                        'message' => 'This account cannot sign in right now. Please contact an administrator.',
                        'data' => [
                            'reasonCode' => 'ACCOUNT_RESTRICTED',
                        ],
                    ], 403);
                }

                if (isset($user->active) && !(bool) $user->active) {
                    return $this->respond([
                        'status' => false,
                        'message' => 'This account is inactive. Contact an administrator.',
                        'data' => [
                            'reasonCode' => 'ACCOUNT_INACTIVE',
                        ],
                    ], 403);
                }

                $this->touchUserActivity((int) $user->id);

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
                $rememberDevice = filter_var(
                    $this->request->getVar('rememberDevice') ?? $this->request->getVar('remember') ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );
                $refreshTtl = $rememberDevice ? (86400 * 30) : 43200;
                $jwt2 = $manager->issue($payload, $refreshTtl);
                $cookieParams = $this->refreshTokenCookieParams(time() + $refreshTtl);

                // Set the cookie
                setcookie('refreshToken',  $jwt2, $cookieParams);

                $data = [
                    'subscription' => 'free',
                    'accessToken' =>  $jwt,
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'user_id' => $user->id,
                    'branchScope' => $this->branchContext->getUserScope((int) $user->id),
                    'accountFlags' => $this->passwordIdentityFlags((int) $user->id),
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
                if (!$user || !method_exists($user, 'getGroups')) {
                    $this->clearRefreshTokenCookie();

                    return $this->respond([
                        'status' => false,
                        'message' => 'Your session has expired. Please log in again.',
                        'data' => null,
                    ], 401);
                }

                if ((method_exists($user, 'isBanned') && $user->isBanned()) || (isset($user->active) && !(bool) $user->active)) {
                    $this->clearRefreshTokenCookie();

                    return $this->respond([
                        'status' => false,
                        'message' => 'Your account is not active. Please log in again or contact an administrator.',
                        'data' => null,
                    ], 403);
                }

                $this->touchUserActivity((int) $user->id);

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
                    'accountFlags' => $this->passwordIdentityFlags((int) $user->id),
                ];

                $response = [
                    "status" => true,
                    "message" => "Token refreshed",
                    'data' => $data
                ];
                return $this->respondCreated($response);
            }
        }

        $this->clearRefreshTokenCookie();

        return $this->respond([
            "status" => false,
            "message" => "No refresh token set",
            'data' => null
        ], 401);
    }

      //Post
      public function logout()
      {
        //   auth()->logout();
        $this->clearRefreshTokenCookie();
          return $this->respondCreated(
              [
                  "status" => true,
                  "message" => "User looged out successfully",
                  "data" => null
              ]
          );
      }

    private function clearRefreshTokenCookie(): void
    {
        setcookie('refreshToken', '', $this->refreshTokenCookieParams(time() - 43200));
    }

    private function refreshTokenCookieParams(int $expires): array
    {
        $secure = filter_var(env('cookie.secure', $this->request->isSecure()), FILTER_VALIDATE_BOOLEAN);

        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
