<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\Expense;

class ExpensesController extends ResourceController
{
      /** This controller holds the following functions
        = Check presence of data
        = Validation check
        = CRUD a stock
        = Fetch stock
    **/

    private $expenseModel;
    public function __construct(){
        $this->expenseModel = new Expense();
    }

    // fetch categories data
    public function noExpensesData(){
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing in the expense table. Add new stock and try again.'
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
        $expenseData =  $this->expenseModel->findAll();
        if(empty($expenseData)){
            return $this->noExpensesData();
        }
        else{
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Success!! expenses have been fetched to your front end.' 
            ];
            return $this->respond($expenseData);
        }
    }

    /**
     * Return the properties of a resource object
     *
     * @return mixed
     */
    public function addExpense()
    {
        $data = [];
        if($this->request->getMethod() === 'post'){
               $data = [
               'category' => $this->request->getVar('category'),
               'description' => $this->request->getVar('description'),
               'amount' => $this->request->getVar('amount'),
               'givenTo' => $this->request->getVar('givenTo'),
               'remarks' => $this->request->getVar('remarks'),
               
           ];
        $insertQuery =  $rawMaterialData =  $this->expenseModel->insert($data);
        if(empty($insertQuery)){
            $response = [
                'status' => false,
                'error' => 'ExpenseFail',
                'message' => 'Expense not added into the table and error occured or check all fields and try again!'
            ];
            return $this->respond($response);
        }
        else{
              $payload = [
                'expId' => null,
                'message' => 'Expense created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('expense-channel', 'expense-created', $payload);
            $response = [
                'status' => true,
                'error' => 'null',
                'message' => 'Expense successfully added in the table.'
            ];
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
        $data = $this->expenseModel->find($id);

        if(empty($data)){
           return $this->noExpensesData();
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
        $id = trim($this->request->getVar('id'));
        
        // Check if the ID is valid
        if (!$id || !$this->expenseModel->find($id)) {
            return $this->respond([
                'status' => false,
                'error' => 'invalidId',
                'message' => 'Invalid or missing expense ID.'
            ]);
        }
    
        // Validate input
        if (!($this->request->getMethod() === 'post' && $this->validateCategoryEntries())) {
            return $this->respond([
                'status' => false,
                'error' => 'validationFailed',
                'message' => 'Validation failed. Please check your input and try again.'
            ]);
        }
    
        // Prepare data
        $expenseUpdateData = [
            'category' => $this->request->getVar('category'),
            'description' => $this->request->getVar('description'),
            'amount' => $this->request->getVar('amount'),
            'givenTo' => $this->request->getVar('givenTo'),
            'remarks' => $this->request->getVar('remarks'),
        ];
    
        // Ensure data is not empty before updating
        if (empty(array_filter($expenseUpdateData))) {
            return $this->respond([
                'status' => false,
                'error' => 'emptyData',
                'message' => 'There is no data to update. Please provide at least one field to modify.'
            ]);
        }
    
        // Correct update method call with ID
        if (!$this->expenseModel->update($id, $expenseUpdateData)) {
            return $this->respond([
                'status' => false,
                'error' => 'rawMaterialUpdateFail',
                'message' => 'Fail! Expense update failed. Please try again later.'
            ]);
        }
      $payload = [
                'expId' => $id,
                'message' => 'Expense updated' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('expense-channel', 'expense-updated', $payload);
        // Success response
        return $this->respond([
            'status' => true,
            'error' => null,
            'message' => 'Success!! Expense has been updated'
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
    $id = trim($this->request->getVar('id'));

    // Check if the ID is valid
    if (!$id || !$this->expenseModel->find($id)) {
        return $this->respond([
            'status' => false,
            'error' => 'invalidId',
            'message' => 'Invalid or missing raw material ID.'
        ]);
    }

    // Perform delete operation
    if (!$this->expenseModel->delete($id)) {
        return $this->respond([
            'status' => false,
            'error' => 'deleteFail',
            'message' => 'Fail! Expense has not been deleted. Please try again later.'
        ]);
    }
  $payload = [
                'expId' => $id,
                'message' => 'Expense deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('expense-channel', 'expense-deleted', $payload);
    // Success response
    return $this->respond([
        'status' => true,
        'error' => null,
        'message' => 'Success!! Selected expense has been deleted.'
    ]);
}


    // validate raw material form entries
    private function validateCategoryEntries(){
            return $this->validate([
                'category' => [
                    'rules' => 'required|max_length[20]|min_length[3]|alpha_space|trim|is_unique[categories.categoryName]'
                ]
            ]);
    }
}
