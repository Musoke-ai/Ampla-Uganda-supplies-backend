<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\RawMaterials;
use App\Services\BranchContextService;

class RawMaterialsController extends ResourceController
{
      /** This controller holds the following functions
        = Check presence of data
        = Validation check
        = CRUD a stock
        = Fetch stock
    **/

    private $rawMaterialModel;
    private BranchContextService $branchContext;
    private array $statusOptions = ['active', 'inactive', 'discontinued'];

    public function __construct(){
        $this->rawMaterialModel = new RawMaterials();
        $this->branchContext = service('branchContext');
    }

    // fetch categories data
    public function noRawMaterialsData(){
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing in the rawMaterials table. Add new stock and try again.'
        ];
        return $this->respond($response);
    }

     // return resource object for validation failure
    public function validationFail(){
        $response = [
            'status' => false,
            'error' => 'validationError',
            'message' => $this->validator->getErrors()
        ];
        return $this->respond($response);
    }

    /**
     * Return an array of resource objects, themselves in array format
     *
     * @return mixed
     */
    public function index()
    {
        $rawMaterialsData = $this->branchContext
            ->scopeBuilder($this->rawMaterialModel)
            ->findAll();
        if(empty($rawMaterialsData)){
            return $this->noRawMaterialsData();
        }
        else{
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Success!! raw materials have been fetched to your front end.' 
            ];
            return $this->respond($rawMaterialsData);
        }
    }

    /**
     * Return the properties of a resource object
     *
     * @return mixed
     */
    public function addRawMaterial()
    {
        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'));
        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'Select a current branch first.'], 422);
        }
        if(strtolower($this->request->getMethod()) === 'post'){
        $data = $this->rawMaterialPayload($branchId);
        try {
            $data['rawMaterialImage'] = $this->storeUploadedImage('rawMaterialImage', 'raw-materials');
        } catch (\InvalidArgumentException $e) {
            return $this->respond(['status' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return $this->respond(['status' => false, 'message' => $e->getMessage()], 500);
        }
        $errors = $this->validateRawMaterialPayload($data);

        if (!empty($errors)) {
            return $this->respond([
                'status' => false,
                'error' => 'validationError',
                'message' => $errors,
            ], 422);
        }

        $insertQuery = $this->rawMaterialModel->insert($data);
        if(empty($insertQuery)){
            $response = [
                'status' => false,
                'error' => 'RawMaterialFail',
                'message' => 'Raw Material not added into the table and error occured or check all fields and try again!'
            ];
            return $this->respond($response);
        }
        else{
            $rawMaterialData = $this->rawMaterialModel->find($insertQuery);
            $response = [
                'status' => true,
                'error' => 'null',
                'message' => 'Item(s) successfully added in the table.',
                'data' => $rawMaterialData,
            ];

             $payload = [
                'rawMatrialId' => $insertQuery,
                'rawMaterialId' => $insertQuery,
                'message' => 'Raw materials created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('rawmaterials-channel', 'rawmaterials-created', $payload);
            return $this->respond($response);
        }
 
}
else{
    $response = [
        'status' => false,
        'error' => 'RequestMethodError',
        'message' => 'The request method is not post set it to post and try again.'
    ];
    return $this->respond($response);
}
    }


    /**
     * Return the editable properties of a resource object
     *
     * @return mixed
     */
    public function edit($id = null)
    {
        //fetch category to edit
        $data = $this->rawMaterialModel->find($id);

        if(empty($data)){
           return $this->noRawMaterialsData();
        }
        // in case a category is found
        else{
            return $this->respond($data);
        }
    }

    /**
     * Add or update a model resource, from "posted" properties
     *
     * @return mixed
     */
    public function update($id = null)
    {
        // Fetch and trim ID
        $id = trim($this->request->getVar('materialId'));
        $rawMaterial = $id ? $this->rawMaterialModel->find($id) : null;
        
        // Check if the ID is valid
        if (!$id || !$rawMaterial) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing raw material ID.'
            ]);
        }
        if (!$this->branchContext->recordMatchesCurrentBranch($rawMaterial)) {
            return $this->respond(['status' => false, 'message' => 'This raw material is outside your current branch scope.'], 403);
        }
    
        // Validate input
        if (!(strtolower($this->request->getMethod()) === 'post')) {
            return $this->respond([
                'status' => false,
                'error' => 'validationFailed',
                'message' => 'Validation failed. Please check your input and try again.'
            ]);
        }
    
        $rawMaterialUpdateData = $this->rawMaterialPayload(
            $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId')) ?? ($rawMaterial['branchId'] ?? null)
        );
        try {
            $uploadedImage = $this->storeUploadedImage('rawMaterialImage', 'raw-materials');
        } catch (\InvalidArgumentException $e) {
            return $this->respond(['status' => false, 'message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return $this->respond(['status' => false, 'message' => $e->getMessage()], 500);
        }
        if ($uploadedImage !== null) {
            $rawMaterialUpdateData['rawMaterialImage'] = $uploadedImage;
        }
        $errors = $this->validateRawMaterialPayload($rawMaterialUpdateData);

        if (!empty($errors)) {
            return $this->respond([
                'status' => false,
                'error' => 'validationError',
                'message' => $errors,
            ], 422);
        }
    
        // Ensure data is not empty before updating
        if (empty(array_filter($rawMaterialUpdateData, static fn ($value) => $value !== null && $value !== ''))) {
            return $this->respond([
                'status' => false,
                'error' => 'emptyData',
                'message' => 'There is no data to update. Please provide at least one field to modify.'
            ]);
        }
    
        // Correct update method call with ID
        if (!$this->rawMaterialModel->update($id, $rawMaterialUpdateData)) {
            return $this->respond([
                'status' => false,
                'error' => 'rawMaterialUpdateFail',
                'message' => 'Fail! Raw Material update failed. Please try again later.'
            ]);
        }
        $payload = [
                'rawMatrialId' => $id,
                'rawMaterialId' => $id,
                'message' => 'Raw material(s) updated' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('rawmaterials-channel', 'rawmaterials-updated', $payload);
        // Success response
        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Success!! Raw Material has been updated',
            'data' => $this->rawMaterialModel->find($id),
        ]);
    }
    

    /**
     * Delete the designated resource object from the model
     *
     * @return mixed
     */
    public function delete($id = null)
{
    // Fetch and trim ID
    $id = trim($this->request->getVar('materialId'));
    $rawMaterial = $id ? $this->rawMaterialModel->find($id) : null;

    // Check if the ID is valid
    if (!$id || !$rawMaterial) {
        return $this->respond([
            'status' => false,
            'error' => 'invalidId',
            'message' => 'Invalid or missing raw material ID.'
        ]);
    }
    if (!$this->branchContext->recordMatchesCurrentBranch($rawMaterial)) {
        return $this->respond(['status' => false, 'message' => 'This raw material is outside your current branch scope.'], 403);
    }

    // Perform delete operation
    if (!$this->rawMaterialModel->delete($id)) {
        return $this->respond([
            'status' => false,
            'error' => 'deleteFail',
            'message' => 'Fail! Raw Material has not been deleted. Please try again later.'
        ]);
    }
      $payload = [
                'rawMatrialId' => $id,
                'rawMaterialId' => $id,
                'message' => 'Raw material(s) deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('rawmaterials-channel', 'rawmaterials-deleted', $payload);
    // Success response
    return $this->respond([
        'status' => true,
        'error' => null,
        'message' => 'Success!! Selected raw material has been deleted.'
    ]);
}


    // validate raw material form entries
    private function validateCategoryEntries(){
            return $this->validate([
                'name' => [
                    'rules' => 'required|max_length[20]|min_length[3]|alpha_space|trim|is_unique[categories.categoryName]'
                ]
            ]);
    }

    private function rawMaterialPayload($branchId): array
    {
        return [
            'branchId' => $branchId,
            'name' => $this->cleanText($this->request->getVar('name')),
            'materialCode' => $this->cleanText($this->request->getVar('materialCode'), true, true),
            'rawMaterialBarcode' => $this->cleanText($this->request->getVar('rawMaterialBarcode'), true),
            'rawMaterialImage' => null,
            'category' => $this->cleanText($this->request->getVar('category'), true),
            'size' => $this->cleanText($this->request->getVar('size')),
            'unitOfMeasure' => $this->cleanText($this->request->getVar('unitOfMeasure')) ?: 'pcs',
            'Quantity' => $this->cleanDecimal($this->request->getVar('Quantity')),
            'unitPrice' => $this->cleanDecimal($this->request->getVar('unitPrice')),
            'reorderLevel' => $this->cleanDecimal($this->request->getVar('reorderLevel')),
            'supplier' => $this->cleanText($this->request->getVar('supplier')),
            'supplierContact' => $this->cleanText($this->request->getVar('supplierContact'), true),
            'storageLocation' => $this->cleanText($this->request->getVar('storageLocation'), true),
            'status' => $this->cleanStatus($this->request->getVar('status')),
            'note' => $this->cleanText($this->request->getVar('note')),
            'expiry' => $this->cleanText($this->request->getVar('expiry'), true),
        ];
    }

    private function validateRawMaterialPayload(array $data): array
    {
        $errors = [];

        if ($data['branchId'] === null || $data['branchId'] === '') {
            $errors['branchId'] = 'Select a branch for this raw material.';
        }

        if ($data['name'] === '') {
            $errors['name'] = 'Raw material name is required.';
        } elseif (mb_strlen($data['name']) < 2 || mb_strlen($data['name']) > 250) {
            $errors['name'] = 'Raw material name must be between 2 and 250 characters.';
        }

        foreach ([
            'materialCode' => 60,
            'rawMaterialBarcode' => 120,
            'category' => 120,
            'size' => 200,
            'unitOfMeasure' => 40,
            'supplier' => 250,
            'supplierContact' => 120,
            'storageLocation' => 150,
            'note' => 250,
        ] as $field => $maxLength) {
            if (mb_strlen((string) ($data[$field] ?? '')) > $maxLength) {
                $errors[$field] = ucfirst($field) . " must not exceed {$maxLength} characters.";
            }
        }

        foreach (['Quantity', 'unitPrice', 'reorderLevel'] as $field) {
            if (!is_numeric($data[$field]) || (float) $data[$field] < 0) {
                $errors[$field] = "{$field} must be a positive number or zero.";
            }
        }

        if (!in_array($data['status'], $this->statusOptions, true)) {
            $errors['status'] = 'Status must be active, inactive, or discontinued.';
        }

        if (!empty($data['expiry'])) {
            $expiry = strtotime((string) $data['expiry']);
            if ($expiry === false) {
                $errors['expiry'] = 'Expiry must be a valid date.';
            }
        }

        return $errors;
    }

    private function cleanText($value, bool $nullable = false, bool $uppercase = false): ?string
    {
        $cleaned = strip_tags((string) ($value ?? ''));
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cleaned) ?? '';
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

        if ($cleaned === '') {
            return $nullable ? null : '';
        }

        return $uppercase ? strtoupper($cleaned) : $cleaned;
    }

    private function cleanDecimal($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 3);
    }

    private function cleanStatus($value): string
    {
        $status = strtolower((string) ($value ?: 'active'));

        return in_array($status, $this->statusOptions, true) ? $status : 'active';
    }

    private function storeUploadedImage(string $field, string $folder): ?string
    {
        $file = $this->request->getFile($field);

        if (!$file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (!$file->isValid()) {
            throw new \InvalidArgumentException('The uploaded image is not valid.');
        }

        if ($file->getSize() > 3 * 1024 * 1024) {
            throw new \InvalidArgumentException('Raw material images must be 3MB or smaller.');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('Only JPEG, PNG, and WEBP images are allowed.');
        }

        $targetDirectory = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . $folder;
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('Could not prepare the image upload folder.');
        }

        $newName = $file->getRandomName();
        $file->move($targetDirectory, $newName);

        return 'uploads/' . $folder . '/' . $newName;
    }
}
