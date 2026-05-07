<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\Categories;

class Category extends ResourceController
{
      /** This controller holds the following functions
        = Check presence of data
        = Validation check
        = CRUD a category
        = Fetch category related stock
    **/

    private $categoryModel;
    public function __construct(){
        $this->categoryModel = new Categories();
    }

    // fetch categories data
    public function nocategoriesdata(){
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing concerning this category or categories. Follow the right procedures and try again in 10 minutes.'
        ];
        return $this->respond($response);
        exit();
    }

     // return resource object for validation failure
    public function validationFail(){
        $response = [
            'status' => false,
            'error' => 'validationError',
            'message' => $this->validator->getErrors()
        ];
        return $this->respond($response);
        exit();
    }

    /**
     * Return an array of resource objects, themselves in array format
     *
     * @return mixed
     */
    public function index()
    {
        //fetch categories
        $data = $this->categoryModel->findAll();
        if(empty($data)){
            return [];
        }
        else{
            return $this->respond($data);
            exit();
        }
    }

    /**
     * Return the properties of a resource object
     *
     * @return mixed
     */
    public function showCategory($id = null)
    {
        //single category fetch
        $data = $this->categoryModel->where('categoryId', $id)->first();

        if(empty($data)){
            return $this->nocategoriesdata();
        }
        // in case a category is found
        else{
            return $this->respond($data);
            exit();
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
     *
     * @return mixed
     */
    public function create()
    {
        if ($adminResponse = $this->ensureAdminAccess()) {
            return $adminResponse;
        }

        //add new category
        if(!($this->request->getMethod() === 'post' && $this->validateCategoryEntries('create'))){
            return $this->validationFail();
        }
        // in case form validation is passed
        else{
            $categoryData = [
                'categoryName' => $this->request->getVar('category_name')
            ];

            $saveCategoryData = $this->categoryModel->save($categoryData);

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
        $data = $this->categoryModel->find($id);

        if(empty($data)){
           return $this->nocategoriesdata();
        }
        // in case a category is found
        else{
            return $this->respond($data);
            exit();
        }
    }

    /**
     * Add or update a model resource, from "posted" properties
     *
     * @return mixed
     */
    public function update($id = null)
    {
        if ($adminResponse = $this->ensureAdminAccess()) {
            return $adminResponse;
        }

        //update fetched category
        $id = trim($this->request->getVar('category_id'));
        $data = $this->categoryModel->find($id);

        if (empty($data)) {
            return $this->nocategoriesdata();
        }

        if(!($this->request->getMethod() === 'post' && $this->validateCategoryEntries('update'))){
            return $this->validationFail();
        }
        // in case form validation fails
        else{
            $categoryUpdateData = [
                'categoryName' => $this->request->getVar('category_name')
            ];

            $updateCategoryData = $this->categoryModel->update($id, $categoryUpdateData);

            if(!$updateCategoryData){
                $reponse = [
                    'status' => false,
                    'error' => 'categoryUpdateFail',
                    'message' => 'Fail! Category update failed. Follow the right procedures and try again in 10 minutes.'
                ];
                return $this->respond($reponse);
                exit();
            }
            // in case category update succeeds
            else{
                $response =  [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success!! Category has been updated'
                ];
                return $this->respond($response);
                exit();
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
        if ($adminResponse = $this->ensureAdminAccess()) {
            return $adminResponse;
        }

        $id = $id ?? $this->request->getVar('category_id');
        //delete selected category
        $data = $this->categoryModel->find($id);

        if(empty($data)){
            return $this->nocategoriesdata();
        }
        // in case a category is found
        else{
            $del_category = $this->categoryModel->delete($id);
            if(!$del_category){
                $response = [
                    'status' => false,
                    'error' => 'deleteFail',
                    'message' => 'Fail! Category has not been deleted. Make sure everything is right and try again in 10 minutes.'
                ];
                return $this->respond($response);
                exit();
            }
            // in case selected category was deleted
            else{
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success!! Selected category has been deleted.'
                ];
                return $this->respond($response);
                exit();
            }
        }
    }

    // validate category form entries
    private function validateCategoryEntries(string $mode = 'create'){
        $rules = 'required|max_length[40]|min_length[3]|regex_match[/^[A-Za-z0-9 &\/._-]+$/]|trim';

        if ($mode === 'create') {
            $rules .= '|is_unique[categories.categoryName]';
        } else {
            $categoryId = $this->request->getVar('category_id');
            $rules .= '|is_unique[categories.categoryName,categoryId,' . $categoryId . ']';
        }

        return $this->validate([
            'category_name' => [
                'rules' => $rules,
                'label' => 'Category Name',
            ]
        ]);
    }

    private function ensureAdminAccess()
    {
        $user = auth()->user();
        $roles = $user && method_exists($user, 'getGroups') ? $user->getGroups() : [];

        if (!in_array('admin', $roles, true) && !in_array('superadmin', $roles, true)) {
            return $this->failForbidden('Only administrators can manage categories.');
        }

        return null;
    }
}
