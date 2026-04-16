<?php

declare(strict_types=1);
$hash = '$argon2id$v=19$m=65536,t=4,p=1$czFqOUlMeGs3WkdvWS9OYw$DtEkmK6H4hUk/zI2kKpu6cWJVatlWFje65KpYk1TWto';
$candidates = ['password', 'Password1!', 'admin', 'admin123', 'komorebi', 'Komorebi123!', 'Pass1234!', '123456', 'komorebi2024', 'Komorebi2024!', 'secret', 'changeme'];
foreach ($candidates as $p) {
    if (password_verify($p, $hash)) {
        echo "FOUND: $p\n";
        exit(0);
    }
}
echo "Not found\n";
