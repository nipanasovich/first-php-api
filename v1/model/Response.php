<?php

class Response {
  private $_success;
  private $_httpStatusCode;
  private $_messages = array();
  private $_data;
  private $_toCache = false;
  private $_responseData = array();

  public function __construct($httpStatusCode, $success, $message = null, $data = null) {
    $this->_success = $success;
    $this->_httpStatusCode = $httpStatusCode;
    ($message !== null && $this->_messages[] = $message);
    $this->_data = $data;
  }


  public function setSuccess($success) {
    $this->_success = $success;
  }

  public function setHttpStatusCode($httpStatusCode) {
    $this->_httpStatusCode = $httpStatusCode;
  }

  public function addMessage($message) {
    $this->_messages[] = $message;
  }

  public function setData($data) {
    $this->_data = $data;
  }

  public function toCache($toCache) {
    $this->_toCache = $toCache;
  }

  private function setHeaders() {
    header('Content-type: application/json;charset=utf-8');

    if ($this->_toCache) {
      header('Cache-control: max-age=60');
    } else {
      header('Cache-control: no-cache, no-store');
    }
  }

  private function setFinalResponseData() {
    if (!is_bool($this->_success) || !is_numeric($this->_httpStatusCode)) {
      http_response_code(500);

      $this->_responseData['statusCode'] = 500;
      $this->_responseData['success'] = false;
      $this->addMessage("Response creation error");
      $this->_responseData['messages'] = $this->_messages;
    } else {
      http_response_code($this->_httpStatusCode);

      $this->_responseData['statusCode'] = $this->_httpStatusCode;
      $this->_responseData['success'] = $this->_success;
      $this->_responseData['messages'] = $this->_messages;
      $this->_responseData['data'] = $this->_data;
    }
  }

  public function send() {
    $this->setHeaders();

    $this->setFinalResponseData();

    echo json_encode($this->_responseData);
  }
}
