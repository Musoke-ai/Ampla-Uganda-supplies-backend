<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class PaymentController extends ResourceController
{
    /** This controller holds the following functions
        = Check presence of data
        = Validation check
        = CRUD a category
        = Fetch category related stock
     **/
private $accessToken;
private $trackId;
    public function __construct() {
    $this->accessToken = '';
    $this->trackId = '';
    }

    // fetch categories data
    public function errorOnPayment()
    {
        $response = [
            'status' => false,
            'error' => 'payment failed',
            'message' => 'There is an error concerning this payment. Follow the right procedures and try again in 10 minutes.'
        ];
        return $this->respond($response);
        exit();
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
        exit();
    }

    /**
     * Return an array of resource objects, themselves in array format
     *
     * @return mixed
     */
    public function processPayment()
    {
       // helper('handle_payment');
        // echo getenv('CONSUMER_KEY');
        // echo getenv('CONSUMER_SECRET');
        //Generate unique id for the transaction id
        $uniqueId = time() . rand(10, 50);
        $this->trackId =   $uniqueId;

        $secrets = [
            'consumer_key' => env('CONSUMER_KEY'),
            'consumer_secret' => env('CONSUMER_SECRET')
        ];

        //Payment request data
        $paymentData = [
            'id' => $uniqueId,
            'amount' => '1000.00',
            'currency' => 'UGX',
            'description' => 'Payment for order #1234',
            'callback_url' => 'http://localhost/mystock/paymentstatus',
            //'callback_url' => 'https://www.poweredstock.com/payments',
            'notification_id' => env('IPN_ID'),
            'billing_address' => [
                'email_address' => 'hmusoke9@gmail.com',
                'phone_number' => '0770968736',
                'country_code' => 'UG',
                'first_name' => 'Musoke ',
                'middle_name' => 'Abas',
                'last_name' => 'Hamuzah',
                'line_1' => '',
                'line_2' => '',
                'city' => 'Kampala',
                'state' => '',
                'postal_code' => ''
            ]
        ];

        //Generate access token in the transation request
        $accessToken = $this->getAccessToken($secrets);

        if ($accessToken) {
            // $this->accessToken = $accessToken;
            setcookie("accessToken", $accessToken, time() + (86400 * 30));
            $payment = $this->createPesapalPayment($accessToken, $paymentData);
            //order_tracking_id
            print_r($payment);
            $redirect_url = $payment['redirect_url'];
            $orderTrackingId = $payment['order_tracking_id'];
            setcookie("orderTrackingId",  $orderTrackingId, time() + (86400 * 30));
            // echo "<script> window.location.href = '$redirect_url';</script>";
            $response = [
                'status' => true,
                'error' => 'false',
                'message' => 'Payment url generated visit the URL to complete the transuction.',
                'data' => $redirect_url
            ];
            return $this->respond($response);
            exit();
        } else {
            $response = [
                'status' => false,
                'error' => 'noAcccessToken',
                'message' => 'No accessToken found!'
            ];
            return $this->respond($response);
            exit();
        }
    }

    public function getAccessToken($secrets)
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
            // echo 'Curl error: ' . curl_error($curl);
        } else {
            // Decode the response
            $responseData = json_decode($response, true);

            // Check if the access token is present in the response
            if (isset($responseData['token'])) {
                $accessToken = $responseData['token'];
                return $accessToken;
            } else {
                // echo 'Failed to get access token. Response: ' . $response;
                return false;
            }
        }

        // Close cURL session
        curl_close($curl);
    }

    public function createPesapalPayment($accessToken, $paymentData)
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
            // echo 'Curl error: ' . curl_error($curl);
            return null;
        }

        $responseData = json_decode($response, true);
        curl_close($curl);

        return $responseData;
    }

    public function paymentStatus(){
        if(isset($_COOKIE["accessToken"])) {
            echo "Welcome " . $_COOKIE["accessToken"] . "!";
            $this->accessToken = $_COOKIE["accessToken"];
            $this->trackId = $_COOKIE["orderTrackingId"];
        }
        echo 'Payment processing';
        echo '<br>';
        echo 'AccessToken: '.$this->accessToken;
        echo '<br>';
        echo 'trackId: '.$this->trackId;
        echo '<br>';
        $url = 'https://pay.pesapal.com/v3/api/Transactions/GetTransactionStatus?orderTrackingId='.$this->trackId;

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ]);
        // curl_setopt($curl, CURLOPT_POST, true);
        // curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($paymentData));

        $response = curl_exec($curl);
        if ($response === false) {
            echo 'Curl error: ' . curl_error($curl);
            return null;
        }

        $responseData = json_decode($response, true);
        curl_close($curl);
        print_r($responseData);
        return $responseData;
    }
}
