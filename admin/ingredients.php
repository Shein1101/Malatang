Claude
Ingredients · PHP
<?php
// admin/ingredients.php — 具材管理 (add / edit / delete). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/ingredients.php
$pageTitle = '具材管理 | Chilli 鍋';
include '../includes/header.php';
?>
 
<main class="container">
  <h1>具材管理</h1>
  <p id="error" class="text-danger d-none"></p>
 
  <!-- 🆕 具材を追加するフォーム -->
  <form id="addForm" class="card card-body mb-4">
    <h2 class="h5">具材を追加</h2>
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">カテゴリ</label>
        <select name="category_id" class="form-select" required></select>
      </div>
      <div class="col-md-3">
        <label class="form-label">名前</label>
        <input name="name" class="form-control" required maxlength="100">
      </div>
      <div class="col-md-2">
        <label class="form-label">値段（円）</label>
        <input name="price" type="number" min="0" class="form-control" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">在庫</label>
        <input name="stock" type="number" min="0" value="0" class="form-control">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-chili w-100">追加</button>
      </div>
    </div>
    <p id="addMessage" class="mt-2 mb-0"></p>
  </form>
 
  <!-- 具材の一覧 (JavaScript がここに表を作る) -->
  <p id="listMessage"></p>   <!-- 🆕 保存・削除のメッセージ -->
  <table class="table align-middle">
    <thead>
      <tr>
        <th>カテゴリ</th>
        <th>名前</th>
        <th>値段</th>
        <th>在庫</th>
        <th>状態</th>
        <th>操作</th>   <!-- 🆕 -->
      </tr>
    </thead>
    <tbody id="list">
      <tr><td colspan="6">読み込み中…</td></tr>
    </tbody>
  </table>
</main>
 
<script>
// 文字を安全に HTML に入れる (名前に < などが入っていても壊れないように)
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
 
// API から具材を全部もらって、表に並べる
async function loadList() {
  const res = await callApi('GET', '../api/ingredients.php');
  if (!res.success) {
    showError(document.getElementById('error'), res.error);
    return;
  }
 
  let rows = '';
  for (const item of res.data) {
    rows += `
      <tr>
        <td>${esc(item.category_name)}</td>
        <td>${esc(item.name)}</td>
        <!-- 🆕 値段と在庫は、その場で書きかえられる入力欄にする -->
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:90px"
                   id="price-${item.id}" value="${item.price}"></td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:80px"
                   id="stock-${item.id}" value="${item.stock}"></td>
        <td>${item.is_available ? '販売中' : '<span class="badge badge-soldout">品切れ</span>'}</td>
        <!-- 🆕 ボタン: data-id で「どの具材か」、data-action で「何をするか」を覚えておく -->
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline" data-action="save" data-id="${item.id}">保存</button>
          <button class="btn btn-sm btn-ghost" data-action="delete" data-id="${item.id}"
                  data-name="${esc(item.name)}">削除</button>
        </td>
      </tr>`;
  }
  document.getElementById('list').innerHTML = rows;
 
  // 🆕 カテゴリの選択肢を作る (同じカテゴリは1回だけ)
  const select = document.querySelector('#addForm [name=category_id]');
  if (select.options.length === 0) {
    const seen = {};
    for (const item of res.data) {
      if (!seen[item.category_id]) {
        seen[item.category_id] = true;
        select.innerHTML += `<option value="${item.category_id}">${esc(item.category_name)}</option>`;
      }
    }
  }
}
 
// 🆕 「追加」ボタンを押したとき
document.getElementById('addForm').addEventListener('submit', async (e) => {
  e.preventDefault();                  // ページが再読み込みされないように止める
  const form = e.target;
  const msg = document.getElementById('addMessage');
 
  const res = await callApi('POST', '../api/ingredients.php', {
    category_id: Number(form.category_id.value),
    name: form.name.value,
    price: Number(form.price.value),
    stock: Number(form.stock.value)
  });
 
  if (res.success) {
    msg.className = 'mt-2 mb-0 text-success';
    msg.textContent = `「${form.name.value}」を追加しました`;
    form.name.value = '';              // 入力欄を空にする
    form.price.value = '';
    form.stock.value = 0;
    loadList();                        // 表をもう一度読み込む
  } else {
    msg.className = 'mt-2 mb-0 text-danger';
    msg.textContent = res.error;
  }
});
 
// 🆕 表の中のボタンを押したとき (保存 / 削除)
document.getElementById('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button');   // 押されたのがボタンでなければ何もしない
  if (!btn) return;
  const id = btn.dataset.id;
  const msg = document.getElementById('listMessage');
  let res;
 
  if (btn.dataset.action === 'save') {
    // 値段と在庫を PUT で送る
    res = await callApi('PUT', `../api/ingredients.php?id=${id}`, {
      price: Number(document.getElementById(`price-${id}`).value),
      stock: Number(document.getElementById(`stock-${id}`).value)
    });
    if (res.success) {
      msg.className = 'text-success';
      msg.textContent = '保存しました';
    }
  }
 
  if (btn.dataset.action === 'delete') {
    // 本当に消していいか確認する
    if (!confirm(`「${btn.dataset.name}」を削除しますか？`)) return;
    res = await callApi('DELETE', `../api/ingredients.php?id=${id}`);
    if (res.success) {
      msg.className = 'text-success';
      msg.textContent = `「${btn.dataset.name}」を削除しました`;
    }
  }
 
  if (!res.success) {
    msg.className = 'text-danger';
    msg.textContent = res.error;
  }
  loadList();   // 表をもう一度読み込む (品切れの表示も新しくなる)
});
 
// app.js (callApi, yen) はフッターで読み込まれるので、ページを全部読んでから動かす
window.addEventListener('load', loadList);
</script>
 
<?php include '../includes/footer.php'; ?>
 
Claude finished the response

