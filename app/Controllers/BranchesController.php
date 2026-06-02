<?php

namespace App\Controllers;

use App\Controllers\Traits\SecuresInput;
use App\Models\Branches;
use App\Services\BranchContextService;
use CodeIgniter\RESTful\ResourceController;

class BranchesController extends ResourceController
{
    use SecuresInput;

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

        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond([
                'status' => false,
                'error' => 'requestMethodError',
                'message' => 'Branch details could not be submitted. Please try again.',
            ], 405);
        }

        if (!$this->validateBranchEntries()) {
            return $this->branchValidationFail();
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

        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond([
                'status' => false,
                'error' => 'requestMethodError',
                'message' => 'Branch details could not be submitted. Please try again.',
            ], 405);
        }

        if (!$this->validateBranchEntries()) {
            return $this->branchValidationFail();
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
        $branchCode = $this->secureText($this->request->getVar('branchCode'), 100, true);

        $payload = [
            'branchName'        => $this->secureText($this->request->getVar('branchName'), 200),
            'branchCode'        => $branchCode !== null && $branchCode !== '' ? strtoupper($branchCode) : null,
            'branchLocation'    => $this->secureText($this->request->getVar('branchLocation'), 255, true),
            'branchContact'     => $this->secureText($this->request->getVar('branchContact'), 100, true),
            'branchEmail'       => $this->secureEmail($this->request->getVar('branchEmail')),
            'branchManager'     => $this->secureText($this->request->getVar('branchManager'), 200, true),
            'branchStatus'      => (int) ($this->request->getVar('branchStatus') ?? 1),
            'branchDescription' => $this->secureText($this->request->getVar('branchDescription'), 1000, true),
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
            'branchStatus' => [
                'rules' => 'permit_empty|in_list[0,1]',
            ],
            'branchDescription' => [
                'rules' => 'permit_empty|max_length[1000]',
            ],
        ]);
    }

    private function branchValidationFail()
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];

        return $this->respond([
            'status' => false,
            'error' => 'validationError',
            'message' => empty($errors)
                ? 'Please review the branch details and try again.'
                : implode(' ', array_values($errors)),
            'errors' => $errors,
        ], 422);
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
