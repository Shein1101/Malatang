<?php
// Shared header: every page includes this first. Owner: Lin
// $pageTitle can be set by the page before including this file.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$pageTitle = $pageTitle ?? 'Chilli 鍋';
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <!-- Bootstrap 5 (shared design) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Our own styles -->
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<header class="site-header">
  <nav class="navbar navbar-expand container">
    <a class="navbar-brand" href="../public/menu.php">Chilli 鍋</a>
    <ul class="navbar-nav ms-auto">
      <li class="nav-item"><a class="nav-link" href="../public/menu.php">メニュー</a></li>
      <li class="nav-item"><a class="nav-link" href="../public/cart.php">カート</a></li>
      <li class="nav-item"><a class="nav-link" href="../public/login.php">ログイン</a></li>
    </ul>
  </nav>
</header>
