<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $basePath = Application::inferBasePath();
        $defaultEnvPath = $basePath.'/.env';
        $testingEnvPath = $basePath.'/.env.testing';
        $exampleEnvPath = $basePath.'/.env.example';

        if (! file_exists($defaultEnvPath) && file_exists($exampleEnvPath)) {
            copy($exampleEnvPath, $defaultEnvPath);
        }

        if (! getenv('APP_KEY') && ! isset($_ENV['APP_KEY']) && ! isset($_SERVER['APP_KEY'])) {
            putenv('APP_KEY=base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=');
            $_ENV['APP_KEY'] = 'base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=';
            $_SERVER['APP_KEY'] = 'base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=';
        }

        /** @var \Illuminate\Foundation\Application $app */
        $app = require $basePath.'/bootstrap/app.php';

        if (file_exists($testingEnvPath)) {
            $app->loadEnvironmentFrom('.env.testing');
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
