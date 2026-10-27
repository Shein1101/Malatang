<?php
// admin/stock.php — 在庫・品切れ (owner). Owner: Myat
// Screen only, NO SQL. Uses ../api/ingredients.php and ../api/menus.php (PUT)
// 変えたらすぐ保存 (保存ボタンなし)。在庫 0 → 自動で品切れ (DB の trigger)
$pageTitle = '在庫・品切れ | Chilli 鍋';
include '../includes/admin_header.php';   // require_admin(): admin でなければ login.php へ
?>
 
<main class="container">
  <h1>在庫・品切れ</h1>
  <p class="caption">数字やスイッチを変えると、すぐ保存されます。在庫を 0 にすると自動で品切れ、また入れると自動で販売中に戻ります。</p>
  <p id="message"></p>
 
  <!-- メニュー (ON / OFF だけ) -->
  <h2 class="h5 mt-4">メニュー</h2>
  <table class="table align-middle">
    <thead><tr><th>メニュー</th><th>販売</th></tr></thead>
    <tbody id="menuList"><tr><td colspan="2">読み込み中…</td></tr></tbody>
  </table>
 
  <!-- 具材 (在庫数 + ON / OFF) -->
  <h2 class="h5 mt-4">具材</h2>
  <table class="table align-middle">
    <thead><tr><th>カテゴリ</th><th>具材</th><th>在庫</th><th>販売</th></tr></thead>
    <tbody id="ingList"><tr><td colspan="4">読み込み中…</td></tr></tbody>
  </table>
</main>
 
<script>
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function say(text, ok) {
  const msg = document.getElementById('message');
  msg.className = ok ? 'text-success' : 'text-danger';
  msg.textContent = text;
}
 
// ON / OFF スイッチの HTML (type = menu か ingredient)
function switchHtml(type, id, on) {
  return `
    <div class="form-check form-switch">
      <input class="form-check-input" type="checkbox" role="switch"
             data-type="${type}" data-id="${id}" ${on ? 'checked' : ''}>
      <label class="form-check-label">${on ? '販売中' : '<span class="badge badge-soldout">品切れ</span>'}</label>
    </div>`;
}
 
// 1. メニューと具材を読み込んで表にする
async function loadAll() {
  const menus = await callApi('GET', '../api/menus.php');
  const ings  = await callApi('GET', '../api/ingredients.php');
  if (!menus.success || !ings.success) {
    say(menus.error || ings.error, false);
    return;
  }
 
  let rows = '';
  for (const m of menus.data) {
    rows += `<tr><td>${esc(m.name)}</td><td>${switchHtml('menu', m.id, m.is_available)}</td></tr>`;
  }
  document.getElementById('menuList').innerHTML = rows;
 
  rows = '';
  for (const i of ings.data) {
    // 在庫が 5 以下なら赤くして目立たせる
    const low = i.stock <= 5 ? 'border-danger text-danger' : '';
    rows += `
      <tr>
        <td>${esc(i.category_name)}</td>
        <td>${esc(i.name)}</td>
        <td><input type="number" min="0" class="form-control form-control-sm ${low}" style="width:90px"
                   data-stock-id="${i.id}" value="${i.stock}"></td>
        <td>${switchHtml('ingredient', i.id, i.is_available)}</td>
      </tr>`;
  }
  document.getElementById('ingList').innerHTML = rows;
}
 
// 2. 何かを変えたら、すぐ PUT で保存
document.querySelector('main').addEventListener('change', async (e) => {
  const el = e.target;
  let res;
 
  // 在庫の数字
  if (el.dataset.stockId) {
    res = await callApi('PUT', `../api/ingredients.php?id=${el.dataset.stockId}`, {
      stock: Number(el.value)
    });
  }
 
  // ON / OFF スイッチ
  if (el.dataset.type) {
    const api = el.dataset.type === 'menu' ? 'menus' : 'ingredients';
    res = await callApi('PUT', `../api/${api}.php?id=${el.dataset.id}`, {
      is_available: el.checked
    });
  }
 
  if (!res) return;
  say(res.success ? '保存しました' : res.error, res.success);
  loadAll();   // 在庫 0 → 品切れ など、DB が自動で変えた結果も表示する
});
 
window.addEventListener('load', loadAll);
</script>
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
