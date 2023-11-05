<?php
use App\Base;

ini_set('memory_limit', 0);

require_once __DIR__.'/bootstrap.php';

$base = new Base();

$base->handleHotelsDumpFile(true);