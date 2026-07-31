<?php

if(!function_exists('set_user_session')){
    
    function set_user_session(array $user = []): void {
        global $_SESSION;
        if(session_status() === PHP_SESSION_NONE){
            session_start();
        }

        if(empty($user['id'])) {
            exit;
        }

        $_SESSION['token'] = md5(md5($user['id']));
        
        $_SESSION['username'] = $user['username'] ?? null;
        $_SESSION['role'] = $user['role'] ?? null;
    }
}

if(!function_exists('authentication')){
    function authentication(): bool|array {
        global $_SESSION, $coon;
        if(session_status() === PHP_SESSION_NONE){
            session_start();
        }

        if (empty ($_SESSION['token'])) {
            return false;
        }

        $getToken = $_SESSION['token'];
        $sqlCheckToken = $coon->query("SELECT * FROM users WHERE md5(md5(id)) = '{$getToken}' LIMIT 1");
        if($sqlCheckToken->num_rows != 1){
            return false;
        }

        return $sqlCheckToken->fetch_assoc();
    }
}