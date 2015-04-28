touch ExampleTwoWorker.zip
rm ExampleTwoWorker.zip
zip -r ExampleTwoWorker.zip . -x *.git*
iron worker upload --stack php-5.6 ExampleTwoWorker.zip php workers/ExampleTwoWorker.php