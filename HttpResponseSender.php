<?php

namespace ACDH\FCSSRU;

use ACDH\FCSSRU\Http\Response;

class HttpResponseSender {
    public static function sendResponse(Response $response) {
        header($response->getHeaders()->toString());
        echo $response->getBody();
    }
}

