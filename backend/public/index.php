<?php

declare(strict_types=1);

use App\Application\Bootstrap;

require __DIR__ . '/../vendor/autoload.php';

$app = Bootstrap::createApp();
$app->run();
