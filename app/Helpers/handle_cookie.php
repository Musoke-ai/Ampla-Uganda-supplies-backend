<?php

if (!function_exists('No such function in this helper')) {
    function setSecureCookie($cookie_value)
    {
        // Get the response object
        $response = service('response');

        // Set the cookie parameters
        $name = 'secure_cookie';
        $value = $cookie_value;
        $expire = 120; // 2 minutes from now
        $path = '/';
        $domain = ''; // Set to your domain
        $secure = true; // Ensure the cookie is only sent over HTTPS
        $httpOnly = true; // Make the cookie HTTP-only

        // Set the cookie
        $response->setCookie($name, $value, $expire, $domain, $path, null, $secure, $httpOnly);

        // Return a response to the client
        return 'Cookie created';
    }
}

if (!function_exists('No such function in this helper')) {
    function getSecureCookie()
    {
        // Get the request instance
        $request = \Config\Services::request();
        // Retrieve the cookie
        $cookieValue = $request->getCookie('secure_cookie');

        // Check if the cookie exists
        if ($cookieValue === null) {
            return 'Cookie not found';
        }

        return  $cookieValue;
    }
}
