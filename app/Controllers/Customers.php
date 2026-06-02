<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Controllers\Traits\SecuresInput;
use App\Models\CustomerModel;
use App\Services\BranchContextService;

class Customers extends ResourceController
{
    use SecuresInput;
      /** This controller holds the following functions
        = Check presence of data
        = Validation check
        = CRUD a category
        = Fetch category related stock
    **/

    private $customerModel;
    private BranchContextService $branchContext;
    public function __construct(){
        $this->customerModel = new CustomerModel();
        $this->branchContext = service('branchContext');
        helper('pusher'); // Load our custom helper
    }

    // fetch categories data
    public function nocustomerdata(){
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing concerning this category or categories. Follow the right procedures and try again in 10 minutes.'
        ];
        return $this->respond($response);
    }

    // return resource object for validation failure
    public function validationFail()
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];

        $response = [
            'status' => false,
            'error' => 'validationError',
            'message' => empty($errors) ? 'Please review the customer details and try again.' : implode(' ', array_values($errors)),
            'errors' => $errors,
        ];
        return $this->respond($response, 422);
    }

    /**
     * Return an array of resource objects, themselves in array format
     *
     * @return mixed
     */
    public function index()
    {
        $data = $this->branchContext
            ->scopeBuilder($this->customerModel)
            ->findAll();
        if(empty($data)){
            return $this->respond([]);
        }
        else{
            return $this->respond($data);
        }
    }

    /**
     * Create a new resource object, from "posted" parameters
     *
     * @return mixed
     */
    public function create()
    {
         $userId = auth()->id();
         $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branch_id'));
         if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'A branch must be selected first.'], 422);
         }
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond([
                'status' => false,
                'error' => 'requestMethodError',
                'message' => 'Customer details could not be submitted. Please try again.',
            ], 405);
        }

        if (!$this->validateCustomerEntries()) {
            return $this->validationFail();
        }
        // in case form validation is passed
        else{
            $customerData = [
                'custOwner' =>   $userId,
                'branchId' => $branchId,
                'custName' => $this->secureText($this->request->getVar('cust_name'), 200),
                'custContact' => $this->secureText($this->request->getVar('cust_contact'), 200),
                'custEmail' => $this->secureEmail($this->request->getVar('cust_email')) ?? '',
                'custLocation' => $this->secureText($this->request->getVar('cust_location'), 200)
            ];

            $saveCustomerData = $this->customerModel->save($customerData);

            if(!$saveCustomerData){
                $response = [
                    'status' => false,
                    'error' => 'customerFail',
                    'message' => 'Fail! Customer creation failed. Follow the right procedures and try again.'
                ];
                return $this->respond($response);
            }
            // in case customer created successfully
            else{
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Customer added.',
                    'data' => $insertedId = $this->customerModel->insertID()
                   
                ];

                
                 $payload = [
                'customerId' => null,
                'message' => 'customer created' 
            ];

            // Trigger the event via Pusher
            $this->broadcastCustomerEvent('customer-created', $payload);

                return $this->respond($response);
            }
        }
    }

    /**
     * Add or update a model resource, from "posted" properties
     *
     * @return mixed
     */
    public function update($id = null)
    {
        $userId = auth()->id();
        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branch_id'));
        //update fetched category
        $id = trim($this->request->getVar('cust_id'));
        $customer = $this->customerModel->find($id);
        if (!$this->branchContext->recordMatchesCurrentBranch($customer)) {
            return $this->respond(['status' => false, 'message' => 'This customer is outside your current branch scope.'], 403);
        }
        // $data = $this->categoryModel->find($id);&& $this->validateCustomerEntries()

        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond([
                'status' => false,
                'error' => 'requestMethodError',
                'message' => 'Customer details could not be submitted. Please try again.',
            ], 405);
        }

        if (!$this->validateCustomerEntries()) {
            return $this->validationFail();
        }
        // in case form validation fails
        else{
            $customerUpdateData = [
                'custOwner' => $userId,
                'branchId' => $branchId ?? ($customer['branchId'] ?? null),
                'custName' => $this->secureText($this->request->getVar('cust_name'), 200),
                'custContact' => $this->secureText($this->request->getVar('cust_contact'), 200),
                'custEmail' => $this->secureEmail($this->request->getVar('cust_email')) ?? '',
                'custLocation' => $this->secureText($this->request->getVar('cust_location'), 200)
            ];

            $updateCustomerData = $this->customerModel->update($id, $customerUpdateData);

            if(!$updateCustomerData){
                $reponse = [
                    'status' => false,
                    'error' => 'customerUpdateFail',
                    'message' => 'Fail! Customer update failed. Follow the right procedures and try again in 10 minutes.'
                ];
                return $this->respond($reponse);
            }
            // in case category update succeeds
            else{
                $response =  [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success!! Customer has been updated'
                ];

                 $payload = [
                'customerId' => $id,
                'message' => 'customer updated' 
            ];

            // Trigger the event via Pusher
            $this->broadcastCustomerEvent('customer-updated', $payload);

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
        $id = trim($this->request->getVar('cust_id'));
        //delete selected customer
        $data = $this->customerModel->find($id);

        if(empty($data)){
            return $this->nocustomerdata();
        }
        if (!$this->branchContext->recordMatchesCurrentBranch($data)) {
            return $this->respond(['status' => false, 'message' => 'This customer is outside your current branch scope.'], 403);
        }
        // in case a category is found
        else{
            $del_customer = $this->customerModel->delete($id);
            if(!$del_customer){
                $response = [
                    'status' => false,
                    'error' => 'deleteFail',
                    'message' => 'Fail! Customer has not been deleted. Make sure everything is right and try again in 10 minutes.'
                ];
                return $this->respond($response);
            }
            // in case selected category was deleted
            else{
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success!! Selected customer has been deleted.'
                ];

                
                 $payload = [
                'customerId' => $id,
                'message' => 'customer deleted' 
            ];

            // Trigger the event via Pusher
            $this->broadcastCustomerEvent('customer-deleted', $payload);

                return $this->respond($response);
            }
        }
    }

    // validate category form entries
    private function validateCustomerEntries(){
            return $this->validate([
                'branch_id' => [
                    'rules' => 'permit_empty|numeric|greater_than[0]',
                    'label' => 'Branch',
                ],
                'cust_name' => [
                    'rules' => 'required|max_length[200]|min_length[2]|trim',
                    'label' => 'Customer name',
                ],
                'cust_contact' => [
                    'rules' => 'permit_empty|max_length[200]|trim',
                    'label' => 'Customer contact',
                ],
                'cust_email' => [
                    'rules' => 'permit_empty|valid_email|max_length[255]',
                    'label' => 'Customer email',
                ],
                'cust_location' => [
                    'rules' => 'permit_empty|max_length[200]|trim',
                    'label' => 'Customer location',
                ],
            ]);
    }

    private function broadcastCustomerEvent(string $event, array $payload): void
    {
        try {
            get_pusher()->trigger('customer-channel', $event, $payload);
        } catch (\Throwable $e) {
            log_message('error', 'Customer realtime broadcast failed: {message}', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeBranchId($branchId)
    {
        $branchId = trim((string) $branchId);
        return $branchId === '' ? null : (int) $branchId;
    }
}
