<?php
// S04 カスタマイズ — Owner: Zan (FEAT-11 / TASK-13)
// 開き方: customize.php?menu_id=3
$pageTitle  = 'カスタマイズ | Chilli 鍋';
$activePage = 'menu';                       // nav で「メニュー」を光らせる
include '../includes/header.php';
?>

<main class="container page">
  <p id="error" class="text-chili d-none"></p>   <!-- showError() がここに出す -->

  <section class="mb-4">
    <h1 class="title" id="menu-name"></h1>
    <p class="muted" id="menu-desc"></p>
    <p class="price-lg" id="menu-price"></p>
  </section>

  <section class="mb-4">
    <h2 class="heading">スープ</h2>
    <div class="chip-group" id="soup-list"></div>
  </section>

  <section class="mb-4">
    <h2 class="heading">辛さ</h2>
    <div class="chip-group" id="spice-list"></div>
  </section>

  <section class="mb-4">
    <h2 class="heading">基本の具材</h2>
    <p class="caption">タップすると抜けます</p>
    <div class="chip-group" id="default-list"></div>
  </section>
</main>

<script src="../assets/js/app.js"></script>
<script src="../assets/js/customize.js"></script>
<?php include '../includes/footer.php'; ?>