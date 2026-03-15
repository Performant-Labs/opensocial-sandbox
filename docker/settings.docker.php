<?php

/**
 * @file
 * Docker container database and environment settings.
 *
 * Included by settings.php when DOCKER_ENV=true.
 */

// Database connection from environment variables.
$databases['default']['default'] = [
  'database' => getenv('MYSQL_DATABASE'),
  'username' => getenv('MYSQL_USER'),
  'password' => getenv('MYSQL_PASSWORD'),
  'host'     => getenv('MYSQL_HOST') ?: 'db',
  'port'     => '3306',
  'driver'   => 'mysql',
  'prefix'   => '',
  'collation' => 'utf8mb4_general_ci',
  'init_commands' => [
    'isolation_level' => 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED',
  ],
];

// Hash salt from environment.
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'change-me-in-dotenv';

// Private files path.
$settings['file_private_path'] = '/var/www/html/private';

// Trusted host pattern from environment.
$trusted_host = getenv('DRUPAL_TRUSTED_HOST');
if ($trusted_host) {
  $settings['trusted_host_patterns'] = [
    '^' . $trusted_host . '$',
    '^localhost$',
  ];
}

// Reverse proxy (host nginx → container).
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = ['127.0.0.1', '172.16.0.0/12', '10.0.0.0/8'];
$settings['reverse_proxy_trusted_headers'] =
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR |
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST |
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT |
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO;
