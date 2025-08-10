<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\RawMaterials;

class RawMaterialsController extends ResourceController
{
      /** This controller holds the following functions
        = Check presence of data
        = Validation check
        = CRUD a stock
        = Fetch stock
    **/

    private $rawMaterialModel;
    public function __construct(){
        $this->rawMaterialModel = new RawMaterials();
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
        //fetch stock
        $rawMaterialsData =  $this->rawMaterialModel->findAll();
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
        $data = [];
        if($this->request->getMethod() === 'post'){
               $data = [
               'name' => $this->request->getVar('name'),
               'size' => $this->request->getVar('size'),
               'Quantity' => $this->request->getVar('Quantity'),
               'unitPrice' => $this->request->getVar('unitPrice'),
               'supplier' => $this->request->getVar('supplier'),
               'note' => $this->request->getVar('note'),
               'expiry' => $this->request->getVar('expiry')
               
           ];
        $insertQuery =  $rawMaterialData =  $this->rawMaterialModel->insert($data);
        if(empty($insertQuery)){
            $response = [
                'status' => false,
                'error' => 'RawMaterialFail',
                'message' => 'Raw Material not added into the table and error occured or check all fields and try again!'
            ];
            return $this->respond($response);
        }
        else{
            $response = [
                'status' => true,
                'error' => 'null',
                'message' => 'Item(s) successfully added in the table.'
            ];

             $payload = [
                'rawMatrialId' => null,
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
        
        // Check if the ID is valid
        if (!$id || !$this->rawMaterialModel->find($id)) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing raw material ID.'
            ]);
        }
    
        // Validate input
        if (!($this->request->getMethod() === 'post')) {
            return $this->respond([
                'status' => false,
                'error' => 'validationFailed',
                'message' => 'Validation failed. Please check your input and try again.'
            ]);
        }
    
        // Prepare data
        $rawMaterialUpdateData = [
            'name' => $this->request->getVar('name'),
            'size' => $this->request->getVar('size'),
            'Quantity' => $this->request->getVar('Quantity'),
            'unitPrice' => $this->request->getVar('unitPrice'),
            'supplier' => $this->request->getVar('supplier'),
            'note' => $this->request->getVar('note'),
            'expiry' => $this->request->getVar('expiry'),
        ];
    
        // Ensure data is not empty before updating
        if (empty(array_filter($rawMaterialUpdateData))) {
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
                'message' => 'Raw material(s) updated' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('rawmaterials-channel', 'rawmaterials-updated', $payload);
        // Success response
        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Success!! Raw Material has been updated'
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

    // Check if the ID is valid
    if (!$id || !$this->rawMaterialModel->find($id)) {
        return $this->respond([
            'status' => false,
            'error' => 'invalidId',
            'message' => 'Invalid or missing raw material ID.'
        ]);
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
}
