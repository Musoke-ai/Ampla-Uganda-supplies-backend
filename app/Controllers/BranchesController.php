<?php

namespace App\Controllers;

use App\Models\Branches;
use App\Services\BranchContextService;
use CodeIgniter\RESTful\ResourceController;

class BranchesController extends ResourceController
{
    private Branches $branchesModel;
    private BranchContextService $branchContext;

    public function __construct()
    {
        $this->branchesModel = new Branches();
        $this->branchContext = service('branchContext');
        helper('pusher');
    }

    public function index()
    {
        if ($adminResponse = $this->ensureAdminAccess()) {
            return $adminResponse;
        }

        $effectiveBranchId = $this->branchContext->getEffectiveBranchId();
        $canSwitchBranches = $this->branchContext->canUserSwitchBranches();

        $branchData = $canSwitchBranches
            ? $this->branchesModel->findAll()
            : ($effectiveBranchId !== null
                ? $this->branchesModel->where('branchId', $effectiveBranchId)->findAll()
                : []);

        if (empty($branchData)) {
            return $this->respond([]);
        }

        return $this->respond($branchData);
    }

    public function create()
    {
        if ($adminResponse = $this->ensureAdminAccess()) {
            return $adminResponse;
        }

        if ($this->request->getMethod() !== 'post' || !$this->validateBranchEntries()) {
            return $this->respond([
                'status' => false,
                'error' => 'validationError',
                'message' => $this->validator ? $this->validator->getErrors() : 'Invalid request.',
            ]);
        }

        $data = $this->getBranchPayload();
        $inserted = $this->branchesModel->insert($data);

        if (!$inserted) {
            return $this->respond([
                'status' => false,
                'error' => 'branchCreateFail',
                'message' => 'Branch could not be created. Please review the details and try again.',
            ]);
        }

        $branchId = $this->branchesModel->insertID();

        $this->broadcastBranchEvent('branch-created', $branchId, 'Branch created');

        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Branch added successfully.',
            'data' => $branchId,
        ]);
    }

    public function update($id = null)
    {
        if ($adminResponse = $this->ensureAdminAccess()) {
            return $adminResponse;
        }

        $branchId = trim((string) $this->request->getVar('branchId'));

        if (!$branchId || !$this->branchesModel->find($branchId)) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing branch ID.',
            ]);
        }

        if ($this->request->getMethod() !== 'post' || !$this->validateBranchEntries()) {
            return $this->respond([
                'status' => false,
                'error' => 'validationError',
                'message' => $this->validator ? $this->validator->getErrors() : 'Invalid request.',
            ]);
        }

        $updated = $this->branchesModel->update($branchId, $this->getBranchPayload());

        if (!$updated) {
            return $this->respond([
                'status' => false,
                'error' => 'branchUpdateFail',
                'message' => 'Branch update failed. Please try again.',
            ]);
        }

        $this->broadcastBranchEvent('branch-updated', (int) $branchId, 'Branch updated');

        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Branch updated successfully.',
        ]);
    }

    public function delete($id = null)
    {
        if ($adminResponse = $this->ensureAdminAccess()) {
            return $adminResponse;
        }

        $branchId = trim((string) $this->request->getVar('branchId'));

        if (!$branchId || !$this->branchesModel->find($branchId)) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing branch ID.',
            ]);
        }

        if (!$this->branchesModel->delete($branchId)) {
            return $this->respond([
                'status' => false,
                'error' => 'branchDeleteFail',
                'message' => 'Branch deletion failed. Please try again.',
            ]);
        }

        $this->broadcastBranchEvent('branch-deleted', (int) $branchId, 'Branch deleted');

        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Branch deleted successfully.',
        ]);
    }

    public function switchBranch()
    {
        if ($adminResponse = $this->ensureAdminAccess()) {
            return $adminResponse;
        }

        $rawBranchId = trim((string) $this->request->getVar('branchId'));
        $branchId = $rawBranchId === '' ? null : (int) $rawBranchId;
        $result = $this->branchContext->switchBranch($branchId);

        if (!$result['status']) {
            return $this->respond([
                'status' => false,
                'message' => $result['message'],
            ], 422);
        }

        return $this->respond($result);
    }

    private function getBranchPayload(): array
    {
        $branchCode = trim((string) $this->request->getVar('branchCode'));

        $payload = [
            'branchName'        => trim((string) $this->request->getVar('branchName')),
            'branchCode'        => $branchCode !== '' ? strtoupper($branchCode) : null,
            'branchLocation'    => trim((string) $this->request->getVar('branchLocation')) ?: null,
            'branchContact'     => trim((string) $this->request->getVar('branchContact')) ?: null,
            'branchEmail'       => trim((string) $this->request->getVar('branchEmail')) ?: null,
            'branchManager'     => trim((string) $this->request->getVar('branchManager')) ?: null,
            'branchStatus'      => (int) ($this->request->getVar('branchStatus') ?? 1),
            'branchDescription' => trim((string) $this->request->getVar('branchDescription')) ?: null,
        ];

        if (db_connect()->fieldExists('allowDebtSales', 'branches')) {
            $payload['allowDebtSales'] = $this->normalizeDebtSaleOverride($this->request->getVar('allowDebtSales'));
        }

        return $payload;
    }

    private function normalizeDebtSaleOverride($value): ?int
    {
        if ($value === null || $value === '' || $value === 'inherit') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    private function validateBranchEntries(): bool
    {
        return $this->validate([
            'branchName' => [
                'rules' => 'required|max_length[200]|min_length[2]',
            ],
            'branchCode' => [
                'rules' => 'permit_empty|max_length[100]',
            ],
            'branchEmail' => [
                'rules' => 'permit_empty|valid_email|max_length[255]',
            ],
            'branchContact' => [
                'rules' => 'permit_empty|max_length[100]',
            ],
            'branchManager' => [
                'rules' => 'permit_empty|max_length[200]',
            ],
            'branchLocation' => [
                'rules' => 'permit_empty|max_length[255]',
            ],
            'allowDebtSales' => [
                'rules' => 'permit_empty|in_list[inherit,0,1,true,false]',
            ],
        ]);
    }

    private function broadcastBranchEvent(string $event, ?int $branchId, string $message): void
    {
        $payload = [
            'branchId' => $branchId,
            'message'  => $message,
        ];

        $pusher = get_pusher();
        $pusher->trigger('branches-channel', $event, $payload);
    }

    private function ensureAdminAccess()
    {
        $user = auth()->user();
        $roles = $user ? $user->getGroups() : [];

        if (!in_array('admin', $roles, true)) {
            return $this->failForbidden('Only administrators can manage branches.');
        }

        return null;
    }
}
