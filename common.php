<?php

/* 
 * The MIT License
 *
 * Copyright 2016 OEAW/ACDH.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Common functions used by all the scripts using the mysql database.
 * 
 * @uses $operation
 * @uses $query
 * @uses $version
 * @uses $scanClause
 * @uses responseTemplate
 * @uses responseTemplateFcs
 * @package config
 */

namespace ACDH\FCSSRU;

\mb_internal_encoding('UTF-8');
\mb_http_output('UTF-8');
\mb_http_input('UTF-8');
\mb_regex_encoding('UTF-8'); 

/**
 * Configuration options
 */
if (!include_once $_SERVER['DOCUMENT_ROOT'] . '/../config/config.php' ) {
	require_once __DIR__ . '/config.php';
};

/**
 * Diagnostic messages
 */
require_once __DIR__ . '/diagnostics.php';

/**
 * vLib templating engine
 */
require_once $vlibPath;

/**
 * Container class for parameters described by the SRU interface 
 */
class SRUParameters {

    // params SRU
    /**
     * The operation requested by the client.
     * 
     * Mandatory. In strict mode a diagnostic error message is shown.<br/>
     * Passed as HTTP GET parameter "operation". If $sruMode is "strict" this is set to false if 
     * the paramter is missing else it's assumed to be "explain" <br/>
     * See also: {@link http://www.loc.gov/standards/sru/specs/index.html}
     * @type string|bool $operation
     */
    public $operation = "explain";

    /**
     * Contains a query expressed in CQL to be processed by the server
     * 
     * See {@link http://www.loc.gov/standards/sru/specs/cql.html CQL}.<br/>
     * Mandatory. In strict mode a diagnostic error message is shown.<br/>
     * Passed as HTTP GET parameter "query".
     * If $sruMode is "strict" this is set to false if the paramter is missing else it's assumed to be ""
     * @type string|bool
     */
    private $query = "";
    public function getQuery() {
        return $this->query;
    }
    public function setQuery($query) {
        $this->query = $query;
        $this->queryParts = $this->findCQLParts();
    }
    
    /**
     * Contains the individiual parts of either the query or the scanClause
     * 
     * Has keys index, operator and searchString
     * @type array 
     */
    public $queryParts = array();

    /**
     * The index to be browsed and the start point within it, expressed as a complete index, relation, term clause in CQL
     * 
     * See {@link http://www.loc.gov/standards/sru/specs/cql.html CQL}.<br/>
     * Mandatory. In strict mode a diagnostic error message is shown.<br/>
     * Passed as HTTP GET parameter "scanClause".
     * If $sruMode is "strict" this is set to false if the paramter is missing else it's assumed to be ""
     * @type string|bool
     */
    public $scanClause = "";

    /**
     * The position within the list of terms returned where the client would like the start term to occur
     * If the position given is 0, then the term should be immediately before the first term in the response.
     * If the position given is 1, then the term should be first in the list, and so forth up to the number of terms
     * requested plus 1, meaning that the term should be immediately after the last term in the response,
     * even if the number of terms returned is less than the number requested.
     * The range of values is 0 to the number of terms requested plus 1. The default value is 1.<br/>
     * Optional.<br/>
     * Passed as HTTP GET parameter "responsePosition". If the parameter is missing 1 is assumed.
     * @type integer
     */
    public $responsePosition = 1;

    /**
     * The number of terms which the client requests be returned
     * 
     * The actual number returned may be less than this, for example if the end of the term list is reached,
     * but may not be more. The explain record for the database may indicate the maximum number of terms which
     * the server will return at once. All positive integers are valid for this parameter. If not specified,
     * the default is server determined.<br/>
     * Optional.<br/>
     * Passed as HTTP GET parameter "maximumTerms". If the parameter is missing 10 is assumed.
     * @type integer $maximumTerms
     */
    public $maximumTerms = 100;

    /**
     * The version of the request, and a statement by the client that it wants the response to be less than, or preferably equal to, that version
     * 
     * See {@link http://www.loc.gov/standards/sru/specs/common.html#version Versions}.<br/>
     * Mandatory. In strict mode a diagnostic error message is shown.<br/>
     * Passed as HTTP GET parameter "version".
     * If $sruMode is "strict" this is set to false if the paramter is missing else it's assumed to be "1.2"
     * @type string|bool
     */
    public $version = "1.2";

    /**
     * The number of records requested to be returned
     * The value must be 0 or greater. Default value if not supplied is determined by the server.
     * The server MAY return less than this number of records, for example if there are fewer matching records
     * than requested, but MUST NOT return more than this number of records.<br/>
     * Optional.<br/>
     * Passed as HTTP GET parameter "maximumRecords". If the parameter is missing "10" is assumed.
     * @type integer
     */
    public $maximumRecords = 10;

    /**
     * The position within the sequence of matched records of the first record to be returned
     * 
     * The first position in the sequence is 1. The value supplied MUST be greater than 0.<br/>
     * Optional.<br/>
     * Passed as HTTP GET parameter "startRecord". If the parameter is missing "1" is assumed.
     * @type integer
     */
    public $startRecord = 1;

    /**
     * A string to determine how the record should be escaped in the response
     * 
     * Defined values are 'string' and 'xml'. The default is
     * 'xml'. See {@link http://www.loc.gov/standards/sru/specs/search-retrieve.html#records Records}.<br/>
     * Optional.<br/>
     * Passed as HTTP GET parameter "recordPacking". If the parameter is missing "xml" is assumed.
     * @type string
     */
    public $recordPacking = "xml";

    /**
     * The schema in which the records MUST be returned
     * 
     * The value is the URI identifier for the schema or the short name for it
     * published by the server. The default value if not supplied is
     * determined by the server. See {@link http://www.loc.gov/standards/sru/resources/schemas.html Record Schemas}.<br/>
     * Optional.<br/>
     * Passed as HTTP GET parameter "recordSchema". If the parameter is missing "" is assumed.
     * @type string
     */
    public $recordSchema = "";

    /**
     * A URL for a stylesheet
     * 
     * The client requests that the server simply return this URL in the response.<br/>
     * See {@link http://www.loc.gov/standards/sru/specs/common.html#stylesheet Stylesheets}.<br/>
     * Optional.<br/>
     * Passed as HTTP GET parameter "stylesheet". If the parameter is missing "" is assumed.
     * @type string
     */
    public $stylesheet = "";

    /**
     * Provides additional information for the server to process.
     * 
     * See {@link http://www.loc.gov/standards/sru/specs/common.html#extraData Extensions}.<br/>
     * Optional.<br/>
     * Passed as HTTP GET parameter "extraRequestData". If the parameter is missing "" is assumed.
     * @type string
     */
    public $extraRequestData = "";

    /**
     * The number of seconds for which the client requests that the result set created should be maintained
     * 
     * The server MAY choose not to fulfil this request, and may respond with a different number of seconds.
     * If resultSetTTL is not supplied then the server will determine the value.
     * See {@link http://www.loc.gov/standards/sru/specs/search-retrieve.html#resultsets Result Sets}.
     * 
     * Passed as HTTP GET parameter "extraRequestData". If the parameter is missing "" is assumed.
     * @type interger | string
     */
    public $resultSetTTL = "";

    /**
     * An opaque key for xdebug.
     */
    public $xdebugSessionStart;

    /**
     * Creates a new container class for FCS and SRU parameters
     * 
     * Initializes all the member variables using the parameters passed in $_GET (filtered).    
     * 
     * @param string $sruMode
     * @uses $sruMode
     */
    public function __construct($sruMode) {
        $operation = filter_input(INPUT_GET, 'operation', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($operation)) {
            $this->operation = $operation;
        } else {
            $this->operation = ($sruMode == "strict") ? false : "explain";
        }
        // Filter encodes UTF-8 as HTML entities (FILTER_FLAG_ENCODE_HIGH). 
        // If sth. else did the same with (part of) the string then everything
        // is decoded below which leaves us with the right php string. 
        $query = filter_input(INPUT_GET, 'query', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($query)) {
            $this->query = utf8_decode(html_entity_decode_numeric(trim($query)));
            // TODO: what's this for ???
            $this->query = str_replace("|", "#", $this->query);
            $this->queryParts = $this->findCQLParts();
        } else {
            $this->query = ($sruMode == "strict") ? false : "";
        }
        $scanClause = filter_input(INPUT_GET, 'scanClause', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($scanClause)) {
            $this->scanClause = utf8_decode(html_entity_decode_numeric(trim($scanClause)));
            $this->queryParts = $this->findCQLParts();
        } else {
            $this->scanClause = ($sruMode == "strict") ? false : "";
        }
        $responsePosition = filter_input(INPUT_GET, 'responsePosition', FILTER_VALIDATE_INT);
        if (isset($responsePosition)) {
            $this->responsePosition = $responsePosition;
        } else {
            $this->responsePosition = 1;
        }
        $maximumTerms = filter_input(INPUT_GET, 'maximumTerms', FILTER_VALIDATE_INT);
        if (isset($maximumTerms)) {
            $this->maximumTerms = $maximumTerms;
        }
        $version = filter_input(INPUT_GET, 'version', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($version)) {
            $this->version = trim($version);
        } else {
            $this->version = ($sruMode == "strict") ? false : "1.2";
        }
        $maximumRecords = filter_input(INPUT_GET, 'maximumRecords', FILTER_VALIDATE_INT);
        if (isset($maximumRecords)) {
            $this->maximumRecords = $maximumRecords;
        }
        $startRecord = filter_input(INPUT_GET, 'startRecord', FILTER_VALIDATE_INT);
        if (isset($startRecord)) {
            $this->startRecord = $startRecord;
        }
        $recordPacking = filter_input(INPUT_GET, 'recordPacking', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($recordPacking)) {
            $this->recordPacking = trim($recordPacking);

            if ($this->recordPacking === "") {
                $this->recordPacking = "xml";
            }
            // TODO: why ... ???
            if ($this->recordPacking !== "xml") {
                $this->recordPacking = "raw";
            }
        }
        $recordSchema = filter_input(INPUT_GET, 'recordSchema', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($recordSchema)) {
            $this->recordSchema = trim($recordSchema);
        }
        $stylesheet = filter_input(INPUT_GET, 'stylesheet', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($stylesheet)) {
            $this->stylesheet = trim($stylesheet);
        }
        $extraRequestData = filter_input(INPUT_GET, 'extraRequestData', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($extraRequestData)) {
            $this->extraRequestData = trim($extraRequestData);
        }
        $resultSetTTL = filter_input(INPUT_GET, 'resultSetTTL', FILTER_VALIDATE_INT);
        if (isset($resultSetTTL)) {
            $this->resultSetTTL = $resultSetTTL;
        }
        $xdebugSessionStart = filter_input(INPUT_GET, 'XDEBUG_SESSION_START', FILTER_UNSAFE_RAW);
        if (isset($xdebugSessionStart)) {
            $this->xdebugSessionStart = $xdebugSessionStart;
        }
    }

    protected $url;

    /**
     * Concats $url with the given $paramName and $paramValue
     * 
     * @param string url The parameter part of the URL. Initially it's assumed to be just ?
     * @param string paramName A parameter name to be added.
     * @param string paramValue A parameter value to be added.
     */
    protected function addParamToUrl($paramName, $paramValue) {
        $this->url = $this->url . ($this->url == "?" ? "" : "&") . "$paramName=". urlencode($paramValue);
    }

    /**
     * Concats $url with the given $paramName and $paramValue
     * 
     * Like $this->AddParamToUrl but adds parameter checking.
     * @param string url The parameter part of the URL. Initially it's assumed to be just ?
     * @param string paramName A parameter name to be added.
     * @param string paramValue A parameter value to be added.
     */
    protected function addParamToUrlIfNotEmpty($paramName, $paramValue) {
        if (($paramValue !== false) && ($paramValue !== "")) {
            $this->addParamToUrl($paramName, $paramValue);
        }
    }
    
    /**
    * Tries to find the index, operator and searchString (start string for scan) in
    * either the query parameter or the scanClause parameter.
    * @return array An array that has all the groups found by preg_match. The
    *               index, operator and searchString found are contained as 
    *               key value pairs.
    */
    protected function findCQLParts() {
        $cqlIdentifier = '("([^"])*")|([^\s()=<>"\/]*)';
        $matches = array();
        $regexp = '/(?<index>'.$cqlIdentifier.') *(?<operator>(==?)|(>=?)|(<=?)|('.$cqlIdentifier.')) *(?<searchString>'.$cqlIdentifier.')/';
        $clause = ($this->query !== false && $this->query !== '') ? $this->query : $this->scanClause;
        preg_match($regexp, $clause, $matches);
        $matches['index'] = trim($matches['index'], '"');
        $matches['operator'] = trim($matches['operator'], '"');
        $matches['searchString'] = trim($matches['searchString'], '"');
        if ($this->operation !== 'scan' && $matches['searchString'] === '') {
            $matches['searchString'] = $matches['index'];
            $matches['index'] = '';
        }
        return $matches;
    }
    
    /**
     * Generates the query url including all mandatory and optional params
     * 
     * @uses $operation
     * @uses $query
     * @uses $scanClause
     * @uses $responsePosition
     * @uses $maximumTerms
     * @uses $version
     * @uses $maximumRecords
     * @uses $startRecord
     * @uses $recordPacking
     * @uses $recordSchema
     * @uses $resultSetTTL
     * @uses $stylesheet
     * @uses $extraRequestData
     * @uses $xformat
     * @uses $xdataview
     * @param string $endPoint The (upstream) endpoint for the query URL
     * @param string type If "fcs.resource" or "fcs" x-context is used else ignored.
     * @return string A URL string that can be used to execute the query.
     */
    public function getQueryUrl($endPoint, $type = null) {
        $this->url = "?";

        //mandatory params for all operations
        $this->addParamToUrl("operation", $this->operation);
        $this->addParamToUrl("version", $this->version);

        //optional params for all operations
        $this->addParamToUrlIfNotEmpty("stylesheet", $this->stylesheet);
        $this->addParamToUrlIfNotEmpty("extraRequestData", $this->extraRequestData);
        //pass on XDEBUG_SESSION_START
        if ($type !== 'ske') {
//            $this->addParamToUrlIfNotEmpty("XDEBUG_SESSION_START", $this->xdebugSessionStart);
//            $this->addParamToUrl("XDEBUG_SESSION_START", 'netbeans-xdebug');
        }

        switch ($this->operation) {
            case "explain":
                //optional
                $this->addParamToUrlIfNotEmpty("recordPacking", $this->recordPacking);
                return $endPoint . $this->url;
            case "scan":
                //mandatory
                $this->addParamToUrl("scanClause", $this->scanClause);
                //optional
                $this->addParamToUrlIfNotEmpty("responsePosition", $this->responsePosition);
                $this->addParamToUrlIfNotEmpty("maximumTerms", $this->maximumTerms);
                return $endPoint . $this->url;
            case "searchRetrieve":
                //mandatory
                $this->addParamToUrl("query", $this->query);
                //optional
                $this->addParamToUrlIfNotEmpty("startRecord", $this->startRecord);
                $this->addParamToUrlIfNotEmpty("maximumRecords", $this->maximumRecords);
                $this->addParamToUrlIfNotEmpty("recordPacking", $this->recordPacking);
                $this->addParamToUrlIfNotEmpty("recordSchema", $this->recordSchema);
                $this->addParamToUrlIfNotEmpty("resultSetTTL", $this->resultSetTTL);
                return $endPoint . $this->url;
            default:
                //"Unsupported parameter value"
                diagnostics(6, "operation: '$this->operation'");
                break;
        }
    }

    /**
     *
     * @var array 
     */
    public $xsltParameters;
    
    /**
     * Pass parameters represented by this object into an XsltProcessor
     * 
     * @param XsltProcessor $proc The processor the parameters should be passed to
     */
    public function passParametersToXSLTProcessor($proc) {
        if (isset($this->operation) && ($this->operation !== false)) {
            $proc->setParameter('', 'operation', $this->operation);
        } else {
            $proc->setParameter('', 'operation', 'explain');
        }
        $proc->setParameter('', 'startRecord', $this->startRecord);
        $proc->setParameter('', 'maximumRecords', $this->maximumRecords);
        $proc->setParameter('', 'scanClause', $this->scanClause);
        $proc->setParameter('', 'q', $this->query);
        if (isset($this->queryParts['index'])) {
            $proc->setParameter('', 'index', $this->queryParts['index']);        
            $proc->setParameter('', 'operator', $this->queryParts['operator']);
            $proc->setParameter('', 'searchString', $this->queryParts['searchString']);
        }
        $proc->setParameter('', 'XDEBUG_SESSION_START', $this->xdebugSessionStart);
        $this->xsltParameters = $this->getParameterForXSLTProcessor($proc);
    }
    
    protected $paramList = array();

    /**
     * Get all the used Xslt parameters and return them as an array.
     * 
     * @param \XsltProcessor $proc
     * @param array $paramList
     * @return array
     */
    public function getParameterForXSLTProcessor($proc, $paramList = null) {
        if (isset($paramList)) {
            $ret = array_flip($paramList);
        } else {
            $ret = array(
                'operation' => '',
                'startRecord' => '',
                'maximumRecords' => '',
                'scanClause' => '',
                'x-context' => '',
                'q' => '',
                'index' => '',
                'operator' => '',
                'searchString' => '',
                'XDEBUG_SESSION_START' => '',
            );
        }
        $ret = array_merge($this->paramList, $ret);
        $this->paramList = $ret;
        foreach ($ret as $param => $value) {
            $ret[$param] = $proc->getParameter('', $param);
        }
        return $ret;
    }
}

/**
 * Container class for FCS and SRU parameters
 */
class SRUWithFCSParameters extends SRUParameters {

    //additional params - non SRU
    /**
     * The x-context parameter passed by the client.
     * 
     * Used to specify the resources for which the operation is to be performed. Resources are separated by ",".
     * An extension to the SRU standard parameter set. Inspired by x-cmd-context where cmd stands for Component MetaData.<br/>
     * Passed as HTTP GET parameter "x-context". If the parameter is missing HTTP GET parameter "x-cmd-context" takes its place. If both are missing "" is assumed.
     * See also: {@link http://www.clarin.eu/fcs}<br/>
     * {@link http://www.clarin.eu/cmdi}
     * @type string
     */
    public $xcontext = "";

    /**
     * The x-format parameter passed by the client
     * 
     * Used to specify the response format expected by the client. Possible values include "html", "xsltproc", "xsl" and "img".
     * On other values XML is assumed as requested response format.
     * FIXME: and others???
     * @type string
     */
    public $xformat = "";

    /**
     * The x-dataview parameter passed by the client
     * 
     * Used to specify which views on the result shall be returned as response.
     * Possible values include "kwic", "full", "title", "facs", "navigation" and "xmlescaped".
     * On other values "the result is undefined "kwic" is assumed.
     * @type string
     */
    public $xdataview = "kwic,title";

    /**
     * The x-filter parameter passed by the client
     * 
     * Used to specify a searchString the is used to filter the scan result.
     * This is in opposite to the searchString from the scan clause that just
     * marks the start of the listing.
     * May use word (exact), word* (startsWith), *word (endsWith) or
     * *word* (contains)
     * @type string|false
     */
    public $xfilter = false;
    
    /**
     * The x-mode parameter passed by the client
     * 
     * Used to determine if the list used for generating the scan result
     * that may be expensive to generate should be refreshed.
     * Only valid value is refresh
     * @type string|false
     */
    
    public $xmode = false;

    /**
     * All contexts/resources given by the HTTP GET parameter "x-context" as array
     *
     * @uses $xcontext
     * @type array
     */
    public $context = "";
    
    /**
     * In CQL/SRU 2.0 there is a queryType parameter. We will use this as a switch
     * to pass a query in e. g. Corpus QL to the endpoints if they support that
     */
    
    public $queryType = "";
    
    /**
     * In some cases the switch is called using localhost on behalf of some script
     * Then the real hostname needs to be supplied to the XSL transforms instead
     * of localhost
     */
    
    public $xrealhostname = "";
       
    /**
     * A comma separated list of languages the user knows so the output
     * can be generated accordingly
     */
    
    public $xuserlangs = "";

    /**
     * Creates a new container class for FCS and SRU parameters
     * 
     * Initializes all the member variables using the parameters passed in $_GET. 
     * @param string $sruMode
     */
    public function __construct($sruMode) {
        parent::__construct($sruMode);
        $xcontext = filter_input(INPUT_GET, 'x-context', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if (isset($xcontext)) {
            $this->xcontext = $xcontext;
        }
        $xcontext2 = filter_input(INPUT_GET, 'x-cmd-context', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if (($this->xcontext === "") && isset($xcontext2)) {
            $this->xcontext = $xcontext2;
        }
        /* default for displaying something nice if no parameters are set so it's likely a human trying to use a switch URL */
        if ($this->operation === false && $this->version === false) {
            $this->xformat = "htmlpagetable";
        }
        $xformat = filter_input(INPUT_GET, 'x-format', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if (isset($xformat)) {
            $this->xformat = trim($xformat);
        }
        $xdataview = filter_input(INPUT_GET, 'x-dataview', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if (isset($xdataview)) {
            $this->xdataview = trim($xdataview);
        }
        $xfilter = filter_input(INPUT_GET, 'x-filter',  FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($xfilter)) {
            $this->xfilter = utf8_decode(html_entity_decode_numeric(trim($xfilter)));
        }
        $xmode = filter_input(INPUT_GET, 'x-mode',  FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if (isset($xmode)) {
            $this->xmode = trim($xmode) === 'refresh' ? 'refresh' : false;
        }
        $queryType = filter_input(INPUT_GET, 'queryType', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if (isset($queryType)) {
            $this->queryType = trim($queryType) === 'native' ? 'native' : '';
        }
        $this->context = explode(",", $this->xcontext);
        $xrealhostname = filter_input(INPUT_GET, 'x-realhostname', FILTER_SANITIZE_URL);
        if (isset($xrealhostname)) {
            $this->xrealhostname = $xrealhostname;
        }
        $xuserlangs = filter_input(INPUT_GET, 'x-userlangs', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if (isset($xuserlangs)) {
            $this->xuserlangs  = join(',', prefered_languages(trim($xuserlangs)));
        } else {
            $this->xuserlangs = join(',', prefered_languages());
        }
    }

    /**
     * Generates the query url including all mandatory and optional params
     * 
     * @uses $xcontext
     * @uses $xformat
     * @uses $xdataview
     * @param string $endPoint The (upstream) endpoint for the query URL
     * @param string type If "fcs.resource" or "fcs" x-context is used else ignored.
     * @return string A URL string that can be used to execute the query.
     */
    public function getQueryUrl($endPoint, $type = null) {        
        parent::getQueryUrl($endPoint, $type);
        switch ($this->operation) {
            // Add the same things for every operation.
            case "explain":
            case "scan":
                if ($type !== 'ske') {
                    $this->addParamToUrlIfNotEmpty('x-mode', $this->xmode);
                    $this->addParamToUrlIfNotEmpty("x-filter", $this->xfilter);
                }
                // plus the following
            case "searchRetrieve":
                if ($this->operation === 'searchRetrieve') {
                    $this->addParamToUrlIfNotEmpty("queryType", $this->queryType);
                }
                if ($type !== 'ske') {
                    $this->addParamToUrl("x-context", $this->xcontext);
                    if ($this->operation === 'searchRetrieve') {
                        $this->addParamToUrlIfNotEmpty("x-dataview", $this->xdataview);
                    }
                    if (stripos($this->xformat, "html") === false) {
                        $this->addParamToUrlIfNotEmpty("x-format", $this->xformat);
                    }
                }
                return $endPoint . $this->url;
            default:
                //"Unsupported parameter value"
                diagnostics(6, "operation: '$this->operation'");
                break;
        }
    }
    
    public function passParametersToXSLTProcessor($proc) {
        parent::passParametersToXSLTProcessor($proc);
        $proc->setParameter('', 'format', $this->xformat);
        $proc->setParameter('', 'queryType', $this->queryType);
        $proc->setParameter('', 'x-dataview', $this->xdataview);
        $proc->setParameter('', 'x-context', $this->xcontext);
        if ($this->xrealhostname !== "") {
            $proc->setParameter('', 'site_url', $this->xrealhostname);
        } else {
            $proc->setParameter('', 'site_url', (isset($_SERVER['HTTPS']) ? 'https://':'http://').$_SERVER['SERVER_NAME']);
        }        
        if ($this->xuserlangs !== "") {
            $proc->setParameter('', 'user_langs', $this->xuserlangs);
        } 
        $this->xsltParameters = $this->getParameterForXSLTProcessor($proc);
        }
    /**
     * Get all the used Xslt parameters and return them as an array.
     * 
     * @param \XsltProcessor $proc
     * @return array
     */
    public function getParameterForXSLTProcessor($proc, $paramList = null) {
        if (!isset($paramList)) {
            $paramList = array();
        }
        $paramList = array_merge($paramList, array('x-dataview', 'x-context', 'format', 'queryType', 'site_url', 'user_langs'),
                    array_keys(parent::getParameterForXSLTProcessor($proc)));
        $ret = parent::getParameterForXSLTProcessor($proc, $paramList);
        return $ret;
    }    
}

/**
 * Predefined container object for SRU and FCS script parameters
 * 
 * Proposal for a naming convention.
 * 
 * @global SRUWithFCSParameters $sru_fcs_params;
 */
$sru_fcs_params;

// only 5.3: define("ENT_HTML401", 0);

/**
 * Decodes all HTML entities, including numeric and hexadecimal ones.
 * 
 * Helper function to fully decode html entities including numeric entities
 * see http://stackoverflow.com/questions/2764781/how-to-decode-numeric-html-entities-in-php
 * @param string|array $string A string or array of strings that should be decoded into UTF-8. 
 * @param const $flags Flags used by html_entity_decode see it's documentation
 * @param string $charset Charset used by html_entity_decode. Noter: Other replcements are UTF-8 only.
 * @return string UTF-8 encoded string.
 */
function html_entity_decode_numeric($string, $flags = NULL, $charset = "UTF-8") {
    if (!isset($flags)) {
        $flags = (ENT_COMPAT | ENT_HTML401);
    }
    $namedEntitiesDecoded = html_entity_decode($string, $flags, $charset);
    $hexEntitiesDecoded = preg_replace_callback('~&#x([0-9a-fA-F]+);~i', "\\ACDH\\FCSSRU\\chr_utf8_callback_hex", $namedEntitiesDecoded);
    $decimalEntitiesDecoded = preg_replace_callback('~&#([0-9]+);~', '\\ACDH\\FCSSRU\\chr_utf8_callback', $hexEntitiesDecoded);
    return $decimalEntitiesDecoded;
}

/**
 * Callback helper 
 */
function chr_utf8_callback_hex($matches) {
    return chr_utf8(hexdec($matches[1]));
}

function chr_utf8_callback($matches) {
    return chr_utf8($matches[1]);
}

/**
 * Multi-byte chr(): Will turn a numeric argument into a UTF-8 string.
 * 
 * @param mixed $num
 * @return string
 */
function chr_utf8($num) {
    if ($num < 128) {
        return chr($num);
    }
    if ($num < 2048) {
        return chr(($num >> 6) + 192) . chr(($num & 63) + 128);
    }
    if ($num < 65536) {
        return chr(($num >> 12) + 224) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    }
    if ($num < 2097152) {
        return chr(($num >> 18) + 240) . chr((($num >> 12) & 63) + 128) . chr((($num >> 6) & 63) + 128) . chr(($num & 63) + 128);
    }
    return '';
}

/**
 * Converts any HTML-entities into characters
 * 
 * from http://php.net/manual/de/function.mb-decode-numericentity.php
 * @param string $string One ore more characters to convert.
 */
function html_decimal_numeric2utf8_character($string) {
    $convmap = array(0xFF, 0x2FFFF, 0, 0xFFFF);
    return mb_decode_numericentity($string, $convmap, 'UTF-8');
}

/**
 * Converts any "non-ASCII" characters into HTML-entities
 * 
 * That is anything that is a known as a "control character" and anything
 * above code point 127.
 * from http://php.net/manual/de/function.mb-decode-numericentity.php
 * @param string $string One or more characters to convert.
 */
function utf8_character2html_decimal_numeric($string) {
    $convmap = array(0x0, 0x1F, 0, 0xFFFFFF, /* control characters, should be unused */
        0x7F, 0x2FFFF, 0, 0xFFFFFF, /* mb characters */);
    return mb_encode_numericentity($string, $convmap, 'UTF-8');
}

/**
 * Initializes the global object holding the parameters and switches off the
 * header declaration of xml on request. (TODO discuss ???)
 * @param string mode Chack parameter "strict" or use "lax" checking.
 * switch uses the strict mode.  
 * @uses $sru_fcs_params
 */
function  getParamsAndSetUpHeader($mode = "lax") {
    global $sru_fcs_params;

    $sru_fcs_params = new SRUWithFCSParameters($mode);

    if ($sru_fcs_params->recordPacking === "xml") {
        header("content-type: text/xml; charset=UTF-8");
    }
}
/**
 * Reverse to parse_url
 * @param array $parsed_url
 * @return string
 */
function unparse_url($parsed_url) {
  $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
  $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
  $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
  $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
  $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
  $pass     = ($user || $pass) ? "$pass@" : '';
  $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
  $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
  $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
  return "$scheme$user$pass$host$port$path$query$fragment";
} 

/** 
 * determine which language out of an available set the user prefers most
 * from: http://stackoverflow.com/a/6038460
 *
 * @param string $available_languages array with language-tag-strings (must be lowercase) that are available 
 * @param string $http_accept_language a HTTP_ACCEPT_LANGUAGE string (read from $_SERVER['HTTP_ACCEPT_LANGUAGE'] if left out)
 * @return array preferred languages as weighted array 
 */ 
function prefered_languages ($http_accept_language="auto") { 
    // if $http_accept_language was left out, read it from the HTTP-Header 
    if ($http_accept_language == "auto") {
        $http_accept_language = filter_input(INPUT_SERVER, 'HTTP_ACCEPT_LANGUAGE', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    }
    
    $hits = array();
    // standard  for HTTP_ACCEPT_LANGUAGE is defined under 
    // http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4 
    // pattern to find is therefore something like this: 
    //    1#( language-range [ ";" "q" "=" qvalue ] ) 
    // where: 
    //    language-range  = ( ( 1*8ALPHA *( "-" 1*8ALPHA ) ) | "*" ) 
    //    qvalue         = ( "0" [ "." 0*3DIGIT ] ) 
    //            | ( "1" [ "." 0*3("0") ] ) 
    preg_match_all('~(?<lang>(?<mainlang>[[:alpha:]]{1,8})-?(?<sublang>[[:alpha:]|-]{1,8}))?' . 
                   '(\s*;\s*q\s*=\s*(?<qvalue>1\.0{0,3}|0\.\d{0,3}))?\s*(,|$)~i', 
                   $http_accept_language, $hits, PREG_SET_ORDER);  
    
    $ret = array();
    $qvalues = array();
       
    $qvalue = 1.0;
    foreach ($hits as $hit) {
        if (empty($hit['lang'])) {continue;}
        // read data from the array of this hit
        array_push($ret, $hit['lang']); 
        if (!empty($hit['qvalue'])) {
            $qvalue = floatval($hit['qvalue']); 
        }
        $qvalues[$hit['lang']] = $qvalue;
        $qvalue *= 0.999;
    } 
    
    usort($ret, function($a, $b) use ($qvalues) {
        if ($qvalues[$a] > $qvalues[$b]) {return -1;}
        elseif ($qvalues[$a] === $qvalues[$b]) {return 0;}
        else {return 1;}
    });
    return $ret; 
}

class ErrorOrWarningException extends \Exception
{
    protected $_Context = null;
    public function getContext()
    {
        return $this->_Context;
    }
    public function setContext( $value )
    {
        $this->_Context = $value;
    }

    public function __construct( $code, $message, $file, $line, $context )
    {
        parent::__construct( $message, $code );

        $this->file = $file;
        $this->line = $line;
        $this->setContext( $context );
    }
    
    public static $code_has_known_errors = false;
}

/**
 * Inspire to write perfect code. everything is an exception, even minor warnings.
 **/
function error_to_exception( $code, $message,  $file, $line, $context )
{
    if (!ErrorOrWarningException::$code_has_known_errors) {
      throw new ErrorOrWarningException( $code, $message, $file, $line, $context );
    }
}
set_error_handler( 'ACDH\FCSSRU\error_to_exception' );