<?php
// S?? 新しいパスワードを決める — Owner: Lin. Screen only, no SQL.
// forgot.php が作った reset.php?token=... を開く。token の確認・保存は ../api/auth.php?action=reset
require_once '../includes/auth.php';
$pageTitle = 'パスワードの再設定 | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <div class="narrow">
    <h1 class="title">新しいパスワード</h1>

    <div id="error" class="alert alert-danger d-none"></div>

    <!-- token を確かめている間 -->
    <p id="checking" class="muted">確認中…</p>

    <!-- token が切れている / 無効 -->
    <div id="invalid" class="alert alert-warning d-none">
      <p class="mb-2">このリンクは無効か、期限が切れています。</p>
      <a class="btn btn-chili btn-sm" href="forgot.php">もう一度リンクを作る</a>
    </div>

    <!-- 新しいパスワードを決める -->
    <form id="resetForm" class="d-none" novalidate>
      <div class="field">
        <label class="field-label" for="password"><svg><use href="#i-lock"/></svg>新しいパスワード（8文字以上）</label>
        <div class="input-wrap">
          <input class="input" id="password" type="password" autocomplete="new-password" minlength="8" required>
          <button class="input-icon" type="button" data-toggle-password="password" aria-label="パスワードを表示"><svg width="18" height="18"><use href="#i-eye"/></svg></button>
        </div>
      </div>
      <div class="field">
        <label class="field-label" for="password2"><svg><use href="#i-lock"/></svg>もう一度入力</label>
        <input class="input" id="password2" type="password" autocomplete="new-password" required>
      </div>
      <button type="submit" class="btn btn-chili btn-block">パスワードを変える <span class="arrow"></span></button>
    </form>

    <!-- 成功 -->
    <div id="done" class="alert alert-success d-none">
      <p class="mb-2">新しいパスワードにしました。</p>
      <a class="btn btn-chili btn-sm" href="login.php">ログインする</a>
    </div>
  </div>
</main>

<script>
const token    = new URLSearchParams(location.search).get('token') || '';
const errorBox = document.getElementById('error');
const checking = document.getElementById('checking');
const invalid  = document.getElementById('invalid');
const form     = document.getElementById('resetForm');

// 1. リンクがまだ有効か確かめてから、フォームを出す
async function check() {
  if (token === '') { checking.classList.add('d-none'); invalid.classList.remove('d-none'); return; }
  const res = await callApi('GET', '../api/auth.php?action=reset&token=' + encodeURIComponent(token));
  checking.classList.add('d-none');
  if (res.success && res.data.valid) form.classList.remove('d-none');
  else invalid.classList.remove('d-none');
}

document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const input = document.getElementById(btn.dataset.togglePassword);
    input.type = input.type === 'password' ? 'text' : 'password';
  });
});

// 2. 新しいパスワードを保存
form.addEventListener('submit', async function (e) {
  e.preventDefault();
  errorBox.classList.add('d-none');
  const p1 = document.getElementById('password').value;
  const p2 = document.getElementById('password2').value;
  if (p1.length < 8) { showError(errorBox, 'パスワードは8文字以上で入力してください。'); return; }
  if (p1 !== p2)     { showError(errorBox, 'パスワードが一致しません。'); return; }

  const btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  const res = await callApi('POST', '../api/auth.php?action=reset', { token: token, password: p1 });
  btn.disabled = false;

  if (!res.success) { showError(errorBox, res.error); return; }
  form.classList.add('d-none');
  document.getElementById('done').classList.remove('d-none');
});

// app.js (callApi) はフッターで読み込まれるので、読み込み後に確認する
window.addEventListener('load', check);
</script>

<?php include '../includes/footer.php'; ?>
