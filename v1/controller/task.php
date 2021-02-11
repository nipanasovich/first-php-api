<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/Task.php');

try {
  $writeDB = DB::connectWriteDB();
  $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
  error_log("Connection error: ".$ex, 0);
  $exceptionResponse = new Response(500, false, 'Database connection failed');
  $exceptionResponse->send();

  exit();
}

if (array_key_exists("taskid", $_GET)) {
  $taskID = $_GET['taskid'];

  if (!$taskID || !is_numeric($taskID)) {
    $failureResponse = new Response(400, false, "ID must be provided");
    $failureResponse->send();

    exit();
  }

  switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET': {
      handleGetRequest();

      break;
    }
    case 'DELETE': {
      handleDeleteRequest();

      break;
    }
    case 'PATCH': {

    }
    default: {
      $unknownMethodResponse = new Response(405, false, "Request method is not allowed");
      $unknownMethodResponse->send();

      exit();
    }
  }
}

function send404Response() {
  global $taskID;

  $notFoundResponse = new Response(404, false, "No entity with id $taskID found");
  $notFoundResponse->send();

  exit();
}

####
# -------------- GET HANDLER --------------
####

function handleGetRequest() {
  global $readDB, $taskID;

  try {
    $query = $readDB->prepare('
      SELECT id, title, description, DATE_FORMAT(deadline, "%Y-%m-%d %H:%i") as deadline, completed
      FROM tbltasks
      WHERE id = :taskID
    ');
    $query->bindParam(':taskID', $taskID, PDO::PARAM_INT);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
      send404Response();
    }

    while($row = $query->fetch(PDO::FETCH_ASSOC)) {
      $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

      $tasks[] = $task->returnTaskAsArray();
    }

    $returnData = array();
    $returnData['rows_returned'] = $rowCount;
    $returnData['tasks'] = $tasks;

    $successResponse = new Response(200, true, "Task retrieved successfully", $returnData);
    $successResponse->toCache(true);

    $successResponse->send();
  } catch (PDOException $ex) {
    error_log("Database query error -".$ex, 0);
    $failureResponse = new Response(500, false, "Failed to retrieve task: ".$ex);
    $failureResponse->send();

    exit();
  } catch (TaskException $ex) {
    $failureResponse = new Response(500, false, "Server error: ".$ex->getMessage());
    $failureResponse->send();

    exit();
  }
}

####
# -------------- DELETE HANDLER --------------
####

function handleDeleteRequest() {
  global $writeDB, $taskID;

  try {
    $query = $writeDB->prepare("
      DELETE FROM tbltasks WHERE id = :taskID
    ");
    $query->bindParam(':taskID', $taskID, PDO::PARAM_INT);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
      send404Response();
    }

    $successResponse = new Response(200, true, "Task $taskID deleted");
    $successResponse->send();
  } catch (PDOException $ex) {
    error_log("Database query error -".$ex, 0);
    $failureResponse = new Response(500, false, "Failed to delete task: ".$ex);
    $failureResponse->send();

    exit();
  }
}
