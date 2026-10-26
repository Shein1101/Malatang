<?php

require_once '../includes/db.php';
require_once '../includes/api.php';
require_once '../includes/auth.php';

$action = $_GET['action'] ?? '';

switch($action){
case 'login':
    require_method('POST');

    $in = read_json();
    require_fields($in,['email','password']);

    $stmt = $pdo->prepare(
        'SELECT id, name, email, password_hash, role
        FROM users
        WHERE email = ?'
    );
    $stmt->execute([$in['email']]);
    $user = $stmt->fetch();
    if(!$user){
        send_error('Invalid email or password',401);
    }

    if(!password_verify($in['password'],$user['password_has'])){
        send_error('Invalid email or password',401);
    }

    $_SESSION['user_id']=(int)$user['id'];
    $_SESSION['role'] = $user['role'];
    send_json([
    'id' => (int)$user['id'],
    'name' => $user['name'],
    'role' => $user['role']
    ]);
    break;
case 'signup':

    $in = read_json();
    require_fields($in, ['name', 'email', 'phone', 'password']);

    //password ကိုhashပြောင်းသိမ်း
    $password_hash = password_hash($in['password'],PASSWORD_DEFAULT);

    //userရှိပြီးသားလားစစ်/user ရှိရင် error ပြစ်
    $stmt = $pdo->prepare('SELECT id from users Where email = ?');
    $stmt->execute([$in['email']]);
    if($stmt->fetch()){
        send_error('Email already exists', 409);
    }

    //မရှိရင် user အသစ်ထည့်
    $stmt = $pdo->prepare(
        'INSERT INTO users (email, password_hash, name, phone, role)
        VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $in['email'],
        $password_hash,
        $in['name'],
        $in['phone'],
        'customer'
    ]);

    //user id ပြန်တောင်း
    $user_id = (int)$pdo->lastInsertId();

    //account done --> log the user in
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = 'customer';

    //send json to signup.php
    send_json([
        'id' => $user_id,
        'name' => $in['name']
    ]);

case 'logout':
    //logout
    break;
case 'me':
    //current user
    break;
default:
    send_error('Invalid action',400);
}

