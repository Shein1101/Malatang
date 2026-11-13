<?php
// admin/options.php — スープ・辛さ管理 (owner). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/options.php
// スープ: 名前・説明・値段を変える / 販売 ON・OFF / 追加
// 辛さ: 説明・追加料金を変える / ON・OFF / 追加
$pageTitle = 'スープ・辛さ管理 | Chilli 鍋';
include '../includes/admin_header.php';
?>

<main class="container">
  <h1>スープ・辛さ管理</h1>
  <p id="message"></p>

  <!-- ============ スープ (味) ============ -->
  <section class="mb-5">
    <h2 class="h4">スープ（味）</h2>
    <p class="caption">「販売」を OFF にしたスープは、お客さんのカスタマイズ画面に出ません。値段を変えると履歴に残ります。</p>

    <form id="addSoup" class="card card-body mb-3">
      <h3 class="h6">スープを追加</h3>
      <div class="row g-2">
        <div class="col-md-3">
          <label class="form-label">名前</label>
          <input name="name" class="form-control" required maxlength="100" placeholder="例: カレースープ">
        </div>
        <div class="col-md-4">
          <label class="form-label">説明</label>
          <input name="description" class="form-control" maxlength="255" placeholder="例: スパイスのきいた">
        </div>
        <div class="col-md-2">
          <label class="form-label">値段（円）</label>
          <input name="price" type="number" min="0" value="0" class="form-control">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-chili w-100">追加</button>
        </div>
      </div>
    </form>

    <table class="table align-middle">
      <thead>
        <tr><th>名前</th><th>説明</th><th>値段（円）</th><th>販売</th><th></th></tr>
      </thead>
      <tbody id="soupList"><tr><td colspan="5">読み込み中…</td></tr></tbody>
    </table>
  </section>

  <!-- ============ 辛さ ============ -->
  <section>
    <h2 class="h4">辛さ</h2>
    <p class="caption">お客さんには「1（辛くない）」のように表示されます。OFF にした辛さはお客さんの画面に出ません。</p>

    <form id="addForm" class="card card-body mb-3">
      <h3 class="h6">辛さを追加</h3>
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

    <table class="table align-middle">
      <thead>
        <tr><th>番号</th><th>説明</th><th>追加料金（円）</th><th>販売</th><th></th></tr>
      </thead>
      <tbody id="list"><tr><td colspan="5">読み込み中…</td></tr></tbody>
    </table>
  </section>
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

// ============ 読み込み (スープと辛さを一度にもらう) ============
async function loadList() {
  const res = await callApi('GET', '../api/options.php?all=1');
  if (!res.success) { say(res.error, false); return; }

  // --- スープ ---
  let soupRows = '';
  for (const s of res.data.soups) {
    soupRows += `
      <tr>
        <td><input class="form-control form-control-sm" id="sname-${s.id}" value="${esc(s.name)}" maxlength="100" style="min-width:120px"></td>
        <td><input class="form-control form-control-sm" id="sdesc-${s.id}" value="${esc(s.description)}" maxlength="255"></td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:100px" id="sprice-${s.id}" value="${s.price}"></td>
        <td>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="son-${s.id}" ${s.is_available ? 'checked' : ''}>
            <label class="form-check-label" for="son-${s.id}">${s.is_available ? 'ON' : 'OFF'}</label>
          </div>
        </td>
        <td><button class="btn btn-sm btn-outline" data-soup="${s.id}">保存</button></td>
      </tr>`;
  }
  document.getElementById('soupList').innerHTML = soupRows || '<tr><td colspan="5" class="muted">スープがありません</td></tr>';

  // --- 辛さ ---
  let rows = '';
  for (const s of res.data.spice_levels) {
    rows += `
      <tr>
        <td><strong>${s.level}</strong></td>
        <td><input class="form-control form-control-sm" id="name-${s.id}" value="${esc(s.name)}" maxlength="50"></td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:100px" id="price-${s.id}" value="${s.extra_price}"></td>
        <td>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="on-${s.id}" ${s.is_available ? 'checked' : ''}>
            <label class="form-check-label" for="on-${s.id}">${s.is_available ? 'ON' : 'OFF'}</label>
          </div>
        </td>
        <td><button class="btn btn-sm btn-outline" data-id="${s.id}">保存</button></td>
      </tr>`;
  }
  document.getElementById('list').innerHTML = rows;
}

// ============ スープ 保存 / 追加 ============
document.getElementById('soupList').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-soup]');
  if (!btn) return;
  const id = btn.dataset.soup;
  const res = await callApi('PUT', `../api/options.php?type=soup&id=${id}`, {
    name: document.getElementById(`sname-${id}`).value,
    description: document.getElementById(`sdesc-${id}`).value,
    price: Number(document.getElementById(`sprice-${id}`).value),
    is_available: document.getElementById(`son-${id}`).checked,
  });
  say(res.success ? '保存しました' : res.error, res.success);
  if (res.success) {
    const on = document.getElementById(`son-${id}`);
    on.nextElementSibling.textContent = on.checked ? 'ON' : 'OFF';
  }
});

document.getElementById('addSoup').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const res = await callApi('POST', '../api/options.php?type=soup', {
    name: form.name.value,
    description: form.description.value,
    price: Number(form.price.value),
  });
  if (res.success) { say(`「${form.name.value}」を追加しました`, true); form.reset(); loadList(); }
  else { say(res.error, false); }
});

// ============ 辛さ 保存 / 追加 ============
document.getElementById('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-id]');
  if (!btn) return;
  const id = btn.dataset.id;
  const res = await callApi('PUT', `../api/options.php?type=spice&id=${id}`, {
    name: document.getElementById(`name-${id}`).value,
    extra_price: Number(document.getElementById(`price-${id}`).value),
    is_available: document.getElementById(`on-${id}`).checked,
  });
  say(res.success ? '保存しました' : res.error, res.success);
  if (res.success) {
    const on = document.getElementById(`on-${id}`);
    on.nextElementSibling.textContent = on.checked ? 'ON' : 'OFF';
  }
});

document.getElementById('addForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const res = await callApi('POST', '../api/options.php?type=spice', {
    level: Number(form.level.value),
    name: form.name.value,
    extra_price: Number(form.extra_price.value),
  });
  if (res.success) { say(`「${form.level.value}（${form.name.value}）」を追加しました`, true); form.reset(); loadList(); }
  else { say(res.error, false); }
});

window.addEventListener('load', loadList);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
