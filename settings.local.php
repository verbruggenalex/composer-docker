<?php

$databases = array (
  'default' => array (
    'default' => array (
      'driver' => 'mysql',
      'username' => 'root',
      'password' => 'password',
      'host' => 'mysql',
      'port' => '3306',
      'database' => 'drupal',
      'charset' => 'utf8mb4',
      'collation' => 'utf8mb4_general_ci',
    ),
    'extra' => array (
      'driver' => 'mysql',
      'username' => 'root',
      'password' => 'password',
      'host' => 'mysql',
      'port' => '3306',
      'database' => 'extra',
      'charset' => 'utf8mb4',
      'collation' => 'utf8mb4_general_ci',
    ),
  ),
);
ini_set('max_input_vars', 3000);
//$base_url = 'http:/domain.local';
$conf['drupal_http_request_fails'] = FALSE;
