<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Entities\User;
use App\Models\Business;
use App\Models\RefreshToken;

use DateTime;

class UserAuthController extends ResourceController
{
    private $businessModel;
    private $refreshTokenModel;
    private $UserObject;

    public function __construct()
    {
        $this->UserObject = new UserModel();
        $this->businessModel = new Business();
        $this->refreshTokenModel = new RefreshToken();
    }

private function extractAndModifyPermissions(array $permissions): array
    {
        $extractedRoles = [];
        $allModifiedActions = [];

        // Iterate over each role (key) and its associated actions (value).
        foreach ($permissions as $role => $actions) {
            // Add the full role name to our list of roles.
            $extractedRoles[] = $role;

            // Get the first word of the role to prepend to the action.
            // e.g., "Sales Desk" becomes "Sales".
            $rolePrefix = explode(' ', $role)[0];

            // Iterate over each action for the current role.
            foreach ($actions as $action) {
                // Create the new permission string, e.g., "Sales View".
                $modifiedAction = $rolePrefix . ' ' . $action;
                
                // Add the newly created permission string to our flat list.
                $allModifiedActions[] = $modifiedAction;
            }
        }

        return [
            'roles' => $extractedRoles,
            'actions' => $allModifiedActions,
        ];
    }

    //post
    public function createSharedAccount()
    {
        $rules = [
            // "username" => "required|is_unique[users.username]",
            "businessName" => "required",
            "email" => "required|valid_email|is_unique[auth_identities.secret]",
            "password" => "required"
        ];
        if (!$this->validate($rules)) {

            $response = [
                "status" => false,
                "message" => $this->validator->getErrors(),
                "data" => []
            ];
        } else {

            //User Entity
            $userEntityObject = new User([
                "username" => $this->request->getVar("name"),
                "email" => $this->request->getVar("email"),
                "password" => $this->request->getVar("password"),
            ]);

            $userSaved = $this->UserObject->save($userEntityObject);

         
         
        // return $this->respondCreated($response);
    }

    }
}