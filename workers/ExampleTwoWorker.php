<?php

require_once __DIR__ . '/libs/bootstrap.php';

$payload = getPayload(true);

fire($payload);

function fire($payload)
{
    try
    {
        $handler = new \App\ExampleTwoHandler();
        echo $handler->handle($payload);
    }

    catch(\Exception $e)
    {
        $message = sprintf("Error with worker %s", $e->getMessage());

        echo $message;
    }

}