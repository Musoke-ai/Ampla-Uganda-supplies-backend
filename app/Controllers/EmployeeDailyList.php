<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\RawMaterialsRegister;
use App\Models\RawMaterials;
use App\Models\ProductRegister;
use App\Models\EmployeeRegister;

class EmployeeDailyList extends ResourceController
 {
    /** This controller holds the following functions
    = Check presence of data
    = Validation check
    = CRUD a stock
    = Fetch stock
    **/

    private $rawMaterialsModel;
    private $rawMaterialsRegister;
    private $productRegister;
    private $employeeRegister;

    public function __construct() {

        $this->rawMaterialsModel = new RawMaterials();
        $this->rawMaterialsRegister = new RawMaterialsRegister();
        $this->productRegister = new ProductRegister();
        $this->employeeRegister = new EmployeeRegister();
    }

    // fetch categories data

    public function noDailyListData() {
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing in the employee daily list table.Create a new list.'
        ];
        return $this->respond( $response );
    }

    // return resource object for validation failure

    public function validationFail() {
        $response = [
            'status' => false,
            'error' => 'validationError',
            'message' => $this->validator->getErrors()
        ];
        return $this->respond( $response );
    }

    /**
    * Return an array of resource objects, themselves in array format
    *
    * @return mixed
    */

    public function index()
 {
        //fetch stock
        $dailyList =  $this->employeeRegister->findAll();
        if ( empty( $dailyList ) ) {
            return $this->noDailyListData();
        } else {
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Success!! expenses have been fetched to your front end.'
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
        if ($this->request->getMethod() !== 'post') {
            return $this->respond([
                'status' => false,
                'error' => 'RequestMethodError',
                'message' => 'The request method is not POST. Set it to POST and try again.'
            ]);
        }
    
        $inputData = $this->request->getJSON(true); // get associative array from JSON input
    
        if (!is_array($inputData) || empty($inputData)) {
            return $this->respond([
                'status' => false,
                'error' => 'InvalidData',
                'message' => 'No valid data received. Please send a non-empty array of employees.'
            ]);
        }
    
        $batchData = [];
    
        foreach ($inputData as $item) {
            if (isset($item['id'], $item['role'], $item['pay'])) {
                $batchData[] = [
                    'empID' => $item['id'],
                    'role' => $item['role'],
                    'payment' => $item['pay'],
                    'amountPaid' => 0,
                ];
            }
        }
    
        if (empty($batchData)) {
            return $this->respond([
                'status' => false,
                'error' => 'ValidationError',
                'message' => 'All entries are missing required fields.'
            ]);
        }
    
        $inserted = $this->employeeRegister->insertBatch($batchData);
    
        if (!$inserted) {
            return $this->respond([
                'status' => false,
                'error' => 'BatchInsertFailed',
                'message' => 'Batch insert failed. Please verify your data and try again.'
            ]);
        }
        
                 $payload = [
                'employeListId' => null,
                'message' => 'employeeList created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('employeeList-channel', 'employeeList-created', $payload);
    
        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Workers/Employees successfully saved to the daily list.'
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
        // Fetch and trim ID
        $id = trim( $this->request->getVar( 'id' ) );

        // Check if the ID is valid
        if ( !$id || !$this->employeeRegister->find( $id ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing daily list ID.'
            ] );
        }

        // Validate input
        if ( !( $this->request->getMethod() === 'post') ) {
            return $this->respond( [
                'status' => false,
                'error' => 'validationFailed',
                'message' => 'Validation failed. Please check your input and try again.'
            ] );
        }

        // Prepare data
        $dailyListUpdateData = [
            'empID' => $this->request->getVar( 'empID' ),
            'role' => $this->request->getVar( 'role' ),
            'payment' => $this->request->getVar( 'pay' ),
            'amountPaid' => $this->request->getVar( 'amountPaid'),
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
        if ( !$this->employeeRegister->update( $id, $dailyListUpdateData ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'dailyListUpdateFail',
                'message' => 'Fail! Daily List update failed. Please try again later.'
            ] );
        }

           $payload = [
                'employeListId' => $id,
                'message' => 'employeeList updated' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('employeeList-channel', 'employeeList-updated', $payload);
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

        // Check if the ID is valid
        if ( !$id || !$this->employeeRegister->find( $id ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing daily list ID.'
            ] );
        }

        // Perform delete operation
        if ( !$this->employeeRegister->delete( $id ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'deleteFail',
                'message' => 'Fail! Employee from the Daily list has not been deleted. Please try again later.'
            ] );
        }

         $payload = [
                'employeListId' => $id,
                'message' => 'employeeList deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('employeeList-channel', 'employeeList-deleted', $payload);

        // Success response
        return $this->respond( [
            'status' => true,
            'error' => null,
            'message' => 'Success!! Selected employee/worker has been deleted from the list.'
        ] );
    }

    /**
    * Handle payment for an employee on the daily list.
    *
    * @return mixed
    */
    public function payEmployee()
    {
        if ($this->request->getMethod() !== 'post') {
            return $this->respond([
                'status' => false,
                'error' => 'RequestMethodError',
                'message' => 'The request method is not POST.'
            ], 405);
        }

        $id = trim($this->request->getVar('id'));
        $amountToPay = $this->request->getVar('amountPaid');

        if (empty($id) || !is_numeric($amountToPay) || $amountToPay <= 0) {
            return $this->respond([
                'status' => false,
                'error' => 'InvalidInput',
                'message' => 'A valid ID and a positive payment amount are required.'
            ], 400);
        }

        $dailyEmployee = $this->employeeRegister->find($id);

        if (!$dailyEmployee) {
            return $this->failNotFound('No employee record found on the daily list with id ' . $id);
        }

        $totalPaymentDue = (float)$dailyEmployee['payment'];
        $alreadyPaid = (float)$dailyEmployee['amountPaid'];

        if ($alreadyPaid >= $totalPaymentDue) {
            return $this->respond([
                'status' => false,
                'error' => 'AlreadyPaid',
                'message' => 'This employee has already been fully paid.'
            ], 409); // Conflict
        }

        $balance = $totalPaymentDue - $alreadyPaid;

        if ((float)$amountToPay > $balance) {
            return $this->respond([
                'status' => false,
                'error' => 'Overpayment',
                'message' => 'Payment amount exceeds the outstanding balance of ' . number_format($balance, 2)
            ], 400);
        }

        $newAmountPaid = $alreadyPaid + (float)$amountToPay;

        if (!$this->employeeRegister->update($id, ['amountPaid' => $newAmountPaid])) {
            return $this->respond(['status' => false, 'error' => 'UpdateFailed', 'message' => 'Failed to update employee payment.'], 500);
        }

        $payload = [
            'employeListId' => $id,
            'amountPaid' => $amountToPay,
            'message' => 'Employee payment processed'
        ];

        // Trigger the event via Pusher
        $pusher = get_pusher();
        $pusher->trigger('employeeList-channel', 'employee-updated', $payload);

        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Payment successfully processed.',
            'data' => ['id' => $id, 'totalPaid' => $newAmountPaid, 'remainingBalance' => $totalPaymentDue - $newAmountPaid]
        ]);
    }

    // validate daily list form entries

    private function validateCategoryEntries() {
        return $this->validate( [
            'empID' => [
                'rules' => 'required|max_length[20]|min_length[3]|alpha_space|trim'
            ]
        ] );
    }
}
