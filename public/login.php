<?php
// S14 ログイン — Owner: Lin (FEAT-16). Screen only, no SQL.
require_once '../includes/auth.php';

// ログイン済みなら来なくていい
if (current_user_id() !== null) {
    header('Location: menu.php');
    exit;
}

$pageTitle = 'ログイン | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <div class="narrow">
    <h1 class="title">ログイン</h1>
    <p class="caption">会員の方は、こちらからログインしてください。</p>

    <div id="error" class="alert alert-danger d-none"></div>

    <form id="loginForm" novalidate>
      <div class="field">
        <label class="field-label" for="email"><svg><use href="#i-mail"/></svg>メールアドレス</label>
        <input class="input" id="email" name="email" type="email" autocomplete="email" placeholder="example@shop.jp" required>
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

    <div class="divider">または</div>

    <div class="stack-sm">
      <a class="btn btn-outline btn-block" href="signup.php">新規会員登録</a>
      <a class="btn btn-ghost btn-block" href="menu.php">ゲストとして注文する <span class="arrow"></span></a>
    </div>
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

  window.location.href = 'menu.php';
});
</script>

<?php include '../includes/footer.php'; ?>
