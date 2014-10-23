<?php

namespace ACDH\FCSSRU\Http;

/**
 * Very simple container meant to become API compatible with ZF2 Zend\Http\Header
 */

class Headers {
    private $headers = array();
    
    public function addHeaders($headers) {
        $this->headers = array_merge($this->headers, $headers);
    }
    
    public function toString() {
        $ret = '';
        foreach ($this->headers as $fieldName => $value) {
            $ret .= "$fieldName: $value\r\n";
        }
        return $ret;
    }
    
    public function clearHeaders()
    {
        $this->headers = array();
    }
}