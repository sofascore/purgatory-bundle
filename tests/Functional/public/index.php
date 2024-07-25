<?php

declare(strict_types=1);

use Sofascore\PurgatoryBundle2\Tests\Functional\AbstractKernelTestCase;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__, 3).'/vendor/autoload.php';

/** @var class-string<AbstractKernelTestCase> $testClass */
$testClass = getenv('TEST_CLASS');
$testKernelOptions = getenv('TEST_KERNEL_OPTIONS');

$kernel = $testClass::createKernel($testKernelOptions ? json_decode($testKernelOptions, true) : []);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
