<?php

require_once 'db.php';
require_once '../model/Response.php';
require_once '../model/Task.php';


try
{
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
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

    //start of auth script
    if(!isset( $_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) <1 )
    {
        $response = new Response();
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        !isset( $_SERVER['HTTP_AUTHORIZATION']) ?  $response->setMessages('Access Token must be provided.'):false;
        strlen($_SERVER['HTTP_AUTHORIZATION']) <1  ?  $response->setMessages('Access Token cannot be blank.'):false;
        $response->send();
        exit;
    }

    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

    try{

        $query = $writeDB->prepare('select userid, accesstokenexpiry, useractive, loginattempts from tblsessions, tblusers where tblsessions.userid = tblusers.id and accesstoken=:accesstoken');
        $query->bindParam(':accesstoken',$accessToken,PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if($rowCount === 0 )
        {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessages('Access Token is not valid');
            $response->send();
            exit;
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);
        $returned_userid = $row['userid'];
        $returned_accesstokenexpiry = $row['accesstokenexpiry'];
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];


        if( $returned_useractive !== 'Y' || $returned_loginattempts >=3 )
        {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessages('User account not active or currently locked out.');
            $response->send();
            exit;

        }

        if(strtotime($returned_accesstokenexpiry) < time())
        {
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->setMessages('Access Token has expired.');
            $response->send();
            exit;
        }



    }
    catch (PDOException $ex)
    {
        error_log('Database error - '.$ex,0);
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->setMessages('There was a Db error. Please try again.');
        $response->send();
        exit;
    }

    //end of auth script
    /**
     * Get the id of the task if the request method is get
     */

    if(array_key_exists("taskid",$_GET)){
        $taskid = $_GET["taskid"];

        if($taskid == '' || !is_numeric($taskid)){
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessages('Task id cannot be blank and must be numeric');
            $response->send();
            exit;
        }

        /**
         * GET METHOD
         */
        if($_SERVER['REQUEST_METHOD'] === 'GET'){

            try{
                $query = $readDB->prepare('select id,title,description,DATE_FORMAT(deadline,"%d/%m/%Y %H:%i") as deadline ,completed from tbltasks where id = :taskid and userid=:userid');
                $query->bindParam(':taskid',$taskid, PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_INT);
                $query->execute();
                $rowCount = $query->rowCount();

                if($rowCount === 0) {
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->setMessages("Task not found");
                    $response->send();
                    exit;
                }

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);
                    $taskArray [] = $task->returnTaskAsArray();
                }
                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->setToCache(true);
                $response->setData($returnData);
                $response->send();
                exit;

            }  catch (PDOException $ex )
            {
                error_log("Database query error - ".$ex,0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages('Could not get Task');
                $response->send();
                exit;
            }catch (TaskException $tex)
            {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages($tex->getMessage());
                $response->send();
                exit;
            }

        }
        /**
         * Delete Method
         */
        elseif($_SERVER['REQUEST_METHOD'] == 'DELETE'){

            try{
                $query = $writeDB->prepare('delete from tbltasks where id = :taskid and userid=:userid');
                $query->bindParam(':taskid',$taskid, PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_INT);
                $query->execute();
                $rowCount = $query->rowCount();

                if($rowCount === 0){
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->setMessages('Task not found');
                    $response->send();
                    exit;
                }

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->setMessages('Task deleted successfully');
                $response->send();
                exit;

            }catch(PDOException $ex)
            {
                error_log("Database query error - ".$ex,0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages('Failed to delete Task');
                $response->send();
                exit;
            }

        }
        /**
         * Patch Method
         */
        elseif($_SERVER['REQUEST_METHOD'] == 'PATCH'){

            try{

              if ($_SERVER['CONTENT_TYPE']!=='application/json')
                {
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->setMessages('The content type must be set to JSON');
                    $response->send();
                    exit;
                }

                $rawJSONData = file_get_contents('php://input');
              if(!$jsonPatchData = json_decode($rawJSONData))
              {
                  $response = new Response();
                  $response->setHttpStatusCode(400);
                  $response->setSuccess(false);
                  $response->setMessages('Not valid JSON syntax');
                  $response->send();
                  exit;
              }

              $titleUpdated = $descriptionUpdated = $deadlineUpdated = $completedUpdated = false;
              $queryFields = '';

              if(isset($jsonPatchData->title))
              {
                  $titleUpdated=true;
                  $queryFields .= "title = :title, ";
              }

              if(isset($jsonPatchData->description))
              {
                  $descriptionUpdated=true;
                  $queryFields .= "description = :description, ";
              }

              if(isset($jsonPatchData->deadline))
              {
                  $deadlineUpdated=true;
                  $queryFields .= "deadline = STR_TO_DATE(:deadline , '%d/%m/%Y %H:%i' ), ";
              }

              if(isset($jsonPatchData->completed))
              {
                  $completedUpdated=true;
                  $queryFields .= "completed = :completed, ";
              }

              $queryFields = rtrim($queryFields,', ');


              if(!$titleUpdated && !$descriptionUpdated && !$deadlineUpdated && !$completedUpdated)
              {
                  $response = new Response();
                  $response->setHttpStatusCode(400);
                  $response->setSuccess(false);
                  $response->setMessages('No data provided');
                  $response->send();
                  exit;
              }


                $query = $writeDB->prepare('select id,title,description,DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id=:taskid and userid=:userid');
                $query->bindParam(':taskid',$taskid,PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount === 0)
                {
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->setMessages('Task not found');
                    $response->send();
                    exit;
                }

                while($row = $query->fetch(PDO::FETCH_ASSOC))
                {
                    $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);

                }


              $query = $writeDB->prepare("update tbltasks set $queryFields where id=:taskid and userid=:userid");

                if($titleUpdated){
                $task->setTitle($jsonPatchData->title);
                $upTitle = $task->getTitle();
                $query->bindParam(":title",$upTitle, PDO::PARAM_STR);
                    $query->bindParam(':userid',$returned_userid, PDO::PARAM_INT);
                }
                if($descriptionUpdated){
                    $task->setDescription($jsonPatchData->description);
                    $upDescription = $task->getDescription();
                    $query->bindParam(":description",$upDescription, PDO::PARAM_STR);
                }
                if($deadlineUpdated){
                    $task->setDeadline($jsonPatchData->deadline);
                    $upDeadline = $task->getDeadline();
                    $query->bindParam(":deadline",$upDeadline, PDO::PARAM_STR);
                }
                if($completedUpdated){
                    $task->setCompleted($jsonPatchData->completed);
                    $upCompleted = $task->getCompleted();
                    $query->bindParam(":completed",$upCompleted, PDO::PARAM_STR);
                }


              $query->bindParam(':taskid',$taskid,PDO::PARAM_INT);


                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount == 0)
                {
                    $response = new Response();
                    $response->setHttpStatusCode(500);
                    $response->setSuccess(false);
                    $response->setMessages('Could not update task');
                    $response->send();
                    exit;
                }


                $query = $writeDB->prepare('select id,title,description,DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id=:taskid and userid=:userid');
                $query->bindParam(':taskid',$taskid,PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount == 0)
                {
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->setMessages('Task not found after update');
                    $response->send();
                    exit;
                }

                $taskArray = array();

                while($row = $query->fetch(PDO::FETCH_ASSOC))
                {
                    $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                    $taskArray [] = $task->returnTaskAsArray();
                }

                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->setMessages('Task updated successfully');
                $response->setData($returnData);
                $response->send();
                exit;


            }
            catch (TaskException $ex)
            {
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->setMessages($ex->getMessage());
                $response->send();
                exit;
            }
            catch (PDOException $ex)
            {
                error_log('Databse query error - '.$ex, 0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages('Failed to update task - check your data for errors');
                $response->send();
                exit;
            }


        }
        else{
            $response = new Response();
            $response->setHttpStatusCode(405);
            $response->setSuccess(false);
            $response->setMessages("Request Method not allowed");
            $response->send();
            exit;
        }


    }
    elseif (array_key_exists('completed',$_GET)){

        $completed = $_GET['completed'];

        if($completed !== 'Y' && $completed !== 'N'){
            $response = new Response();
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->setMessages('Completed must be Y or N');
            $response->send();
            exit;
        }

        if($_SERVER['REQUEST_METHOD'] == 'GET'){

            try{

                $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline,"%d/%m/%Y %H:%i") as deadline, completed from tbltasks where completed = :completed and userid=:userid');
                $query->bindParam(':completed',
                    $completed,PDO::PARAM_STR);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();
                $taskArray = array();

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'],$row['title'],$row['description'],$row['deadline'],$row['completed']);
                    $taskArray [] = $task->returnTaskAsArray();
                }

                $returnData = array();
                $returnData['rows'] = $rowCount;
                $returnData['data'] = $taskArray;
                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->setData($returnData);
                $response->setToCache(true);
                $response->send();
                exit;
            }
            catch (PDOException $pex)
            {
                error_log("Database query error - ".$pex,0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages('Failed to get tasks');
                $response->send();
                exit;
            }
            catch (TaskException $tex)
            {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages($tex->getMessage());
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
    elseif (array_key_exists('page',$_GET)){

        if($_SERVER['REQUEST_METHOD'] === 'GET'){

            $page = $_GET['page'];

            if(!is_numeric($page) || $page<=0 || $page==''){
                $response = new Response();
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->setMessages("The page number must be greater or equal to 1");
                $response->send();
                exit;
            }

            $limitPerPage = 20;
            $offset = ($page-1)*$limitPerPage;
            try{

                $query = $readDB->prepare('select id,title,description,DATE_FORMAT(deadline,"%d/%m/%Y %H:%i") as deadline, completed from tbltasks where userid=:userid LIMIT :limit offset :offset');
                $query->bindParam(':offset', $offset,PDO::PARAM_INT);
                $query->bindParam(':limit', $limitPerPage,PDO::PARAM_INT);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount == 0 && $page==1){
                    $response = new Response();
                    $response->setHttpStatusCode(200);
                    $response->setSuccess(true);
                    $response->setToCache(true);
                    $response->setData(['rows_returned'=>0,'tasks'=>[]]);
                    $response->send();
                    exit;
                }
                if($rowCount == 0){
                    $response = new Response();
                    $response->setHttpStatusCode(404);
                    $response->setSuccess(false);
                    $response->setMessages('This page is not found');
                    $response->send();
                    exit;
                }


                $taskArray = array();

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'],$row['title'] , $row['description'], $row['deadline'], $row['completed']);
                    $taskArray [] = $task->returnTaskAsArray();
                }

                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks'] = $taskArray;

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->setToCache(true);
                $response->setData($returnData);
                $response->send();
                exit;

            }
            catch (PDOException $pex){
                error_log('Database query - '.$pex,0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages('Failed to get tasks');
                $response->send();
                exit;
            }
            catch (TaskException $tex)
            {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages($tex->getMessage());
                $response->send();
                exit;
            }


        }else{
            $response = new Response();
            $response->setHttpStatusCode(405);
            $response->setSuccess(false);
            $response->setMessages("Request method not allowed");
            $response->send();
            exit;
        }





    }
    elseif (empty($_GET))
    {

        if($_SERVER['REQUEST_METHOD'] == 'GET'){

            try{

                $query = $readDB->prepare('select id,title,description, DATE_FORMAT(deadline,"%d/%m/%Y %H:%i") as deadline, completed from tbltasks where userid=:userid');
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_INT);
                $query->execute();
                $rowCount = $query->rowCount();
                $taskArray = array();

                while($row = $query->fetch(PDO::FETCH_ASSOC)){
                    $task = new Task($row['id'],$row['title'],$row['description'], $row['deadline'], $row['completed'] );
                    $taskArray [] = $task->returnTaskAsArray();

                }

                $returnData = array();
                $returnData['rows_returned'] = $rowCount;
                $returnData['tasks']=$taskArray;

                $response = new Response();
                $response->setHttpStatusCode(200);
                $response->setSuccess(true);
                $response->setToCache(true);
                $response->setData($returnData);
                $response->send();
                exit;

            }
            catch (PDOException $pex)
            {
                error_log("Database query error - ".$pex,0);
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages('Failed to get all tasks');
                $response->send();
                exit;
            }catch (TaskException $tex)
            {
                $response = new Response();
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->setMessages($tex->getMessage());
                $response->send();
                exit;
            }


        }
        elseif ($_SERVER['REQUEST_METHOD'] == 'POST')
        {

            try{
                if($_SERVER['CONTENT_TYPE'] !== 'application/json')
                {
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->setMessages('Content Type header is not set to JSON');
                    $response->send();
                    exit;
                }

                $rawPostData = file_get_contents('php://input');
                if(!$jsonData = json_decode($rawPostData))
                {
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    $response->setMessages('Request body is not valid JSON');
                    $response->send();
                    exit;
                }


                if(!isset($jsonData->title) || !isset($jsonData->completed))
                {
                    $response = new Response();
                    $response->setHttpStatusCode(400);
                    $response->setSuccess(false);
                    !isset($jsonData->title)?$response->setMessages('Title cannot be empty'):false;
                    !isset($jsonData->completed)?$response->setMessages('Completed cannot be empty'):false;
                    $response->send();
                    exit;
                }

                $newTask = new Task(null,$jsonData->title, isset($jsonData->description) ? $jsonData->description: null, isset($jsonData->deadline) ? $jsonData->deadline: null, $jsonData->completed);

                $title = $newTask->getTitle();
                $description = $newTask->getDescription();
                $deadline = $newTask->getDeadline();
                $completed = $newTask->getCompleted();

                $query = $writeDB->prepare('insert into tbltasks (title, description, deadline, completed, userid) values (:title,:description,STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'),:completed, :userid)');
                $query->bindParam(':title', $title, PDO::PARAM_STR);
                $query->bindParam(':description', $description, PDO::PARAM_STR);
                $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
                $query->bindParam(':completed', $completed, PDO::PARAM_STR);
                $query->bindParam(':userid',$returned_userid, PDO::PARAM_INT);
                $query->execute();

                $rowCount = $query->rowCount();

                if($rowCount == 0)
                {
                    $response = new Response();
                    $response->setHttpStatusCode(500);
                    $response->setSuccess(false);
                    $response->setMessages('Could not persist task to Db');
                    $response->send();
                    exit;
                }


                $newTask->setId($writeDB->lastInsertId());
                $taskArray []= $newTask->returnTaskAsArray();
                $returnData = array();
                $returnData['rows_returned']=1;
                $returnData['task']= $taskArray;

                $response = new Response();
                $response->setHttpStatusCode(201);
                $response->setSuccess(true);
                $response->setData($returnData);
                $response->setMessages('Task created');
                $response->send();
                exit;

            }
            catch (PDOException $ex)
            {
                error_log('Database query error - '.$ex,0);

            }
            catch (TaskException $ex)
            {
                $response = new Response();
                $response->setHttpStatusCode(400);
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
    else{
        $response = new Response();
        $response->setHttpStatusCode(404);
        $response->setSuccess(false);
        $response->setMessages('Endpoint not found');
        $response->send();
        exit;
    }





