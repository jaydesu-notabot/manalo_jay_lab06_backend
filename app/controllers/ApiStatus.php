<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiStatus extends Controller {

    public function index()
    {
        $this->call->library('api');
        $this->api->require_method('GET');
        $this->api->respond([
            'status' => 'ok',
            'message' => 'Product Management API is running',
        ]);
    }
}
