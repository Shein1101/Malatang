<?php
// S?? パスワードをお忘れですか — Owner: Lin. Screen only, no SQL.
// option B: メールは送らず、再設定リンクを画面に出す (ローカル・テスト用)
// 本番でメール送信にするなら、api/auth.php?action=forgot の reset_path をメールで送る。
require_once '../includes/auth.php';
$pageTitle = 'パスワードの再設定 | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <div class="narrow">
    <h1 class="title">パスワードの再設定</h1>
    <p class="caption">登録したメールアドレスを入れてください。再設定用のリンクを作ります。</p>

    <div id="error" class="alert alert-danger d-none"></div>

    <form id="forgotForm" novalidate>
      <div class="field">
        <label class="field-label" for="email"><svg><use href="#i-mail"/></svg>メールアドレス</label>
        <input class="input" id="email" name="email" type="email" autocomplete="email" placeholder="example@shop.jp" required>
      </div>
      <button type="submit" class="btn btn-chili btn-block">リンクを作る <span class="arrow"></span></button>
    </form>

    <!-- できたリンクをここに出す (本番ではメールで送ります) -->
    <div id="result" class="alert alert-success mt-3 d-none">
      <p class="mb-2"><b>再設定リンクができました。</b></p>
      <p class="caption mb-3">本番ではこのリンクをメールで送ります。今はテスト用に画面に出しています（1時間で期限切れ・1回だけ使えます）。</p>
      <a id="resetLink" class="btn btn-chili btn-block" href="#">新しいパスワードを決める <span class="arrow"></span></a>
    </div>
    <div id="notfound" class="alert alert-warning mt-3 d-none">そのメールアドレスは登録されていません。</div>

    <div class="divider">または</div>
    <a class="btn btn-ghost btn-block" href="login.php">ログインにもどる</a>
  </div>
</main>

<script>
document.getElementById('forgotForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  const errorBox = document.getElementById('error');
  const result   = document.getElementById('result');
  const notfound = document.getElementById('notfound');
  errorBox.classList.add('d-none');
  result.classList.add('d-none');
  notfound.classList.add('d-none');

  const email = this.email.value.trim();
  if (email === '') { showError(errorBox, 'メールアドレスを入力してください。'); return; }

  const btn = this.querySelector('button[type="submit"]');
  btn.disabled = true;
  const res = await callApi('POST', '../api/auth.php?action=forgot', { email: email });
  btn.disabled = false;

  if (!res.success) { showError(errorBox, res.error); return; }

  if (res.data.reset_path) {
    document.getElementById('resetLink').href = res.data.reset_path;
    result.classList.remove('d-none');
  } else {
    notfound.classList.remove('d-none');
  }
});
</script>

<?php include '../includes/footer.php'; ?>
