<?php
$pass = 'sLp@2W9q7g#TzFx!';
$hash = '$2a$12$tu0emiWRVMyr.z5QvWgLlOm450nVXdcWBXsoO43apKw7zNuH6iv5C';

if (password_verify($pass, $hash)) {
    echo "✅ Пароль вірний";
} else {
  echo password_hash('sLp@2W9q7g#TzFx!', PASSWORD_DEFAULT);
  
}


//                 ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php-error.log');