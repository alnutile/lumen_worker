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
            $path_to_worker = base_path('bin');
            exec("chmod +x {$path_to_worker}/jp2a");
            exec("TERM=xterm {$path_to_worker}/jp2a $image", $output);
            return implode("\n", $output);
        }
    }

}