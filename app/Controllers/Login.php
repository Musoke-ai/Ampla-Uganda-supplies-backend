<?php

namespace App\Controllers;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use App\Models\Business;
use App\Models\RefreshToken;

use DateTime;

class Login extends ResourceController
{

    private $TokenModel;
    protected $encrypter;

    public function __construct(){
        $this->encrypter = \Config\Services::encrypter();
        $this->TokenModel = new RefreshToken();
    }

// post
public function signUp() {
   $response = $this->generateRefreshToken(2);
    return $this->respondCreated($response);
}

private function generateRefreshToken($userId)
    {
        //create a random refresh token
        $refreshToken = bin2hex(random_bytes(32));
        //hash the token 
        $hashedToken = hash('sha256',  $refreshToken);
        $expiryDate = (new DateTime())->modify('+2 minutes')->format('Y-m-d H:i:s');
         $data= [
            'refreshToken' => $refreshToken,
            'hashedToken' => $hashedToken 
         ];
        
        $refresh = $this->TokenModel->save([
            'user_id' => $userId,
            'token' =>   $hashedToken,
            'expiry_date' => $expiryDate
        ]);
        return  $data;
    }

    // public function checkToken(){
    //     $token = 'c8de90a757765d5c33f7cee441ad8c088a2b66b1a79c14cb46ee5ae3d558c2d8';
    //     $this->validateRefreshToken($token);
    // }

    public function validateRefreshToken($token)
    {
        //hash the token using the same method as used for generating the token
        $hashedToken = hash('sha256',  $token);

        $tokenData = $this->TokenModel->where(['token' => $hashedToken])->first();
        if ($tokenData) {
            $currentDate = new DateTime();
            $expiryDate = new DateTime($tokenData["expiry_date"]);

            //check for expiration of the token from the database
            if ($expiryDate >= $currentDate) {
                echo"Ok";
                return true;
            }else {
            echo"null";
                return false;
            }
        }     
        return  $tokenData;
    }
}
