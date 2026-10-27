<?php
// S16 ログアウト — Owner: Lin (FEAT-16). Calls the API, then goes back to the menu.
$pageTitle = 'ログアウト | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <p class="caption">ログアウトしています…</p>
</main>

<script>
// app.js は footer で読み込まれるので、読み込み後に実行
document.addEventListener('DOMContentLoaded', async function () {
  await callApi('POST', '../api/auth.php?action=logout');
  window.location.href = 'menu.php';
});
</script>

<?php include '../includes/footer.php'; ?>
