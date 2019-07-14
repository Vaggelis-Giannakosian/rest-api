    <?php

require_once 'db.php';
require_once '../model/Response.php';


    try
    {
        $writeDB = DB::connectWriteDB();
    }
    catch (PDOException $ex)
    {
        error_log("Connection error - " .$ex, 0);
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessages("Database connection error");
        $response->send();
        exit;
    }

    if($_SERVER['REQUEST_METHOD'] !== 'POST')
    {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->setMessages('Request method not allowed');
        $response->send();
        exit;
    }

    if($_SERVER["CONTENT_TYPE"] !== 'application/json')
    {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->setMessages('Content type must be application/json');
        $response->send();
        exit;
    }


    $rawPostData = file_get_contents('php://input');

    if(!$postData = json_decode($rawPostData))
    {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->setMessages('Not valid JSON format');
        $response->send();
        exit;
    }


    if(!isset($postData->fullname) || !isset($postData->username) || !isset($postData->password) )
    {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        !isset($postData->fullname)? $response->setMessages('Haven\'t provided a fullname'):false;
        !isset($postData->username)? $response->setMessages('Haven\'t provided a username'):false;
        !isset($postData->password)? $response->setMessages('Haven\'t provided a password'):false;
        $response->send();
        exit;
    }

    if((strlen($postData->fullname)<1 || strlen($postData->fullname)>255) || (strlen($postData->username)<1 || strlen($postData->username)>255) || (strlen($postData->password)<1 || strlen($postData->password)>255))
        {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            (strlen($postData->fullname)<1 || strlen($postData->fullname)>255)? $response->setMessages('Fullname cannot be blank or greater than 255 chars'):false;
            (strlen($postData->username)<1 || strlen($postData->username)>255)? $response->setMessages('username cannot be blank or greater than 255 chars'):false;
            (strlen($postData->password)<1 || strlen($postData->password)>255)? $response->setMessages('password cannot be blank or greater than 255 chars'):false;
            $response->send();
            exit;
        }


    $fullname = trim($postData->fullname);
    $username = trim($postData->username);
    $password = $postData->password;


    try{
        $hashedPassword = password_hash($password,PASSWORD_DEFAULT);
        $query = $writeDB->prepare('insert into tblusers(fullname,username,password) values (:fullname,:username,:password)');
        $query->bindParam(':fullname',$fullname,PDO::PARAM_STR);
        $query->bindParam(':username',$username,PDO::PARAM_STR);
        $query->bindParam(':password',$hashedPassword,PDO::PARAM_STR);
        $query->execute();
        $rowCount = $query->rowCount();

        if($rowCount == 0)
        {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages('Database error creating new user');
            $response->send();
            exit;
        }

        $userId = $writeDB->lastInsertId();
        $returnData ['user_id'] = $userId;
        $returnData['full name']=$fullname;
        $returnData['username']=$username;


        $response = new Response();
        $response->setHttpStatusCode(201);
        $response->setSuccess(true);
        $response->setData($returnData);
        $response->setMessages('User created');
        $response->send();
        exit;


    }
    catch (PDOException $ex)
    {
        error_log('Database query error - '.$ex, 0);
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessages('Database error creating new user');
        $response->send();
        exit;
    }

