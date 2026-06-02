<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class PaymentController extends ResourceController
{
    public function processPayment()
    {
        return $this->respond([
            'status' => false,
            'message' => 'Online payment processing is not configured for this workspace.',
        ], 501);
    }

    public function paymentStatus()
    {
        return $this->respond([
            'status' => false,
            'message' => 'Online payment status is not configured for this workspace.',
        ], 501);
    }
}
