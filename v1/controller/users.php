<?php

require_once('db.php');
require_once('../model/Response.php');

try {
  $writeDB = DB::connectWriteDB();
} catch (PDOException $ex) {
  error_log("Connection Error: $ex", 0);
  $failureResponse = new Response(500, false, "Failed to connect to database");
  $failureResponse->send();

  exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  $failureResponse = new Response(405, false, "Request method not allowed");
  $failureResponse->send();

  exit();
}

if ($_SERVER["CONTENT_TYPE"] !== "application/json") {
  $failureResponse = new Response(400, false, "Content-Type header must be set to application/json");
  $failureResponse->send();

  exit();
}

$rawData = file_get_contents("php://input");
$jsonData = json_decode($rawData);

if (!$jsonData) {
  $failureResponse = new Response(400, false, "Request body must be a valid json");
  $failureResponse->send();

  exit();
}

$failureResponse = new Response(400, false);

$data = get_object_vars($jsonData);
$fieldsArray = array(
  "fullname" => "Full name",
  "username" => "Username",
  "password" => "Password",
);

$fieldsErrors = false;

foreach ($fieldsArray as $key => $value) {
  if (!array_key_exists($key, $data) || $data[$key] === null || strlen($data[$key]) < 1 || strlen($data[$key]) > 255) {
    $fieldsErrors = true;

    if (!array_key_exists($key, $data) || $data[$key] === null) {
      $failureResponse->addMessage("$value must be provided");
    } else if (strlen($data[$key]) < 1) {
      $failureResponse->addMessage("$value cannot be empty");
    } else if (strlen($data[$key]) > 255) {
      $failureResponse->addMessage("$value cannot be longer then 255 characters");
    }
  }
}

if ($fieldsErrors) {
  $failureResponse->send();

  exit();
}

$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = $jsonData->password;

try {
  $query = $writeDB->prepare("SELECT id FROM tblusers WHERE username = :username");
  $query->bindParam(":username", $username, PDO::PARAM_STR);
  $query->execute();

  if ($query->rowCount() !== 0) {
    $failureResponse = new Response(401, false, "Username already exists");
    $failureResponse->send();

    exit();
  }

  $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

  $query = $writeDB->prepare("INSERT INTO tblusers(fullname, username, password) values (:fullname, :username, :password)");
  $query->bindParam(":fullname", $fullname, PDO::PARAM_STR);
  $query->bindParam(":username", $username, PDO::PARAM_STR);
  $query->bindParam(":password", $hashedPassword, PDO::PARAM_STR);
  $query->execute();

  if ($query->rowCount() === 0) {
    $failureResponse = new Response(500, false, "There was an issue while creating new user, please try again");
    $failureResponse->send();

    exit();
  }

  $lastUserID = $writeDB->lastInsertId();

  $returnData = array(
    "user_id" => $lastUserID,
    "fullname" => $fullname,
    "username" => $username,
  );

  $response = new Response(201, true, "User created", $returnData);
  $response->send();

} catch (PDOException $ex) {
  error_log("Database query error: $ex", 0);
  $failureResponse = new Response(400, false, "There was an issue while creating new user, please try again");
  $failureResponse->send();

  exit();
}
