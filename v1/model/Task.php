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

  public function __construct(?int $id, $title = null, $description = null, $deadline = null, $completed = 0) {
    ($id !== null ? $this->setID($id) : false);
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

  public function setID(int $id) {
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
    $date = date_create_from_format(self::DATE_FORMAT, $deadline);

    if (!$date && $deadline !== null) {
      throw new TaskException("Wrong date format. Must follow this pattern: YYYY-MM-DD HH:MM");
    }

    if ($deadline !== null && date_format($date, self::DATE_FORMAT) !== $deadline) {
      throw new TaskException("Task deadline datetime error");
    }

    $this->_deadline = $deadline;
  }

  public function setCompleted(int $completed) {
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
