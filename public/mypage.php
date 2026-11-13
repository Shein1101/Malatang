<?php
// public/mypage.php — マイページ. Owner: Lin (FEAT-16)
// Screen only, NO SQL. Data comes from ../api/auth.php?action=me / ?action=profile
// 自分の情報 (名前・電話・住所) を見て・編集できる + 各ページへの入口
require_once '../includes/auth.php';
require_login();

$pageTitle = 'マイページ | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <h1 class="title">マイページ</h1>
  <div id="error" class="alert alert-danger d-none"></div>
  <div id="done" class="alert alert-success d-none"></div>

  <!-- 自分の情報 (編集できる) -->
  <section class="card card-body mb-4">
    <h2 class="heading" id="name">読み込み中…</h2>
    <div class="caption"><svg width="14" height="14"><use href="#i-mail"></use></svg> <span id="email"></span></div>

    <form id="profileForm" class="stack mt-3" novalidate>
      <div class="grid-2">
        <div class="field">
          <label class="field-label" for="pName">お名前 <span class="req">必須</span></label>
          <input class="input" id="pName" maxlength="100" required autocomplete="name">
        </div>
        <div class="field">
          <label class="field-label" for="pPhone">電話番号 <span class="req">必須</span></label>
          <input class="input" id="pPhone" type="tel" placeholder="090-1234-5678" required autocomplete="tel">
        </div>
      </div>
      <div class="grid-2">
        <div class="field">
          <label class="field-label" for="pPostal">郵便番号</label>
          <input class="input" id="pPostal" maxlength="10" placeholder="123-4567" autocomplete="postal-code">
        </div>
        <div class="field">
          <label class="field-label" for="pAddress">ご住所</label>
          <input class="input" id="pAddress" maxlength="255" placeholder="東京都…" autocomplete="street-address">
        </div>
      </div>
      <p class="caption">ここに入れた内容は、次のご注文のときに自動で入ります。</p>
      <button type="submit" id="pSave" class="btn btn-chili">保存する</button>
    </form>
  </section>

  <div class="tile-grid mb-4">
    <a class="tile" href="history.php"><svg><use href="#i-receipt"></use></svg>注文履歴</a>
    <a class="tile" href="favorites.php"><svg><use href="#i-heart"></use></svg>お気に入り</a>
    <a class="tile" href="friends.php"><svg><use href="#i-users"></use></svg>ファミリー&フレンズ</a>
    <a class="tile" href="customize.php"><svg><use href="#i-bowl"></use></svg>自分で作る</a>
    <a class="tile" href="cart.php"><svg><use href="#i-cart"></use></svg>カート</a>
    <a class="tile" href="logout.php"><svg><use href="#i-logout"></use></svg>ログアウト</a>
  </div>
</main>

<script>
const errBox  = () => document.getElementById('error');
const doneBox = () => document.getElementById('done');

async function loadMe() {
  const res = await callApi('GET', '../api/auth.php?action=me');
  if (!res.success || !res.data) {
    showError(errBox(), res.error || 'ログインし直してください');
    return;
  }
  document.getElementById('name').textContent  = res.data.name + ' さん';
  document.getElementById('email').textContent = res.data.email;
  document.getElementById('pName').value    = res.data.name  || '';
  document.getElementById('pPhone').value   = res.data.phone || '';
  document.getElementById('pPostal').value  = res.data.postal_code || '';
  document.getElementById('pAddress').value = res.data.address || '';
}

document.getElementById('profileForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  errBox().classList.add('d-none');
  doneBox().classList.add('d-none');
  const btn = document.getElementById('pSave');
  btn.disabled = true;
  const res = await callApi('PUT', '../api/auth.php?action=profile', {
    name:        document.getElementById('pName').value.trim(),
    phone:       document.getElementById('pPhone').value.trim(),
    postal_code: document.getElementById('pPostal').value.trim(),
    address:     document.getElementById('pAddress').value.trim(),
  });
  btn.disabled = false;
  if (!res.success) { showError(errBox(), res.error); return; }
  doneBox().textContent = '保存しました。';
  doneBox().classList.remove('d-none');
  document.getElementById('name').textContent = document.getElementById('pName').value.trim() + ' さん';
});

window.addEventListener('load', loadMe);
</script>

<?php include '../includes/footer.php'; ?>
