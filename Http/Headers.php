<?php

namespace ACDH\FCSSRU\Http;

/**
 * Very simple container meant to become API compatible with ZF2 Zend\Http\Header
 */

class Headers {
    private $headers = array();
    
    public function addHeaders($headers) {
        array_merge($this->headers, $headers); 
    }
}