<?php

namespace App\Controllers;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use App\Models\Business;
use App\Models\RefreshToken;

use DateTime;

class PasswordController extends ResourceController
{

    private $TokenModel;
    protected $encrypter;

    public function __construct(){
    
    }

public function index(){
    return "Hi";
}// post


}