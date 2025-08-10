<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use Config\Services;

class PasswordResetController extends ResourceController
{
    protected $helpers = ['url', 'form'];

    public function forgotPasswordForm()
    {
        echo view('layout', [
            'title' => 'Forgot Password',
            'content' => '
                <h2>Forgot Password</h2>
                <form method="post" action="' . site_url('forgot-password') . '">
                    ' . csrf_field() . '
                    <label>Email</label><br>
                    <input type="email" name="email" required><br><br>
                    <button type="submit">Send Reset Link</button>
                </form>
            ',
        ]);
    }

    public function sendResetLink()
    {
        //$email = 'hmusoke9@gmail.com';
       $email =  $this->request->getVar('email');

        // $user = (new UserModel())->where('email', $email)->first();
        $user = model(UserModel::class)
    ->join('auth_identities', 'users.id = auth_identities.user_id')
    ->where('auth_identities.type', 'email_password')
    ->where('auth_identities.secret', $email)
    ->select('users.*')
    ->first();

        if (!$user) {
            return redirect()->back()->with('error', 'No user found with that email.');
        }

        // $passwords = service('passwords');

        // $passwords->forgot($user);
        service('passwords')->forgotten($user);

        return redirect()->back()->with('message', 'Password reset link sent to your email.');
    }

    public function resetPasswordForm()
    {
        $token =  $this->request->getVar('token');

        if (empty($token)) {
            return redirect()->to('/forgot-password')->with('error', 'Invalid reset token.');
        }

        echo view('layout', [
            'title' => 'Reset Password',
            'content' => '
                <h2>Reset Password</h2>
                <form method="post" action="' . site_url('reset-password?token=' . esc($token, 'url')) . '">
                    ' . csrf_field() . '
                    <label>New Password</label><br>
                    <input type="password" name="password" required><br><br>
                    <button type="submit">Reset Password</button>
                </form>
            ',
        ]);
    }

    public function handleReset()
    {
        $token = $this->request->getGet('token');
        $password = $this->request->getPost('password');

        if (empty($token) || empty($password)) {
            return redirect()->to('/forgot-password')->with('error', 'Invalid or missing data.');
        }

        $passwords = service('passwords');

        $result = $passwords->reset($token, $password);

        if (! $result->isOK()) {
            return redirect()->back()->with('error', $result->reason());
        }

        return redirect()->to('/login')->with('message', 'Password has been reset. You can now log in.');
    }
}
