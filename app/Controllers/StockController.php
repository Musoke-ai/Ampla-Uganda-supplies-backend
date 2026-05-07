<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\Stock;

class StockController extends ResourceController
{
      /** This controller holds the following functions
        = Check presence of data
        = Validation check
        = CRUD a stock
        = Fetch stock
    **/

    private $stockModel;
    public function __construct(){
        $this->stockModel = new Stock();
    }

    // fetch categories data
    public function nostockdata(){
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing in the stock table. Add new stock and try again.'
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
        $stockData =  $this->stockModel->findAll();
        if(empty($stockData)){
            return $this->nostockdata();
        }
        else{
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Success!! items have been fetched to your front end.' 
            ];
            return $this->respond($stockData);
        }
    }

    /**
     * Return the properties of a resource object
     *
     * @return mixed
     */
    public function addStock()
    {
        $data = [];
        $oldStock = 3;
        if($this->request->getMethod() === 'post'){
               $data = [
               'branchId' => $this->normalizeBranchId($this->request->getVar('branchId')),
               'stockItem' => $this->request->getVar('stockItem'),
               'stockItemQuantity' => $this->request->getVar('stockItemQuantity'),
               'oldStock' => $this->request->getVar('oldStock'),         
           ];
        $insertQuery =  $stockData =  $this->stockModel->insert($data);
        if(empty($insertQuery)){
            $response = [
                'status' => false,
                'error' => 'StockItemFail',
                'message' => 'Item not added in the stock and error occured or check all fields and try again!'
            ];
            return $this->respond($response);
        }
        else{
             $payload = [
                'stockId' => null,
                'message' => 'Stock created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('stock-channel', 'stock-created', $payload);
            $response = [
                'status' => true,
                'error' => 'null',
                'message' => 'Item(s) successfully added in the stock. 3'
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
     * Return a new resource object, with default properties
     *
     * @return mixed
     */
    public function new()
    {
        //
    }

    /**
     * Create a new resource object, from "posted" parameters
     *qqqqqqsssss
     * @return mixed
     */
    public function create()
    {
        //add new category
        if(!($this->request->getMethod() === 'post' && $this->validateCategoryEntries())){
            return $this->validationFail();
        }
        // in case form validation is passed
        else{
            $categoryData = [
                'categoryName' => $this->request->getVar('category_name')
            ];

            $saveCategoryData = $this->stockModel->save($categoryData);

            if(!$saveCategoryData){
                $response = [
                    'status' => false,
                    'error' => 'categoryFail',
                    'message' => 'Fail! Category creation failed. Follow the right procedures and try again.'
                ];
                return $this->respond($response);
                exit();
            }
            // in case category created successfully
            else{
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success!! Category was created, items can now be added to it' 
                ];
                return $this->respond($response);
                exit();
            }
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
        $data = $this->stockModel->find($id);

        if(empty($data)){
           return $this->nostockdata();
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
        //update fetched category
        $id = trim($this->request->getVar('category_id'));
        $data = $this->stockModel->find($id);

        if(!($this->request->getMethod() === 'post' && $this->validateCategoryEntries())){
            return $this->validationFail();
        }
        // in case form validation fails
        else{
            $categoryUpdateData = [
                'categoryName' => $this->request->getVar('category_name')
            ];

            $updateCategoryData = $this->stockModel->update($categoryUpdateData);

            if(!$updateCategoryData){
                $response = [
                    'status' => false,
                    'error' => 'categoryUpdateFail',
                    'message' => 'Fail! Category update failed. Follow the right procedures and try again in 10 minutes.'
                ];
                return $this->respond($response);
            }
            // in case category update succeeds
            else{
                       $payload = [
                'stockId' => null,
                'message' => 'Stock updated' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('stock-channel', 'stock-updated', $payload);
                $response =  [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success!! Stock has been updated'
                ];
                return $this->respond($response);
            }
        }
    }

    /**
     * Delete the designated resource object from the model
     *
     * @return mixed
     */
    public function delete($id = null)
    {
        //delete selected category
        $data = $this->stockModel->find($id);

        if(empty($data)){
            return $this->nostockdata();
        }
        // in case a category is found
        else{
            $del_category = $this->stockModel->update($id);
            if(!$del_category){
                $response = [
                    'status' => false,
                    'error' => 'deleteFail',
                    'message' => 'Fail! Category has not been deleted. Make sure everything is right and try again in 10 minutes.'
                ];
                return $this->respond($response);
            }
            // in case selected category was deleted
            else{
                       $payload = [
                'stockId' => null,
                'message' => 'Stock deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('stock-channel', 'stock-deleted', $payload);
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success!! Selected stock has been deleted.'
                ];
                return $this->respond($response);
            }
        }
    }

    // validate category form entries
    private function validateCategoryEntries(){
            return $this->validate([
                'category_name' => [
                    'rules' => 'required|max_length[20]|min_length[3]|alpha_space|trim|is_unique[categories.categoryName]'
                ]
            ]);
    }

    private function normalizeBranchId($branchId)
    {
        $branchId = trim((string) $branchId);
        return $branchId === '' ? null : (int) $branchId;
    }
}
