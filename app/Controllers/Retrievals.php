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

    public function __construct(){
        $this->inventoryModel = new Inventory();
        $this->historyModel = new History();
        $this->statisticsModel = new Statistics();
        $this->indebtModel = new Indebt();
        $this->salesModel = new Sales();
        $this->stockModel = new Stock();
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
        $data = $this->inventoryModel->findAll();

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
        $data = $this->inventoryModel->where('itemId', $id)->first();

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
        $stockData =  $this->stockModel->findAll();
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
        $data = $this->statisticsModel->findAll();

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
        $data = $this->historyModel->orderBy('historyDateCreated', 'desc')->findAll();

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
    //     if(!($this->request->getMethod() === 'post' && $this->validateRetrievals('searchstock'))){
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
    $debts = $this->indebtModel->findAll();
    if($debts){
        return $this->respond($debts);
        exit();
    }else{
        $response = [
            'status' => false,
            'error' => 'NoDebtsFound',
            'message' => 'An error occured or no debts found!'
        ];
        return $this->respond($response);
        exit();
    }
    }
        //fetch items in Sales

public function getSales(){
    $userId = auth()->id();
    // $sales = $this->salesModel->where('saleOwner', $userId)->findAll();
    $sales = $this->salesModel->findAll();
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

}
