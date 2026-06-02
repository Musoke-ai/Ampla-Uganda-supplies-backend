<?php

namespace App\Controllers;

use CodeIgniter\I18n\Time;
use CodeIgniter\RESTful\ResourceController;
use App\Models\Inventory;
use App\Models\History;
use App\Models\Statistics;
use App\Models\Indebt;
use App\Models\Sales;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use App\Models\Stock;
use App\Services\BranchContextService;
use Config\Database;

class Retrievals extends ResourceController
{
    /** This controller will hold the following functions
     = Check presence of administrators' data
     = Validation check
     = CRUD stockq
     **/

    private $inventoryModel;
    private $historyModel;
    private $statisticsModel;
    private $indebtModel;
    private $salesModel;
    private $stockModel;
    private BranchContextService $branchContext;

    public function __construct(){
        $this->inventoryModel = new Inventory();
        $this->historyModel = new History();
        $this->statisticsModel = new Statistics();
        $this->indebtModel = new Indebt();
        $this->salesModel = new Sales();
        $this->stockModel = new Stock();
        $this->branchContext = service('branchContext');
    }

     // return a response for a lack of data error 
    private function nostockdata(){
        //check presence of admin data
        $tim = Time::now('America/Chicago', 'en_US');
        $response = [
            'status' => false,
            'error' => 'no data',
            'time' => $tim,
            'message' => 'We have no stock data here. Make sure everything is right and try again in 10 minutes.'
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
        exit();
    }

    /**
     * Return an array of resource objects, themselves in array format
     *
     * @return mixed
     */
    public function index()
    {
        $userId = auth()->id();
        // home page => fetch stock item details
        // $data = $this->inventoryModel->where('itemOwner', $userId)->findAll();
        $data = $this->branchContext->scopeBuilder($this->inventoryModel)->findAll();

        if(empty($data)){
            $data = [];
            return $this->respond($data);
        }
        // in case there is stock to display
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
    public function getItem($id = null)
    {
        //single item details
        $data = $this->branchContext
            ->scopeBuilder($this->inventoryModel->where('itemId', $id))
            ->first();

        if(empty($data)){
            return $this->nostockdata();
        }
        // in case there is a stock item
        else{
            return $this->respond($data);
            exit();
        }
    }

    public function getStock()
    {
        $userId = auth()->id();
        //fetch stock
        // $stockData =  $this->stockModel->where('stockOwner',$userId)->findAll();
        $stockData =  $this->branchContext->scopeBuilder($this->stockModel)->findAll();
        if(empty($stockData)){
            return $this->respond([]);
        }
        else{
            // $response = [
            //     'status' => true,
            //     'error' => null,
            //     'message' => 'Success!! items have been fetched to your front end.' 
            // ];
            return $this->respond($stockData);
            exit();
        }
    }

    /** 
     * Return a new resource object containing statistics/error
     *
     * @return mixed
     */
    public function statistics()
    {
        $userId = auth()->id();
        // get stats
        // $data = $this->statisticsModel->where('busId', $userId)->findAll();
        $data = $this->branchContext->scopeBuilder($this->statisticsModel)->findAll();

        if(empty($data)){
            return $this->nostockdata();
        }
        // in case there are statistics
        else{
            return $this->respond($data);
            exit();
        }
    }

    /**
     * Create a new resource object containing stock history/error
     *
     * @return mixed
     */
    public function history()
    {
        $userId = auth()->id();
        //stock history
        // $data = $this->historyModel->where('busId', $userId)->orderBy('historyDateCreated', 'desc')->findAll();
        $data = $this->branchContext
            ->scopeBuilder($this->historyModel->orderBy('historyDateCreated', 'desc'))
            ->findAll();

        if(empty($data)){
            return $this->nostockdata();
        }
        // in case there is history
        else{
            return $this->respond($data);
            exit();
        }
    }


    /**
     * Create a new resource object containing results of keyword search/error
     *
     * @return mixed
     */
    // public function searchStock($keyword = null)
    // {
    //     //stock history
    //     if(!(strtolower($this->request->getMethod()) === 'post' && $this->validateRetrievals('searchstock'))){
    //         // return $this->validationFail();
            
    //         return $this->respond('Hello');
    //     }
    //     // in case validation is passed
    //     else{
    //         $keyword = trim($this->request->getVar('search_keyword'));
    //         $query = $this->db->query('SELECT * FROM stock WHERE itemName REGEXP "'.$key.'" || itemModel REGEXP "'.$key.'" || itemQuality REGEXP "'.$key.'" || itemCondition REGEXP "'.$key.'" || itemSize REGEXP "'.$key.'" || itemNotes REGEXP "'.$key.'"');
    //         return $this->respond($query);
    //     }

    // }

    public function validateRetrievals(){
        return $this->validate([
            'search_keyword' => [
                'rules' => 'trim|alpha_space|min_length[3]|max_length[100]|required',
                'label' => 'Search word'
            ]
        ]);
    }


        //fetch items in debts

public function getDebts(){
    $userId = auth()->id();
    // $debts = $this->indebtModel->where('indebtOwner', $userId)->findAll();
    $debts = $this->branchContext->scopeBuilder($this->indebtModel)->findAll();
    if($debts){
        return $this->respond($debts);
        exit();
    }else{
        return $this->respond([]);
        exit();
    }
    }
        //fetch items in Sales

public function getSales(){
    $userId = auth()->id();
    // $sales = $this->salesModel->where('saleOwner', $userId)->findAll();
    $builder = $this->salesModel
        ->groupStart()
            ->where('saleStatus <>', 'cancelled')
            ->orWhere('saleStatus IS NULL', null, false)
        ->groupEnd();
    $sales = $this->branchContext->scopeBuilder($builder)->findAll();
    if($sales){
        return $this->respond($sales);
        exit();
    }else{
        // $response = [
        //     'status' => false,
        //     'error' => 'NoItemsFound',
        //     'message' => 'An error occured or no sales found!'
        // ];
        return $this->respond([]);
        exit();
    }
    }

public function getReceipts()
{
    $db = Database::connect();
    $dateFrom = trim((string) ($this->request->getGet('date_from') ?? ''));
    $dateTo = trim((string) ($this->request->getGet('date_to') ?? ''));

    $builder = $db->table('receipt r')
        ->select(
            'r.SR_ID AS receiptId,
            r.branchId,
            r.createdBy,
            r.timeStamp AS receiptCode,
            r.srDateCreated AS issuedAt,
            COALESCE(r.discount, 0) AS discountAmount,
            COALESCE(r.dueAmount, 0) AS dueAmount,
            COALESCE(r.amountPaid, 0) AS tenderedAmount,
            r.moreInfo,
            r.paymentMethod,
            r.receiptStatus,
            b.branchName,
            b.branchLocation,
            b.branchContact,
            s.saleId,
            s.saleItemId,
            s.saleQuantity,
            s.salePrice,
            s.custId,
            i.itemName,
            i.itemSku,
            i.itemBarcode,
            i.itemUnit,
            c.custName AS customerName,
            c.custContact AS customerContact,
            c.custEmail AS customerEmail,
            c.custLocation AS customerLocation',
            false
        )
        ->join('sales s', 's.SR_ID = r.SR_ID', 'left')
        ->join('inventory i', 'i.itemId = s.saleItemId', 'left')
        ->join('customers c', 'c.custId = s.custId', 'left')
        ->join('branches b', 'b.branchId = r.branchId', 'left')
        ->groupStart()
            ->where('r.receiptStatus <>', 'cancelled')
            ->orWhere('r.receiptStatus IS NULL', null, false)
        ->groupEnd()
        ->groupStart()
            ->where('s.saleStatus <>', 'cancelled')
            ->orWhere('s.saleStatus IS NULL', null, false)
        ->groupEnd()
        ->orderBy('r.srDateCreated', 'DESC')
        ->orderBy('r.SR_ID', 'DESC')
        ->orderBy('s.saleId', 'ASC');

    $this->branchContext->scopeBuilder($builder, 'r.branchId');

    if ($dateFrom !== '') {
        $builder->where('DATE(r.srDateCreated) >=', $dateFrom);
    }

    if ($dateTo !== '') {
        $builder->where('DATE(r.srDateCreated) <=', $dateTo);
    }

    $rows = $builder->get()->getResultArray();
    $receipts = [];

    foreach ($rows as $row) {
        $receiptId = (int) ($row['receiptId'] ?? 0);

        if ($receiptId === 0) {
            continue;
        }

        if (!isset($receipts[$receiptId])) {
            $receipts[$receiptId] = [
                'receiptId' => $receiptId,
                'receiptNumber' => $receiptId,
                'receiptCode' => $row['receiptCode'] ?? '',
                'issuedAt' => $row['issuedAt'] ?? null,
                'branchId' => isset($row['branchId']) ? (int) $row['branchId'] : null,
                'branchName' => $row['branchName'] ?? '',
                'branchLocation' => $row['branchLocation'] ?? '',
                'branchContact' => $row['branchContact'] ?? '',
                'createdBy' => isset($row['createdBy']) ? (int) $row['createdBy'] : null,
                'customerId' => isset($row['custId']) ? (int) $row['custId'] : null,
                'customerName' => $row['customerName'] ?: 'Walk-in Customer',
                'customerContact' => $row['customerContact'] ?? '',
                'customerEmail' => $row['customerEmail'] ?? '',
                'customerLocation' => $row['customerLocation'] ?? '',
                'paymentMethod' => $row['paymentMethod'] ?: 'Cash',
                'discountAmount' => (float) ($row['discountAmount'] ?? 0),
                'taxAmount' => 0,
                'tenderedAmount' => (float) ($row['tenderedAmount'] ?? 0),
                'dueAmount' => (float) ($row['dueAmount'] ?? 0),
                'receiptStatus' => $row['receiptStatus'] ?: 'completed',
                'moreInfo' => $row['moreInfo'] ?? '',
                'items' => [],
                'subtotal' => 0,
                'total' => 0,
                'lineCount' => 0,
            ];
        }

        if (!empty($row['saleId'])) {
            $quantity = (float) ($row['saleQuantity'] ?? 0);
            $unitPrice = (float) ($row['salePrice'] ?? 0);
            $lineTotal = round($quantity * $unitPrice, 2);

            $receipts[$receiptId]['items'][] = [
                'saleId' => (int) $row['saleId'],
                'saleItemId' => isset($row['saleItemId']) ? (int) $row['saleItemId'] : null,
                'itemName' => $row['itemName'] ?: ('Item #' . ($row['saleItemId'] ?? '')),
                'itemSku' => $row['itemSku'] ?? '',
                'itemBarcode' => $row['itemBarcode'] ?? '',
                'itemUnit' => $row['itemUnit'] ?? '',
                'saleQuantity' => $quantity,
                'salePrice' => $unitPrice,
                'lineTotal' => $lineTotal,
            ];

            $receipts[$receiptId]['subtotal'] += $lineTotal;
            $receipts[$receiptId]['lineCount']++;
        }
    }

    foreach ($receipts as &$receipt) {
        $receipt['subtotal'] = round((float) $receipt['subtotal'], 2);
        $receipt['total'] = round($receipt['subtotal'] - (float) $receipt['discountAmount'] + (float) $receipt['taxAmount'], 2);
    }
    unset($receipt);

    return $this->respond(array_values($receipts));
}

}
