<?php
// Shared admin header: every admin/ page includes this first. Owner: Lin
// It runs require_admin(), so non-admins are sent to admin/login.php.
// admin/login.php sets $isLoginPage = true before including (no check, no nav).
require_once __DIR__ . '/asset.php';  // asset_url(): CSS / JS の URL
require_once __DIR__ . '/auth.php';
if (empty($isLoginPage)) {
    require_admin();
}
$pageTitle = $pageTitle ?? '管理画面 | Chilli 鍋';
$current   = basename($_SERVER['PHP_SELF']);
// サイドバーのメニュー: ファイル => [名前, アイコン]
$adminNav  = [
    'orders.php'      => ['注文', 'a-orders'],
    'home.php'        => ['ホーム編集', 'a-home'],
    'menus.php'       => ['メニュー', 'a-menu'],
    'options.php'     => ['スープ・辛さ', 'a-spice'],
    'juices.php'      => ['ジュース', 'a-drink'],
    'stock.php'       => ['具材・在庫', 'a-box'],
    'supplies.php'    => ['備品', 'a-tools'],
    'reviews.php'     => ['レビュー', 'a-star'],
    'store.php'       => ['店舗', 'a-store'],
];
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <!-- light / dark: set before paint so there is no flash -->
  <script src="<?= asset_url('assets/js/theme.js') ?>"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') ?>">
</head>
<body class="admin shell<?= empty($isLoginPage) ? '' : ' admin-login' ?>">
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-flame" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2c1 3.5-1.6 5.2-1.6 8 0 1.3.8 2.3 2 2.3s2-1 2-2.4c0-.8-.2-1.5-.6-2.2 2.9 1.6 4.7 4.6 4.7 7.6A6.5 6.5 0 0 1 12 22a6.5 6.5 0 0 1-6.5-6.7c0-4.3 3.3-6.6 4.4-10.2.3.9.4 1.7.3 2.6C12.5 6 13 4 12 2Z"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></g></symbol>
  <symbol id="i-lock" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></g></symbol>
  <symbol id="i-eye" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></g></symbol>
  <symbol id="i-logout" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"/></g></symbol>
  <symbol id="i-trash" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/></g></symbol>
  <symbol id="i-sun" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4.5"/><path d="M12 2.5v3M12 18.5v3M3.5 12h3M17.5 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/></g></symbol>
  <symbol id="i-moon" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M20 14.5A8.5 8.5 0 0 1 9.5 4 8.5 8.5 0 1 0 20 14.5Z"/></symbol>
  <symbol id="a-orders" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/></g></symbol>
  <symbol id="a-home" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M3 9h18M8 14h3"/></g></symbol>
  <symbol id="a-menu" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 12h16a8 8 0 0 1-16 0Z"/><path d="M8 8c0-2 1.5-2 1.5-4M13 8c0-2 1.5-2 1.5-4"/></g></symbol>
  <symbol id="a-spice" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4c.4 1.3 1.5 2 2.8 2.2"/><path d="M17 7c2.4 2.3 1.6 6.9-2.1 9.9-3 2.4-7.1 3.4-9.9 3.1 2.4-1.2 4.6-3.1 6.2-5.6 1.7-2.6 2.3-5 2.4-6.9 1-.8 2.4-1.3 3.4-.5Z"/></g></symbol>
  <symbol id="a-drink" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1.5 13h-9z"/><path d="M12 8 14 2h3"/></g></symbol>
  <symbol id="a-box" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/></g></symbol>
  <symbol id="a-tools" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 6a4 4 0 0 0 5 5l-9 9a2 2 0 0 1-3-3l9-9a4 4 0 0 0-2-2Z"/></g></symbol>
  <symbol id="a-star" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></symbol>
  <symbol id="a-store" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10v10h16V10M3 10l2-6h14l2 6M3 10a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/></g></symbol>
  <symbol id="a-open" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/></g></symbol>
  </svg>
<!-- デスクトップ: 左のサイドバー / スマホ: 上のバー + 横にスクロールするメニュー -->
<header class="site-header side admin-side">
  <a class="logo" href="<?= empty($isLoginPage) ? 'orders.php' : 'login.php' ?>"><span class="logo-name">Chilli <em>鍋</em><span class="logo-sub">管理画面</span></span></a>
  <?php if (empty($isLoginPage)): ?>
  <nav class="side-nav" aria-label="管理メニュー">
    <?php foreach ($adminNav as $file => [$label, $icon]): ?>
    <a href="<?= $file ?>"<?= $current === $file ? ' class="is-active" aria-current="page"' : '' ?>><svg><use href="#<?= $icon ?>"></use></svg><?= $label ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <div class="side-foot">
    <div class="side-tools">
      <button class="theme-toggle" type="button" aria-label="ライトとダークを切り替える">
        <svg class="i-sun"><use href="#i-sun"></use></svg>
        <svg class="i-moon"><use href="#i-moon"></use></svg>
      </button>
      <?php if (empty($isLoginPage)): ?>
      <a href="../public/logout.php" aria-label="ログアウト" title="ログアウト"><svg><use href="#i-logout"></use></svg></a>
      <?php endif; ?>
    </div>
    <?php if (empty($isLoginPage)): ?>
    <a class="side-shop" href="../public/store.php" target="_blank" rel="noopener"><svg><use href="#a-open"></use></svg><span>お店のページを見る</span></a>
    <?php endif; ?>
  </div>
</header>
