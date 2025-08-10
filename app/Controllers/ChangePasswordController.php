<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\RESTful\ResourceController;

class ChangePasswordController extends ResourceController
 {
    public function index()
 {
        // Only allow logged-in users
        if ( !auth()->loggedIn() ) {
            return redirect()->to( '/login' )->with( 'error', 'You must be logged in to change your password.' );
        }

        return view( 'auth/change_password' );
    }

    public function update( $id = null )
 {
        if ( !auth()->loggedIn() ) {
            return redirect()->to( '/login' );
        }

        $rules = [
            'password'     => 'required|min_length[8]',
            'pass_confirm' => 'required|matches[password]',
        ];

        if ( ! $this->validate( $rules ) ) {
            return redirect()->back()
            ->withInput()
            ->with( 'errors', $this->validator->getErrors() );
        }

        $user = auth()->user();

        // Fill new data
        $user->fill( [
            'password' => $this->request->getPost( 'password' )
        ] );

        // Save with Shield's UserModel to ensure password is hashed
$model = new UserModel();
$model->save($user);

        // Logout user after password change
        auth()->logout();

        return  redirect()->to('http://localhost:3000');
        // return redirect()->to('localhost:3000')->with('message', 'Password changed successfully. Please log in again.' );
    }
}
