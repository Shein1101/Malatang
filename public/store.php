<?php
// public/store.php — ホーム (customer). Owner: Myat (FEAT-10), デザイン・ブロック: Lin
// Screen only, NO SQL. 中身は ../assets/js/home.js
// 何をどの順番で出すかは お店が admin/home.php で決める。メニューは menu.php、お店の情報は info.php
$pageTitle = 'Chilli 鍋 | 自分だけの麻辣湯';
include '../includes/header.php';
?>

<main class="home">
  <p id="error" class="field-error container d-none"></p>
  <div id="blocks"></div>
</main>

<script src="../assets/js/menus.js"></script>
<script src="../assets/js/home.js"></script>

<?php include '../includes/footer.php'; ?>
