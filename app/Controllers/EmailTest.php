<?php

namespace App\Controllers;
use CodeIgniter\Controller;
use Config\Services;

class EmailTest extends Controller
{
    public function send()
    {
        $email = Services::email();

        $email->setTo('hmusoke9@gmail.com'); // change this
        $email->setFrom('hmusoke9@gmail.com', 'CI4 Test Email');
        $email->setSubject('Hello from CodeIgniter 4');
        $email->setMessage('<h2>This is a test email sent from CodeIgniter 4 using Gmail SMTP</h2>');

        if ($email->send()) {
            echo '✅ Email sent successfully!';
        } else {
            echo '❌ Email failed to send.';
            print_r($email->printDebugger(['headers']));
        }
    }
}
