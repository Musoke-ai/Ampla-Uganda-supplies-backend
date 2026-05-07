<?php

namespace App\Services\Reports;

use CodeIgniter\Shield\Models\PermissionModel;

class ReportPermissionService
{
    private PermissionModel $permissionModel;

    public function __construct()
    {
        $this->permissionModel = new PermissionModel();
    }

    public function can(string $permission): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $groups = method_exists($user, 'getGroups') ? $user->getGroups() : [];

        if (array_intersect($groups, ['superadmin', 'admin', 'developer'])) {
            return true;
        }

        $permissions = $this->permissionModel->getForUser($user);

        return in_array($permission, $permissions, true)
            || in_array('reports.*', $permissions, true)
            || in_array(strtolower(str_replace('.', '', $permission)), $permissions, true);
    }

    public function assertCan(string $permission): ?array
    {
        if ($this->can($permission)) {
            return null;
        }

        return [
            'status' => false,
            'error' => 'permissionDenied',
            'message' => 'You do not have permission to view this report.',
        ];
    }
}
