# Lumen Iron Worker

## What and why

A worker is a great way to run tasks as needed taking the load off your applications server and greatly speeding up the process of a task as you can run numerous workers at once.

A lot of this comes from http://dev.iron.io/worker/beta/getting_started/ and 
http://dev.iron.io/worker/beta/cli/ and their examples


## Topics covered

  * Creating a Lumen Worker
  * Creating a statically linked binary in the worker
  * Testing the worker locally with Docker
  * Entering your docker environment
  * Design patterns

## Install Lumen

~~~
composer create-project laravel/lumen --prefer-dist
~~~

Add to composer.json

>       "iron-io/iron_mq": "~1.5",
      "iron-io/iron_worker": "~1.4"
        
So now it looks like
~~~
    "require": {
        "laravel/lumen-framework": "5.0.*",
        "vlucas/phpdotenv": "~1.0",
        "iron-io/iron_mq": "~1.5",
        "iron-io/iron_worker": "~1.4"
    },
~~~

## Install iron client

See their notes here http://dev.iron.io/worker/beta/cli/

## Install docker

On a mac they have great steps here for that https://docs.docker.com/installation/mac/

## Environment settings

For Lumen we can simply use our typical .env file. For Iron you put your info in the iron.json file in the root of the app (make sure to add this to .gitignore)

The format is 

~~~
{ "token": "foo", "project_id": "bar" }
~~~


## The worker

Make a folder called workers at the root of your app

In there place your worker file. In this case `ExampleOneWorker`. This is what gets called, as you will see soon, when the worker starts. This is what will receive the payload.

~~~
workers/ExampleOneWorker.php
~~~

Inside of this to start will be 

~~~
<?php

require_once __DIR__ . '/libs/bootstrap.php';

$payload = getPayload(true);

fire($payload);

function fire($payload)
{
    try
    {
        $handler = new \App\ExampleOneHandler();
        $handler->handle($payload);
    }

    catch(\Exception $e)
    {
        $message = sprintf("Error with worker %s", $e->getMessage());
        echo $message;
    }

}
~~~

For testing reasons and code clarity I do not like to put much code in here. I instantiate a handler class and pass in the payload.

The getPayload in the helper.php file, provided by an Iron.io example, will get the payload for us.

There is another folder to make in there called libs and for now it has this file `bootstrap.php` and `helper.php` [1]

With the contents

~~~
<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
require_once __DIR__ . '/helper.php';
use Illuminate\Encryption\Encrypter;
$app->boot();

function decryptPayload($payload)
{
    $crypt = new Encrypter(getenv('IRON_ENCRYPTION_KEY'));
    $payload = $crypt->decrypt($payload);
    return json_decode(json_encode($payload), FALSE);
}
~~~

`helper.php` I placed a gist here https://gist.github.com/alnutile/41ee747bb8e1810d19e8


Also for this example we will need a `payload.json` file in the root of our app. More on that shortly, for now put this into the file.

~~~
{
    "foo": "bar"
}
~~~

Finally our app folder has the `ExampleOneHandler.php` file to handle the job.

~~~
<?php

namespace App;


class ExampleOneHandler {

    public function handle($payload)
    {
        echo "This is the Payload";
        echo print_r($payload, 1);

        
    }
}
~~~

We will do more shortly.

Here is the folder/file layout

![files](https://dl.dropboxusercontent.com/s/c561wmsnv8hl2rm/worker_files.png?dl=0)

## Round 1 ExampleOneHandler

Lets now run this and see what happens.

Using docker we can run this locally

~~~
docker run --rm -v "$(pwd)":/worker -w /worker iron/images:php-5.6 sh -c "php /worker/workers/ExampleOneWorker.php -payload payload.json"
~~~

You just ran, what ideally will be, the exact worker you will run when you upload the code. It will take a moment on the first run. After that it will be super fast.

Here is my output

![outputone](https://dl.dropboxusercontent.com/s/4qkq5e21jl550sg/worker_command.png?dl=0)

### Uploading to Iron

#### Bundle

This is really easy to make a script for by just adding them to an upload_worker.sh file in the root of your app and running that as needed.

~~~
touch ExampleOneWorker.zip
rm ExampleOneWorker.zip
zip -r ExampleOneWorker.zip . -x *.git*
iron worker upload --stack php-5.6 ExampleOneWorker.zip php workers/ExampleOneWorker.php
~~~

So we are touching the file so there are no errors if it is not there.
Then we rm it 
And zip it ignoring .git to keep it slim
and then we upload it with the worker and point to the directory to use.

**Don't run it just yet**

I add my iron.json file to the root of my app as noted above.

and I make the Project on the Iron HUD

![iron](https://dl.dropboxusercontent.com/s/qq2h0to2epnc0qw/worker_json.png?dl=0)

And then I can run the `make_worker.sh` I made above

You should end up with this output

![output](https://dl.dropboxusercontent.com/s/utb478g6510rssd/worker_iron_upload.png?dl=0)

#### Looking at the HUD (Iron WebUI)

Under Worker and tasks we see

![worker](https://dl.dropboxusercontent.com/s/7d1klwablw037wh/worker_hud_tasks.png?dl=0)

So lets run it from the command line to see it work

~~~
iron worker queue --wait -payload-file payload.json ExampleOneWorker
~~~

The wait is pretty cool since we can get this output. This is key when doing master slave workers as well.

You get the same output as before. But it was run on the worker

Here is the HUD 

![worker ran](https://dl.dropboxusercontent.com/s/bxc1dolij0l2f7w/worker_run_example1.png?dl=0)

## Round 2 Lets do something real

So far the payload has not done much but lets use it in this next example.

As above we make and `ExampleTwoWorker.php`

Make payload2.json file

~~~
{
    "search_word": "batman"
}
~~~

Then we use it to call our `ExampleTwoWorkerHandler`

**warning this is not an example on good php code**

~~~
<?php namespace App;


class ExampleTwoHandler {

    protected $search_word;
    protected $result;

    public function handle($payload)
    {
		 $this->search_word = $payload['search_word'];
        $this->getImage();
        return $this->popFirstResult();
    }

    protected function getImage()
    {
        $url = 'http://ajax.googleapis.com/ajax/services/search/images?v=1.0&q=';
        $url .= urlencode("site:www.thebrickfan.com " . $this->search_word . " lego");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($data, true);
        $this->result = $result;
    }

    protected function popFirstResult()
    {
        $max = count($this->result['responseData']['results']);
        if($max == 0)
        {
            throw new \Exception(sprintf("No image found :( for %s", $this->search_word));
        }
        else
        {
            $image = $this->result['responseData']['results'][rand(0, $max - 1)]['url'];
            return file_get_contents($image);
        }
    }

}
~~~

I test locally 

~~~
docker run --rm -v "$(pwd)":/worker -w /worker iron/images:php-5.6 sh -c "php /worker/workers/ExampleTwoWorker.php -payload payload2.json" > output.png
~~~

But this time put the output into a file and we get

![lego guys](https://dl.dropboxusercontent.com/s/kmtuvgzhpzws6xz/worker_lego_one.png?dl=0)


### Making a custom binary

Before I get this to iron lets make it more useful since I will lose that output.png file on the worker. Some workers we have would convert that into a base64 blob and send that back in a callback. 


One enter into docker like I noted above

Two run `apt-get update`

Then run `apt-get install jp2a`

Then make a folder called /worker/builds/

And in there follow these instructions http://jurjenbokma.com/ApprenticesNotes/getting_statlinked_binaries_on_debian.html replacing jp2a as needed.

Then make a folder called /worker/bin and copy jp2a from `/worker/builds/jp2a-1.0.6/src/jp2a` to this bin folder.

You should be able to see that run now by ding /worker/bin/jp2a even run `apt-get remove jp2a` to show it works as a standalone library [3]

Let's adjust our code

~~~
<?php
/**
 * Created by PhpStorm.
 * User: alfrednutile
 * Date: 4/27/15
 * Time: 9:02 PM
 */

namespace App;


use Illuminate\Support\Facades\File;

class ExampleTwoHandler {


    protected $search_word;
    protected $result;

    public function handle($payload)
    {
        $this->search_word = $payload['search_word'];
        $this->getImage();
        return $this->popFirstResult();
    }

    protected function getImage()
    {
        $url = 'http://ajax.googleapis.com/ajax/services/search/images?v=1.0&q=';
        $url .= urlencode("site:www.thebrickfan.com " . $this->search_word . " lego");
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($data, true);
        $this->result = $result;
    }

    protected function popFirstResult()
    {
        $max = count($this->result['responseData']['results']);
        if($max == 0)
        {
            throw new \Exception(sprintf("No image found :( for %s", $this->search_word));
        }
        else
        {
            $image = $this->result['responseData']['results'][rand(0, $max - 1)]['url'];
            $path_to_worker = base_path('bin/');
            exec("chmod +x {$path_to_worker}/jp2a");
            exec("TERM=xterm {$path_to_worker}/bin/jp2a $image", $output);
            return implode("\n", $output);
        }
    }

}
~~~


run locally and you might get some decent output or not :(


![batman](https://dl.dropboxusercontent.com/s/76vbdf0iubehf5c/worker_batman.png?dl=0)


### Make and upload the worker

Then I run `sh ./make_worker_two.php`

~~~
touch ExampleTwoWorker.zip
rm ExampleTwoWorker.zip
zip -r ExampleTwoWorker.zip . -x *.git*
iron worker upload --stack php-5.6 ExampleTwoWorker.zip php workers/ExampleTwoWorker.php
~~~

And run and wait 

~~~
iron worker queue --wait -payload-file payload2.json ExampleTwoWorker
~~~

And if all goes well your console and the logs should show something like

![batman](https://dl.dropboxusercontent.com/s/263tlw5vkswqqvp/worker_results.png?dl=0)


## Entering your docker environment 

Easy

~~~
docker run -it -v "$(pwd)":/worker -w /worker iron/images:php-5.6 /bin/bash
~~~

Now you can test things in there, download packages etc.

## MVC

Not sure if this really is correct but I tend to see the Worker file as my route file. The handler as the controller and other classes as needed, Service, Repository etc. This makes things more testable etc and better organize imo.


## Connecting the Queue to the Worker


Coming soon...


## Numerous Environments

Coming soon...


## Deploy from CodeShip


Coming soon...


[1] These seems to be a part of the iron worker for version 1 but not sure why not for 2 maybe there is a better pattern for this.

[2] I renamed it to ExampleOneLumen

[3] So far this is a 50/50 solution it did not work for pdf2svg but it did work for pdftk
