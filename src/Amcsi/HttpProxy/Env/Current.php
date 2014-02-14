<?php
class Amcsi_HttpProxy_Env_Current extends Amcsi_HttpProxy_Env
{

    public function __construct()
    {
        parent::__construct(
            $_GET,
            $_POST,
            file_get_contents('php://input'),
            $_SERVER,
            apache_request_headers()
        );
    }
}

