<?php

namespace ACDH\FCSSRU\Http;

use ACDH\FCSSRU\Http\Headers;

/**
 * Very simple container meant to become API compatible with ZF2 Zend\Http
 */
class Response {
    /**
     *
     * @var Headers 
     */
    private $headers;
    
    /**
     *
     * @var string
     */
    private $body;
    
    public function getHeaders() {
        if (!isset($this->headers)) {
            $this->headers = new Headers();
        }
        return $this->headers;
    }
    
    public function setContent($value) {
        $this->body = $value;
    }
    
    public function getBody() {
        return $this->body;
    }
}

