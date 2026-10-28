<?php
// admin/menus.php — メニュー管理 (owner). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/menus.php
// 追加 / 名前・説明・値段の変更 / 販売 ON・OFF / 削除
$pageTitle = 'メニュー管理 | Chilli 鍋';
include '../includes/admin_header.php';
?>
 
<main class="container">
  <h1>メニュー管理</h1>
  <p id="message"></p>
 
  <!-- menu を追加 -->
  <form id="addForm" class="card card-body mb-4">
    <h2 class="h5">メニューを追加</h2>
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">名前</label>
        <input name="name" class="form-control" required maxlength="100">
      </div>
      <div class="col-md-5">
        <label class="form-label">説明</label>
        <input name="description" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">値段（円）</label>
        <input name="price" type="number" min="0" class="form-control" required>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-chili w-100">追加</button>
      </div>
    </div>
    <p class="caption mt-2 mb-0">※ 春雨は自動で入ります（お客さんは外せません）</p>
  </form>
 
  <!-- menu の一覧 -->
  <table class="table align-middle">
    <thead>
      <tr><th>名前</th><th>説明</th><th>値段（円）</th><th>販売</th><th></th></tr>
    </thead>
    <tbody id="list"><tr><td colspan="5">読み込み中…</td></tr></tbody>
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
 
// 1. 一覧を表示
async function loadList() {
  const res = await callApi('GET', '../api/menus.php');
  if (!res.success) { say(res.error, false); return; }
  let rows = '';
  for (const m of res.data) {
    rows += `
      <tr>
        <td><input class="form-control form-control-sm" id="name-${m.id}" value="${esc(m.name)}"></td>
        <td><input class="form-control form-control-sm" id="desc-${m.id}" value="${esc(m.description)}"></td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:100px"
                   id="price-${m.id}" value="${m.price}"></td>
        <td>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="on-${m.id}" ${m.is_available ? 'checked' : ''}>
            <label class="form-check-label" for="on-${m.id}">${m.is_available ? 'ON' : 'OFF'}</label>
          </div>
        </td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline" data-action="save" data-id="${m.id}">保存</button>
          <button class="btn btn-sm btn-ghost" data-action="delete" data-id="${m.id}">削除</button>
        </td>
      </tr>`;
  }
  document.getElementById('list').innerHTML = rows;
}
 
// 2. 保存 / 削除
document.getElementById('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button');
  if (!btn) return;
  const id = btn.dataset.id;
  const name = document.getElementById(`name-${id}`).value;
 
  if (btn.dataset.action === 'save') {
    const res = await callApi('PUT', `../api/menus.php?id=${id}`, {
      name: name,
      description: document.getElementById(`desc-${id}`).value,
      price: Number(document.getElementById(`price-${id}`).value),
      is_available: document.getElementById(`on-${id}`).checked
    });
    say(res.success ? `「${name}」を保存しました` : res.error, res.success);
    if (res.success) {
      const on = document.getElementById(`on-${id}`);
      on.nextElementSibling.textContent = on.checked ? 'ON' : 'OFF';
    }
  }
 
  if (btn.dataset.action === 'delete') {
    if (!confirm(`「${name}」を削除しますか？`)) return;
    const res = await callApi('DELETE', `../api/menus.php?id=${id}`);
    say(res.success ? `「${name}」を削除しました` : res.error, res.success);
    if (res.success) loadList();
  }
});
 
// 3. 追加
document.getElementById('addForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const res = await callApi('POST', '../api/menus.php', {
    name: form.name.value,
    description: form.description.value,
    price: Number(form.price.value),
    sort_order: 99                       // 新しい menu は一番下
  });
  if (res.success) {
    say(`「${form.name.value}」を追加しました`, true);
    form.reset();
    loadList();
  } else {
    say(res.error, false);
  }
});
 
window.addEventListener('load', loadList);
</script>
 
<?php include '../includes/footer.php'; ?>
 

