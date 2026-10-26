<?php
// public/menu.php — メニュー一覧 (customer). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/menus.php
// Card click → customize.php?menu_id=... (Zan)
$pageTitle = 'メニュー | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container">
  <h1>メニュー</h1>
  <p id="error" class="text-danger d-none"></p>

  <!-- menu のカードがここに並ぶ -->
  <div id="menuList" class="grid-4">
    <p>読み込み中…</p>
  </div>
</main>

<script>
// 文字を安全に HTML に入れる
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

// menu 1つ分のカードを作る
function menuCard(m) {
  // 写真があれば <img>、なければ空 (背景色だけ)
  const img = m.image_url ? `<img src="${esc(m.image_url)}" alt="${esc(m.name)}">` : '';

  // 売り切れ: グレーのカード、リンクなし (押せない)
  if (!m.is_available) {
    return `
      <div class="card-bowl is-soldout">
        <div class="card-media">${img}</div>
        <div class="card-info">
          <div>
            <div class="card-name">${esc(m.name)}</div>
            <span class="badge badge-soldout">売り切れ</span>
          </div>
        </div>
      </div>`;
  }

  // ふつう: 押すと customize.php?menu_id=… へ
  return `
    <a class="card-bowl" href="customize.php?menu_id=${m.id}">
      <div class="card-media">${img}</div>
      <div class="card-info">
        <div>
          <div class="card-name">${esc(m.name)}</div>
          <div class="caption">${esc(m.description)}</div>
          <div class="price">${yen(m.price)}</div>
        </div>
        <span class="text-chili">→</span>
      </div>
    </a>`;
}

// API から menu をもらって、カードを並べる
async function loadMenus() {
  const res = await callApi('GET', '../api/menus.php');
  const list = document.getElementById('menuList');
  if (!res.success) {
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  let html = '';
  for (const m of res.data) {
    html += menuCard(m);
  }
  list.innerHTML = html;
}

// app.js (callApi, yen) はフッターで読み込まれるので、ページを全部読んでから動かす
window.addEventListener('load', loadMenus);
</script>

<?php include '../includes/footer.php'; ?>