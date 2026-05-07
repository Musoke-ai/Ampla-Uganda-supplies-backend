<?php

namespace App\Controllers;

use App\Models\RawMaterialCategory;
use App\Models\RawMaterials;
use App\Services\BranchContextService;
use CodeIgniter\RESTful\ResourceController;

class RawMaterialCategoriesController extends ResourceController
{
    private RawMaterialCategory $categoryModel;
    private RawMaterials $rawMaterialModel;
    private BranchContextService $branchContext;

    public function __construct()
    {
        $this->categoryModel = new RawMaterialCategory();
        $this->rawMaterialModel = new RawMaterials();
        $this->branchContext = service('branchContext');
    }

    public function index()
    {
        $builder = $this->categoryModel->orderBy('categoryName', 'ASC');

        return $this->respond($this->branchContext->scopeBuilder($builder, 'branchId')->findAll());
    }

    public function create()
    {
        if ($this->request->getMethod() !== 'post') {
            return $this->respond(['status' => false, 'message' => 'Invalid request method.'], 405);
        }

        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'));
        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }

        $payload = $this->payload($branchId);
        $errors = $this->validatePayload($payload);

        if (!empty($errors)) {
            return $this->respond(['status' => false, 'error' => 'validationError', 'message' => $errors], 422);
        }

        if ($this->categoryExists($payload['categoryName'], $branchId)) {
            return $this->respond(['status' => false, 'message' => 'A raw material category with this name already exists.'], 422);
        }

        $categoryId = $this->categoryModel->insert($payload);

        if (!$categoryId) {
            return $this->respond(['status' => false, 'message' => 'Raw material category could not be created.'], 500);
        }

        return $this->respond([
            'status' => true,
            'message' => 'Raw material category created successfully.',
            'data' => $this->categoryModel->find($categoryId),
        ]);
    }

    public function update($id = null)
    {
        if ($this->request->getMethod() !== 'post') {
            return $this->respond(['status' => false, 'message' => 'Invalid request method.'], 405);
        }

        $categoryId = trim((string) ($id ?? $this->request->getVar('categoryId')));
        $existing = $categoryId ? $this->categoryModel->find($categoryId) : null;

        if (!$existing) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing raw material category ID.'], 422);
        }

        if (!$this->branchContext->recordMatchesCurrentBranch($existing)) {
            return $this->respond(['status' => false, 'message' => 'This category is outside your current branch scope.'], 403);
        }

        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId')) ?? ($existing['branchId'] ?? null);
        $payload = $this->payload($branchId);
        $errors = $this->validatePayload($payload);

        if (!empty($errors)) {
            return $this->respond(['status' => false, 'error' => 'validationError', 'message' => $errors], 422);
        }

        if ($this->categoryExists($payload['categoryName'], $branchId, (int) $categoryId)) {
            return $this->respond(['status' => false, 'message' => 'A raw material category with this name already exists.'], 422);
        }

        $db = db_connect();
        $db->transBegin();

        $updated = $this->categoryModel->update($categoryId, $payload);
        if ($updated && $payload['categoryName'] !== ($existing['categoryName'] ?? '')) {
            $db->table('raw_materials')
                ->where('branchId', $existing['branchId'])
                ->where('category', $existing['categoryName'])
                ->update(['category' => $payload['categoryName']]);
        }

        if (!$updated || $db->transStatus() === false) {
            $db->transRollback();
            return $this->respond(['status' => false, 'message' => 'Raw material category update failed.'], 500);
        }

        $db->transCommit();

        return $this->respond([
            'status' => true,
            'message' => 'Raw material category updated successfully.',
            'data' => $this->categoryModel->find($categoryId),
        ]);
    }

    public function delete($id = null)
    {
        $categoryId = trim((string) ($id ?? $this->request->getVar('categoryId')));
        $existing = $categoryId ? $this->categoryModel->find($categoryId) : null;

        if (!$existing) {
            return $this->respond(['status' => false, 'message' => 'Invalid or missing raw material category ID.'], 422);
        }

        if (!$this->branchContext->recordMatchesCurrentBranch($existing)) {
            return $this->respond(['status' => false, 'message' => 'This category is outside your current branch scope.'], 403);
        }

        $used = $this->rawMaterialModel
            ->where('branchId', $existing['branchId'])
            ->where('category', $existing['categoryName'])
            ->countAllResults();

        if ($used > 0) {
            return $this->respond([
                'status' => false,
                'message' => 'This category is used by raw materials. Mark it inactive or move those materials first.',
            ], 422);
        }

        if (!$this->categoryModel->delete($categoryId)) {
            return $this->respond(['status' => false, 'message' => 'Raw material category could not be deleted.'], 500);
        }

        return $this->respond(['status' => true, 'message' => 'Raw material category deleted successfully.']);
    }

    private function payload($branchId): array
    {
        return [
            'branchId' => $branchId,
            'categoryName' => $this->cleanText($this->request->getVar('categoryName')),
            'description' => $this->cleanText($this->request->getVar('description'), true),
            'isActive' => $this->request->getVar('isActive') === null ? 1 : (int) (bool) $this->request->getVar('isActive'),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if ($payload['branchId'] === null || $payload['branchId'] === '') {
            $errors['branchId'] = 'Select a branch for this category.';
        }

        if ($payload['categoryName'] === '') {
            $errors['categoryName'] = 'Category name is required.';
        } elseif (mb_strlen($payload['categoryName']) < 2 || mb_strlen($payload['categoryName']) > 120) {
            $errors['categoryName'] = 'Category name must be between 2 and 120 characters.';
        }

        if (!empty($payload['description']) && mb_strlen($payload['description']) > 250) {
            $errors['description'] = 'Description must not exceed 250 characters.';
        }

        return $errors;
    }

    private function categoryExists(string $categoryName, ?int $branchId, ?int $ignoreId = null): bool
    {
        $builder = $this->categoryModel
            ->where('branchId', $branchId)
            ->where('LOWER(categoryName)', mb_strtolower($categoryName));

        if ($ignoreId !== null) {
            $builder->where('categoryId !=', $ignoreId);
        }

        return $builder->first() !== null;
    }

    private function cleanText($value, bool $nullable = false): ?string
    {
        $cleaned = trim(preg_replace('/\s+/', ' ', (string) ($value ?? '')));

        if ($cleaned === '') {
            return $nullable ? null : '';
        }

        return $cleaned;
    }
}
