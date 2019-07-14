<?php

require_once 'db.php';
require_once '../model/Response.php';


try{
    $writeDb = DB::connectWriteDB();

}catch (PDOException $ex)
{
    error_log('Connection error: '.$ex,0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->setMessages('Database connection error');
    $response->send();
    exit;

}

if(array_key_exists('sessionid',$_GET)){
    if($_SERVER['REQUEST_METHOD'] == 'DELETE')
    {

    }
    elseif($_SERVER['REQUEST_METHOD'] == 'PATCH')
    {

    }
}elseif(empty($_GET))
{

    if($_SERVER['REQUEST_METHOD'] == 'POST')
    {
        sleep(1);

        if($_SERVER['CONTENT_TYPE']!=='application/json')
        {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessages('Content Type must be JSON');
            $response->send();
            exit;
        }

        $rawJson = file_get_contents('php://input');

        if(!$jsonData = json_decode($rawJson))
        {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessages('Not valid JSON syntax');
            $response->send();
            exit;
        }

        if(!isset($jsonData->username) || !isset($jsonData->password))
        {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            !isset($jsonData->username) ?   $response->setMessages('Username not provided'):false;
            !isset($jsonData->password)?   $response->setMessages('Password not provided'):false;
            $response->send();
            exit;
        }


        try{

            $username = $jsonData->username;
            $password = $jsonData->password;

            $query = $writeDb->prepare('select id,fullname,username,password, useractive, loginattempts from tblusers where username = :username');
            $query->bindParam(":username",$username,PDO::PARAM_STR);
            $query->execute();
            $rowCount = $query->rowCount();

            if($rowCount == 0)
            {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('Credentials are incorrect');
                $response->send();
                exit;
            }



            $row = $query->fetch(PDO::FETCH_ASSOC);
            $returnedId=$row['id'];
            $returnedFullname = $row['fullname'];
            $returnedUsername = $row['username'];
            $returnedPassword = $row['password'];
            $returnedUseractive = $row['useractive'];
            $returnedLoginattempts = $row['loginattempts'];

            if($returnedUseractive!=='Y')
            {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('User account not active');
                $response->send();
                exit;
            }

            if($returnedLoginattempts>=3)
            {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('User account is currently locked out');
                $response->send();
                exit;
            }

            if(!password_verify('7777','$2y$10$JSh7UF00lXLw/PYAML6YY.KtH1ezFLmTfh7/2ZdDp2nK86oFXSuDW'))
            {
                $query = $writeDb->prepare('update tblusers set loginattempts = loginattempts + 1 where id =:id');
                $query->bindParam(':id',$returnedId,PDO::PARAM_INT);
                $query->execute();

                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('Username or password is incorrect');
                $response->setMessages($returnedPassword);
                $response->setMessages(password_hash('7777',PASSWORD_DEFAULT));
                $response->send();
                exit;

            }


            $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());
            $refreshToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)).time());

            $accessTokenExpirySeconds = 1200;
            $refreshTokenExpirySeconds = 1209600;//14days

        }
        catch (PDOException $ex)
        {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages('There was an issue logging in');
            $response->send();
            exit;

        }


        try{

            $writeDb->beginTransaction();

            $query = $writeDb->prepare('update tblusers set loginattempts = 0 where id =:id');
            $query->bindParam(':id',$returnedId,PDO::PARAM_INT);
            $query->execute();


            $query = $writeDb->prepare('insert into tblsessions (userid,accesstoken,accesstokenexpiry,refreshtoken,refreshtokenexpiry) values (:userid , :accesstoken,date_add(NOW(), INTERVAL :accesstokenexpiry SECOND ),:refreshtoken, date_add(NOW(), INTERVAL :refreshtokenexpiry SECOND ))');
            $query->bindParam(":userid",$returnedId,PDO::PARAM_INT);
            $query->bindParam(":accesstoken",$accessToken,PDO::PARAM_STR);
            $query->bindParam(":refreshtoken",$refreshToken,PDO::PARAM_STR);
            $query->bindParam(":accesstokenexpiry",$accessTokenExpirySeconds,PDO::PARAM_INT);
            $query->bindParam(":refreshtokenexpiry",$refreshTokenExpirySeconds,PDO::PARAM_INT);
            $query->execute();

          $sessionId = $writeDb->lastInsertId();
            $writeDb->commit();

            $returnData = array();
            $returnData['session_id'] = intval($sessionId);
            $returnData['access_token'] = $accessToken;
            $returnData['access_token_expires_in'] = $accessTokenExpirySeconds;
            $returnData['refresh_token'] = $refreshToken;
            $returnData['refresh_token_expires_in'] = $refreshTokenExpirySeconds;



            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->setMessages('Session created');
            $response->setData($returnData);
            $response->send();
            exit;



        }
        catch (PDOException $ex)
        {

            $writeDb->rollBack();

            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages('There was an issue loggin in - please try again');
            $response->send();
            exit;
        }



    }else{

        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->setMessages('Request Method not allowed');
        $response->send();
        exit;
    }

}
else{
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->setMessages('Endpoint is not found');
    $response->send();
    exit;

}









