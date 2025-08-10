<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\RawMaterialsRegister;
use App\Models\RawMaterials;
use App\Models\ProductRegister;
use App\Models\EmployeeRegister;

class RawMaterialsRegisterController extends ResourceController
 {
    /** This controller holds the following functions
    = Check presence of data
    = Validation check
    = CRUD a stock
    = Fetch stock
    **/

    private $rawMaterialsModel;
    private $rawMaterialsRegister;

    public function __construct() {
        $this->rawMaterialsModel = new RawMaterials();
        $this->rawMaterialsRegister = new RawMaterialsRegister();
    }

    // fetch categories data

    public function noData() {
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing in the raw materials daily list table.Create a new list.'
        ];
        return $this->respond( $response );
        exit();
    }

    // return resource object for validation failure

    public function validationFail() {
        $response = [
            'status' => false,
            'error' => 'validationError',
            'message' => $this->validator->getErrors()
        ];
        return $this->respond( $response );
        exit();
    }

    /**
    * Return an array of resource objects, themselves in array format
    *
    * @return mixed
    */

    public function index()
 {
        //fetch stock
        $dailyList =  $this->rawMaterialsRegister->findAll();
        if ( empty( $dailyList ) ) {
            return $this->noData();
        } else {
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Success!! daily raw materials list has been fetched to your front end.'
            ];
            return $this->respond( $dailyList );
        }
    }

    /**
    * Return the properties of a resource object
    *
    * @return mixed
    */
    public function createList()
    {
        // Decode raw JSON from request body
        $json = $this->request->getBody();
        $inputData = json_decode($json, true); // Decode into an associative array
    
        // Validate input
        if (empty($inputData)) {
            return $this->respond([
                'status' => false,
                'error' => 'InvalidInput',
                'message' => 'Invalid or empty material data. Expecting a JSON object or array.'
            ], 400);
        }

        // Standardize input to be an array of materials, so we can handle a single object or an array of objects
        $materials = isset($inputData[0]) && is_array($inputData[0]) ? $inputData : [$inputData];
    
        $insertData = [];
        $updateMap = [];
    
        foreach ($materials as $material) {
            $materialId = $material['materialId'] ?? null;
            $quantity = $material['quantity'] ?? 0;
            $initials = $material['initials'] ?? null;

            // Validation: required fields
            if (empty($materialId) || !is_numeric($quantity) || (float)$quantity <= 0) {
                return $this->respond([
                    'status' => false,
                    'error' => 'MissingOrInvalidFields',
                    'message' => 'Each material must have a valid materialId and a numeric quantity greater than 0.'
                ], 400);
            }
    
            // Get current raw material from the main stock
            $rawMaterial = $this->rawMaterialsModel->find($materialId);
            if (!$rawMaterial) {
                 return $this->respond([
                    'status' => false,
                    'error' => 'NotFound',
                    'message' => "Material with ID {$materialId} not found in stock."
                ], 404);
            }

            // Check for sufficient stock
            if ((float)$quantity > (float)$rawMaterial['Quantity']) {
                return $this->respond([
                    'status' => false,
                    'error' => 'InsufficientStock',
                    'message' => "Insufficient stock for: ".$rawMaterial['name'].". Requested: {$quantity}, Available: ".$rawMaterial['Quantity']
                ], 409); // 409 Conflict
            }

            // Calculate total cost based on unit price from stock
            $totalCost = (float)$rawMaterial['unitPrice'] * (float)$quantity;
    
            // Prepare data for batch insertion into the daily register
            $insertData[] = [
                'materialId' => $materialId,
                'quantity' => $quantity,
                'totalCost' => $totalCost,
                'initials' => $initials
            ];
    
            // Prepare data for updating the main stock quantity
            $updateMap[$materialId] = [
                'Quantity' => (float)$rawMaterial['Quantity'] - (float)$quantity,
            ];
        }
    
        // Insert all records to the daily register table
        if (!empty($insertData)) {
            $inserted = (count($insertData) > 1)
                ? $this->rawMaterialsRegister->insertBatch($insertData)
                : $this->rawMaterialsRegister->insert($insertData[0]);
        
            if (!$inserted) {
                return $this->respond([
                    'status' => false,
                    'error' => 'InsertError',
                    'message' => 'Failed to add materials to the daily register.'
                ], 500);
            }
        }
    
        // Update stock quantities in the main raw materials table
        foreach ($updateMap as $materialId => $updateData) {
            $this->rawMaterialsModel->update($materialId, $updateData);
        }

        $payload = [
            'rawMatrialId' => null, // This could be improved to return IDs of created records
                'message' => 'Raw material List created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('rawmaterialsregister-channel', 'rawmaterialsregister-created', $payload);
    
        // Success response
        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Raw materials successfully added to the daily list.'
        ]);
    }
    

    /**
    * Return the editable properties of a resource object
    *
    * @return mixed
    */

    public function edit( $id = null )
 {
    }

    /**
    * Add or update a model resource, from 'posted' properties
    *
    * @return mixed
    */

    public function update( $id = null )
 {
     // Validate input
    //  if ( !( $this->request->getMethod() === 'post') ) {
    //     return $this->respond( [
    //         'status' => false,
    //         'error' => 'validationFailed',
    //         'message' => 'Validation failed. Please check your input and try again.'
    //     ] );
    // }
        // Fetch and trim ID
        $id = trim( $this->request->getVar('id') );
           // Check if the ID is valid
        $dailyRawMaterial = $this->rawMaterialsRegister->find( $id );
           if ( !$id || !$dailyRawMaterial ) {
            return $this->respond( [
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing raw material list ID.'
            ] );
        }
        $rawMaterial = $this->rawMaterialsModel->find( $this->request->getVar( 'materialId' ) );
        $requestedQty = $this->request->getVar('quantity');
        $oldQty = $dailyRawMaterial['quantity'];
        $stockQty = $rawMaterial['Quantity'];
        
        $diff = $requestedQty - $oldQty; // positive if more is needed, negative if less is used
        
        $newQty = $requestedQty;
        $newItemQty = $stockQty - $diff; // since more usage reduces stock, less usage increases stock
        
        // If more quantity is being used than before, ensure store has enough stock
        if ($diff > 0 && $diff > $stockQty) {
            return $this->respond([
                'status' => false,
                'error' => 'lowStock',
                'message' => 'Not enough stock in the store.'
            ]);
        }

        // Prepare data
        $dailyListUpdateData = [
            'materialId' => $this->request->getVar( 'materialId' ),
            'quantity' => $newQty,
            'totalCost' => $rawMaterial['unitPrice'] * $newQty,
            'initials' => $this->request->getVar( 'initials' ),
        ];

        // Ensure data is not empty before updating
        if ( empty( array_filter( $dailyListUpdateData ) ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'emptyData',
                'message' => 'There is no data to update. Please provide at least one field to modify.'
            ] );
        }
     
            // Correct update method call with ID
            if ( !$this->rawMaterialsRegister->update( $id, $dailyListUpdateData ) ) {
                return $this->respond( [
                    'status' => false,
                    'error' => 'dailyListUpdateFail',
                    'message' => 'Fail! Daily List update failed. Please try again later.'
                ] );
            }

             //update the raw material quantity
             $rawMaterialUpdateData = [
                'name' => $rawMaterial[ 'name' ],
                'size' => $rawMaterial[ 'size' ],
                'Quantity' => $newItemQty,
                'unitPrice' => $rawMaterial[ 'unitPrice' ],
                'supplier' => $rawMaterial[ 'supplier' ],
                'note' => $rawMaterial[ 'note' ],
            ];
            $updateQty = $this->rawMaterialsModel->update( $rawMaterial[ 'materialId' ], $rawMaterialUpdateData );
    $payload = [
                'rawMatrialId' => $rawMaterial,
                'message' => 'Raw material List updated' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('rawmaterialsregister-channel', 'rawmaterialsregister-updated', $payload);
            // Success response
            return $this->respond( [
                'status' => true,
                'error' => null,
                'message' => 'Success!! Daily list has been updated'
            ] );
        }

    /**
    * Delete the designated resource object from the model
    *
    * @return mixed
    */

    public function delete( $id = null )
 {
        // Fetch and trim ID
        $id = trim( $this->request->getVar( 'id' ) );

        $rawMaterial = $this->rawMaterialsModel->find( $this->request->getVar( 'materialId' ) );
        $dailyRawMaterialQty = $this->rawMaterialsRegister->find( $id )['quantity'];

        // Check if the ID is valid
        if ( !$id || !$this->rawMaterialsRegister->find( $id ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing daily list ID.'
            ] );
        }

        // Perform delete operation
        if ( !$this->rawMaterialsRegister->delete( $id ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'deleteFail',
                'message' => 'Fail! Daily list has not been deleted. Please try again later.'
            ] );
        }
         //update the raw material quantity
         $rawMaterialUpdateData = [
            'name' => $rawMaterial[ 'name' ],
            'size' => $rawMaterial[ 'size' ],
            'Quantity' => $rawMaterial[ 'Quantity' ] + $dailyRawMaterialQty,
            'unitPrice' => $rawMaterial[ 'unitPrice' ],
            'supplier' => $rawMaterial[ 'supplier' ],
            'note' => $rawMaterial[ 'note' ],
        ];
        $updateQty = $this->rawMaterialsModel->update( $rawMaterial[ 'materialId' ], $rawMaterialUpdateData );
 $payload = [
                'rawMatrialId' => $rawMaterial,
                'message' => 'Raw material List deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('rawmaterialsregister-channel', 'rawmaterialsregister-deleted', $payload);
        // Success response
        return $this->respond( [
            'status' => true,
            'error' => null,
            'message' => 'Success!! Selected raw material has been removed from the list.'
        ] );
    }

    // validate daily list form entries

    private function validateCategoryEntries() {
        return $this->validate( [
            'materialId' => [
                'rules' => 'required|max_length[20]|min_length[3]|alpha_space|trim'
            ]
        ] );
    }
}
