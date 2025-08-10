<?php

if (!function_exists('getAccessToken')) {
    function getAccessToken($secrets)
    {
       // Pesapal API URL to request the access token
$url = 'https://pay.pesapal.com/v3/api/Auth/RequestToken';

// Initialize cURL
$curl = curl_init($url);

// Set cURL options
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($secrets)); // Send the credentials as JSON in the POST data

// Execute cURL request
$response = curl_exec($curl);

// Check for errors
if ($response === false) {
    echo 'Curl error: ' . curl_error($curl);
} else {
    // Decode the response
    $responseData = json_decode($response, true);
    
    // Check if the access token is present in the response
    if (isset($responseData['token'])) {
        $accessToken = $responseData['token'];
        echo 'Access Token: ' . $accessToken;
        return $accessToken;
    } else {
        echo 'Failed to get access token. Response: ' . $response;
        return false;
    }
}

// Close cURL session
curl_close($curl);
    }
}

if (!function_exists('createPesapalPayment')) {
    function createPesapalPayment($accessToken, $paymentData)
    {
        $url = 'https://pay.pesapal.com/v3/api/Transactions/SubmitOrderRequest';
    
        $curl = curl_init($url);
    
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($paymentData));
    
        $response = curl_exec($curl);
        if ($response === false) {
            echo 'Curl error: ' . curl_error($curl);
            return null;
        }
    
        $responseData = json_decode($response, true);
        curl_close($curl);
    
        return $responseData;
    }
}
