<?php

namespace App\Controllers;

use App\Controllers\Traits\SecuresInput;
use App\Services\BranchContextService;
use App\Services\ImportService;
use CodeIgniter\RESTful\ResourceController;
use InvalidArgumentException;
use Throwable;

class ImportsController extends ResourceController
{
    use SecuresInput;

    private ImportService $importService;
    private BranchContextService $branchContext;

    public function __construct()
    {
        $this->importService = new ImportService();
        $this->branchContext = service('branchContext');
    }

    public function history()
    {
        return $this->respond([
            'status' => true,
            'data' => $this->importService->getHistory((int) auth()->id()),
        ]);
    }

    public function mappings()
    {
        $type = $this->request->getGet('type');

        return $this->respond([
            'status' => true,
            'data' => $this->importService->getSavedMappings((int) auth()->id(), $type ?: null),
        ]);
    }

    public function show($id = null)
    {
        try {
            return $this->respond([
                'status' => true,
                'data' => $this->importService->getBatchWithRows((int) $id, (int) auth()->id()),
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function upload()
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();

        try {
            $branchId = $this->branchContext->resolveWritableBranchId($payload['branchId'] ?? null);
            if ($branchId === null) {
                return $this->respond([
                    'status' => false,
                    'message' => 'Select a current branch before importing data.',
                ], 422);
            }

            $batch = $this->importService->createBatch(
                $this->secureAllowed($payload['type'] ?? '', ['products', 'customers', 'sales', 'stock', 'raw_materials'], ''),
                $this->secureText($payload['fileName'] ?? null, 255, true),
                is_array($payload['headers'] ?? null) ? $this->secureArrayRecursive($payload['headers'], 255) : [],
                is_array($payload['rows'] ?? null) ? $this->secureArrayRecursive($payload['rows'], 1000) : [],
                $branchId,
                (int) auth()->id()
            );

            return $this->respond([
                'status' => true,
                'message' => 'Import file prepared for mapping.',
                'data' => $batch,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function validateBatch($id = null)
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();

        try {
            $batch = $this->importService->validateBatch(
                (int) $id,
                is_array($payload['mapping'] ?? null) ? $this->secureArrayRecursive($payload['mapping'], 255) : [],
                is_array($payload['options'] ?? null) ? $this->secureArrayRecursive($payload['options'], 255) : [],
                (int) auth()->id()
            );

            return $this->respond([
                'status' => true,
                'message' => 'Import validation completed.',
                'data' => $batch,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function updateRow($batchId = null, $rowId = null)
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();

        try {
            $batch = $this->importService->updateRowRawData(
                (int) $batchId,
                (int) $rowId,
                is_array($payload['rawData'] ?? null) ? $this->secureArrayRecursive($payload['rawData'], 1000) : [],
                (int) auth()->id()
            );

            return $this->respond([
                'status' => true,
                'message' => 'Import row updated. Validate again before confirming.',
                'data' => $batch,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function confirm($id = null)
    {
        try {
            $batch = $this->importService->confirmBatch((int) $id, (int) auth()->id());

            return $this->respond([
                'status' => true,
                'message' => 'Import completed successfully.',
                'data' => $batch,
            ]);
        } catch (Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    private function errorResponse(Throwable $e)
    {
        $statusCode = $e instanceof InvalidArgumentException ? 422 : 500;
        log_message('error', 'Import API error: ' . $e->getMessage());

        return $this->respond([
            'status' => false,
            'message' => $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'The import request could not be completed.',
        ], $statusCode);
    }
}
