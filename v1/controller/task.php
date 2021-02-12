<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/Task.php');

########### SEND ERROR RESPONSE FUNCTIONS #######################

function send404Response($message) {
  $notFoundResponse = new Response(404, false, $message);
  $notFoundResponse->send();

  exit();
}

function send405Response() {
  $unknownMethodResponse = new Response(405, false, "Request method is not allowed");
  $unknownMethodResponse->send();

  exit();
}

function send400Response($message) {
  $failureResponse = new Response(400, false, $message);
  $failureResponse->send();

  exit();
}

function sendDatabaseErrorResponse($message, $ex) {
  error_log("Database query error -".$ex, 0);
  $failureResponse = new Response(500, false, $message);
  $failureResponse->send();

  exit();
}

function sendTaskControllerErrorResponse($message) {
  $failureResponse = new Response(500, false, $message);
  $failureResponse->send();

  exit();
}

##################################################

try {
  $writeDB = DB::connectWriteDB();
  $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
  sendDatabaseErrorResponse("Database connection failed", $ex);
}

$requestMethod = $_SERVER['REQUEST_METHOD'];

if (array_key_exists("taskid", $_GET)) {
  $taskID = $_GET['taskid'];

  if (!$taskID || !is_numeric($taskID)) {
    send400Response("ID must be provided");
  }

  switch ($requestMethod) {
    case 'GET': {
      handleGetEntityRequest();

      break;
    }
    case 'DELETE': {
      handleDeleteEntityRequest();

      break;
    }
    case 'PATCH': {

    }
    default: {
      send405Response();
    }
  }
} else if (array_key_exists("completed", $_GET)) {
  handleGetListByStatusRequest();
} else if (array_key_exists("page", $_GET)) {
  $page = $_GET['page'];

  if ($page === '' || !is_numeric($page)) {
    send400Response("Page value should be provided");
  }

  handleGetListRequest($page);
} else if (empty($_GET)) {
  if ($requestMethod === 'GET') {
    handleGetListRequest();
  } else if ($requestMethod === 'POST') {
    handleCreateEntityRequest();
  } else {
    send405Response();
  }
}

########################

function parseTasks($query) {
  $tasks = array();

  while($row = $query->fetch(PDO::FETCH_ASSOC)) {
    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

    $tasks[] = $task->returnTaskAsArray();
  }

  $parsedData = array();
  $parsedData['rows_returned'] = $query->rowCount();
  $parsedData['tasks'] = $tasks;

  return $parsedData;
}

####
# -------------- GET SINGLE ENTITY HANDLER --------------
####

function handleGetEntityRequest() {
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
      send404Response("No entity with id $taskID found");
    }

    $returnData = parseTasks($query);

    $successResponse = new Response(200, true, "Task retrieved successfully", $returnData);
    $successResponse->toCache(true);
    $successResponse->send();
  } catch (PDOException $ex) {
    sendDatabaseErrorResponse("Failed to retrieve task: ".$ex, $ex);
  } catch (TaskException $ex) {
    sendTaskControllerErrorResponse("Server error: ".$ex->getMessage());
  }
}

####
# -------------- CREATE HANDLER --------------
####

function handleCreateEntityRequest() {
  global $writeDB;

  try {
    if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
      send400Response("Content-Type header must be application/json");
    }

    $requestRawData = file_get_contents('php://input');
    $requestJSONData = json_decode($requestRawData);

    if (!$requestJSONData) {
      send400Response("Request body is not valid JSON");
    }

    if (!isset($requestJSONData->title)) {
      send400Response("Title is mandatory for task creation");
    }

    $newTask = new Task(
      null,
      $requestJSONData->title,
      isset($requestJSONData->description) ? $requestJSONData->description : null,
      isset($requestJSONData->deadline) ? $requestJSONData->deadline : null,
      isset($requestJSONData->completed) ? $requestJSONData->completed : 0,
    );

    $title = $newTask->getTitle();
    $description = $newTask->getDescription();
    $deadline = $newTask->getDeadline();
    $completed = $newTask->getCompleted();

    $query = $writeDB->prepare('
      INSERT INTO tbltasks (title, description, deadline, completed)
      values (:title, :description, STR_TO_DATE(:deadline, "%Y-%m-%d %H:%i"), :completed);
    ');

    $query->bindParam(":title", $title, PDO::PARAM_STR);
    $query->bindParam(":description", $description, PDO::PARAM_STR);
    $query->bindParam(":deadline", $deadline, PDO::PARAM_STR);
    $query->bindParam(":completed", $completed, PDO::PARAM_INT);

    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
      sendTaskControllerErrorResponse("Failed to create task");
    }

    $lastTaskID = $writeDB->lastInsertId();

    $query = $writeDB->prepare('
    SELECT id, title, description, DATE_FORMAT(deadline, "%Y-%m-%d %H:%i") as deadline, completed
    FROM tbltasks
    WHERE id = :taskID
    ');
    $query->bindParam(':taskID', $lastTaskID, PDO::PARAM_INT);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
      sendTaskControllerErrorResponse("Failed to retrieve task after creation");
    }

    $returnData = parseTasks($query);

    $successResponse = new Response(201, true, "Task created successfully", $returnData);
    $successResponse->send();
  } catch (PDOException $ex) {
    sendDatabaseErrorResponse("Failed to create task: ".$ex, $ex);
  } catch (TaskException $ex) {
    sendTaskControllerErrorResponse("Failed to create task: ".$ex->getMessage());
  }
}

####
# -------------- DELETE HANDLER --------------
####

function handleDeleteEntityRequest() {
  global $writeDB, $taskID;

  try {
    $query = $writeDB->prepare('DELETE FROM tbltasks WHERE id = :taskID');
    $query->bindParam(':taskID', $taskID, PDO::PARAM_INT);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
      send404Response();
    }

    $successResponse = new Response(200, true, "Task $taskID deleted");
    $successResponse->send();
  } catch (PDOException $ex) {
    sendDatabaseErrorResponse("Failed to delete task: ".$ex, $ex);
  }
}

####
# -------------- GET LIST HANDLER --------------
####

function handleGetListRequest(?int $page = null) {
  global $readDB;

  $defaultQuery = '
  SELECT id, title, description, DATE_FORMAT(deadline, "%Y-%m-%d %H:%i") as deadline, completed
  FROM tbltasks
  ';

  try {
    if ($page !== null) {
      $enitiesPerPageLimit = 10;

      $countQuery = $readDB->prepare('SELECT count(id) as tasksAmount FROM tbltasks');
      $countQuery->execute();

      $row = $countQuery->fetch(PDO::FETCH_ASSOC);

      $tasksAmount = intval($row['tasksAmount']);

      $pagesAmount = intval(ceil($tasksAmount/$enitiesPerPageLimit));

      if ($pagesAmount === 0) {
        $pagesAmount = 1;
      }

      if ($page > $pagesAmount || $page === 0) {
        send404Response("Page $page not found");
      }

      $offset = ($page === 1 ? 0 : $enitiesPerPageLimit*($page - 1));

      $query = $readDB->prepare($defaultQuery."LIMIT :perPageLimit OFFSET :offset");
      $query->bindParam("perPageLimit", $enitiesPerPageLimit);
      $query->bindParam("offset", $offset);
    } else {
      $query = $readDB->prepare($defaultQuery);
    }

    $query->execute();
    $rowCount = $query->rowCount();

    $returnData = parseTasks($query);

    if ($page !== null) {
      $returnData['total_rows'] = $tasksAmount;
      $returnData['total_pages'] = $pagesAmount;
      $returnData['is_last_page'] = $page === $pagesAmount;
    }

    $successResponse = new Response(200, true, "Tasks retrieved successfully", $returnData);
    $successResponse->toCache(true);
    $successResponse->send();
  }  catch (PDOException $ex) {
    sendDatabaseErrorResponse("Failed to retrieve tasks: ".$ex, $ex);
  } catch (TaskException $ex) {
    sendTaskControllerErrorResponse("Server error: ".$ex->getMessage());
  }
}

####
# -------------- GET LIST BY STATUS HANDLER --------------
####

function handleGetListByStatusRequest() {
  global $readDB;

  $completedStatus = $_GET['completed'];

  if ($completedStatus !== '1' && $completedStatus !== '0') {
    send400Response("Incorrect value of completed. Allowed values: 1 or 0");
  }

  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
      $completedStatus = intval($completedStatus, 10);

      $query = $readDB->prepare('
      SELECT id, title, description, DATE_FORMAT(deadline, "%Y-%m-%d %H:%i") as deadline, completed
      FROM tbltasks
      WHERE completed = :completedStatus
      ');
      $query->bindParam(':completedStatus', $completedStatus, PDO::PARAM_INT);
      $query->execute();

      $rowCount = $query->rowCount();

      $returnData = parseTasks($query);

      $successResponse = new Response(200, true, "Tasks retrieved successfully", $returnData);
      $successResponse->toCache(true);
      $successResponse->send();
    }  catch (PDOException $ex) {
      sendDatabaseErrorResponse("Failed to retrieve tasks: ".$ex, $ex);
    } catch (TaskException $ex) {
      sendTaskControllerErrorResponse("Server error: ".$ex->getMessage());
    }
  } else {
    send405Response();
  }
}
