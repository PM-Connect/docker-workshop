<?php

include '../vendor/autoload.php';

$app = new App\App();
$app->setupLogging();

$content = $app->renderExamplePage();
echo $content;
