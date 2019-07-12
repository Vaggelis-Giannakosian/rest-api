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
    }

/**
 *
 * GET METHOD
 */
    if($_SERVER['REQUEST_METHOD'] == 'GET'){

        try{
            $query = $readDB->prepare('select id,title,description,DATE_FORMAT(deadline,"%d/%m/%Y %H:%i") as deadline ,completed from tbltasks where id = :taskid');
            $query->bindParam(':taskid',$taskid, PDO::PARAM_INT);
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

    }elseif($_SERVER['REQUEST_METHOD'] == 'DELETE'){




    }elseif($_SERVER['REQUEST_METHOD'] == 'PATCH'){


    }else{
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->setMessages("Request Method not allowed");
        $response->send();
        exit;
    }




