<?php
// API: /api/menus (GET/POST/PUT/DELETE) — Owner: Myat
// APIs return JSON only (no HTML). Format from Team Rules:
// { "success": true, "data": { }, "error": null }
header('Content-Type: application/json; charset=utf-8');
require '../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];   // GET, POST, PUT or DELETE

// YOUR API CODE GOES HERE

echo json_encode(['success' => true, 'data' => null, 'error' => null]);
