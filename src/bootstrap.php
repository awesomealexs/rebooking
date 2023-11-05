<?php

use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__) . '/vendor/autoload.php';


$dotenv = new Dotenv(true);
$dotenv
    ->usePutenv()
    ->bootEnv(dirname(__DIR__ ). '/.env');

