<?php

namespace App;


class ExampleOneHandler {

    public function handle($payload)
    {
        echo "This is the Payload";
        echo print_r($payload, 1);
    }
}