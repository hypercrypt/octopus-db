<?php

foreach (scandir(__DIR__) as $file)
    if ($file !== __FILE__ && str_ends_with(haystack: $file, needle: '.php'))
        require_once __DIR__ . '/' . $file;
