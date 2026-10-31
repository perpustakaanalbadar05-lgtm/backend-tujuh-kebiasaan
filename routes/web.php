<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Secure file storage serving
Route::get('/file-storage/{path}', function ($path) {
    $basePath = realpath(storage_path('app/public'));
    $fullPath = realpath(storage_path('app/public/' . $path));

    // Cegah path traversal: pastikan file berada di dalam folder storage/app/public
    if ($basePath && $fullPath && str_starts_with($fullPath, $basePath) && file_exists($fullPath)) {
        return response()->file($fullPath);
    }
    abort(404);
})->where('path', '.*');
