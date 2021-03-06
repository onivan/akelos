<?php

class AkTestApplication extends AkUnitTest
{
    public $Dispatcher;
    public $_response;
    public $_cacheHeaders = array();

    public function assertWantedText($text, $message = '%s') {
        $this->assertPattern('/'.preg_quote($text).'/', $message);
    }

    public function getResponseText(){
        return $this->_response;
    }
    /**
     * Asserts only if the whole response matches $text
     */
    public function assertTextMatch($text, $message = '%s') {
        $this->assertPattern('|^'.$text.'$|', $message);
    }

    public function assertText($text, $message = '%s') {
        return $this->assert(
        new TextExpectation($text),
        strip_tags($this->_response),
        $message);
    }

    public function assertNoText($text, $message = '%s') {
        return $this->assert(
        new NoTextExpectation($text),
        strip_tags($this->_response),
        $message);
    }

    public function assertHeader($header, $content = null) {
        if (is_array($this->_cacheHeaders)) {
            foreach ($this->_cacheHeaders as $ch) {
                $parts = explode(': ', $ch);
                if ($parts[0] == $header) {
                    if ($content != null) {
                        $this->assertEqual($content, $parts[1],'1 Header content does not match: '.$parts[1].'!='.$content.':'.var_export($this->_cacheHeaders,true)."\n".var_export($this->Dispatcher->Request->_format,true));
                        return;
                    } else {
                        $this->assertTrue(true);
                        return;
                    }
                }
            }
        }
        if ($this->Dispatcher) {
            $value = $this->Dispatcher->Response->getHeader($header);
            $this->assertTrue($value!=false,'Header "'.$header.'" not found');
            if ($content != null) {
                $this->assertEqual($value, $content,'2 Header content does not match: '.$content.'!='.$value.':'.var_export($this->Dispatcher->Response->_headers,true).':'.var_export($this->Dispatcher->Response->_headers_sent,true)."\n".var_export($this->Dispatcher->Request->_format,true));;
            }
        } else {
            $this->assertTrue(false,'Header "'.$header.'" not found');
        }
    }


    public function assertWantedPattern($pattern, $message = '%s') {
        return $this->assertPattern($pattern, $message);
    }

    public function assertPattern($pattern, $subject, $message = '%s') {
        return $this->assert(
        new PatternExpectation($pattern),
        $this->_response,
        $message);
    }

    public function _testXPath($xpath_expression) {
        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            if (function_exists('domxml_open_mem')) {
                $dom = domxml_open_mem($this->_response);
                if (!$dom) {
                    $this->fail('Error parsing doc');
                    return false;
                }
                var_dump($dom);
                $xpath = $dom->xpath_init();
                var_dump($xpath);
                $ctx = $dom->xpath_new_context();
                var_dump($xpath_expression);
                $result = $ctx->xpath_eval($xpath_expression);
                var_dump($result);
                $return = new stdClass();
                $return->length = count($result->nodeset);
                return $return;
            }
            $this->fail('No xpath support built in');
            return false;
        } else if (extension_loaded('domxml')) {
            $this->fail('Please disable the domxml extension. Only php5 builtin domxml is supported');
            return false;
        }

        $dom = new DOMDocument();
        $response = preg_replace('/(<!DOCTYPE.*?>)/is','',$this->_response);

        $dom->loadHtml($response);
        $xpath = new DOMXPath($dom);
        $node = $xpath->query($xpath_expression);
        return $node;
    }

    public function assertXPath($xpath_expression, $message = null) {
        $node = $this->_testXPath($xpath_expression);
        if ($node->length<1) {
            $message = empty($message)?'Element not found using xpath: %xpath':$message;
            $message = str_replace('%xpath',$xpath_expression,$message);
            $this->fail($message);
        } else {
            $message = empty($message)?'Element found using xpath: %xpath':$message;
            $this->pass($message);
        }
        return $node;
    }
    public function assertNoXPath($xpath_expression, $message = null) {
        $node = $this->_testXPath($xpath_expression);
        if ($node->length>0) {
            $message = empty($message)?'Element found using xpath: %xpath':$message;
            $message = str_replace('%xpath',$xpath_expression,$message);
            $this->fail($message);
        } else {
            $message = empty($message)?'Element not found using xpath: %xpath':$message;
            $this->pass($message);
        }
    }
    public function assertValidXhtml($message = null) {
        $response = $this->_response;

        $validator = new AkXhtmlValidator();
        $valid = $validator->validate($response);

        if (!$valid) {
            $message = empty($message)?'Non valid Xhtml: %errors':$message;
            $message = str_replace('%errors',strip_tags(join("\n- ",$validator->getErrors())),$message);
            $this->fail($message);
        } else {
            $message = empty($message)?'XHtml valid':$message;
            $this->pass($message);
        }
    }

    /**
     * Asserts a variable has been assined to the controller
     *
     * @variable string $variable
     */
    public function assertAssigns($variable){
        if($Controller = $this->getController()){
            if(isset($Controller->$variable)){
                $this->pass('Variable '.$variable.' assigned to controller '.get_class($Controller));
            }else{
                $this->fail('Variable '.$variable.' is not set assigned to controller '.get_class($Controller));
            }
        }else{
            $this->fail('Could not get controller instance');
        }
    }

    public function &getController() {
        if (isset($this->Dispatcher)) {
            $controller = $this->Dispatcher->Controller;
            return $controller;
        } else {
            $false = false;
            return $false;
        }
    }
    public function _setConstants($constants = array()) {
        foreach ($constants as $constant=>$value) {
            !defined($constant)?define($constant,$value):null;
        }
    }
    public function setIp($ip) {
        $_SERVER['HTTP_CLIENT_IP'] = $ip;
        $_SERVER['REMOTE_ADDR'] = $ip;
    }

    public function assertResponse($code) {
        if($code == 'success'){
            $code = 200;
        }
        $this->assertHeader('Status',$code);
    }

    public function setForwaredForIp($ip) {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $ip;
    }
    public function addIfModifiedSince($gmtDateString) {
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = $gmtDateString;
    }
    public function setXmlHttpRequest() {
        $_SERVER['HTTP_X_REQUESTED_WITH']='xmlhttprequest';
    }
    public function setAcceptEncoding($encoding) {
        $_SERVER['HTTP_ACCEPT_ENCODING']=$encoding;
    }
    public function &getHeader($name) {
        if ($this->Dispatcher) {
            $sentHeader = $this->Dispatcher->Response->getHeader($name);
        } else {
            $sentHeader=false;
        }
        if (!$sentHeader) {
            if (is_array($this->_cacheHeaders)) {
                foreach ($this->_cacheHeaders as $ch) {
                    $parts = explode(': ', $ch);
                    if ($parts[0] == $name) {
                        $return=@$parts[1];
                        return $return;
                    }
                }
            }
        }
        return $sentHeader;
    }

    public function _reset() {
        $_REQUEST = array();
        $_POST = array();
        $_SESSION = array();
        $_GET = array();
        $_POST = array();
    }

    public function _init($url, $constants = array(), $controllerVars = array()) {
        $this->_reset();
        $this->_response = null;
        $this->_cacheHeaders = array();
        $this->_setConstants($constants);
        $parts = parse_url($url);
        $_REQUEST['ak'] = isset($parts['path'])?$parts['path']:'/';
        $_SERVER['AK_HOST']= isset($parts['host'])?$parts['host']:'localhost';
        $cache_settings = Ak::getSettings('caching', false);
        if ($cache_settings['enabled']) {

            $null = null;
            $pageCache = new AkCacheHandler();

            $pageCache->init($null, $cache_settings);
            if ($cachedPage = $pageCache->getCachedPage()) {
                static $_cachedHeaders = array();
                ob_start();
                global $sendHeaders, $returnHeaders, $exit;
                $sendHeaders = false;
                $returnHeaders = true;
                $exit = false;
                $headers = include $cachedPage;
                //$headers = $cachedPage->render(false,false,true);
                $this->_response = ob_get_clean();
                if (is_array($headers)) {
                    $this->_cacheHeaders = $headers;
                }
                return true;
            }
        }

        $this->Dispatcher = new AkTestDispatcher($controllerVars);
    }

    public function get($url,$data = array(), $constants = array(), $controllerVars = array()) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        $rendered = $this->_init($url, $constants, $controllerVars);
        if (!$rendered) {
            $res = $this->Dispatcher->get($url, $data);
            $this->_response = ob_get_clean();
        } else {
            $res = true;
        }
        $this->_cleanUp();
        return $res;
    }

    public function _cleanUp() {
        unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }
    public function post($url, $data = null, $constants = array(), $controllerVars = array()) {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        ob_start();

        $rendered = $this->_init($url, $constants, $controllerVars);
        if (!$rendered) {
            $res = $this->Dispatcher->post($url, $data);
            $this->_response = ob_get_clean();
        } else {
            $res=true;
        }
        return $res;
    }

    public function put($url,$data = null, $constants = array(), $controllerVars = array()) {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        ob_start();
        $rendered = $this->_init($url, $constants, $controllerVars);
        if (!$rendered) {
            $res = $this->Dispatcher->put($url,$data);
            $this->_response = ob_get_clean();
        } else {
            $res = true;
        }
        return $res;
    }

    public function delete($url, $constants = array(), $controllerVars = array()) {
        $_SERVER['REQUEST_METHOD'] = 'DELETE';
        ob_start();
        $rendered = $this->_init($url, $constants, $controllerVars);
        if (!$rendered) {
            $res = $this->Dispatcher->delete($url);
            $this->_response = ob_get_clean();
        } else {
            $res= true;
        }
        return $res;
    }

}