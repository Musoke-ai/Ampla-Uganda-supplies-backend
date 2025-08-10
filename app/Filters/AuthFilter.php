<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

use App\Services\ValidateToken;

class AuthFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */

    protected $validate_token;

    public function __construct()
    {
        $this->validate_token = new ValidateToken();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        // $valid_token = $this->validate_token->validation();

        // if ($valid_token ) {
        //     return redirect()->to(base_url("api/invalid-access"));
        // }
        // helper("auth");
        // if (!auth("tokens")->loggedIn()){
        //     return redirect()->to(base_url("api/invalid-access"));
        // }


        $authHeader = $request->getHeader('Authorization');

        if ($authHeader) {
            $authHeader = $authHeader->getValue();

            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
                // You can now validate the token or perform further actions

                $valid_token = $this->validate_token->validation($token);

                if (!$valid_token) {
                    return redirect()->to(base_url("api/invalid-access"));
                }

                // return redirect()->to(base_url("api/invalid-access"));
                return;
            }
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
