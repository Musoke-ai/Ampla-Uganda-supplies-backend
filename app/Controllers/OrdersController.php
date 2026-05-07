<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\Orders;
use App\Models\CustomerModel;
use App\Models\Inventory;
use App\Services\BranchContextService;

use function PHPUnit\Framework\isEmpty;

class OrdersController extends ResourceController
 {
    /** This controller holds the following functions
    = Check presence of data
    = Validation check
    = CRUD a stock
    = Fetch stock
    **/

    private $orderModel;
    private CustomerModel $customerModel;
    private Inventory $inventoryModel;
    private BranchContextService $branchContext;

    public function __construct() {
        $this->orderModel = new Orders();
        $this->customerModel = new CustomerModel();
        $this->inventoryModel = new Inventory();
        $this->branchContext = service('branchContext');
    }

    // fetch categories data

    public function nostockdata() {
        $response = [
            'status' => false,
            'error' => 'noData',
            'message' => 'There is nothing in the orders table. Follow the right procedures and try again in 10 minutes.'
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
        // //fetch stock
        // $data = [
        //     'orders' => $this->orderModel->findAll()
        // ];
        // if ( empty( $data( 'orders' ) ) ) {
        //     return $this->nostockdata();
        // }
        // else {
        //     return $this->respond( $data );
        //     exit();
        // }
        $orders = $this->branchContext
            ->scopeBuilder($this->orderModel)
            ->findAll();
        if ( empty( $orders ) ) {
            return $this->failNotFound( 'No orders found.' );
        }
        return $this->respond( $orders );
    }

    /**
    * Create a new resource object, from 'posted' parameters
    *
    * @return mixed
    */

    public function create()
 {
        $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId'));
        if ($branchId === null) {
            return $this->respond(['status' => false, 'message' => 'A branch must be selected first.'], 422);
        }
        //add new order
        if ( !( $this->request->getMethod() === 'post' ) ) {
            return $this->validationFail();
        }
        // in case form validation is passed
        else {
            if (!$this->relatedRecordsMatchBranch($branchId)) {
                return $this->respond(['status' => false, 'message' => 'Customer or product does not belong to the selected branch.'], 422);
            }

            $orderData = [
                'branchId' => $branchId,
                'custId' => $this->request->getVar( 'custId' ),
                'prodId' => $this->request->getVar( 'prodId' ),
                'customSize' => $this->request->getVar( 'customSize' ),
                'layers' => $this->request->getVar( 'layers' ),
                'quantity' => $this->request->getVar( 'quantity' ),
                'totalCost' => $this->request->getVar( 'totalCost' ),
                'amountPaid' => $this->request->getVar( 'amountPaid' ),
                'quantityProduced' => $this->request->getVar( 'quantityProduced' ),
                'description' => $this->request->getVar( 'description' ),
            ];

            $saveOrderData = $this->orderModel->save( $orderData );

            if ( !$saveOrderData ) {
                $response = [
                    'status' => false,
                    'error' => 'orderFail',
                    'message' => 'Fail! Order creation failed. Follow the right procedures and try again.'
                ];
                return $this->respond( $response );
            }
            // in case order created successfully
            else {

                 $payload = [
                'OrderId' => null,
                'message' => 'order created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('orders-channel', 'order-created', $payload);

                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success!! Order(s) added'
                ];
                return $this->respond( $response );
            }
        }
    }

    /**
    * Add or update a model resource, from 'posted' properties
    *
    * @return mixed
    */

//     public function update( $id = null )
//  {
//         //update fetched order
//         $id = trim( $this->request->getVar( 'transactionId' ) );
//         $action = trim( $this->request->getVar( 'type' ) );
//         $data = $this->orderModel->find( $id );

//         if ( !( $this->request->getMethod() === 'post' ) && $id ) {
//             return $this->validationFail();
//         }
//         // in case form validation fails
//         else {

//             if ( $action === 'order' ) {
//                 $order = $this->orderModel->find( $id );
//                 if (  $order !== null) {
//                     $amountPaid = $this->request->getVar( 'amountPaid' );
//                     $totalAmountPaid = $order->amountPaid+$amountPaid;
//                     $this->orderModel->set('amountPaid', $totalAmountPaid);
//                     $this->orderModel->where('orderId',$id);
//                     $payOrder = $this->orderModel->update();
//                     if($payOrder){
//                            $reponse = [
//                         'status' => true,
//                         'error' => null,
//                         'message' => 'Order payment successfull'
//                     ];
//                     return $this->respond( $reponse );
//                     }else{
//                            $reponse = [
//                         'status' => false,
//                         'error' => 'orderpayment failed',
//                         'message' => 'Fail! Order payment failed. Follow the right procedures and try again in 10 minutes.',
//                         'data' => $id,
//                         'order' => $order
//                     ];
//                     return $this->respond( $reponse );
//                     }
//                 } else {
//                     $reponse = [
//                         'status' => false,
//                         'error' => 'There is no order to pay',
//                         'message' => 'Fail! Order payment failed. Follow the right procedures and try again in 10 minutes.',
//                         'data' => $id,
//                         'order' => $order
//                     ];
//                     return $this->respond( $reponse );
//                 }

//             }

//             $orderUpdateData = [
//                 'custId' => $this->request->getVar( 'custId' ),
//                 'prodId' => $this->request->getVar( 'prodId' ),
//                 'customSize' => $this->request->getVar( 'customSize' ),
//                 'layers' => $this->request->getVar( 'layers' ),
//                 'quantity' => $this->request->getVar( 'quantity' ),
//                 'totalCost' => $this->request->getVar( 'totalCost' ),
//                 'amountPaid' => $this->request->getVar( 'amountPaid' ),
//                 'quantityProduced' => $this->request->getVar( 'quantityProduced' ),
  //                 'description' => $this->request->getVar( 'description' ),
//             ];

//             $updateOrderData = $this->orderModel->update( $id, $orderUpdateData );

//             if ( !$updateOrderData ) {
//                 $reponse = [
//                     'status' => false,
//                     'error' => 'orderUpdateFail',
//                     'message' => 'Fail! Order update failed. Follow the right procedures and try again in 10 minutes.'
//                 ];
//                 return $this->respond( $reponse );
//             }
//             // in case order update succeeds
//             else {
//                 $response =  [
//                     'status' => true,
//                     'error' => null,
//                     'message' => 'Success!! Order has been updated',
//                     'data' => $updateOrderData
//                 ];
//                 return $this->respond( $response );
//             }
//         }
//     }

/**
 * Updates an existing order record.
 * The specific action is determined by the 'type' parameter in the POST request.
 *
 * @return \CodeIgniter\HTTP\Response
 */
public function update($i = null)
{
    // 1. Guard Clause: Ensure the request method is POST
    if ($this->request->getMethod() !== 'post') {
        return $this->fail('Only POST requests are allowed.', 405); // 405 Method Not Allowed
    }

    // 2. Get and validate common inputs
     
    $action = trim($this->request->getVar('type'));

    if($action !== 'order'){
        $action='details';
         $id = trim( $this->request->getVar( 'orderId' ) );
    }else
    {$id = trim( $this->request->getVar( 'transactionId' ) );}

    if (empty($id) || empty($action)) {
        return $this->fail('Missing required parameters: orderId and type.', 400); // 400 Bad Request
    }

    // 3. Find the order first. If it doesn't exist, no action can be taken.
    $order = $this->orderModel->find($id);
    if (!$order) {
        return $this->failNotFound('Order not found with ID: ' . $id); // 404 Not Found
    }
    if (!$this->branchContext->recordMatchesCurrentBranch($order)) {
        return $this->fail('This order is outside your current branch scope.', 403);
    }

    // 4. Use a switch statement to handle different actions
    switch ($action) {
        case 'order':
            return $this->handlePaymentUpdate($order);

        case 'details':
            return $this->handleDetailsUpdate($order);

        default:
            return $this->fail('Invalid action type specified.', 400);
    }
}

/**
 * Handles the logic for updating an order's payment.
 *
 * @param object $order The existing order object from the database.
 * @return \CodeIgniter\HTTP\Response
 */
private function handlePaymentUpdate($order)
{
    // Check 1: See if the order is already fully paid
    if ((float)$order['amountPaid'] >= (float)$order['totalCost']) {
        return $this->fail('This order is already fully paid.', 409); // 409 Conflict
    }

    $incomingPayment = $this->request->getVar('amountPaid');

    // Check 2: Validate the incoming payment amount
    if (!is_numeric($incomingPayment) || (float)$incomingPayment <= 0) {
        return $this->fail('Invalid payment amount. It must be a positive number.', 400);
    }

    // Calculate the outstanding balance
    $balance = (float)$order['totalCost'] - (float)$order['amountPaid'];

    // Check 3: Ensure payment does not exceed the balance
    if ((float)$incomingPayment > $balance) {
        $formattedBalance = number_format($balance, 2);
        return $this->fail("Payment exceeds the outstanding balance. The remaining balance is {$formattedBalance}.", 400);
    }

    // Calculate the new total and prepare data for update
    $newTotalPaid = (float)$order['amountPaid'] + (float)$incomingPayment;
    $updateData = ['amountPaid' => $newTotalPaid];

    // Perform the update
    if ($this->orderModel->update($order['orderId'], $updateData)) {
        $response = [
            'status'  => true,
            'message' => 'Order payment successful.',
            'data'    => ['orderId' => $order['orderId'], 'newAmountPaid' => $newTotalPaid]
        ];
        
                 $payload = [
                'OrderId' => $order['orderId'],
                'message' => 'order payment' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('orders-channel', 'order-payment', $payload);
        return $this->respond($response);
    } else {
        return $this->fail('Failed to update order payment.', 500);
    }
}

/**
 * Handles the logic for updating an order's details.
 *
 * @param object $order The existing order object from the database.
 * @return \CodeIgniter\HTTP\Response
 */
private function handleDetailsUpdate($order)
{
    $branchId = $this->branchContext->resolveWritableBranchId($this->request->getVar('branchId')) ?? ($order['branchId'] ?? null);
    if ($branchId === null) {
        return $this->fail('A branch must be selected first.', 422);
    }
    if (!$this->relatedRecordsMatchBranch($branchId)) {
        return $this->fail('Customer or product does not belong to the selected branch.', 422);
    }

    // NOTE: Using CodeIgniter's Validation library here is highly recommended for production.
    $data = [
        'branchId'         => $branchId,
        'custId'           => $this->request->getVar('custId'),
        'prodId'           => $this->request->getVar('prodId'),
        'customSize'       => $this->request->getVar('customSize'),
        'layers'           => $this->request->getVar('layers'),
        'quantity'         => $this->request->getVar('quantity'),
        'totalCost'        => $this->request->getVar('totalCost'),
        'amountPaid'       => $this->request->getVar('amountPaid'),
        'quantityProduced' => $this->request->getVar('quantityProduced'),
        'description'      =>      $this->request->getVar( 'description' ),
    ];

    // Filter out any null values so you don't overwrite existing data with nothing.
    $updateData = array_filter($data, function ($value) {
        return $value !== null;
    });

    if (empty($updateData)) {
        return $this->fail('No valid update data provided.', 400);
    }

    // Perform the update
    if ($this->orderModel->update($order['orderId'], $updateData)) {
        $response = [
            'status'  => true,
            'message' => 'Order has been updated successfully.',
            'data'    => ['orderId' => $order['orderId']]
        ];
         $payload = [
                'OrderId' => $order['orderId'],
                'message' => 'order update' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('orders-channel', 'order-updated', $payload);
        return $this->respondUpdated($response);
    } else {
        return $this->fail('Failed to update order details.', 500);
    }
}

private function normalizeBranchId($branchId)
{
    $branchId = trim((string) $branchId);
    return $branchId === '' ? null : (int) $branchId;
}

    /**
    * Delete the designated resource object from the model
    *
    * @return mixed
    */

    public function delete( $id = null )
 {
        $id = $this->request->getVar( 'orderId' );
        //delete selected category
        $data = $this->orderModel->find( $id );

        if ( empty( $data ) ) {
            return $this->nostockdata();
        }
        if (!$this->branchContext->recordMatchesCurrentBranch($data)) {
            return $this->respond(['status' => false, 'message' => 'This order is outside your current branch scope.'], 403);
        }
        // in case an order is found
        else {
            $del_order = $this->orderModel->delete( $id );
            if ( !$del_order ) {
                $response = [
                    'status' => false,
                    'error' => 'deleteFail',
                    'message' => 'Fail! Order has not been deleted. Make sure everything is right and try again in 10 minutes.'
                ];
                return $this->respond( $response );
            }
            // in case selected category was deleted
            else {
                   $payload = [
                'OrderId' => $id,
                'message' => 'order deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('orders-channel', 'order-deleted', $payload);
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success!! Selected order has been deleted.'
                ];
                return $this->respond( $response );
            }
        }
    }

    // validate category form entries

    private function validateCategoryEntries() {
        // return $this->validate( [
        //     'category_name' => [
        //         'rules' => 'required|max_length[20]|min_length[3]|alpha_space|trim|is_unique[categories.categoryName]'
        // ]
        // ] );
    }

    private function relatedRecordsMatchBranch(int $branchId): bool
    {
        $custId = $this->request->getVar('custId');
        $prodId = $this->request->getVar('prodId');

        if ($custId) {
            $customer = $this->customerModel->find($custId);
            if (!$customer || (int) ($customer['branchId'] ?? 0) !== $branchId) {
                return false;
            }
        }

        if ($prodId) {
            $product = $this->inventoryModel->find($prodId);
            if (!$product || (int) ($product['branchId'] ?? 0) !== $branchId) {
                return false;
            }
        }

        return true;
    }
}
