<?php
namespace App\Services;

use App\Models\RefreshToken;

class ValidateToken {

    protected $model;

    public function __construct()
    {
        $this->model = new RefreshToken();
    }

    public function validation($token) {

        $response = false;

        $hashedToken = hash('sha256',  $token);

        $tokenData = $this->model->where(['token' => $hashedToken])->first();

        $validated = validateRefreshToken($tokenData["expiry_date"]);

        if($validated) {
            $response = true;
        }

        return $response;

    }
}
