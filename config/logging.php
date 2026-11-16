<?php
return [
    "default" => "daily",
    "channels" => [
        "daily" => ["driver"=>"daily","path"=>storage_path("logs/laravel.log"),"level"=>"warning","days"=>7,"replace_placeholders"=>true],
        "single" => [
            "driver" => "single",
            "path" => storage_path("logs/laravel.log"),
            "level" => "info",
            "replace_placeholders" => true,
        ],
    ],
];
