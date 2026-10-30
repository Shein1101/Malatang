<?php
// admin/ingredients.php — 具材管理 (add / edit / delete). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/ingredients.php
$pageTitle = '具材管理 | Chilli 鍋';
include '../includes/admin_header.php';
?>
 
<main class="container">
  <h1>具材管理</h1>
  <p class="caption">具材の追加・値段の変更・削除をします。在庫の数や品切れは <a href="stock.php">在庫</a> ページで変えてください。</p>
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

  <!-- 🆕 検索: 名前かカテゴリで、打つとすぐ表をしぼる (ひらがな → カタカナも OK) -->
  <div class="d-flex align-items-center gap-2 mb-2">
    <input id="search" type="search" class="form-control" style="max-width:320px"
           placeholder="具材を検索 (例: えび、きのこ)" autocomplete="off">
    <span id="searchCount" class="text-muted small"></span>
  </div>
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
 
// 🆕 検索用に文字をそろえる: 小文字にする / ひらがな → カタカナ / 前後の空白をとる
function normalize(s) {
  return String(s ?? '').toLowerCase().trim()
    .replace(/[ぁ-ゖ]/g, c => String.fromCharCode(c.charCodeAt(0) + 0x60));
}

// 🆕 検索欄の文字が入っている行だけ見せる
function filterList() {
  const word = normalize(document.getElementById('search').value);
  const rows = document.querySelectorAll('#list tr[data-search]');
  let shown = 0;
  rows.forEach(tr => {
    const hit = word === '' || tr.dataset.search.includes(word);
    tr.hidden = !hit;
    if (hit) shown++;
  });

  // 見つからないときのメッセージ行
  let empty = document.getElementById('noResult');
  if (shown === 0 && rows.length > 0) {
    if (!empty) {
      document.getElementById('list').insertAdjacentHTML('beforeend',
        '<tr id="noResult"><td colspan="6" class="text-muted">見つかりませんでした</td></tr>');
    }
  } else if (empty) {
    empty.remove();
  }
  document.getElementById('searchCount').textContent = word === '' ? '' : `${shown} / ${rows.length} 件`;
}
document.getElementById('search').addEventListener('input', filterList);

// API から具材を全部もらって、表に並べる
async function loadList() {
  const res = await callApi('GET', '../api/ingredients.php');
  if (!res.success) {
    showError(document.getElementById('error'), res.error);
    return;
  }
 
  let rows = '';
  for (const item of res.data) {
    // 🆕 在庫が 5 以下なら赤くして目立たせる (stock.php と同じ)
    const low = item.stock <= 5 ? 'text-danger fw-bold' : '';
    rows += `
      <tr data-search="${esc(normalize(item.name + ' ' + item.category_name))}">
        <td>${esc(item.category_name)}</td>
        <td>${esc(item.name)}</td>
        <!-- 🆕 値段は、その場で書きかえられる入力欄にする -->
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:90px"
                   id="price-${item.id}" value="${item.price}"></td>
        <!-- 在庫は見るだけ。変えるのは 在庫 ページ (stock.php) -->
        <td class="${low}">${item.stock}</td>
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
  filterList();   // 🆕 保存・削除で表を作り直しても、検索の文字はそのまま

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
    // 値段を PUT で送る (在庫は stock.php で変える)
    res = await callApi('PUT', `../api/ingredients.php?id=${id}`, {
      price: Number(document.getElementById(`price-${id}`).value)
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
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
