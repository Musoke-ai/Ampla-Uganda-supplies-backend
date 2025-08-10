<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\Inventory;
use App\Models\Statistics;
use App\Models\Business;
use App\Models\History;
use App\Models\Sales;
use App\Models\Indebt;
use App\Models\DebtTrack;
use App\Models\Stock;
use App\Models\Receipt;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;

class Entries extends ResourceController
{
    /** This controller will hold the following functions
     * Check presence of administrators' data
     * Validation check
     * CRUD stock
     * Sales recording
     * Indebt recording
     * Overall statistics recording
     * In the addition to the update of stock funcns, itemBuyPrice and itemSalePrice have been merged to make itemLeastPrice(amount an owner can sell an item at the minimum)
     **/

    private $inventoryModel;
    private $statisticsModel;
    private $businessModel;
    private $historyModel;
    private $salesModel;
    private $indebtModel;
    private $debtTrackModel;
    private $stockModel;
    private $receiptModel;


    public function __construct()
    {
        $this->inventoryModel = new Inventory();
        $this->statisticsModel = new Statistics();
        $this->businessModel = new Business();
        $this->historyModel = new History();
        $this->salesModel = new Sales();
        $this->indebtModel = new Indebt();
        $this->debtTrackModel = new DebtTrack();
        $this->stockModel = new Stock();
        $this->receiptModel = new Receipt();
    }

    private function nostockdata()
    {
        //check presence of admin data
        $response = [
            'status' => false,
            'error' => 'no data',
            'message' => 'We have no stock data here. Make sure everything is right and try again in 10 minutes.'
        ];
        return $this->respond($response);
    }

    // return resource object for validation failure
    public function validationFail()
    {
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
        //home page
        $data = [
            'title' => 'HSMS',
            'From' => 'Lwetutte Steven',
            'To' => 'Musoke Hamzah',
            'Message' => 'Hello Mr. Musoke, this is how our API based CodeIgniter returns data, hope it is the one you expected.'
        ];
        if (empty($data)) {
            return $this->nostockdata();
        } else {
            return $this->respond($data);
        }
    }

    //Enter new stock quantites
    public function addStock()
    {
        $userId = auth()->id();
        // $data = [];
        if ($this->request->getMethod() === 'post') {
            // $stockItems = $this->request->getVar('stockItems');
            $stockItems = $this->request->getVar('stockItems');
               if (empty($stockItems)) {
                $response = [
                    'status' => false,
                    'error' => 'Stock Items List Empty',
                    'message' => 'Stock Items list is empty add an item or items and try again!'
                ];
                return $this->respond($response);
            }else{

                $batchData = [];
                $batchUpdateData = [];

               foreach ($stockItems as $item) {
                
      $batchData[] = [
        'stockOwner'        => $userId,
        'stockItem'         => $item->stockItem,
        'oldStock'          => $item->oldStock,
        'stockItemQuantity' => $item->stockItemQuantity,
        'stockItemPrice'    => $item->itemStockPrice,
        'itemSellingPrice'  => $item->itemLeastPrice,
        'itemSupplier'      => 'none',
    ];
        $batchUpdateData[] = [
        'itemId'         => $item->stockItem,
        'itemQuantity' => (int)$item->stockItemQuantity + (int)$item->oldStock,
    ];
               }


               // Insert all at once
$insertBatchQuery = $this->stockModel->insertBatch($batchData);
 if (empty($insertBatchQuery)) {
                $response = [
                    'status' => false,
                    'error' => 'StockItemFail',
                    'message' => 'Item not added in the stock and error occured or check all fields and try again!'
                ];
                return $this->respond($response);
            } else {
                // $item = $this->inventoryModel->find($this->request->getVar('stockItem'));
                // $newQuantity =  $item['itemQuantity'] + $this->request->getVar('stockItemQuantity');
                // $this->inventoryModel->set('itemQuantity', $newQuantity);
                // $this->inventoryModel->where('itemId', $this->request->getVar('stockItem'));
                // Update all rows where `id` matches
                $updateInventoryItems = $this->inventoryModel->updateBatch($batchUpdateData, 'itemId');
                // $updateInventoryItem = $this->inventoryModel->update();
                if ($updateInventoryItems) {
                      $payload = [
                'stockId' => null,
                'message' => 'Stock added' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'stock-added', $payload);
                    $response = [
                        'status' => true,
                        'error' => 'null',
                        'itemsUpdated' => $updateInventoryItems,
                        'iems2' => $batchUpdateData,
                        'items' => $batchData,
                        'stock' => $stockItems,
                        'message' => 'Item(s) successfully added in the stock.'
                    ];
                    // $this->recordStat($this->request->getVar('stockItem'), 'addStock', NULL);
                    return $this->respond($response);
                } else {
                    $response = [
                        'status' => false,
                        'error' => 'null',
                        'message' => 'Inventory not updated.'
                    ];
                    return $this->respond($response);
                    // exit();
                }
            }

               }
            }
        else {
            $response = [
                'status' => false,
                'error' => 'RequestMethodError',
                'message' => 'The request method is not post set it to post and try again.'
            ];
            return $this->respond($response);
        }
    }

    /**
     * Create a new resource object, from "posted" parameters
     *
     * @return mixed
     */
    //&& $this->validateStockEntries('stockentry')
    public function createStock()
    {
        $userId = auth()->id();
        //handle & submit stock form entries
        if ($this->request->getMethod() === 'post') {
            $stockData = [
                'itemName' => $this->request->getVar('item_name'),
                'itemCategoryId' => $this->request->getVar('item_category'),
                'itemModel' => $this->request->getVar('item_model'),
                'itemQuality' => $this->request->getVar('item_quality'),
                'itemQuantity' => $this->request->getVar('item_quantity'),
                'itemCondition' => $this->request->getVar('item_condition'),
                'itemSize' => $this->request->getVar('item_size'),
                'itemStockPrice' => $this->request->getVar('item_stock_price'),
                'itemLeastPrice' => $this->request->getVar('item_min_price'),
                'itemNotes' => $this->request->getVar('item_notes'),
                'itemOwner' =>  $userId
            ];

            $saveStock = $this->inventoryModel->save($stockData);

            // is stock data saved?? 
            if ($saveStock) {
                $data = $this->inventoryModel->where('itemOwner', $userId)->orderBy('itemId', 'DESC')->find();
                $item_id = $data[0]['itemId'];
                $historyData = [
                    'historyItemId' => $item_id,
                    'busId' => $userId,
                    'historyAction' => 'New item added',
                    'historyDetails' => $this->request->getVar('item_quantity') . " items",
                ];

                $saveHistoryData = $this->historyModel->save($historyData);
     $payload = [
                'stockId' => null,
                'message' => 'Stock created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'stock-created', $payload);
                $response = [
                    'status' => true,
                    'error' => null,
                    'Message' => 'Success!, a new item has been added to existing stock'
                ];
                $this->recordStat(NULL, 'createStock', NULL);
                // stock entry history handling
                if (!$saveHistoryData) {
                    $response = [
                        'status' => false,
                        'error' => 'historyUploadFailed',
                        'Messages' => 'Fail!! New item creation was successful, but your action history was not recorded. But this is OK though you should report it.'
                    ];

                    return $this->respond($response);
                }
                // in case upload & it's history were recorded ok 
                else {
                    $response = [
                        'status' => true,
                        'error' => null,
                        'Messages' => 'Action history has been recorded successfully.'
                    ];
                    return $this->respond($response);
                }

                // return $this->respond($response);
            } else {
                $response = [
                    'status' => false,
                    'error' => 'itemUploadFailed',
                    'Messages' => 'Fail!! New item creation could not be fully processed, check your form entries and try again, please'
                ];
                return $this->respond($response);
            }
        } else {
            return $this->validationFail();
        }
    }

    /**
     * Return the editable properties of a resource object
     *
     * @return mixed
     */
    public function edit($id = null)
    {
        //fetch item to edit
        $data = $this->inventoryModel->find($id);

        if ($data) {
            return $this->respond($data);
        } else {
            return $this->nostockdata();
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
        //update selected item
        $id = $this->request->getVar('itemId');
        $data = $this->inventoryModel->find($id);

        if (empty($data)) {
            // return $this->nostockdata();
            return $this->respond($id);
        }
        // in case data to update is available //&& $this->validateStockEntries('updateitem')
        else {
            if ($this->request->getMethod() === 'post' && $this->validateStockEntries('updateitem')) {
                $updateStock = [];
                $stockDataUpdate = [
                    'itemName' => $this->request->getVar('item_name'),
                    'itemCategoryId' => $this->request->getVar('item_category'),
                    'itemModel' => $this->request->getVar('item_model'),
                    'itemQuality' => $this->request->getVar('item_quality'),
                    'itemQuantity' => $this->request->getVar('item_quantity'),
                    'itemCondition' => $this->request->getVar('item_condition'),
                    'itemSize' => $this->request->getVar('item_size'),
                    'itemStockPrice' => $this->request->getVar('item_stock_price'),
                    'itemLeastPrice' => $this->request->getVar('item_min_price'),
                    'itemNotes' => $this->request->getVar('item_notes'),
                    'itemOwner' => $userId,
                ];

                $historyData = [
                    'historyItemId' => $id,
                    'historyAction' => 'Updated an item',
                    'historyDetails' => ''
                ];

                $updateStock = $this->inventoryModel->update($id, $stockDataUpdate);

                if ($updateStock) {
                    $saveHistoryData = $this->historyModel->save($historyData);

                    // stock entry history handling
                    if (!$saveHistoryData) {
                        $response = [
                            'status' => false,
                            'error' => 'historyUpdateFailed',
                            'Message' => 'Fail!! Item update was successful, but your action history was not recorded. But this is OK though you should report it and get it fixed.'
                        ];
                        return $this->respond($response);
                    }
                    // in case history was recorded ok 
                    else {
                             $payload = [
                'itemId' => $id,
                'message' => 'Item updated' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'item-updated', $payload);
                        $response = [
                            'status' => true,
                            'error' => null,
                            'Message' => 'Item has been updated.'
                        ];
                        return $this->respond($response);
                        // exit();
                    }
                    $this->recordStat($id, 'update', NULL);
                    exit();
                }
                // in case item update fails
                else {
                    $response = [
                        'status' => false,
                        'error' => 'updateFail',
                        'message' => 'We could not update this item, make sure everything is right and try again',
                        'Data'    =>  $stockDataUpdate
                    ];
                    return $this->respond($response);
                }
            }
            // in case form validation fails 
            else {
                return $this->validationFail();
            }
        }
    }

    /**
     * Insert a sale into the sales table
     *
     * @return mixed
     */
    public function saleStock($items = null)
    {
        $userId = auth()->id();
        if ($this->request->getMethod() === 'post' && $this->validateStockEntries('sellitem')) {
            return $this->validationFail();
        } else {

            //   $items = json_decode($items,true);//true is passed to make a real php array not an object
            $items = $this->request->getVar('saleItems'); //true is passed to make a real php array not an object
            $saleDetails = $this->request->getVar('saleDetails'); //true is passed to make a real php array not an object
            $limit = sizeof($items);
            //Check wether there is items to sell or not
            if (empty($items)) {
                $response = [
                    'status' => false,
                    'error' => 'ItemsListEmpty',
                    'message' => 'Items list is empty add an item or items to make a complete transaction'
                ];
                return $this->respond($response);
            } else {

                $saleData = $items;
                $items_sold = []; //To store all ids of the sold item for history store
                $updateItems = [];
                $saveSaleData = $this->salesModel->insertBatch($saleData);
                if (empty($saveSaleData)) {
                    $response = [
                        'status' => false,
                        'error' => 'SalesTransactionFailed',
                        'message' => 'Sales Transaction Failed'
                    ];
                    return $this->respond($response);
                } else {
                    //Create and Record sale transactionId/timeStamp in the receipt table
                    $timeStamp = uniqid('RS-', true);
                    $rcptdata = [
                        'timeStamp' => $timeStamp,
                        'discount' => $saleDetails->discount,
                        'dueAmount' => $saleDetails->dueAmount,
                        'moreInfo' => $saleDetails->moreInfo,
                        'paymentMethod' => $saleDetails->paymentMethod,
                        'amountPaid' => $saleDetails->tenderedAmount,
                    ];

                    // Reflect back / update quantity changes after sales in the stock table
                    // $updateStock = $this->inventoryModel->updateBatch($saleData, 'itemId');
                    // if($updateStock){
                    $this->recordStat(NULL, 'saleStock', $limit);
                    //Convert the payload properly into an array for proper iteration
                    $items = json_encode($items); //payload to json string
                    $items = json_decode($items, true); //payload to an array
                    $itemSize = sizeof($items);
                    //find the last sizeof($items) from the sales table
                    $getSaleIds = $this->salesModel->orderBy('saleDateCreated', 'desc')->findAll($itemSize);
                    
                    $receiptNo = $this->receiptModel->insert($rcptdata, true);
                    //record dues to indebt table for this sale
                    if($saleDetails->dueAmount > 0){
  $indebtData = [
                // 'indebtItemId' => $this->request->getVar('indebtItemId'),
                'indebtOwner' =>  $userId,
                // 'quantityDebted' => $this->request->getVar('quantityDebted'),
                'totalAmount' =>    $saleDetails->total,
                'initialDeposit' => $saleDetails->tenderedAmount,
                'endDate' => $saleDetails->endDate,
                'custId' => $saleDetails->custId,
                'SR_ID' =>  $receiptNo
            ];
             $saveIndebtData = $this->indebtModel->save($indebtData);
                    }
                    //Attach receiptNumber to each sale
                    foreach ($getSaleIds as $sale => $key) {
                        $this->salesModel->set('SR_ID', $receiptNo);
                        $this->salesModel->where('saleId', $key['saleId']);
                        $this->salesModel->update();
                    };
                    //Update item quantity in the inventory table
                    foreach ($getSaleIds as $sale => $key) {
                        $item = $this->inventoryModel->find($key['saleItemId']);
                        $itemQty = $item['itemQuantity'] - $key['saleQuantity'];
                        $this->inventoryModel->set('itemQuantity', $itemQty);
                        $this->inventoryModel->where('itemId', $key['saleItemId']);
                        $this->inventoryModel->update();
                    }

                    //Get all item ids into the history for save
                    foreach ($items as $item => $key) {
                        foreach ($getSaleIds as $sale => $saleKey) {

                            if ($key['saleItemId'] == $saleKey['saleItemId']) {
                                $history = [
                                    'historyItemId' => $key['saleItemId'],
                                    'busId'         => $userId,
                                    'historyAction' => $items[0]['saleQuantity'].' Item(s) sold',
                                    'historyDetails' => $items[0]['custId']
                                ];
                                array_push($items_sold, $history);
                            }
                        }
                    }
                    $saveHistoryData = $this->historyModel->insertBatch($items_sold);
                    if ($saveHistoryData) {
                             $payload = [
                'saleId' => null,
                'message' => 'Sale created' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'sale-created', $payload);
                        $response = [
                            'status' => true,
                            'error' => 'null',
                            'receiptNumber' => $receiptNo,
                            'message' => 'transaction completed successfully'
                        ];
                        return $this->respond($response);
                    } else {
                        $response = [
                            'status' => false,
                            'error' => 'History Save error',
                            'message' => 'Sales history not saved, you need to report this error immediately to our team!'
                        ];
                        return $this->respond($response);
                    }
                    // }        
                }
            }
        }
    }

    public function updateSales() {
        $userId = auth()->id();
        $receiptNumber = $this->request->getVar('SR_ID');
        $this->salesModel->where('SR_ID', $receiptNumber);
        $this->salesModel->where('saleOwner', $userId);
        $sales = $this->salesModel->findAll();

        //Update item quantity in the inventory table
        foreach ($sales as $sale => $key) {
            $item = $this->inventoryModel->find($key['saleItemId']);
            $itemQty = $item['itemQuantity'] + $key['saleQuantity'];
            $this->inventoryModel->set('itemQuantity', $itemQty);
            $this->inventoryModel->where('itemId', $key['saleItemId']);
            $this->inventoryModel->update();
        }

        //Finally delete the sales from the sales table
        $this->salesModel->where('saleOwner', $userId);
        $this->salesModel->where('SR_ID', $receiptNumber);
        $delete = $this->salesModel->delete();
        print_r($delete);
        if ($delete) {
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Sales deleted successfully'
            ];
            return $this->respond($response);
        } else {
            $response = [
                'status' => false,
                'error' => 'Sale deletion error',
                'message' => 'Sales deletion not , you need to report this error imediatetly to our team!'
            ];
            return $this->respond($response);
        }
    }

    public function cancelSale()
    {
        $userId = auth()->id();
        $receiptNumber = $this->request->getVar('SR_ID');
        $this->salesModel->where('SR_ID', $receiptNumber);
        $this->salesModel->where('saleOwner', $userId);
        $sales = $this->salesModel->findAll();

        //Update item quantity in the inventory table
        foreach ($sales as $sale => $key) {
            $item = $this->inventoryModel->find($key['saleItemId']);
            $itemQty = $item['itemQuantity'] + $key['saleQuantity'];
            $this->inventoryModel->set('itemQuantity', $itemQty);
            $this->inventoryModel->where('itemId', $key['saleItemId']);
            $this->inventoryModel->update();
        }

        //Finally delete the sales from the sales table
        $this->salesModel->where('saleOwner', $userId);
        $this->salesModel->where('SR_ID', $receiptNumber);
        $delete = $this->salesModel->delete();
        print_r($delete);
        if ($delete) {
                 $payload = [
                'saleId' => $receiptNumber,
                'message' => 'Sale deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'sale-deleted', $payload);
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Sales deleted successfully'
            ];
            return $this->respond($response);
        } else {
            $response = [
                'status' => false,
                'error' => 'Sale deletion error',
                'message' => 'Sales deletion not , you need to report this error imediatetly to our team!'
            ];
            return $this->respond($response);
        }
    }

    /**
     * Update a record in the sales table
     *
     * @return mixed
     */
    public function createDebt($debtData=null)
    {
        $userId = auth()->id();
        $dbReceipt = null;
        $indebtData = [];
        if ($this->request->getMethod() === 'post') {
            //Create and Record debt transactionId/timeStamp in the receipt table
            $timeStamp = uniqid('DBT-', true);
            $rcptdata = [
                'timeStamp' => $timeStamp
            ];
            $receiptNo = $this->receiptModel->insert($rcptdata, true);

            if($receiptNo){
                $dbReceipt = $receiptNo;
            }
            else{
                $response = [
                    'status' => false,
                    'error' => 'RecieptInssertionError',
                    'message' => 'TID inssertion failed'
                ];

                return $response;
            }
            // if($debtData === null){
   $indebtData = [
                'indebtItemId' => $this->request->getVar('indebtItemId'),
                'indebtOwner' =>  $userId,
                'quantityDebted' => $this->request->getVar('quantityDebted'),
                'atPrice' =>    $this->request->getVar('atPrice'),
                'initialDeposit' => $this->request->getVar('initialDeposit'),
                'totalAmount' => $this->request->getVar('totalAmount'),
                'endDate' => $this->request->getVar('endDate'),
                'custId' => $this->request->getVar('custId'),
                'SR_ID' =>  $dbReceipt
            ];
            // }
            // else{
            //     $indebtData = $debtData;
            // }

            $saveIndebtData = $this->indebtModel->save($indebtData);
            if ($saveIndebtData) {
                //update item Quantity in the inventory table
                $item = $this->inventoryModel->find($this->request->getVar('indebtItemId'));
                $itemQty = $item['itemQuantity'] -  $this->request->getVar('quantityDebted');
                $this->inventoryModel->set('itemQuantity', $itemQty);
                $this->inventoryModel->where('itemId', $this->request->getVar('indebtItemId'));
                $this->inventoryModel->update();
                $response = [
                    'status' => true,
                    'error' => 'null',
                    'message' => 'Your item(s) have been added to your debts. To setup your alerts and client SMS notifications, please visit the Alerts and Notification panel...!'
                ];
                $this->recordStat($this->request->getVar('indebtItemId'), 'addDebt', NULL);
                return $this->respond($response);
            } else {
                $response = [
                    'status' => false,
                    'error' => 'debtNotAdded',
                    'message' => 'Fail! We could not process your debt order now. Check all entries and try again!'
                ];
                return $this->respond($response);
            }
        }
    }

    // Handle debt payments

    public function payDebt()
    {
        $userId = auth()->id();
        $debtId = $this->request->getVar('transactionId');
        $newPay = $this->request->getVar('amountPaid');
        $data = [
            'debtId' => $this->request->getVar('transactionId'),
            'indebtOwner' => $userId,
            'amountPaid' => $this->request->getVar('amountPaid'),
        ];

        $debt = $this->indebtModel->find($debtId);
        $oldPay = $debt['initialDeposit'];
        $totalPay = $debt['totalAmount'];
        $saleItemId =  $debt['indebtItemId'];
        $saleQty = $debt['quantityDebted'];
        $custId = $debt['custId'];
        $updatedPay = $oldPay + $newPay;

        $saleData = [
            'saleItemId' => $saleItemId,
            'saleOwner' =>  $userId,
            'salePrice' => $totalPay,
            'saleQuantity' => $saleQty,
            'custId' => $custId,
        ];

        if ($updatedPay <= $totalPay) {
            $this->indebtModel->Set('initialDeposit', $updatedPay);
            $this->indebtModel->where('indebtId',  $debtId);
            $paymentUpdate = $this->indebtModel->update();
            if ($paymentUpdate) {
                $paid = $this->debtTrackModel->save($data);

                if ($paid) {
                    //Save the debt as a sale if the payment is fullfilled
                    $ispaid = false;
                    if ($totalPay == $updatedPay) {
                        $saveSaleData = $this->salesModel->save($saleData);
                        $ispaid = true;
                        if ($saveSaleData) {
                            $this->recordStat(NULL, 'saleStock', 1);
                        }
                    }
                    $response = [
                        'status' => true,
                        'error' => 'null',
                        'message' => 'Payment fullfilled...',
                        'isPaidFully' => $ispaid
                    ];
                    return $this->respond($response);
                } else {
                    $response = [
                        'status' => false,
                        'error' => 'PaymentFailed',
                        'message' => 'Fail! We could not process your payment order now. try again...!'
                    ];
                    return $this->respond($response);
                }
            } else {
                $response = [
                    'status' => false,
                    'error' => 'OverPayment',
                    'message' => 'Aready Paid or cash paid exceeds the amount to be paid!'
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
        //delete stock item
        $data = $this->inventoryModel->find($id);

        if ($data) {
            $del_stock_item = $this->inventoryModel->delete($id);

            $historyData = [
                'historyItemId' => $id,
                'historyAction' => 'Deleted an item',
                'historyDetails' => ''
            ];

            if ($del_stock_item) {
                // $saveHistoryData = $this->historyModel->save($historyData);

                // if(!$saveHistoryData){
                //     $response = [
                //         'status' => false,
                //         'error' => 'historyDeleteFailed',
                //         'Messages' => 'Fail!! Your action history was not recorded. But this is OK though you should report it.'
                //     ];
                //     return $this->respond($response);
                //     exit();
                // }
                // in case upload & it's history were recorded ok 
                // else{

                $payload = [
                'saleId' => $id,
                'message' => 'Item deleted' 
            ];

            // Trigger the event via Pusher
            $pusher = get_pusher();
            $pusher->trigger('entries-channel', 'item-deleted', $payload);
                
                $response = [
                    'status' => true,
                    'error' => null,
                    // 'Messages' => 'Action history has been recorded successfully.'
                    'Messages' => 'Your Item has been deleted successfully!.'
                ];
                return $this->respond($response);
                // }    
            } else {
                $response = [
                    'status' => false,
                    'error' => 'deleteFail',
                    'message' => 'Item selected could not be deleted, try again in 10 minutes'
                ];
                return $this->respond($response);
            }
        }
        // in case there is no matching item
        else {
            return $this->nostockdata();
        }
    }

    /**
     * Add the designated resource object as a stat to the model
     *
     * @return mixed
     */

    private function recordStat($item_id = NULL, $action = NULL, $limit = NULL)
    {
        $userId = auth()->id();
        $itemStockWorth = 0;
        $itemQty = 0;
        $statId = null;
        if ($item_id === NULL && ($action === 'update' || $action === 'createStock')) {
            if ($item_id === Null) {
                $this->inventoryModel->orderBy('itemId', 'DESC');
                $fetchedData = $this->inventoryModel->findAll(1);
                $item_id = $fetchedData[0]['itemId'];
                $itemStockWorth = $fetchedData[0]['itemQuantity'] * $fetchedData[0]['itemStockPrice'];
                $itemQty = $fetchedData[0]['itemQuantity'];
            } else {
                $fetchedData = $this->inventoryModel->find($item_id);
                $itemStockWorth = $fetchedData['itemQuantity'] * $fetchedData['itemStockPrice'];
                $itemQty = $fetchedData['itemQuantity'];
                $stat = $this->statisticsModel->where('statItemId', $item_id)->findAll();
                $statId =  $stat[0]['statId'];
            }

            $statData = [
                'statItemId' => $item_id,
                'busId' => $userId,
                'statItemStock' => $itemQty,
                'statItemStockWorth' => $itemStockWorth,
                'statItemSales' => 0,
                'statItemSalesWorth' => 0,
                'statItemIndebt' => 0,
                'statItemIndebtWorth' => 0
            ];

            $recordStatData = $this->statisticsModel->save($statData);

            if (!$recordStatData) {
                $response = [
                    'status' => false,
                    'error' => 'recordFail',
                    'message' => 'Your records have not been fully kept.'
                ];

                return $this->respond($response);
            }
            // in case stat data was recorded successfully
            else {
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Your statistics records have successfully been kept.'
                ];

                return $this->respond($response);
            }
        }
        //update stock stats 
        if ($action === 'addStock') {
            $this->stockModel->orderBy('stockId', 'DESC');
            $fetchedStock = $this->stockModel->findAll(1);
            $stat = $this->statisticsModel->where('statItemId', $item_id)->findAll(1);
            // $itemStockWorth = $stat[0]['statItemStockWorth'] + ($fetchedStock[0]['stockItemQuantity'] * $fetchedStock[0]['stockItemPrice']);
            $itemStockWorth = 0;
            $itemQty =  $stat[0]['statItemStock'] + $fetchedStock[0]['stockItemQuantity'];
            $statId =  $stat[0]['statId'];

            $statData = [
                'statItemStock' => $itemQty,
                'statItemStockWorth' => $itemStockWorth,
                // 'statItemSales' => 0,
                // 'statItemSalesWorth' => 0,
                // 'statItemIndebt' => 0,
                // 'statItemIndebtWorth' => 0
            ];
            $this->statisticsModel->set($statData);
            $this->statisticsModel->where('statId', $statId);
            $recordStatData = $this->statisticsModel->update();

            if (!$recordStatData) {
                $response = [
                    'status' => false,
                    'error' => 'recordFail',
                    'message' => 'Your records have not been fully kept.'
                ];

                return $this->respond($response);
            }
            // in case stat data was recorded successfully
            else {
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Your statistics records have successfully been kept.'
                ];

                return $this->respond($response);
            }
        }

        //update debt stats 
        if ($action === 'addDebt') {
            $this->indebtModel->orderBy('indebtId', 'DESC');
            $fetchedDebt = $this->indebtModel->findAll(1);
            $stat = $this->statisticsModel->where('statItemId', $item_id)->findAll(1);
            $itemStockWorth = $stat[0]['statItemIndebtWorth'] + ($fetchedDebt[0]['quantityDebted'] * $fetchedDebt[0]['atPrice']);
            $statItemIndebt =  $stat[0]['statItemIndebt'] + $fetchedDebt[0]['quantityDebted'];
            $statId =  $stat[0]['statId'];

            $statData = [
                // 'statItemSales' => 0,
                // 'statItemSalesWorth' => 0,
                'statItemIndebt' => $statItemIndebt,
                'statItemIndebtWorth' => $itemStockWorth
            ];
            $this->statisticsModel->set($statData);
            $this->statisticsModel->where('statId', $statId);
            $recordStatData = $this->statisticsModel->update();

            if (!$recordStatData) {
                $response = [
                    'status' => false,
                    'error' => 'recordFail',
                    'message' => 'Your records have not been fully kept.'
                ];

                return $this->respond($response);
            }
            // in case stat data was recorded successfully
            else {
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Your statistics records have successfully been kept.'
                ];

                return $this->respond($response);
            }
        }

        //update item sales stats 
        if ($action === 'saleStock') {
            $userId = auth()->id();
            $this->salesModel->orderBy('saleId', 'DESC');
            $this->salesModel->where('saleOwner', $userId);
            $fetchedSales = $this->salesModel->findAll($limit);
            foreach ($fetchedSales as $sale => $item) {
                $stat = $this->statisticsModel->where('statItemId',  $item['saleItemId'])->findAll(1);
                $itemSaleWorth = $stat[0]['statItemSalesWorth'] + ($item['saleQuantity'] * $item['salePrice']);
                $statItemSale =  $stat[0]['statItemSales'] + $item['saleQuantity'];
                $statId =  $stat[0]['statId'];

                $statData = [
                    'statItemSales' =>  $statItemSale,
                    'statItemSalesWorth' => $itemSaleWorth,
                ];
                $this->statisticsModel->set($statData);
                $this->statisticsModel->where('statId', $statId);
                $recordStatData = $this->statisticsModel->update();

                if (!$recordStatData) {
                    $response = [
                        'status' => false,
                        'error' => 'recordFail',
                        'message' => 'Your records have not been fully kept.'
                    ];

                    return $this->respond($response);
                }
                // in case stat data was recorded successfully
                else {
                    $response = [
                        'status' => true,
                        'error' => null,
                        'message' => 'Your statistics records have successfully been kept.'
                    ];

                    return $this->respond($response);
                }
            }
        }
    }

    // Example controller method for image upload
    public function uploadProfileImage()
    {
        if ($this->request->getMethod() === 'post') {
            $file = $this->request->getFile('file'); // Get the uploaded file

            if ($file->isValid() && $file->getClientMimeType() === 'image/jpeg') {
                // Save the file to a designated folder (e.g., 'uploads')
                $newName = $file->getRandomName();
                $file->move(ROOTPATH . 'public/uploads', $newName);

                // Update the user's profile image path in the database
                // (You'll need to implement this part based on your database schema)
                // Example: $this->profile_model->updateProfileImage($user_id, $newName);

                return redirect()->to('/profile')->with('success', 'Profile image uploaded successfully.');
            } else {
                return redirect()->back()->with('error', 'Invalid file format. Please upload a JPEG image.');
            }
        }

        // Load your view here
        // Example: return view('profile/upload_form');
    }


    private function validateStockEntries($page)
    {
        $lowercase = strtolower($page);
        if ($lowercase == 'entry') {
            return $this->validate([
                'item_name' => [
                    'rules' => 'required|max_length[255]|trim|min_length[3]|is_unique[stock.itemName]',
                    'label' => 'Item Name'
                ],
                'item_category' => [
                    'rules' => 'numeric|max_length[11]|min_length[1]|trim|required|greater_than[0]',
                    'label' => 'Item Category'
                ],
                'item_model' => [
                    'rules' => 'max_length[50]|trim|min_length[2]',
                    'label' => 'Item Model'
                ],
                'item_quality' => [
                    'rules' => 'required|max_length[50]|trim',
                    'label' => 'Item Quality'
                ],
                'item_quantity' => [
                    'rules' => 'required|numeric|max_length[11]|trim|min_length[1]|greater_than[0]',
                    'label' => 'Item Quantity'
                ],
                'item_condition' => [
                    'rules' => 'required|max_length[50]|min_length[3]|trim|alpha_space',
                    'label' => 'Item Condition'
                ],
                'item_size' => [
                    'rules' => 'max_length[50]|trim|min_length[1]',
                    'label' => 'Item Size'
                ],
                // 'item_buy_price' => [
                //     'rules' => 'required|max_length[11]|numeric|min_length[2]|trim|greater_than[49]',
                //     'label' => 'Item Buy Price'
                // ],
                // 'item_sale_price' => [
                //     'rules' => 'required|max_length[11]|numeric|min_length[2]|trim|greater_than[500]',
                //     'label' => 'Item Sale Price'
                // ],
                'item_notes' => [
                    'rules' => 'max_length[1500]|min_length[3]|trim',
                    'label' => 'Item Notes'
                ],
                'item_owner' => [
                    'rules' => 'numeric|max_length[11]|min_length[1]|greater_than[0]|trim',
                    'label' => 'Item Owner'
                ]

            ]);
        } elseif ($lowercase === 'updateitem') {
            return $this->validate([
                'item_name' => [
                    'rules' => 'required|max_length[255]|trim|min_length[3]',
                    'label' => 'Item Name'
                ],
                // 'item_name' => [
                //     'rules' => 'required|max_length[255]|trim|min_length[3]|is_unique[stock.itemName]',
                //     'label' => 'Item Name'
                // ],
                'item_category' => [
                    'rules' => 'numeric|max_length[11]|min_length[1]|trim|required|greater_than[0]',
                    'label' => 'Item Category'
                ],
                'item_model' => [
                    'rules' => 'max_length[50]|trim|min_length[2]',
                    'label' => 'Item Model'
                ],
                'item_quality' => [
                    'rules' => 'max_length[50]|trim',
                    'label' => 'Item Quality'
                ],
                'item_quantity' => [
                    'rules' => 'numeric|max_length[11]|trim|min_length[1]',
                    'label' => 'Item Quantity'
                ],
                'item_condition' => [
                    'rules' => 'max_length[50]|trim|alpha_space',
                    'label' => 'Item Condition'
                ],
                'item_size' => [
                    'rules' => 'max_length[50]|trim|min_length[1]',
                    'label' => 'Item Size'
                ],
                'item_min_price' => [
                    'rules' => 'required|max_length[11]|numeric|min_length[2]|trim|greater_than[49]',
                    'label' => 'Item Buy Price'
                ],
                'item_notes' => [
                    'rules' => 'max_length[1500]|min_length[3]|trim',
                    'label' => 'Item Notes'
                ]
            ]);
        } elseif ($lowercase === 'sellitem') {
            return $this->validate([
                'sale_items' => [
                    'rules' => 'required|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Item being sold'
                ],
            ]);
        } elseif ($lowercase === 'debtitem') {
            return $this->validate([
                'debt_item' => [
                    'rules' => 'required|numeric|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Item being sold'
                ],
                'debt_owner' => [
                    'rules' => 'required|numeric|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Stock Owner'
                ],
                'debt_quantity' => [
                    'rules' => 'required|numeric|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Item quantity being sold',
                ],
                'debt_price' => [
                    'rules' => 'required|numeric|max_length[1]|max_length[11]|greater_than[0]|trim',
                    'label' => 'Item Price'
                ]
            ]);
        } else {
            echo "Nothing";
        }
    }
}
