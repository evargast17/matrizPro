<?php
// config/app.php
return [
  'db' => [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'name' => getenv('DB_NAME') ?: 'bdsistemaiepin',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') ?: '',
    'charset' => 'utf8mb4'
  ],
  'app' => [
    'name' => 'Matriz Escolar',
    'base_path' => rtrim(str_replace('index.php','', $_SERVER['PHP_SELF']), '/'),
  ]
];