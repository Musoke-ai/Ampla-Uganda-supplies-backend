<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use App\Models\Business;

class AuthController extends ResourceController
{
    private $businessModel;

    public function __construct(){
        $this->businessModel = new Business();
    }
  //post
  public function register(){
    $rules = [
        // "username" => "required|is_unique[users.username]",
        "businessName" => "required",
        "email" => "required|valid_email|is_unique[auth_identities.secret]",
        "password" => "required"
    ];
    if (!$this->validate($rules)){

        $response = [
            "status" => false,
            "message" => $this->validator->getErrors(),
            "data" => []
        ];
    }else {
        // User Modal
        $UserObject = new UserModel();

        //User Entity
        $userEntityObject = new User([
"username" => $this->request->getVar("businessName"),
"email" => $this->request->getVar("email"),
"password" => $this->request->getVar("password"),
        ]);

        $userSaved = $UserObject->save($userEntityObject);

        if($userSaved){
            $userObject = new UserModel();
 
            //find the user the rescent user by email 
            $userData = $userObject->findByCredentials(["email" => $this->request->getVar("email"),]);
        }
        $busProfile = [
            "busId" => $userData->id,//save user id in the profile table
            "busName" => $this->request->getVar("businessName"),
            "busLocation" => $this->request->getVar("location"),
            "busBuilding" => $this->request->getVar("building"),
            "busNumberShop" => $this->request->getVar("shopNumber"),
            "busContactOne" => $this->request->getVar("contactOne"),
            "busContactTwo" => $this->request->getVar("contactTwo"),
            "busEmail" => $this->request->getVar("email"),
            "busOwner" => $this->request->getVar("owner"),
            "busLogo" => "",
        ];

        if($userSaved ) {
             $profileSaved = $this->businessModel->save($busProfile);
             if($profileSaved){
                $response = [
                    "status" => true,
                    "message" => "User saved successfully",
                    "data" => [$profileSaved]
                ];
             }else{
                $response = [
                    "status" => false,
                    "message" => "User not saved !",
                    "data" => []
                ];
             }
         
        }else{
            $response = [
                "status" => false,
                "message" => "User not saved successfully",
                "data" => []
            ];
        }
   
    }
    return $this->respondCreated($response);
  }

  public function uploadLogo() {
    $userId = auth()->id();
    $logo = $this->request->getVar("logo");
    if(isset($logo)) {
        $errors = array();
        $file_name = $logo['name'];
        $file_size = $logo['size'];
        $file_tmp = $logo['tmp_name'];
        $file_type = $logo['type'];
        $file_ext = strtolower(end(explode('.',$logo['name'])));
        $file_path = "uploads/logos/".$file_name;

        $extensions = array("jpeg","jpg","png");

        if(in_array($file_ext, $extensions) ===false){
            $errors[]="extension not allowed, please choose a JPEG or PNG file.";
        }

        if($file_size > 2097152){
            $errors[]="File size must be exactly 2MB";
        }
        if(empty($errors)){
            move_uploaded_file($file_tmp, $file_path);
            $this->businessModel->set("busLogo", $file_path);
            $this->businessModel->where("busId", $userId);
            $logoUpdate = $this->businessModel->update();
            if($logoUpdate){
                return $this->respondCreated("Success");
            }else{
                return $this->respondCreated("Data base error");
            }
        }else{
            return $this->respondCreated($errors);
        }
    }
  }

// post

public function login() {

 if(auth()->loggedIn()){
auth()->logout();
    }

    $rules =[
"email" => "required|valid_email",
"password" => "required"
    ];

    if(!$this->validate($rules)){
        $response = [
            "status" => false,
            "message" => $this->validator->getErrors(),
            "data" => []
        ];
    } else {
        //success
        $credentials = [
            "email" => $this->request->getVar("email"),
            "password" => $this->request->getVar("password")
        ];

        $loginAttempt = auth()->attempt($credentials);

        if (!$loginAttempt->isOk()){
            $response = [
                "status" => false,
                "message" => "Invalid login details",
                "data" => []
            ];
        }else {
            // we have a valid data set
            $userObject = new UserModel();

            $userData = $userObject->findById(auth()->id());
            
            $token = $userData->generateAccessToken("thisismysecretekey");

            $auth_token = $token->raw_token;

            $response = [
                "status" => true,
                "message" => "User logged in successfully",
                'data' => $auth_token
            ];
        }
    }
    return $this->respondCreated($response);
}

//Get profile
public function profile() {

$userId = auth()->id();

$userObject = new UserModel();

// $userData = $userObject->findById($userId);

$businessprofile = $this->businessModel->where('busId', $userId)->findAll();
$data = $businessprofile[0];

return $this->respondCreated($data);

}

//post

public function updateProfile() {
    $userId = auth()->id();
    $this->businessModel->set('busName',$this->request->getVar("businessName"));
    $this->businessModel->set('busLocation',$this->request->getVar("location"));
    $this->businessModel->set('busBuilding',$this->request->getVar("building"));
    $this->businessModel->set('busNumberShop',$this->request->getVar("shopNumber"));
    $this->businessModel->set('busContactOne',$this->request->getVar("contactOne"));
    $this->businessModel->set('busContactTwo',$this->request->getVar("contactTwo"));
    //email can not be edited/changed here
    // $this->businessModel->set('busEmail',$this->request->getVar("email"));
    $this->businessModel->set('busOwner',$this->request->getVar("owner"));
    $this->businessModel->where('busId',$userId);
    $profileUpdated = $this->businessModel->update();

    if($profileUpdated){
        return $this->respondCreated(
            [
                "status" => true,
                "message" => "Profile updated successfully",
                "data" => []
            ]);
    }else{
        return $this->respondCreated(
            [
                "status" => true,
                "message" => "Profile updated failed!",
                "data" => []
            ]); 
    }
}

//Get
public function logout() {
    auth()->logout();

    auth()->user()->revokeAllAccessTokens();

    return $this->respondCreated(
        [
            "status" => true,
            "message" => "User looged out successfully",
            "data" => []
        ]
        );
}

public function accessDenied()
{
    return $this->respondCreated([
        "status" => false,
        "message" => "Invalid access",
        "data" => []
    ]);
}

}
