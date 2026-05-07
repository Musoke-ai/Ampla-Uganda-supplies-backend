<?php

namespace App\Services;

use App\Models\Branches;
use App\Models\UserBranchScope;
use CodeIgniter\Shield\Models\UserModel;

class BranchContextService
{
    private UserBranchScope $scopeModel;
    private Branches $branchesModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->scopeModel = new UserBranchScope();
        $this->branchesModel = new Branches();
        $this->userModel = new UserModel();
    }

    public function getUserScope(?int $userId = null): array
    {
        $userId ??= auth()->id() ? (int) auth()->id() : null;

        if ($userId === null) {
            return $this->emptyScope();
        }

        $user = $this->resolveUser($userId);
        $roles = $user ? $user->getGroups() : [];
        $scope = $this->scopeModel->where('user_id', $userId)->first() ?? [];
        $canSwitchBranches = array_key_exists('can_switch_branches', $scope)
            ? (bool) $scope['can_switch_branches']
            : $this->rolesCanSwitchBranches($roles);
        $assignedBranchId = $this->normalizeBranchId($scope['assigned_branch_id'] ?? null);
        $activeBranchId = $this->normalizeBranchId($scope['active_branch_id'] ?? null);
        $effectiveBranchId = $activeBranchId ?? $assignedBranchId;

        return [
            'user_id' => $userId,
            'roles' => $roles,
            'can_switch_branches' => $canSwitchBranches,
            'assigned_branch_id' => $assignedBranchId,
            'active_branch_id' => $activeBranchId,
            'effective_branch_id' => $effectiveBranchId,
        ];
    }

    public function getEffectiveBranchId(?int $userId = null): ?int
    {
        $scope = $this->getUserScope($userId);

        return $scope['effective_branch_id'];
    }

    public function canUserSwitchBranches(?int $userId = null): bool
    {
        $scope = $this->getUserScope($userId);

        return (bool) ($scope['can_switch_branches'] ?? false);
    }

    public function rolesCanSwitchBranches(array $roles): bool
    {
        return in_array('admin', $roles, true) || in_array('superadmin', $roles, true);
    }

    public function resolveWritableBranchId($requestedBranchId = null, ?int $userId = null): ?int
    {
        $requestedBranchId = $this->normalizeBranchId($requestedBranchId);
        $scope = $this->getUserScope($userId);

        if ($scope['can_switch_branches']) {
            if ($requestedBranchId !== null) {
                return $requestedBranchId;
            }

            return $scope['effective_branch_id'];
        }

        return $scope['effective_branch_id'];
    }

    public function assignUserToBranch(
        int $userId,
        ?int $assignedBranchId,
        ?int $createdBy = null,
        ?bool $canSwitchBranches = null,
        ?int $activeBranchId = null
    ): bool {
        $user = $this->resolveUser($userId);
        $roles = $user ? $user->getGroups() : [];
        $canSwitchBranches ??= $this->rolesCanSwitchBranches($roles);
        $assignedBranchId = $this->normalizeBranchId($assignedBranchId);
        $activeBranchId = $this->normalizeBranchId($activeBranchId);

        if (!$canSwitchBranches) {
            $activeBranchId = $assignedBranchId;
        } elseif ($activeBranchId === null) {
            $activeBranchId = $assignedBranchId;
        }

        $existing = $this->scopeModel->where('user_id', $userId)->first();
        $data = [
            'user_id' => $userId,
            'assigned_branch_id' => $assignedBranchId,
            'active_branch_id' => $activeBranchId,
            'created_by' => $createdBy,
            'can_switch_branches' => $canSwitchBranches ? 1 : 0,
        ];

        if ($existing) {
            return $this->scopeModel->update($existing['id'], $data);
        }

        return (bool) $this->scopeModel->insert($data);
    }

    public function switchBranch(?int $branchId, ?int $userId = null): array
    {
        $userId ??= auth()->id() ? (int) auth()->id() : null;

        if ($userId === null) {
            return ['status' => false, 'message' => 'Authentication is required.'];
        }

        if (!$this->canUserSwitchBranches($userId)) {
            return ['status' => false, 'message' => 'This user cannot switch branches.'];
        }

        if ($branchId !== null && !$this->branchExists($branchId)) {
            return ['status' => false, 'message' => 'Selected branch does not exist.'];
        }

        $scope = $this->getUserScope($userId);
        $existing = $this->scopeModel->where('user_id', $userId)->first();
        $data = [
            'user_id' => $userId,
            'assigned_branch_id' => $scope['assigned_branch_id'],
            'active_branch_id' => $branchId,
            'created_by' => $existing['created_by'] ?? $scope['user_id'],
            'can_switch_branches' => 1,
        ];

        $saved = $existing
            ? $this->scopeModel->update($existing['id'], $data)
            : (bool) $this->scopeModel->insert($data);

        if (!$saved) {
            return ['status' => false, 'message' => 'Could not update the active branch.'];
        }

        return [
            'status' => true,
            'message' => $branchId === null ? 'Branch scope reset to all branches.' : 'Branch switched successfully.',
            'scope' => $this->getUserScope($userId),
        ];
    }

    public function branchExists(?int $branchId): bool
    {
        if ($branchId === null) {
            return false;
        }

        return $this->branchesModel->find($branchId) !== null;
    }

    public function recordMatchesCurrentBranch(?array $record, string $column = 'branchId', ?int $userId = null): bool
    {
        if (empty($record)) {
            return false;
        }

        $effectiveBranchId = $this->getEffectiveBranchId($userId);

        if ($effectiveBranchId === null) {
            return $this->canUserSwitchBranches($userId);
        }

        return isset($record[$column]) && (int) $record[$column] === $effectiveBranchId;
    }

    public function scopeBuilder($builder, string $column = 'branchId', ?int $userId = null)
    {
        $effectiveBranchId = $this->getEffectiveBranchId($userId);

        if ($effectiveBranchId !== null) {
            $builder->where($column, $effectiveBranchId);
        }

        return $builder;
    }

    public function normalizeBranchId($branchId): ?int
    {
        $branchId = trim((string) $branchId);

        return $branchId === '' ? null : (int) $branchId;
    }

    private function resolveUser(int $userId)
    {
        if (auth()->id() && (int) auth()->id() === $userId && auth()->user()) {
            return auth()->user();
        }

        return $this->userModel->find($userId);
    }

    private function emptyScope(): array
    {
        return [
            'user_id' => null,
            'roles' => [],
            'can_switch_branches' => false,
            'assigned_branch_id' => null,
            'active_branch_id' => null,
            'effective_branch_id' => null,
        ];
    }
}
