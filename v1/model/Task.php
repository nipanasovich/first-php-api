<?php

class TaskException extends Exception {

}

class Task {
  const DATE_FORMAT = "Y-m-d H:i";

  private $_id;
  private $_title;
  private $_description;
  private $_deadline;
  private $_completed;

  public function __construct($id, $title, $description, $deadline, $completed) {
    $this->setID($id);
    $this->setTitle($title);
    $this->setDescription($description);
    $this->setDeadline($deadline);
    $this->setCompleted($completed);
  }

  public function getID() {
    return $this->_id;
  }

  public function getTitle() {
    return $this->_title;
  }

  public function getDescription() {
    return $this->_description;
  }

  public function getDeadline() {
    return $this->_deadline;
  }

  public function getCompleted() {
    return $this->_completed;
  }

  public function setID($id) {
    if ($id === null || !is_numeric($id) || $id <= 0 || $this->_id !== null) {
      throw new TaskException("Task ID error");
    }

    $this->_id = $id;
  }

  public function setTitle($title) {
    if (strlen($title) <= 0 || strlen($title) > 255) {
      throw new TaskException("Task title error");
    }

    $this->_title = $title;
  }

  public function setDescription($description) {
    if ($description !== null && strlen($description) > 16777215) {
      throw new TaskException("Task description error");
    }

    $this->_description = $description;
  }

  public function setDeadline($deadline) {
    if ($deadline !== null && date_format(date_create_from_format(self::DATE_FORMAT, $deadline), self::DATE_FORMAT) !== $deadline) {
      throw new TaskException("Task deadline datetime error");
    }

    $this->_deadline = $deadline;
  }

  public function setCompleted($completed) {
    if(!is_numeric($completed) || ($completed !== 1 && $completed !== 0)) {
      throw new TaskException("Task completed must be 1 or 0");
    }

    $this->_completed = $completed;
  }

  public function returnTaskAsArray() {
    return array(
      "id" => $this->_id,
      "title" => $this->_title,
      "description" => $this->_description,
      "deadline" => $this->_deadline,
      "completed" => $this->_completed,
    );
  }
}
