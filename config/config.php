<?php
declare(strict_types=1);

return [
  'db' => [
    'host' => getenv('DB_HOST') ?: 'db',
    'name' => getenv('DB_NAME') ?: 'whisperedframes',
    'user' => getenv('DB_USER') ?: 'wf_user',
    'pass' => getenv('DB_PASS') ?: 'wf_password',
    'charset' => 'utf8mb4',
  ],
  'app' => [
    'session_name' => 'wf_session',
  ],
];
