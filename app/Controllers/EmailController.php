<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class EmailController extends ResourceController
{
    /**
     * send email functionality
     *
     * @return mixed
     */
    public function sendmail()
    {
        // echo "Hi";
        // initialising the email library
        $email = \Config\Services::email();

        $email->setFrom(env('MAIL_FROM_ADDRESS', 'support@amplauganda.local'), env('MAIL_FROM_NAME', 'Ampla Uganda'));
        $email->setTo('hmusoke9@gmail.com');

        //these two are optional and can be removed
        // $email->setCC('another@another-example.com');
        // $email->setBCC('them@their-example.com');

        $email->setSubject('Email Test');
        $email->setMessage('Testing the email class.');
        // //you have to include a full path of the location of doc
        // // $email->attach('C:\Users\xyz\Desktop\images\abc.png');

       $send = $email->send();
        // print_r($send);
         if($send)
         {
          $response = [
                    'status' => true,
                    'error' =>  $email->printDebugger(["headers"]),
                    'message' => 'Email sent successfully.'
                ];
            return $this->respond($response);
         }
         else
        {

            $response = [
                    'status' => false,
                    'error' => 'error',
                    'message' => 'Email not sent;.'
                ];
            $data = $email->printDebugger(["headers"]);
            return $this->respond($data);
        }
    }

    /**
     * Return the properties of a resource object
     *
     * @return mixed
     */
    public function show($id = null)
    {
        //
    }

    /**
     * Return a new resource object, with default properties
     *
     * @return mixed
     */
    public function new()
    {
        //
    }

    /**
     * Create a new resource object, from "posted" parameters
     *
     * @return mixed
     */
    public function create()
    {
        //
    }

    /**
     * Return the editable properties of a resource object
     *
     * @return mixed
     */
    public function edit($id = null)
    {
        //
    }

    /**
     * Add or update a model resource, from "posted" properties
     *
     * @return mixed
     */
    public function update($id = null)
    {
        //
    }

    /**
     * Delete the designated resource object from the model
     *
     * @return mixed
     */
    public function delete($id = null)
    {
        //
    }
}
