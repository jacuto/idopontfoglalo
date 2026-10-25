<?php
return [
    "name" => env("APP_NAME", "Ráérő"),
    "env" => env("APP_ENV", "production"),
    "debug" => (bool) env("APP_DEBUG", false),
    "url" => env("APP_URL", "http://localhost:8000"),
    "timezone" => "Europe/Budapest",
    "locale" => "hu",
    "fallback_locale" => "en",
    "cipher" => "AES-256-CBC",
    "key" => env("APP_KEY"),
    "previous_keys" => [],
    "maintenance" => ["driver" => "file"],
];
