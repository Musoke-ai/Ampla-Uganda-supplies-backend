<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\Employees;

class EmployeesController extends ResourceController
 {
    /** This controller holds the following functions
    = Check presence of data
    = Validation check
    = CRUD a stock
    = Fetch stock
    **/

    private $employeesModel;

    public function __construct() {
        $this->employeesModel = new Employees();
    }

    // fetch categories data

    public function noEmployeesData() {
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing in the employees table. Add new employee and try again.'
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
        $employeeData =  $this->employeesModel->findAll();
        if ( empty( $employeeData ) ) {
            return $this->noEmployeesData();
        } else {
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Success!! raw materials have been fetched to your front end.'
            ];
            return $this->respond( $employeeData );
        }
    }

    /**
    * Return the properties of a resource object
    *
    * @return mixed
    */

    public function addEmployee()
 {
        $data = [];
        if ( $this->request->getMethod() === 'post' ) {
            $data = [
                'empName' => $this->request->getVar( 'empName' ),
                'empEmail' => $this->request->getVar( 'empEmail' ),
                'empLocation' => $this->request->getVar( 'empLocation' ),
                'empContact' => $this->request->getVar( 'empContact' ),
                'empRole' => $this->request->getVar( 'empRole' ),
                'empSalary' => $this->request->getVar( 'empSalary' ),
                'empStatus' => $this->request->getVar( 'empStatus' ),
                'startDate' => $this->request->getVar( 'startDate' ),
                'endDate' => $this->request->getVar( 'endDate' ),
            ];
            $insertQuery  =  $this->employeesModel->insert( $data );
            if ( empty( $insertQuery ) ) {
                $response = [
                    'status' => false,
                    'error' => 'RawMaterialFail',
                    'message' => 'Employee not added into the table and error occured or check all fields and try again!'
                ];
                return $this->respond( $response );
            } else {
                       $payload = [
                'empId' => null,
                'message' => 'Employee created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('employees-channel', 'employee-created', $payload);
                $response = [
                    'status' => true,
                    'error' => 'null',
                    'message' => 'Employee successfully added in the table.'
                ];
                return $this->respond( $response );
            }

        } else {
            $response = [
                'status' => false,
                'error' => 'RequestMethodError',
                'message' => 'The request method is not post set it to post and try again.'
            ];
            return $this->respond( $response );
        }
    }

    /**
    * Return the editable properties of a resource object
    *
    * @return mixed
    */

    public function edit( $id = null )
 {
        //fetch category to edit
        $data = $this->employeesModel->find( $id );

        if ( empty( $data ) ) {
            return $this->noEmployeesData();
        }
        // in case a category is found
        else {
            return $this->respond( $data );
        }
    }

    /**
    * Add or update a model resource, from 'posted' properties
    *
    * @return mixed
    */

    public function update( $id = null )
 {
        // Fetch and trim ID
        $id = trim( $this->request->getVar( 'empID' ) );

        // Check if the ID is valid
        if ( !$id || !$this->employeesModel->find( $id ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing employee ID.'
            ] );
        }

        // Validate input
        if ( !( $this->request->getMethod() === 'post' && $this->validateCategoryEntries() ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'validationFailed',
                'message' => 'Validation failed. Please check your input and try again.'
            ] );
        }

        // Prepare data
        $employeeUpdateData = [
            'empName' => $this->request->getVar( 'empName' ),
            'empEmail' => $this->request->getVar( 'empEmail' ),
            'empLocation' => $this->request->getVar( 'empLocation' ),
            'empContact' => $this->request->getVar( 'empContact' ),
            'empRole' => $this->request->getVar( 'empRole' ),
            'empSalary' => $this->request->getVar( 'empSalary' ),
            'empStatus' => $this->request->getVar( 'empStatus' ),
            'startDate' => $this->request->getVar( 'startDate' ),
            'endDate' => $this->request->getVar( 'endDate' ),
        ];

        // Ensure data is not empty before updating
        if ( empty( array_filter( $employeeUpdateData ) ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'emptyData',
                'message' => 'There is no data to update. Please provide at least one field to modify.'
            ] );
        }

        // Correct update method call with ID
        if ( !$this->employeesModel->update( $id, $employeeUpdateData ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'employeeUpdateFail',
                'message' => 'Fail! Employee update failed. Please try again later.'
            ] );
        }
  $payload = [
                'empId' => $id,
                'message' => 'Employee updated' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('employees-channel', 'employee-updated', $payload);
        // Success response
        return $this->respond( [
            'status' => true,
            'error' => null,
            'message' => 'Success!! employee has been updated'
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
        $id = trim( $this->request->getVar( 'empID' ) );

        // Check if the ID is valid
        if ( !$id || !$this->employeesModel->find( $id ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing employee ID.'
            ] );
        }

        // Perform delete operation
        if ( !$this->employeesModel->delete( $id ) ) {
            return $this->respond( [
                'status' => false,
                'error' => 'deleteFail',
                'message' => 'Fail! Employee has not been deleted. Please try again later.'
            ] );
        }
  $payload = [
                'empId' => $id,
                'message' => 'Employee deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('employees-channel', 'employee-deleted', $payload);
        // Success response
        return $this->respond( [
            'status' => true,
            'error' => null,
            'message' => 'Success!! Selected Employee has been deleted.'
        ] );
    }

    // validate category form entries

    private function validateCategoryEntries() {
        return $this->validate( [
            'empName' => [
                'rules' => 'required|max_length[20]|min_length[3]|alpha_space|trim|is_unique[categories.categoryName]'
            ]
        ] );
    }
}
