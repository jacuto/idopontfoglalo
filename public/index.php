<?php
use Illuminate\Http\Request;
if (PHP_SAPI === "cli-server") {
    $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    if (in_array($path, ["/app.js", "/workspace.js", "/embed.js", "/style.css"], true)) {
        return false;
    }
}
define("LARAVEL_START", microtime(true));
require __DIR__ . "/../vendor/autoload.php";
$application = require_once __DIR__ . "/../bootstrap/app.php";
$application->handleRequest(Request::capture());
