<?php

namespace App\Controllers;

use App\Controllers\Traits\SecuresInput;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use App\Models\Business;
use App\Models\RefreshToken;
use App\Services\AuditLogService;
use App\Services\BranchContextService;
use CodeIgniter\Shield\Models\GroupModel;
use CodeIgniter\Shield\Models\PermissionModel;

use DateTime;
// Get the User Provider ( UserModel by default )
// $users = auth()->getProvider();

class AuthController extends ResourceController {
    use SecuresInput;

    private $businessModel;
    private $refreshTokenModel;
    private $UserObject;
    private $Users;
    private $group;
    private $permission;
    private BranchContextService $branchContext;
    private AuditLogService $auditLog;

    public function __construct() {
        $this->UserObject = new UserModel();
        $this->businessModel = new Business();
        $this->refreshTokenModel = new RefreshToken();
        $this->group =  new GroupModel();
        $this->permission =  new PermissionModel();
        $this->branchContext = service('branchContext');
        $this->auditLog = new AuditLogService();
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
                if (!$user || !method_exists($user, 'getGroups')) {
                    continue;
                }

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

    private function staffAccessDenied(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->branchContext->canUserSwitchBranches()) {
            return $this->failForbidden('Only admins can manage staff accounts.');
        }

        return null;
    }

    private function identityRowsByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = \Config\Database::connect()
            ->table('auth_identities')
            ->select('user_id, secret as email, force_reset, last_used_at')
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->whereIn('user_id', $userIds)
            ->get()
            ->getResultArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['user_id']] = $row;
        }

        return $indexed;
    }

    private function loginStatsByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = \Config\Database::connect()
            ->table('auth_logins')
            ->select('user_id, COUNT(*) as login_count, MAX(date) as last_login_at')
            ->whereIn('user_id', $userIds)
            ->where('success', 1)
            ->groupBy('user_id')
            ->get()
            ->getResultArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['user_id']] = $row;
        }

        return $indexed;
    }

    private function auditStatsByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $db = \Config\Database::connect();
        if (!$db->tableExists('audit_logs')) {
            return [];
        }

        $rows = $db->table('audit_logs')
            ->select('userId, COUNT(*) as activity_count, MAX(auditDateCreated) as last_activity_at')
            ->whereIn('userId', $userIds)
            ->groupBy('userId')
            ->get()
            ->getResultArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['userId']] = $row;
        }

        return $indexed;
    }

    private function staffDocumentsByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $db = \Config\Database::connect();
        if (!$db->tableExists('staff_user_documents')) {
            return [];
        }

        $rows = $db->table('staff_user_documents')
            ->whereIn('user_id', $userIds)
            ->get()
            ->getResultArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['user_id']] = [
                'passportPhotoPath' => $row['passport_photo_path'] ?? null,
                'passportPhotoName' => $row['passport_photo_name'] ?? null,
                'idDocumentPath' => $row['id_document_path'] ?? null,
                'idDocumentName' => $row['id_document_name'] ?? null,
                'updatedAt' => $row['updated_at'] ?? null,
            ];
        }

        return $indexed;
    }

    private function isOnline(?string $dateTime): bool
    {
        if (!$dateTime) {
            return false;
        }

        $timestamp = strtotime($dateTime);
        return $timestamp !== false && $timestamp >= strtotime('-15 minutes');
    }

    private function formatStaffUser($user, array $identity = [], array $login = [], array $audit = [], array $documents = []): array
    {
        $data = $user->toArray();
        $userId = (int) $user->id;
        $lastSeenAt = $data['last_active'] ?? $identity['last_used_at'] ?? $login['last_login_at'] ?? null;
        $isBanned = ($data['status'] ?? null) === 'banned';
        $isActive = (bool) ($data['active'] ?? false);
        $forceReset = (bool) ($identity['force_reset'] ?? false);

        $accountStatus = 'active';
        if ($isBanned) {
            $accountStatus = 'banned';
        } elseif (!$isActive) {
            $accountStatus = 'inactive';
        } elseif ($forceReset) {
            $accountStatus = 'password_reset_required';
        }

        return array_merge($data, [
            'id' => $userId,
            'email' => $user->email ?? ($identity['email'] ?? null),
            'roles' => method_exists($user, 'getGroups') ? $user->getGroups() : [],
            'permissions' => $this->permission->getForUser($user),
            'branchScope' => $this->branchContext->getUserScope($userId),
            'accountStatus' => $accountStatus,
            'isActive' => $isActive,
            'isBanned' => $isBanned,
            'forcePasswordReset' => $forceReset,
            'online' => $this->isOnline($lastSeenAt),
            'lastSeenAt' => $lastSeenAt,
            'lastLoginAt' => $login['last_login_at'] ?? null,
            'loginCount' => (int) ($login['login_count'] ?? 0),
            'activityCount' => (int) ($audit['activity_count'] ?? 0),
            'lastActivityAt' => $audit['last_activity_at'] ?? null,
            'documents' => $documents,
        ]);
    }

    private function staffOverviewPayload(): array
    {
        $users = $this->UserObject->findAll();
        $userIds = array_map(fn($user) => (int) $user->id, $users);
        $identities = $this->identityRowsByUser($userIds);
        $logins = $this->loginStatsByUser($userIds);
        $audits = $this->auditStatsByUser($userIds);
        $documents = $this->staffDocumentsByUser($userIds);

        $staff = [];
        foreach ($users as $user) {
            $userId = (int) $user->id;
            $staff[] = $this->formatStaffUser(
                $user,
                $identities[$userId] ?? [],
                $logins[$userId] ?? [],
                $audits[$userId] ?? [],
                $documents[$userId] ?? []
            );
        }

        return [
            'users' => $staff,
            'summary' => [
                'total' => count($staff),
                'online' => count(array_filter($staff, fn($user) => (bool) $user['online'])),
                'active' => count(array_filter($staff, fn($user) => $user['accountStatus'] === 'active')),
                'inactive' => count(array_filter($staff, fn($user) => $user['accountStatus'] === 'inactive')),
                'banned' => count(array_filter($staff, fn($user) => $user['accountStatus'] === 'banned')),
                'passwordResetRequired' => count(array_filter($staff, fn($user) => (bool) $user['forcePasswordReset'])),
            ],
        ];
    }

    public function staffOverview()
    {
        if ($denied = $this->staffAccessDenied()) {
            return $denied;
        }

        return $this->respond([
            'status' => true,
            'message' => 'Staff overview loaded successfully.',
            'data' => $this->staffOverviewPayload(),
        ]);
    }

    public function staffActivity($userId = null)
    {
        if ($denied = $this->staffAccessDenied()) {
            return $denied;
        }

        $userId = (int) $userId;
        $user = $this->UserObject->find($userId);
        if (!$user) {
            return $this->failNotFound('User not found.');
        }

        $db = \Config\Database::connect();
        $activity = [];
        if ($db->tableExists('audit_logs')) {
            $activity = $db->table('audit_logs')
                ->select('id, action, entityType, entityId, ipAddress, userAgent, auditDateCreated')
                ->where('userId', $userId)
                ->orderBy('auditDateCreated', 'DESC')
                ->limit(30)
                ->get()
                ->getResultArray();
        }

        $logins = $db->table('auth_logins')
            ->select('id, ip_address, user_agent, identifier, date, success')
            ->groupStart()
            ->where('user_id', $userId)
            ->orWhere('identifier', $user->email ?? '')
            ->groupEnd()
            ->orderBy('date', 'DESC')
            ->limit(15)
            ->get()
            ->getResultArray();

        return $this->respond([
            'status' => true,
            'message' => 'Staff activity loaded successfully.',
            'data' => [
                'activity' => $activity,
                'logins' => $logins,
            ],
        ]);
    }

    public function updateStaffStatus()
    {
        if ($denied = $this->staffAccessDenied()) {
            return $denied;
        }

        $userId = (int) $this->request->getVar('user_id');
        $action = strtolower(trim((string) $this->request->getVar('action')));
        $reason = trim((string) ($this->request->getVar('reason') ?? ''));
        $allowedActions = [
            'activate',
            'deactivate',
            'ban',
            'unban',
            'force_password_reset',
            'clear_password_reset',
        ];

        if (!$userId || !in_array($action, $allowedActions, true)) {
            return $this->fail('A valid user and staff action are required.');
        }

        if (auth()->id() && (int) auth()->id() === $userId && in_array($action, ['deactivate', 'ban'], true)) {
            return $this->fail('You cannot lock your own administrator account.');
        }

        $user = $this->UserObject->find($userId);
        if (!$user) {
            return $this->failNotFound('User not found.');
        }

        $before = $user->toArray();
        $db = \Config\Database::connect();

        switch ($action) {
            case 'activate':
                $this->UserObject->update($userId, ['active' => 1]);
                break;
            case 'deactivate':
                $this->UserObject->update($userId, ['active' => 0]);
                break;
            case 'ban':
                $user->ban($reason !== '' ? $reason : 'Account blocked by administrator.');
                break;
            case 'unban':
                $user->unBan();
                break;
            case 'force_password_reset':
            case 'clear_password_reset':
                $db->table('auth_identities')
                    ->where('user_id', $userId)
                    ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
                    ->update(['force_reset' => $action === 'force_password_reset' ? 1 : 0]);
                break;
        }

        $after = $this->UserObject->find($userId);
        $this->auditLog->record(
            'staff.' . $action,
            'user',
            $userId,
            $before,
            $after ? $after->toArray() : null,
            auth()->id() ? (int) auth()->id() : null,
            $this->branchContext->getEffectiveBranchId(),
            ['reason' => $reason]
        );

        return $this->respond([
            'status' => true,
            'message' => 'Staff account action completed successfully.',
            'data' => $this->staffOverviewPayload(),
        ]);
    }

    private function staffUploadDirectory(int $userId): string
    {
        return FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'staff-users' . DIRECTORY_SEPARATOR . $userId;
    }

    private function storeStaffFile(int $userId, string $field, array $allowedMimeTypes, int $maxBytes): ?array
    {
        $file = $this->request->getFile($field);
        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (!$file->isValid()) {
            throw new \RuntimeException('The uploaded file for ' . $field . ' is not valid.');
        }

        if ($file->getSize() > $maxBytes) {
            throw new \RuntimeException('The uploaded file for ' . $field . ' is too large.');
        }

        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            throw new \RuntimeException('The uploaded file type for ' . $field . ' is not allowed.');
        }

        $targetDirectory = $this->staffUploadDirectory($userId);
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $newName = $file->getRandomName();
        $file->move($targetDirectory, $newName);

        return [
            'path' => 'uploads/staff-users/' . $userId . '/' . $newName,
            'name' => $file->getClientName(),
        ];
    }

    private function removeOldStaffFile(?string $path): void
    {
        if (!$path) {
            return;
        }

        $absolutePath = realpath(FCPATH . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
        $uploadsRoot = realpath(FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'staff-users');

        if ($absolutePath && $uploadsRoot && str_starts_with($absolutePath, $uploadsRoot) && is_file($absolutePath)) {
            unlink($absolutePath);
        }
    }

    public function uploadStaffDocuments()
    {
        if ($denied = $this->staffAccessDenied()) {
            return $denied;
        }

        $db = \Config\Database::connect();
        if (!$db->tableExists('staff_user_documents')) {
            return $this->fail('Run the staff user documents migration before uploading staff files.');
        }

        $userId = (int) $this->request->getVar('user_id');
        $user = $this->UserObject->find($userId);
        if (!$user) {
            return $this->failNotFound('User not found.');
        }

        try {
            $passportPhoto = $this->storeStaffFile(
                $userId,
                'passportPhoto',
                ['image/jpeg', 'image/png', 'image/webp'],
                2 * 1024 * 1024
            );
            $idDocument = $this->storeStaffFile(
                $userId,
                'idDocument',
                ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
                5 * 1024 * 1024
            );
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage());
        }

        if (!$passportPhoto && !$idDocument) {
            return $this->fail('Upload a passport photo, an ID document, or both.');
        }

        $existing = $db->table('staff_user_documents')->where('user_id', $userId)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        $payload = [
            'user_id' => $userId,
            'uploaded_by' => auth()->id() ? (int) auth()->id() : null,
            'updated_at' => $now,
        ];

        if (!$existing) {
            $payload['created_at'] = $now;
        }

        if ($passportPhoto) {
            $this->removeOldStaffFile($existing['passport_photo_path'] ?? null);
            $payload['passport_photo_path'] = $passportPhoto['path'];
            $payload['passport_photo_name'] = $passportPhoto['name'];
        }

        if ($idDocument) {
            $this->removeOldStaffFile($existing['id_document_path'] ?? null);
            $payload['id_document_path'] = $idDocument['path'];
            $payload['id_document_name'] = $idDocument['name'];
        }

        $saved = $existing
            ? $db->table('staff_user_documents')->where('user_id', $userId)->update($payload)
            : $db->table('staff_user_documents')->insert($payload);

        if (!$saved) {
            return $this->failServerError('Staff documents could not be saved.');
        }

        $this->auditLog->record(
            'staff.documents_uploaded',
            'user',
            $userId,
            $existing ?: null,
            $payload,
            auth()->id() ? (int) auth()->id() : null,
            $this->branchContext->getEffectiveBranchId(),
            [
                'passportPhotoUploaded' => $passportPhoto !== null,
                'idDocumentUploaded' => $idDocument !== null,
            ]
        );

        return $this->respond([
            'status' => true,
            'message' => 'Staff documents uploaded successfully.',
            'data' => $this->staffOverviewPayload(),
        ]);
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
$user->password_hash = $passwordService->hash($newPassword);

// Now use the model to save the updated user
$this->UserObject->save($user);
\Config\Database::connect()
    ->table('auth_identities')
    ->where('user_id', $userId)
    ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
    ->update(['force_reset' => 0]);

$this->auditLog->record(
    'staff.password_changed',
    'user',
    $userId,
    null,
    ['force_reset' => 0],
    auth()->id() ? (int) auth()->id() : null,
    $this->branchContext->getEffectiveBranchId()
);
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
            'password' => $this->request->getVar( 'password' ),
            'active' => 1
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

        if (!$userId) {
            return $this->respond(['status' => false, 'message' => 'Authentication is required.'], 401);
        }

        $logo = $this->request->getFile('logo');

        if (!$logo || !$logo->isValid()) {
            return $this->respond(['status' => false, 'message' => 'A valid logo image is required.'], 422);
        }

        if ($logo->getSize() > 2 * 1024 * 1024) {
            return $this->respond(['status' => false, 'message' => 'Logo file size must not exceed 2MB.'], 422);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png'];
        if (!in_array($logo->getMimeType(), $allowedMimeTypes, true)) {
            return $this->respond(['status' => false, 'message' => 'Only JPEG and PNG logo files are allowed.'], 422);
        }

        $targetDirectory = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'logos';
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $newName = $logo->getRandomName();
        $logo->move($targetDirectory, $newName);
        $filePath = 'uploads/logos/' . $newName;

        $this->businessModel->set('busLogo', $filePath);
        $this->businessModel->where('busId', $userId);

        if (!$this->businessModel->update()) {
            return $this->respond(['status' => false, 'message' => 'Logo could not be saved.'], 500);
        }

        return $this->respond(['status' => true, 'message' => 'Logo uploaded successfully.', 'data' => ['path' => $filePath]]);
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
    $updateData = $this->sanitizeProfilePayload($updateData);

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

private function sanitizeProfilePayload(array $updateData): array
{
    $textFields = [
        'busName' => 200,
        'busLocation' => 200,
        'busBuilding' => 200,
        'busNumberShop' => 100,
        'busContactOne' => 100,
        'busContactTwo' => 100,
        'busOwner' => 200,
    ];

    foreach ($textFields as $field => $maxLength) {
        if (array_key_exists($field, $updateData)) {
            $updateData[$field] = $this->secureText($updateData[$field], $maxLength);
        }
    }

    if (array_key_exists('busEmail', $updateData)) {
        $updateData['busEmail'] = $this->secureEmail($updateData['busEmail']) ?? '';
    }

    return $updateData;
}

    public function accessDenied() {
        return $this->respondCreated( [
            'status' => false,
            'message' => 'Invalid access',
            'data' => []
        ] );
    }
}
