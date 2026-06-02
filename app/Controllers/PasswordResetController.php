<?php

namespace App\Controllers;

use App\Controllers\Traits\SecuresInput;
use CodeIgniter\RESTful\ResourceController;

class PasswordResetController extends ResourceController
{
    use SecuresInput;

    public function forgotPasswordForm()
    {
        return $this->respond([
            'status' => true,
            'message' => 'Submit your email address to request password help.',
        ]);
    }

    public function sendResetLink()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->respond([
                'status' => false,
                'message' => 'Password reset requests must be submitted by POST.',
            ], 405);
        }

        $email = $this->secureEmail($this->request->getVar('email'));
        if (!$email) {
            return $this->respond([
                'status' => false,
                'message' => 'Enter a valid email address.',
            ], 422);
        }

        log_message('info', 'Password reset requested for {email}', ['email' => $email]);

        return $this->respond([
            'status' => true,
            'message' => 'If that account exists, an administrator will help you reset access.',
        ]);
    }

    public function resetPasswordForm()
    {
        return $this->respond([
            'status' => true,
            'message' => 'Use the administrator password reset workflow to complete this request.',
        ]);
    }

    public function handleReset()
    {
        return $this->respond([
            'status' => false,
            'message' => 'Password reset links are not enabled for this workspace. Ask an administrator to reset the password from Staff Management.',
        ], 501);
    }
}
