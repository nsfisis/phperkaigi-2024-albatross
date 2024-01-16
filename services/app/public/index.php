<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Nsfisis\Albatross\App;
use Nsfisis\Albatross\Config;

(new App(Config::fromEnvVars()))->run();
