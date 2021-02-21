<?php

require_once('db.php');
require_once('../model/Response.php');

try {
  $writeDB = DB::connectWriteDB();
} catch (PDOException $ex) {
  error_log("Connection Error: $ex", 0);
  $failureResponse = new Response(500, false, "Database connection error");
  $failureResponse->send();

  exit();
}

if (array_key_exists("sessionid", $_GET)) {
  $sessionID = $_GET['sessionid'];

  if ($sessionID === '' || !is_numeric($sessionID)) {
    $failureResponse = new Response(400, false);
    (!is_numeric($sessionID) && $failureResponse->addMessage("Session ID must be numeric"));
    ($sessionID === '' && $failureResponse->addMessage("Session ID cannot be blank"));
    $failureResponse->send();

    exit();
  }

  if (!isset($_SERVER["HTTP_AUTHORIZATION"]) || strlen($_SERVER["HTTP_AUTHORIZATION"]) < 1) {
    $failureResponse = new Response(400, false);
    (!isset($_SERVER["HTTP_AUTHORIZATION"]) && $failureResponse->addMessage("Access token is missing"));
    (strlen($_SERVER["HTTP_AUTHORIZATION"]) < 1 && $failureResponse->addMessage("Access toke cannot be blank"));
    $failureResponse->send();

    exit();
  }

  $accessToken = $_SERVER["HTTP_AUTHORIZATION"];

  switch ($_SERVER["REQUEST_METHOD"]) {
    case "DELETE": {
      try {
        $query = $writeDB->prepare("DELETE FROM tblsessions WHERE id = :session_id AND access_token = :access_token");
        $query->bindParam(":session_id", $sessionID, PDO::PARAM_INT);
        $query->bindParam(":access_token", $accessToken, PDO::PARAM_STR);
        $query->execute();

        if ($query->rowCount() === 0) {
          $failureResponse = new Response(400, false, "Failed to log out using provided access token");
          $failureResponse->send();

          exit();
        }

        $returnData = array(
          "session_id" => intval($sessionID),
        );

        $response = new Response(400, true, "Successfully logged out", $returnData);
        $response->send();

        exit();
      } catch (PDOException $ex) {
        $failureResponse = new Response(500, false, "Failed to log out - please try again");
        $failureResponse->send();

        exit();
      }
    }
    case "PATCH": {

    }
    default: {
      $failureResponse = new Response(405, false, "Request method not allowed");
      $failureResponse->send();

      exit();
    }
  }
} else if (empty($_GET)) {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $failureResponse = new Response(405, false, "Request method not allowed");
    $failureResponse->send();

    exit();
  }

  sleep(1);

  if ($_SERVER["CONTENT_TYPE"] !== "application/json") {
    $failureResponse = new Response(400, false, "Content type header must be 'application/json'");
    $failureResponse->send();

    exit();
  }

  $rawData = file_get_contents('php://input');
  $jsonData = json_decode($rawData);

  if (!$jsonData) {
    $failureResponse = new Response(400, false, "Request body must be a valid json");
    $failureResponse->send();

    exit();
  }

  if (!isset($jsonData->username) || !isset($jsonData->password)) {
    $failureResponse = new Response(400, false);

    if (!isset($jsonData->username)) {
      $failureResponse->addMessage("Username must be provided");
    }

    if (!isset($jsonData->password)) {
      $failureResponse->addMessage("Password must be provided");
    }

    $failureResponse->send();
    exit();
  }

  function validateField($field, $fieldName) {
    $fieldLength = strlen($field);

    if ($fieldLength < 1) {
      return "$fieldName can't be empty";
    }

    if ($fieldLength > 255) {
      return "$fieldName can't be longer than 255 characters";
    }

    return false;
  }

  $usernameError = validateField($jsonData->username, "Username");
  $passwordError = validateField($jsonData->password, "Password");

  if ($usernameError || $passwordError) {
    $failureResponse = new Response(400, false);

    ($usernameError && $failureResponse->addMessage($usernameError));
    ($passwordError && $failureResponse->addMessage($passwordError));

    $failureResponse->send();

    exit();
  }

  try {
    $username = $jsonData->username;
    $password = $jsonData->password;

    $query = $writeDB->prepare("SELECT * FROM tblusers WHERE username = :username");
    $query->bindParam(":username", $username, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
      $failureResponse = new Response(401, false, "Username or password is incorrect");
      $failureResponse->send();

      exit();
    }

    $row = $query->fetch(PDO::FETCH_ASSOC);

    $returnedId = $row["id"];
    $returnedFullname = $row["fullname"];
    $returnedUsername = $row["username"];
    $returnedPassword = $row["password"];
    $returnedUserActive = $row["user_active"];
    $returnedLoginAttempts = $row["login_attempts"];

    if (!$returnedUserActive) {
      $failureResponse = new Response(401, false, "Username account is not active");
      $failureResponse->send();

      exit();
    }

    if ($returnedLoginAttempts >= 3) {
      $failureResponse = new Response(401, false, "Username account is currently locked out");
      $failureResponse->send();

      exit();
    }

    if (!password_verify($password, $returnedPassword)) {
      $query = $writeDB->prepare("UPDATE tblusers SET login_attempts = login_attempts+1 WHERE id = :id");
      $query->bindParam(":id", $returnedId, PDO::PARAM_INT);
      $query->execute();

      $failureResponse = new Response(401, false, "Username or password is incorrect");
      $failureResponse->send();

      exit();
    }

    $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
    $refreshToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

    $accessTokenExpiryTime = 1200;
    $refreshTokenExpiryTime = 1209600;


  } catch (PDOException $ex) {
    $failureResponse = new Response(500, false, "There was an issue logging in");
    $failureResponse->send();

    exit();
  }

  try {
    $writeDB->beginTransaction();

    $query = $writeDB->prepare("UPDATE tblusers SET login_attempts = 0 WHERE id = :id");
    $query->bindParam(":id", $returnedId, PDO::PARAM_INT);
    $query->execute();

    $query = $writeDB->prepare("
      INSERT INTO tblsessions(user_id, access_token, access_token_expiry, refresh_token, refresh_token_expiry)
      values (:user_id, :access_token, date_add(NOW(), INTERVAL :access_token_expiry SECOND), :refresh_token, date_add(NOW(), INTERVAL :refresh_token_expiry SECOND));
    ");
    $query->bindParam(":user_id", $returnedId, PDO::PARAM_INT);
    $query->bindParam(":access_token", $accessToken, PDO::PARAM_STR);
    $query->bindParam(":access_token_expiry", $accessTokenExpiryTime, PDO::PARAM_INT);
    $query->bindParam(":refresh_token", $refreshToken, PDO::PARAM_STR);
    $query->bindParam(":refresh_token_expiry", $refreshTokenExpiryTime, PDO::PARAM_INT);
    $query->execute();

    $lastSessionID = $writeDB->lastInsertId();

    $writeDB->commit();

    $returnData = array(
      "session_id" => $lastSessionID,
      "access_token" => $accessToken,
      "access_token_expiry" => $accessTokenExpiryTime,
      "refresh_token" => $refreshToken,
      "refresh_token_expiry" => $refreshTokenExpiryTime,
    );

    $response = new Response(201, false, null, $returnData);
    $response->send();

    exit();

  } catch (PDOException $ex) {
    $writeDB->rollBack();
    $failureResponse = new Response(500, false, "There was an issue logging in");
    $failureResponse->send();

    exit();
  }
} else {
  $failureResponse = new Response(404, false, "Endpoint not found");
  $failureResponse->send();

  exit();
}
