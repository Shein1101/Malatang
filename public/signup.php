<?php
// S15 新規登録 — Owner: Lin (FEAT-16). Screen only, no SQL.
require_once '../includes/auth.php';

// login ပြီးရင် သွားမယ့်နေရာ (login.php?redirect=history.php)。 ကိုယ့် site ထဲက .php ပဲ ခွင့်ပြု
$redirect = safe_redirect($_GET['redirect'] ?? '');

// ログイン済みなら来なくていい
if (current_user_id() !== null) {
    header('Location: ' . $redirect);
    exit;
}

$pageTitle = '新規登録 | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <div class="narrow">
    <h1 class="title">新規会員登録</h1>
    <p class="caption">登録すると、注文履歴やお気に入りが使えます。</p>

    <div id="error" class="alert alert-danger d-none"></div>

    <form id="signupForm" novalidate>
      <div class="field">
        <label class="field-label" for="name"><svg><use href="#i-user"/></svg>名前</label>
        <input class="input" id="name" name="name" type="text" autocomplete="name" maxlength="100" required>
      </div>

      <div class="field">
        <label class="field-label" for="email"><svg><use href="#i-mail"/></svg>メールアドレス</label>
        <input class="input" id="email" name="email" type="email" autocomplete="email" placeholder="example@shop.jp" maxlength="255" required>
      </div>

      <div class="field">
        <label class="field-label" for="phone">電話番号</label>
        <input class="input" id="phone" name="phone" type="tel" autocomplete="tel" placeholder="090-1234-5678" maxlength="20" required>
      </div>

      <div class="field">
        <label class="field-label" for="password"><svg><use href="#i-lock"/></svg>パスワード</label>
        <div class="input-wrap">
          <input class="input" id="password" name="password" type="password" autocomplete="new-password" placeholder="8文字以上で入力" required>
          <button class="input-icon" type="button" data-toggle-password="password" aria-label="パスワードを表示"><svg width="18" height="18"><use href="#i-eye"/></svg></button>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="passwordConfirm"><svg><use href="#i-lock"/></svg>パスワード（確認）</label>
        <input class="input" id="passwordConfirm" name="passwordConfirm" type="password" autocomplete="new-password" required>
      </div>

      <button type="submit" class="btn btn-chili btn-block">登録する <span class="arrow"></span></button>
    </form>

    <div class="divider">または</div>

    <a class="btn btn-outline btn-block" href="login.php<?= $redirect !== 'menu.php' ? '?redirect=' . urlencode($redirect) : '' ?>">ログインはこちら</a>
  </div>
</main>

<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    const input = document.getElementById(btn.dataset.togglePassword);
    input.type = input.type === 'password' ? 'text' : 'password';
  });
});

document.getElementById('signupForm').addEventListener('submit', async function (e) {
  e.preventDefault();

  const form = e.target;
  const errorBox = document.getElementById('error');
  errorBox.classList.add('d-none');

  const name = form.name.value.trim();
  const email = form.email.value.trim();
  const phone = form.phone.value.trim();
  const password = form.password.value;
  const passwordConfirm = form.passwordConfirm.value;

  // 画面でも先にチェック（サーバーでも同じチェックをする）
  if (name === '' || email === '' || phone === '' || password === '') {
    showError(errorBox, 'すべての項目を入力してください。');
    return;
  }
  if (password.length < 8) {
    showError(errorBox, 'パスワードは8文字以上で入力してください。');
    return;
  }
  if (password !== passwordConfirm) {
    showError(errorBox, 'パスワードが一致しません。');
    return;
  }

  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;

  const res = await callApi('POST', '../api/auth.php?action=signup', {
    name: name,
    email: email,
    phone: phone,
    password: password
  });

  button.disabled = false;

  if (!res.success) {
    showError(errorBox, res.error);
    return;
  }

  window.location.href = <?= json_encode($redirect) ?>;
});
</script>

<?php include '../includes/footer.php'; ?>
