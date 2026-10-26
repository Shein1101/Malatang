<?php

function current_user_id(){
    return $_SESSION['user_id'] ?? null;
}

function require_login(){
    if(current_user_id() === null){
        header('Location: login.php');
        exit;
    }
}

function guest_token(){
    if(empty($_SESSION['guest_token'])){
        $_SESSION['guest_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['guest_token'];
}

function require_admin(){
    if(current_user_id() === null || ($_SESSION['role'] ?? null) !== 'admin'){
        header('Location: ../public/login.php');
        exit;
    }
}
