<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (file_exists(dirname(__DIR__) . '/config/bootstrap.php')) {
    require dirname(__DIR__) . '/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    $projectRoot = dirname(__DIR__);

    $envFile = file_exists($projectRoot . '/.env.test')
        ? $projectRoot . '/.env.test'
        : (file_exists($projectRoot . '/.env.test.dist')
            ? $projectRoot . '/.env.test.dist'
            : $projectRoot . '/.env');

    (new Dotenv())->bootEnv($envFile);
}
