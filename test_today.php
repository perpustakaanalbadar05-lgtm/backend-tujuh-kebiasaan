<?php
require 'vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$student = \App\Models\Student::find(1);
$user = \App\Models\User::find($student->user_id);

$request = \Illuminate\Http\Request::create('/api/journals/today', 'GET');
$request->setUserResolver(function () use ($user) { return $user; });

$controller = new \App\Http\Controllers\Api\Transaction\JournalController();
$response = $controller->today($request);

echo $response->getContent();
