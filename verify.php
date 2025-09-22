<?php
$pass = 'sLp@2W9q7g#TzFx!ruskiy-korabl-idi-nahuy';
$hash = '$2a$12$Hw.T26d8a5pWROcqcu7Bm.O8Olp4dDl5TdY0iauRcVcSrw/LFi9oy';

if (password_verify($pass, $hash)) {
    echo "✅ Пароль вірний";
} else {
  echo password_hash('sLp@2W9q7g#TzFx!', PASSWORD_DEFAULT);
  
}


// file_put_contents(__DIR__ . '/php-error.log', print_r($headers, true) . "\n", FILE_APPEND);
