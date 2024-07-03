<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use App\Models\Administration;

class Adminauth extends ResourceController
{

    /** This controller will hold the following functions
     = Check presence of administrators' data
     = Validation check
     = Register an administrator
     = Login an administrator
     = Fetch administrator's details 
     = Update administrator details 
     = Delete administrator
     **/

    private $adminModel;
    public function __construct(){
        $this->adminModel = new Administration();
        $session = session();
    }

    private function noadminauthdata(){
        //check presence of admin data
        $response = [
            'status' => false,
            'error' => 'no data',
            'message' => 'We have no administrator data here....'
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
        //fetch administrator's data
        $data = [
            'title' => 'Admin name here..'
        ];

        if(empty($data)){
            return $this->noadminauthdata();
            exit();
        }
        else{
            return $this->repond($data);
            exit();
        }
    }

    /**
     * Return the properties of a resource object
     *
     * @return mixed
     */
    public function registerAdmin($icon)
    {
        //register system administrator
        if($this->request->getMethod() === 'post' && $this->validateAdminEntries('signupadmin')){

            // business logo upload
            $input = $this->validate([
                'admin_photo' => [
                    'uploaded[admin_photo]',
                    'mime_in[admin_photo,image/jpg,image/jpeg,image/png]',
                    'max_size[admin_photo,1024]',
                ]
            ]);

            // check for proper image validation 
            if(!$input){
                $response = [
                    'status' => false,
                    'error' => 'validationError',
                    'message' => $this->validator->getErrors()
                ];
                return $this->respond($response);
                exit();
            }
            else{
                $icon = $this->request->getFile('admin_photo');

                if(!$icon->move('./uploads/administrators/icons/')){
                    $response = [
                        'status' => false,
                        'error' => 'uploadFail',
                        'message' => 'Could not completely upload your photo, try again after 10 minutes or use another version of your photo'
                    ];
                    return $this->respond($response);
                    exit();
                }
                else{
                    $adminData = [
                        'adFirstName' => $this->request->getVar('admin_fname'),
                        'adMiddleName' => $this->request->getVar('admin_mname'),
                        'adLastName' => $this->request->getVar('admin_lname'),
                        'adEmail' => $this->request->getVar('admin_email'),
                        'adPhoto' => $icon->getName(),
                        'adPassword' => password_hash(trim($this->request->getVar('admin_password')), PASSWORD_DEFAULT)
                ];

        $saveAdmin = $this->adminModel->save($adminData);
        // Is admin registered?? 
        if($saveAdmin){
            $response = [
                'status' => true,
                'error' => null,
                'message' => 'Success! You have registered as an administrator, log in and start managing HSMS'
            ];
            return $this->respond($response);
            exit();
        }
        else{
            $response = [
                'status' => false,
                'error' => 'adminRegistrationFailed',
                'message' => 'Fail! You could not be registered as an administrator, try again after 10 minutes'
            ];
            return $this->respond($response);
            exit();
        }
        }}}
        else{
            return $this->validationFail();
        }
    }

    /**
     * Return a new resource object, with default properties
     *
     * @return mixed
     */
    public function loginAdmin()
    {
        //login an adiministrator
        if($this->request->getMethod() === 'post' && $this->validateAdminEntries('logadmin')){
            $admin_email = $this->request->getVar('admin_log_email');
            $admin_password = trim($this->request->getVar('admin_log_password'));
            $data = $this->adminModel->where('adEmail', $admin_email)->first();
            // if email entered is right
            if($data){
                $pass = $data['adPassword'];
                $verify_pass = password_verify($admin_password,$pass);

                // if password entered matches
                if($verify_pass){
                   $ses_data = [
                        'adminId' => $data['adId'],
                        // 'adminFirstName' => $data['adFirstName'],
                        // 'adminMiddleName' => $data['adMiddleName'],
                        // 'adminLastName' => $data['adLastName']
                   ];

                   $session->set($ses_data);
                   $response = [
                        'status' => true,
                        'error' => null,
                        'message' => 'Success! You have logged in, redirecting.....'
                   ];
                   return $this->respond($response);
                   exit();
                }
                // in case wrong password is used
                else{
                    $response = [
                        'status' => false,
                        'error' => 'wrongPassword',
                        'message' => 'Fail! Wrong password, rethink it and try again'
                    ];
                    return $this->respond($response);
                    exit();
                }
            }
            // in case wrong email is used
            else{
                $response = [
                    'status' => false,
                    'error' => 'wrongEmail',
                    'message' => 'Fail! We do not know about this e-mail, try again with the one you registered with'
                ];
                return $this->respond($response);
                exit();
            }
        }
        // in case form validation fails
        else{
            return $this->validationFail();
        }
    }

     /**
     * Return logged in administrator properties of a resource object
     *
     * @return mixed
     */
    public function showAdmin($id = null)
    {
        // show admin 
        $data = $this->adminModel->where('adId', $id)->first();
        if($data){
            return $this->respond($data);
            exit();
        }
        else{
            $response = [
                'status' => false,
                'error' => 'noAdminData',
                'Message' => 'Nothing to show here'
            ];
            return $this->respond($response);
            exit();
        }
    }

     /**
     * Return the logout properties of a resource object
     *
     * @return mixed
     */
    public function logoutAdmin($id = null)
    {
        // logout admin
        $this->session->destroy();
        return redirect()->to('enter some page here');
    }

    /**
     * Return the editable properties of a resource object
     *
     * @return mixed
     */
    public function editAdmin($id = null)
    {
        // edit administrator's details
        $data = $this->adminModel->find($id);

        if(!$data){
            $response = [
                'status' => false,
                'error' => 'noAdminData',
                'message' => 'Fail! No administrator to edit. Make sure everything is correct and try again'
            ];
            return $this->respond($response);
            exit();
        }
        // in case admin to edit is found/exists
        else{
            return $this->respond($data);
            exit();
        }
    }

    /**
     * Add or update a model resource, from "posted" properties
     *
     * @return mixed
     */
    public function updateAdmin($id = null, $icon = null)
    {
        //update administrator's details
        $id = trim($this->request->getVar('admin_id'));
        $data = $this->adminModel->find($id);

        if(empty($data)){
            $response = [
                'status' => false,
                'error' => 'noData',
                'message' => 'There is no profile to update. Follow the right procedures and try again in 10 minutes'
            ];
            return $this->respond($response);
            exit();
        }
        // in case an admin profile to update exists
        else{
            if(!($this->request->getMethod() === 'post' && $this->validateAdminEntries('editadmin'))){
                return $this->validationFail();
            }
            // in case form validation is passed 
            else{
                // administrator logo upload
            $input = $this->validate([
                'admin_photo' => [
                    'uploaded[admin_photo]',
                    'mime_in[admin_photo,image/jpg,image/jpeg,image/png]',
                    'max_size[admin_photo,1024]',
                ]
            ]);

            // check for proper image validation 
            if(!$input){
                return $this->validationFail();
            }
            else{
                $icon = $this->request->getFile('admin_photo');

                if(!$icon->move('./uploads/administrators/icons/')){
                    $response = [
                        'status' => false,
                        'error' => 'uploadFail',
                        'message' => 'Could not completely upload your photo, try again after 10 minutes or use another version of your photo'
                    ];
                    return $this->respond($response);
                    exit();
                }
                else{
                 $adminDataUpdate = [
                    'adFirstName' => $this->request->getVar('admin_fname'),
                    'adMiddleName' => $this->request->getVar('admin_mname'),
                    'adLastName' => $this->request->getVar('admin_lname'),
                    'adEmail' => $this->request->getVar('admin_email'),
                    'adPhoto' => $icon->getName()
                ];

                $updateAdmin = $this->adminModel->update($adminDataUpdate);

                if(!$updateAdmin){
                    $response = [
                        'status' => false,
                        'error' => 'updateFail',
                        'message' => 'Fail! Administrator update could not complete, correct everything and try again in 10 minutes'
                    ];
                    return $this->respond($response);
                    exit();
                }
                // in case update succeeds
                else{
                    $response = [
                        'status' => true,
                        'error' => null,
                        'message' => 'Success!! Administrator information has been updated.'
                    ];
                    return $this->respond($response);
                    exit();
                }
            }
        }
    }
}}

    /**
     * Delete the designated resource object from the model
     *
     * @return mixed
     */
    public function deleteAdmin($id = null)
    {
        //delete an admin with all his/her data
        $data = $this->adminModel->find($id);
        if($data){
            if(file_exists('uploads/admins/'.$data['adPhoto']) && $data['adPhoto']){
                unlink('uploads/admins/'.$data['adPhoto']);
            }
            $del = $this->adminModel->delete($id);
            if($del){
                $response = [
                    'status' => true,
                    'error' => null,
                    'message' => 'Success! Administrator deleted'
                ];
                return $this->respond($response);
                exit();
            }
            // in case deletion fails
            else{
                $response = [
                    'status' => false,
                    'error' => 'deleteFail',
                    'message' => 'Administrator could not be deleted, login and try to delete again after 10 minutes'
                ];
                return $this->respond($response);
                exit();
            }
        }
        // in case no admin is found
        else{
            $response = [
                'status' => false,
                'error' => 'notFound',
                'message' => 'Fail! We do not know about this administrator, and stop that...'
            ];
            return $this->respond($response);
            exit();
        }
    }

    private function validateAdminEntries($page){
        $lowercase = strtolower($page);
        if($lowercase === 'registeradmin'){
            return $this->validate([
                'admin_fname' => [
                    'rules' => 'required|max_length[20]|min_length[2]|alpha|trim',
                    'label' => 'Administrator First Name'
                ],
                'admin_mname' => [
                    'rules' => 'max_length[20]|min_length[2]|alpha|trim',
                    'label' => 'Administrator Last Name'
                ],
                'admin_lname' => [
                    'rules' => 'max_length[20]|min_length[2]|alpha|trim|required',
                    'label' => 'Administrator Middle Name'
                ],
                'admin_email' => [
                    'rules' => 'required|trim|min_length[11]|max_length[50]|is_unique[administrators.adEmail]|valid_email',
                    'label' => 'Administrator E-mail'
                ],
                'admin_password' => [
                    'rules' => 'required|min_length[10]|trim',
                    'label' => 'Administrator Password'
                ],
                'admin_password_repeat' => [
                    'rules' => 'required|min_length[10]|trim|matches[admin_password]',
                    'label' => 'Administrator Password Repeat'
                ]
            ]);
            exit();
        }
        elseif($lowercase === 'logadmin'){
            return $this->validate([
                'admin_log_email' => [
                    'rules' => 'required|min_length[11]|max_length[50]|trim|valid_email',
                    'label' => 'Administrator E-mail'
                ],
                'admin_log_password' => [
                    'rules' => 'required|min_length[10]|trim',
                    'label' => 'Administrator Password'
                ]
            ]);
            exit();
        }
        elseif($lowercase === 'editadmin'){
            return $this->validate([
                'admin_fname' => [
                    'rules' => 'required|max_length[20]|min_length[2]|alpha|trim',
                    'label' => 'Administrator First Name'
                ],
                'admin_mname' => [
                    'rules' => 'max_length[20]|min_length[2]|alpha|trim',
                    'label' => 'Administrator Last Name'
                ],
                'admin_lname' => [
                    'rules' => 'max_length[20]|min_length[2]|alpha|trim|required',
                    'label' => 'Administrator Middle Name'
                ],
                'admin_email' => [
                    'rules' => 'required|trim|min_length[11]|max_length[50]|is_unique[administrators.adEmail]|valid_email',
                    'label' => 'Administrator E-mail'
                ]
            ]);
            exit();
        }
        else{
            echo "Nothing";
        }
    }
}
