<?php
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

/**
 * Configuration options
 */
require_once '../utils-php/config.php';

/**
 * Diagnostic messages
 */
require_once '../utils-php/diagnostics.php';

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
    public $query = "";

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
     * Passed as HTTP GET parameter "responsePosition". If the parameter is missing "" is assumed.
     * @type integer|string
     */
    public $responsePosition = "";

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
    public $maximumTerms = 10;

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
        $query = filter_input(INPUT_GET, 'query', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($query)) {
            $this->query = trim($query);
        } else {
            $this->query = ($sruMode == "strict") ? false : "";
        }
        $scanClause = filter_input(INPUT_GET, 'scanClause', FILTER_UNSAFE_RAW, FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_ENCODE_HIGH);
        if (isset($scanClause)) {
            $this->scanClause = trim($scanClause);
        } else {
            $this->scanClause = ($sruMode == "strict") ? false : "";
        }
        $responsePosition = filter_input(INPUT_GET, 'responsePosition', FILTER_VALIDATE_INT);
        if (isset($responsePosition)) {
            $this->responsePosition = $responsePosition;
        } else {
            $this->responsePosition = "";
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
    public $xcontext;

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
     * All contexts/resources given by the HTTP GET parameter "x-context" as array
     *
     * @uses $xcontext
     * @type array
     */
    public $context = "";

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
        $xformat = filter_input(INPUT_GET, 'x-format', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if (isset($xformat)) {
            $this->xformat = trim($xformat);
        }
        $xdataview = filter_input(INPUT_GET, 'x-dataview', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
        if (isset($xdataview)) {
            $this->xdataview = trim($xdataview);
        }
        $this->context = explode(",", $this->xcontext);
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

define ("ENT_HTML401", 0);

/**
 * Decodes all HTML entities, including numeric and hexadecimal ones.
 * 
 * Helper function to fully decode html entities including numeric entities
 * see http://stackoverflow.com/questions/2764781/how-to-decode-numeric-html-entities-in-php
 * @param string|array $string A string or array of strings that should be decoded into UTF-8. 
 * @param const $flags Flags used by html_entity_decode see it's documentation
 * @param string $charset Charset used by html_entity_decode. Noter: Other replcements are UTF-8 only.
 * @return string decoded HTML
 */
function html_entity_decode_numeric($string, $flags = NULL, $charset = "UTF-8") {
    if (!isset($flags)) {
        $flags = (ENT_COMPAT | ENT_HTML401);
    }
    $namedEntitiesDecoded = html_entity_decode($string, $flags, $charset);
    $hexEntitiesDecoded = preg_replace_callback('~&#x([0-9a-fA-F]+);~i', "chr_utf8_callback", $namedEntitiesDecoded);
    $decimalEntitiesDecoded = preg_replace('~&#([0-9]+);~e', 'chr_utf8("\\1")', $hexEntitiesDecoded);
    return $decimalEntitiesDecoded;
}

/**
 * Callback helper 
 */
function chr_utf8_callback($matches) {
    return chr_utf8(hexdec($matches[1]));
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
 function html_decimal_numeric2utf8_character($string)
 {
     $convmap = array(0xFF, 0x2FFFF, 0, 0xFFFF);
     return mb_decode_numericentity($string, $convmap, 'UTF-8');
 }
 
 /**
  * Converts any characters into HTML-entities
  * 
  * from http://php.net/manual/de/function.mb-decode-numericentity.php
  * @param string $string One or more characters to convert.
  */
 function utf8_character2html_decimal_numeric($string)
 {
     $convmap = array(0x0, 0x1F, 0, 0xFFFFFF, /*control characters, should be unused*/
         0xFF, 0x2FFFF, 0, 0xFFFFFF, /*mb characters*/);
     return mb_encode_numericentity($string, $convmap, 'UTF-8');
 }
