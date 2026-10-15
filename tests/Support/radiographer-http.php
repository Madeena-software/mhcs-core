<?php

declare(strict_types=1);
use Illuminate\Http\Request;

if (getenv('APP_ENV') !== 'testing') {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_string($path) && preg_match('~^/(build|css|js|fonts)/[A-Za-z0-9_./-]+$~', $path) && ! str_contains($path, '..') && is_file(dirname(__DIR__, 2).'/public'.$path)) {
    return false;
}
$app = require __DIR__.'/radiographer-browser-bootstrap.php';
$app->handleRequest(Request::capture());
