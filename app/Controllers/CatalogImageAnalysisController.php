<?php

namespace App\Controllers;

use App\Models\Categories;
use App\Models\RawMaterialCategory;
use App\Services\BranchContextService;
use App\Services\CatalogImageAnalysisService;
use CodeIgniter\RESTful\ResourceController;

class CatalogImageAnalysisController extends ResourceController
{
    private Categories $productCategoryModel;
    private RawMaterialCategory $rawMaterialCategoryModel;
    private BranchContextService $branchContext;
    private CatalogImageAnalysisService $analysisService;

    public function __construct()
    {
        $this->productCategoryModel = new Categories();
        $this->rawMaterialCategoryModel = new RawMaterialCategory();
        $this->branchContext = service('branchContext');
        $this->analysisService = new CatalogImageAnalysisService();
    }

    public function analyze()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond(['status' => false, 'message' => 'Invalid request method.'], 405);
        }

        $type = $this->normalizeType($this->request->getVar('type'));
        if ($type === null) {
            return $this->respond(['status' => false, 'message' => 'Use type product or raw_material.'], 422);
        }

        $image = $this->request->getFile('image');
        if (!$image || !$image->isValid()) {
            return $this->respond(['status' => false, 'message' => 'Upload a valid image first.'], 422);
        }

        if (!in_array($image->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return $this->respond(['status' => false, 'message' => 'Only JPEG, PNG, and WEBP images are supported.'], 422);
        }

        if ($image->getSizeByUnit('mb') > 4) {
            return $this->respond(['status' => false, 'message' => 'Image must not exceed 4MB.'], 422);
        }

        try {
            return $this->respond(
                $this->analysisService->analyze($image, $type, $this->categoriesForType($type))
            );
        } catch (\Throwable $error) {
            log_message('error', 'Catalog image analysis failed: ' . $error->getMessage());

            return $this->respond([
                'status' => false,
                'message' => 'Could not analyze the image. Fill the fields manually or try another photo.',
            ], 502);
        }
    }

    private function normalizeType($value): ?string
    {
        $type = strtolower(trim((string) $value));

        if (in_array($type, ['product', 'products'], true)) {
            return 'product';
        }

        if (in_array($type, ['raw_material', 'raw-material', 'rawmaterial'], true)) {
            return 'raw_material';
        }

        return null;
    }

    private function categoriesForType(string $type): array
    {
        if ($type === 'raw_material') {
            $builder = $this->rawMaterialCategoryModel
                ->where('isActive', 1)
                ->orderBy('categoryName', 'ASC');

            return $this->branchContext->scopeBuilder($builder, 'branchId')->findAll();
        }

        return $this->productCategoryModel
            ->orderBy('categoryName', 'ASC')
            ->findAll();
    }
}
