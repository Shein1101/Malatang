<?php
// admin/options.php — 辛さ管理 (owner). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/options.php
// 辛さの説明・追加料金を変える / ON・OFF / 新しい辛さを追加
$pageTitle = '辛さ管理 | Chilli 鍋';
include '../includes/admin_header.php';
?>
 
<main class="container">
  <h1>辛さ管理</h1>
  <p class="caption">お客さんには「1（辛くない）」のように表示されます。OFF にした辛さはお客さんの画面に出ません。</p>
  <p id="message"></p>
 
  <!-- 辛さの一覧 -->
  <table class="table align-middle">
    <thead>
      <tr>
        <th>番号</th>
        <th>説明</th>
        <th>追加料金（円）</th>
        <th>販売</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="list">
      <tr><td colspan="5">読み込み中…</td></tr>
    </tbody>
  </table>
 
  <!-- 新しい辛さを追加 -->
  <form id="addForm" class="card card-body">
    <h2 class="h5">辛さを追加</h2>
    <div class="row g-2">
      <div class="col-md-2">
        <label class="form-label">番号</label>
        <input name="level" type="number" min="0" class="form-control" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">説明</label>
        <input name="name" class="form-control" required maxlength="50" placeholder="例: 地獄辛">
      </div>
      <div class="col-md-3">
        <label class="form-label">追加料金（円）</label>
        <input name="extra_price" type="number" min="0" value="0" class="form-control">
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-chili w-100">追加</button>
      </div>
    </div>
  </form>
</main>
 
<script>
// 文字を安全に HTML に入れる
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
 
// メッセージを出す (ok = true なら緑、false なら赤)
function say(text, ok) {
  const msg = document.getElementById('message');
  msg.className = ok ? 'text-success' : 'text-danger';
  msg.textContent = text;
}
 
// 1. 辛さを全部もらって表にする (?all=1 で OFF のものも)
async function loadList() {
  const res = await callApi('GET', '../api/options.php?all=1');
  if (!res.success) {
    say(res.error, false);
    return;
  }
  let rows = '';
  for (const s of res.data.spice_levels) {
    rows += `
      <tr>
        <td><strong>${s.level}</strong></td>
        <td><input class="form-control form-control-sm" id="name-${s.id}" value="${esc(s.name)}" maxlength="50"></td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:100px"
                   id="price-${s.id}" value="${s.extra_price}"></td>
        <td>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="on-${s.id}" ${s.is_available ? 'checked' : ''}>
            <label class="form-check-label" for="on-${s.id}">${s.is_available ? 'ON' : 'OFF'}</label>
          </div>
        </td>
        <td><button class="btn btn-sm btn-outline" data-id="${s.id}">保存</button></td>
      </tr>`;
  }
  document.getElementById('list').innerHTML = rows;
}
 
// 2. 「保存」ボタン → PUT
document.getElementById('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button');
  if (!btn) return;
  const id = btn.dataset.id;
 
  const res = await callApi('PUT', `../api/options.php?type=spice&id=${id}`, {
    name: document.getElementById(`name-${id}`).value,
    extra_price: Number(document.getElementById(`price-${id}`).value),
    is_available: document.getElementById(`on-${id}`).checked
  });
  say(res.success ? '保存しました' : res.error, res.success);
 
  // その行の ON / OFF の文字だけ新しくする
  // (表を全部読み直すと、ほかの行で入力中の文字が消えてしまうため)
  if (res.success) {
    const on = document.getElementById(`on-${id}`);
    on.nextElementSibling.textContent = on.checked ? 'ON' : 'OFF';
  }
});
 
// 3. 「追加」ボタン → POST
document.getElementById('addForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const res = await callApi('POST', '../api/options.php?type=spice', {
    level: Number(form.level.value),
    name: form.name.value,
    extra_price: Number(form.extra_price.value)
  });
  if (res.success) {
    say(`「${form.level.value}（${form.name.value}）」を追加しました`, true);
    form.reset();
    loadList();
  } else {
    say(res.error, false);
  }
});
 
// app.js (callApi) はフッターで読み込まれるので、ページを全部読んでから動かす
window.addEventListener('load', loadList);
</script>
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
