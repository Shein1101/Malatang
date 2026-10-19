<?php
// Shared database connection (PDO). Owner: Myat / Lin
// Real passwords go in .env, never in GitHub.
$host = 'localhost';
$dbname = 'chilli_nabe';
$user = 'root';
$pass = '';   // XAMPP default

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
