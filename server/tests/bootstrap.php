<?php

declare(strict_types=1);

use Tests\TestDatabase;

require_once __DIR__.'/../vendor/autoload.php';

// Before the app boots: this run's own MySQL database (Tests\TestDatabase).
TestDatabase::bootstrap(dirname(__DIR__));
