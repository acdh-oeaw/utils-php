<?php
/* This class is part of the epiphany framework which is licensed as follows:
 * 
 * Copyright (c) 2007, Jaisen Mathai
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * * Redistributions of source code must retain the above copyright
 * notice, this list of conditions and the following disclaimer.
 * * Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 * * The names of its contributors may NOT be used to endorse or promote
 * products derived from this software without specific prior written
 * permission.
 *
 * THIS SOFTWARE IS PROVIDED BY Jaisen Mathai "AS IS" AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL Jaisen Mathai BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * This is the version of the class stored at
 * https://github.com/jmathai/php-multi-curl.git
 * 
 * There is an open issue with curl_multi_select() which may return -1
 * forever because of the way the spec is interpreted. See
 * https://bugs.php.net/bug.php?id=63411
 * https://bugs.php.net/bug.php?id=63842
 * A workaround is published here:
 * https://github.com/jmathai/php-multi-curl/issues/14
 */

namespace jmathai\phpMultiCurl;

class EpiCurl
{
  const timeout = 3;
  private static $inst = null;
  private static $singleton = 0;
  private $mc;
  private $msgs;
  private $running;
  private $execStatus;
  private $selectStatus;
  private $sleepIncrement = 1.1;
  private $requests = array();
  private $responses = array();
  private $properties = array();

  /**
   * This is a non-constructor as this is a singleton class
   * 
   * @throws Exception
   */
  function __construct()
  {
    if(self::$singleton == 0)
    {
      throw new Exception('This class cannot be instantiated by the new keyword.  You must instantiate it using: $obj = EpiCurl::getInstance();');
    }

    $this->mc = curl_multi_init();
    $this->properties = array(
      'code'  => CURLINFO_HTTP_CODE,
      'time'  => CURLINFO_TOTAL_TIME,
      'length'=> CURLINFO_CONTENT_LENGTH_DOWNLOAD,
      'type'  => CURLINFO_CONTENT_TYPE,
      'url'   => CURLINFO_EFFECTIVE_URL
      );
  }

  /**
   * Adds a curl request to the queue and starts executing it.
   * @param curlhandle $ch Ein von curl_init() zurückgegebenes cURL-Handle.
   * @return \jmathai\phpMultiCurl\EpiCurlManager
   */
  public function addCurl($ch)
  {
    $key = $this->getKey($ch);
    $this->requests[$key] = $ch;
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'headerCallback'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $code = curl_multi_add_handle($this->mc, $ch);
    
    // (1)
    if($code === CURLM_OK || $code === CURLM_CALL_MULTI_PERFORM)
    {
      do {
          $code = $this->execStatus = curl_multi_exec($this->mc, $this->running);
      } while ($this->execStatus === CURLM_CALL_MULTI_PERFORM);

      return new EpiCurlManager($key);
    }
    else
    {
      return $code;
    }
  }

  /**
   * Waits for the data for the given handle to arrive.
   * @param type $key The request the data of which should be fetched.
   * @return boolean|null|array Returns false if no $key was provided (or null)
   *    Returns null if no data could be fetched (there could be other infos)
   *    Returns an array with data and additional infos if the request succedes.
   */
  public function getResult($key = null)
  {
    if($key != null)
    {
      if(isset($this->responses[$key]['data']))
      {
        return $this->responses[$key];
      }

      $innerSleepInt = $outerSleepInt = 1;
      while($this->running && ($this->execStatus == CURLM_OK || $this->execStatus == CURLM_CALL_MULTI_PERFORM))
      {
        $this->curlMultiExec();
        usleep($outerSleepInt);
        $outerSleepInt *= $this->sleepIncrement;
        $ms=curl_multi_select($this->mc);
        if ($ms === -1) {
           usleep(100);
           $ms=curl_multi_select($this->mc);
        }
        if($ms >= 0) {
            $this->curlMultiExec();
        }
        $this->storeResponses();
        if(isset($this->responses[$key]['data']))
        {
          return $this->responses[$key];
        }
        $runningCurrent = $this->running;
      }
      return null;
    }
    return false;
  }
  
  private function curlMultiExec() {
        $innerSleepInt = 1;
        do{
            $this->execStatus = curl_multi_exec($this->mc, $this->running);
            usleep($innerSleepInt);
            $innerSleepInt *= $this->sleepIncrement;
        }while($this->execStatus==CURLM_CALL_MULTI_PERFORM);
  }
  /**
   * Clear all responses, that is free up the memory.
   */
  public function cleanupResponses()
  {
    $this->responses = array();
  }

  private function getKey($ch)
  {
    return (string)$ch;
  }

  private function headerCallback($ch, $header)
  {
    $_header = trim($header);
    $colonPos= strpos($_header, ':');
    if($colonPos > 0)
    {
      $key = substr($_header, 0, $colonPos);
      $val = preg_replace('/^\W+/','',substr($_header, $colonPos));
      $this->responses[$this->getKey($ch)]['headers'][$key] = $val;
    }
    return strlen($header);
  }

  private function storeResponses()
  {
    while($done = curl_multi_info_read($this->mc))
    {
      $key = (string)$done['handle'];
      $this->responses[$key]['data'] = curl_multi_getcontent($done['handle']);
      foreach($this->properties as $name => $const)
      {
        $this->responses[$key][$name] = curl_getinfo($done['handle'], $const);
      }
      curl_multi_remove_handle($this->mc, $done['handle']);
      curl_close($done['handle']);
    }
  }

  /**
   * Get the Singleton
   * @return EpiCurl The singleton
   */
  public static function getInstance()
  {
    if(self::$inst == null)
    {
      self::$singleton = 1;
      self::$inst = new EpiCurl();
    }

    return self::$inst;
  }
}

/**
 * Encapsules a handle for a request added to the multi curl.
 */
class EpiCurlManager
{
  private $key;

  /**
   * Associates a key, a curlhandle, with the EpiCurl singleton.
   * This class behaves like an associative array.
   * @param string $key
   */
  function __construct($key)
  {
    $this->key = $key;
  }

  /**
   * Actually execute the request if it wasn't executed and return the data fetched.
   * Executes all requests added before this one too.
   * @see EpiCurl::getRequest()
   * @param string $name A "field" in the results "array" of this request.
   * One of (see curl_getinfo):
   *  'data'  => the data fetched by this request,
   *  'headers' => the headers sent by the server,
   *  'code'  => CURLINFO_HTTP_CODE,
   *  'time'  => CURLINFO_TOTAL_TIME,
   *  'length'=> CURLINFO_CONTENT_LENGTH_DOWNLOAD,
   *  'type'  => CURLINFO_CONTENT_TYPE,
   *  'url'   => CURLINFO_EFFECTIVE_URL
   *  'timestamp' => CURLINFO_FILETIME
   * @return string
   */
  function __get($name)
  {
    $responses = EpiCurl::getInstance()->getResult($this->key);
    return $responses[$name];
  }

  function __isset($name)
  {
    $val = self::__get($name);
    return empty($val);
  }
}

/*
 * Credits:
 *  - (1) Alistair pointed out that curl_multi_add_handle can return CURLM_CALL_MULTI_PERFORM on success.
 */
