<?php

return [
  'db' => [
    'host'    => '127.0.0.1',     // or 'localhost'
    'port'    => 3306,            // default MySQL port
    'name'    => 'watchbuddy',    // <-- your database name
    'user'    => 'root',          // <-- your MySQL user
    'pass'    => '',              // <-- your MySQL password
    'charset' => 'utf8mb4',
  ],

  'session' => [
    'name'     => 'WATCHBUDDYSESSID', // custom session name
    'lifetime' => 0,                  // 0 = session cookie
    'secure'   => false,              // must be false on http://localhost, true only on HTTPS
    'samesite' => 'Lax',              // 'Lax' works well for same-origin dev
  ],

  'cors' => [
    // Frontend origin(s). Example: React/Vite on localhost:5173
    'allowed_origins'   => ['http://localhost', 'http://127.0.0.1:5173'],
    'allowed_methods'   => ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
    'allowed_headers'   => ['Content-Type','Accept','Authorization','X-Requested-With'],
    'allow_credentials' => true,
  ],
];
