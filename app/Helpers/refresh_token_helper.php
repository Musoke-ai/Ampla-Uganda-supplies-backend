<?php

if (!function_exists('No such function in this helper')) {
    function generateRefreshToken()
    {
        //create a random refresh token
        $refreshToken = bin2hex(random_bytes(32));
        //hash the token 
        $hashedToken = hash('sha256',  $refreshToken);
        $expiryDate = (new DateTime())->modify('+2 minutes')->format('Y-m-d H:i:s');
        $data = [
            'refreshToken' => $refreshToken,
            'hashedToken' => $hashedToken
        ];

        return  $data;
    }
}

if (!function_exists('No such function in this helper')) {
    function validateRefreshToken($expiryDate)
    {
        $currentDate = new DateTime();
        $expiryDate = new DateTime($expiryDate);
        // $expiryDate = new DateTime($tokenData["expiry_date"]);

        //check for expiration of the token from the database
        if ($expiryDate >= $currentDate) {
            echo "Ok";
            return true;
        } else {
            echo "null";
            return false;
        }
    }
}
