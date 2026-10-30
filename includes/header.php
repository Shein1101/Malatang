<?php
// Shared header: every page includes this first. Owner: Lin
// $pageTitle can be set by the page before including this file.
require_once __DIR__ . '/auth.php';   // starts the session, gives current_user_id()
$pageTitle = $pageTitle ?? 'Chilli 鍋';
$loggedIn  = current_user_id() !== null;
// tabbar: 今のページのタブを赤くする
$current   = basename($_SERVER['SCRIPT_NAME']);
$tab = function (array $pages) use ($current) {
    return in_array($current, $pages, true) ? ' class="is-active"' : '';
};
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
<body class="has-tabbar">
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-flame" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2c1 3.5-1.6 5.2-1.6 8 0 1.3.8 2.3 2 2.3s2-1 2-2.4c0-.8-.2-1.5-.6-2.2 2.9 1.6 4.7 4.6 4.7 7.6A6.5 6.5 0 0 1 12 22a6.5 6.5 0 0 1-6.5-6.7c0-4.3 3.3-6.6 4.4-10.2.3.9.4 1.7.3 2.6C12.5 6 13 4 12 2Z"/></symbol>
  <symbol id="i-search" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></g></symbol>
  <symbol id="i-user" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.2-4 4.3-6 8-6s6.8 2 8 6"/></g></symbol>
  <symbol id="i-cart" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4h2l2.2 11h10.6L20 7H6.2"/><circle cx="9" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/></g></symbol>
  <symbol id="i-home" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M4 11 12 4l8 7v9h-5v-6H9v6H4z"/></g></symbol>
  <symbol id="i-menu" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 3v8M9 3v8M6 7h3M7.5 11v10M17 3c-2 2-2.5 5-2.5 8H17v10"/></g></symbol>
  <symbol id="i-burger" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></g></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></g></symbol>
  <symbol id="i-lock" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></g></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></g></symbol>
  <symbol id="i-heart" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M12 20s-7.5-4.6-9.3-9.2C1.6 7.6 3.7 4.5 7 4.5c2 0 3.5 1.2 5 3 1.5-1.8 3-3 5-3 3.3 0 5.4 3.1 4.3 6.3C19.5 15.4 12 20 12 20Z"/></symbol>
  <symbol id="i-receipt" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></g></symbol>
  <symbol id="i-wallet" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M4 7h15a1 1 0 0 1 1 1v11H4a1 1 0 0 1-1-1V6a2 2 0 0 1 2-2h11v3"/><circle cx="16" cy="13" r="1.3" fill="currentColor"/></g></symbol>
  <symbol id="i-users" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.9-3.5 3.4-5 6.5-5s5.6 1.5 6.5 5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 15c2 .6 3.1 2.2 3.5 5"/></g></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/></g></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></g></symbol>
  <symbol id="i-bowl" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 11h18a9 9 0 0 1-18 0Z"/><path d="M8 7c0-2 1.5-2 1.5-4M12.5 7c0-2 1.5-2 1.5-4"/></g></symbol>
</svg>
<header class="site-header">
  <div class="container">
    <a class="logo" href="store.php"><svg><use href="#i-flame"></use></svg><span class="logo-name">Chilli 鍋<span class="logo-sub">麻辣香鍋・麻辣湯</span></span></a>
    <div class="header-icons">
      <a href="cart.php" aria-label="カート"><svg><use href="#i-cart"></use></svg><span class="cart-count">2</span></a>
      <?php if ($loggedIn): ?>
      <a href="favorites.php" aria-label="お気に入り"><svg><use href="#i-heart"></use></svg></a>
      <a href="history.php" aria-label="注文履歴"><svg><use href="#i-receipt"></use></svg></a>
      <a class="d-none d-sm-grid" href="friends.php" aria-label="ファミリー&フレンズ"><svg><use href="#i-users"></use></svg></a>
      <a href="logout.php" aria-label="ログアウト"><svg><use href="#i-logout"></use></svg></a>
      <?php else: ?>
      <a href="login.php" aria-label="ログイン"><svg><use href="#i-user"></use></svg></a>
      <?php endif; ?>
      <button class="menu-toggle" aria-label="メニューを開く"><svg><use href="#i-burger"></use></svg></button>
    </div>
  </div>
</header>
<div style="height:140px"></div>
<nav class="tabbar" aria-label="タブ">
  <ul>
    <li><a<?= $tab(['store.php']) ?> href="store.php"><svg><use href="#i-home"></use></svg>ホーム</a></li>
    <li><a<?= $tab(['menu.php', 'customize.php']) ?> href="menu.php"><svg><use href="#i-menu"></use></svg>メニュー</a></li>
    <li><a<?= $tab(['cart.php', 'checkout.php', 'payment.php']) ?> href="cart.php"><svg><use href="#i-cart"></use></svg>カート</a></li>
    <?php if ($loggedIn): ?>
    <li><a<?= $tab(['mypage.php', 'history.php', 'favorites.php', 'friends.php']) ?> href="mypage.php"><svg><use href="#i-user"></use></svg>マイページ</a></li>
    <?php else: ?>
    <li><a<?= $tab(['login.php', 'signup.php']) ?> href="login.php"><svg><use href="#i-user"></use></svg>ログイン</a></li>
    <?php endif; ?>
  </ul>
</nav>
