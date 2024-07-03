<?php

// namespace App\Controllers;

// class EmailVerification extends BaseController
// {
//     public function sendVerificationEmail($user_id)
//     {
//         $user = new UserModel(); // Assuming a UserModel exists
//         $user->find($user_id);
    
//         $verification_code = $user->createEmailVerificationCode();
//         $verification_url = base_url('email-verification/verify/' . $verification_code);
    
//         // Send email using Email library
//         $this->email->setTo($user->email);
//         $this->email->setSubject('Verify Your Email');
//         $this->email->setMessage("Please click this link to verify your email: {$verification_url}");
//         $this->email->send();
//     }
// }
