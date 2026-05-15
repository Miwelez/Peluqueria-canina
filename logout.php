<?php
session_start();

// vaciamos el array de sesión en memoria
$_SESSION = [];

// borramos la cookie mandándole una nueva con fecha en el pasado
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// y por último destruimos el archivo de sesión del servidor
session_destroy();

header('Location: index.php');
exit;
