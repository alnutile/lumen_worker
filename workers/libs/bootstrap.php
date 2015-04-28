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