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

    $sessionId = $_GET['sessionid'];
    if($sessionId === '' || !is_numeric($sessionId))
    {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $sessionId === '' ?  $response->setMessages('Session ID cannot be blank') : false;
        !is_numeric($sessionId) ?  $response->setMessages('Session ID must be numeric') : false;
        $response->send();
        exit;
    }

    if(!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 )
    {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        !isset($_SERVER['HTTP_AUTHORIZATION'])  ?  $response->setMessages('Access Token is missing from the header') : false;
        strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ?  $response->setMessages('Access Token cannot be blank') : false;
        $response->send();
        exit;
    }

    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];


    if($_SERVER['REQUEST_METHOD'] == 'DELETE')
    {
        try{
            $query=$writeDb->prepare('delete from tblsessions where id = :sessionid and accesstoken = :accesstoken');
            $query->bindParam(':sessionid',$sessionId,PDO::PARAM_INT);
            $query->bindParam(':accesstoken',$accessToken,PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0)
            {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->setMessages('Failed to logout of this session.');
                $response->send();
                exit;
            }

            $returnData = array();
            $returnData['session_id'] = intval($sessionId);


            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setData($returnData);
            $response->setSuccess(true);
            $response->setMessages('Logged out successfully.');
            $response->send();
            exit;

        }
        catch (PDOException $ex)
        {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages('There was an issue logging out. Please try again.');
            $response->send();
            exit;
        }


    }

    elseif($_SERVER['REQUEST_METHOD'] == 'PATCH')
    {

        if($_SERVER['CONTENT_TYPE'] !== 'application/json')
        {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessages('Content type must be JSON');
            $response->send();
            exit;
        }

        $rawJson = file_get_contents('php://input');

        if(!$patchData = json_decode($rawJson))
        {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessages('Not valid JSON syntax');
            $response->send();
            exit;
        }

        if(!isset($patchData->refreshtoken) || strlen($patchData->refreshtoken) < 1 )
        {
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            !isset($patchData->refreshtoken) ? $response->setMessages('Refresh Token not provided') :false;
            strlen($patchData->refreshtoken) < 1 ? $response->setMessages('Refresh Token cannot be blank') :false;
            $response->send();
            exit;
        }

        try{

            $refreshToken = $patchData->refreshtoken;
            $query = $writeDb->prepare('select tblsessions.id as sessionid, tblsessions.userid as userid, accesstoken, refreshtoken, useractive, loginattempts, accesstokenexpiry, refreshtokenexpiry from tblsessions, tblusers where tblusers.id = tblsessions.userid and tblsessions.id = :sessionid and tblsessions.accesstoken = :accesstoken and tblsessions.refreshtoken = :refreshtoken');
            $query->bindParam(':sessionid',$sessionId,PDO::PARAM_STR);
            $query->bindParam(':accesstoken',$accessToken,PDO::PARAM_STR);
            $query->bindParam(':refreshtoken',$refreshToken,PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0)
            {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('Access token or refresh token is incorrect for session id.');
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_sessionid = $row['sessionid'];
            $returned_userid = $row['userid'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_useractive = $row['useractive'];
            $returned_loginattempts = $row['loginattempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

            if($returned_useractive !== 'Y')
            {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('User account is not active');
                $response->send();
                exit;
            }

            if($returned_loginattempts >=3 )
            {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('User account is currently locked out.');
                $response->send();
                exit;
            }

            if(strtotime($returned_refreshtokenexpiry) < time()) {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('Refresh token has expired. Please log in again.');
                $response->send();
                exit;
            }


            $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24).time()));                  $refreshToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24).time()));

            $accessTokenExpirySeconds = 1200;
            $refreshTokenExpirySeconds = 1209600;


            $query = $writeDb->prepare('update tblsessions set accesstoken = :accesstoken, accesstokenexpiry = date_add(NOW() , INTERVAL :accesstokenexpiryseconds SECOND ), refreshtoken = :refreshtoken, refreshtokenexpiry = date_add(NOW(), INTERVAL  :refreshtokenexpiryseconds SECOND ) where id = :sessionid and userid= :userid and accesstoken=:returnedaccesstoken and refreshtoken = :returnedrefreshtoken');
            $query->bindParam(':accesstoken',$accessToken,PDO::PARAM_STR);
            $query->bindParam(':refreshtoken',$refreshToken,PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiryseconds',$accessTokenExpirySeconds,PDO::PARAM_INT);
            $query->bindParam(':refreshtokenexpiryseconds',$refreshTokenExpirySeconds,PDO::PARAM_INT);
            $query->bindParam(':sessionid',$returned_sessionid,PDO::PARAM_INT);
            $query->bindParam(':userid',$returned_userid,PDO::PARAM_INT);
            $query->bindParam(':returnedaccesstoken',$returned_accesstoken,PDO::PARAM_STR);
            $query->bindParam(':returnedrefreshtoken',$returned_refreshtoken,PDO::PARAM_STR);

            $query->execute();
            $rowCount = $query->rowCount();

            if($rowCount === 0)
            {
                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('Access token could not be refreshed. Please log in again.');
                $response->send();
                exit;
            }

            $returnData = array();
            $returnData['session_id'] = $returned_sessionid;
            $returnData['access_token'] = $accessToken;
            $returnData['access_token_expiry'] = $accessTokenExpirySeconds;
            $returnData['refresh_token'] = $refreshToken;
            $returnData['refresh_token_expiry'] = $refreshTokenExpirySeconds;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->setMessages('Refreshed access token successfully.');
            $response->send();
            exit;

        }
        catch (PDOException $ex)
        {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->setMessages($ex->getMessage());
            $response->send();
            exit;
        }

    }
    else{
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->setMessages('Request method not allowed');
        $response->send();
        exit;
    }
}
elseif(empty($_GET))
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

            if(!password_verify($password,$returnedPassword))
            {
                $query = $writeDb->prepare('update tblusers set loginattempts = loginattempts + 1 where id =:id');
                $query->bindParam(':id',$returnedId,PDO::PARAM_INT);
                $query->execute();

                $response = new Response();
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->setMessages('Username or password is incorrect');
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









