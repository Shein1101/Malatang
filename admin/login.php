<?php
// A01 管理者ログイン — Owner: Lin (FEAT-16). Screen only, no SQL.
require_once '../includes/auth.php';

// 管理者でログイン済みなら注文画面へ
if (is_admin()) {
    header('Location: orders.php');
    exit;
}

$isLoginPage = true;
$pageTitle = '管理者ログイン | Chilli 鍋';
include '../includes/admin_header.php';
?>

<main class="container page">
  <div class="narrow">
    <h1 class="title">管理者ログイン</h1>
    <p class="caption">店舗スタッフ専用のログイン画面です。</p>

    <div id="error" class="alert alert-danger d-none"></div>

    <form id="loginForm" novalidate>
      <div class="field">
        <label class="field-label" for="email"><svg><use href="#i-mail"/></svg>メールアドレス</label>
        <input class="input" id="email" name="email" type="email" autocomplete="username" required>
      </div>

      <div class="field">
        <label class="field-label" for="password"><svg><use href="#i-lock"/></svg>パスワード</label>
        <div class="input-wrap">
          <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
          <button class="input-icon" type="button" data-toggle-password="password" aria-label="パスワードを表示"><svg width="18" height="18"><use href="#i-eye"/></svg></button>
        </div>
      </div>

      <button type="submit" class="btn btn-chili btn-block">ログイン <span class="arrow"></span></button>
    </form>
  </div>
</main>

<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const input = document.getElementById(btn.dataset.togglePassword);
    input.type = input.type === 'password' ? 'text' : 'password';
  });
});

document.getElementById('loginForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const form = e.target;
  const errorBox = document.getElementById('error');
  errorBox.classList.add('d-none');

  const email = form.email.value.trim();
  const password = form.password.value;

  if (email === '' || password === '') {
    showError(errorBox, 'メールアドレスとパスワードを入力してください。');
    return;
  }

  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;

  const res = await callApi('POST', '../api/auth.php?action=login', {
    email: email,
    password: password
  });

  button.disabled = false;

  if (!res.success) {
    showError(errorBox, res.error);
    return;
  }

  // customer アカウントでは管理画面に入れない → すぐログアウト
  if (res.data.role !== 'admin') {
    await callApi('POST', '../api/auth.php?action=logout');
    showError(errorBox, '管理者アカウントではありません。');
    return;
  }

  window.location.href = 'orders.php';
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
