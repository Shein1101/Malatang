<?php
// admin/juices.php — ジュース管理 (owner). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/juices.php
// お店が 飲み物を 追加／変更／削除 する。OFF にするとメニューに出ない。
$pageTitle = 'ジュース管理 | Chilli 鍋';
include '../includes/admin_header.php';
?>

<main class="container">
  <h1>ジュース管理</h1>
  <p class="caption">メニューページに表示する飲み物です。「販売」を OFF にすると、お客さんの画面に出ません。（今は表示だけで、カートには入りません）</p>
  <p id="message"></p>

  <!-- 追加 -->
  <form id="addForm" class="card card-body mb-4">
    <h2 class="h5">ジュースを追加</h2>
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">名前</label>
        <input name="name" class="form-control" required maxlength="100" placeholder="例: マンゴージュース">
      </div>
      <div class="col-md-4">
        <label class="form-label">説明</label>
        <input name="description" class="form-control" maxlength="255" placeholder="例: 濃厚な南国マンゴー">
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

  <!-- 一覧 -->
  <table class="table align-middle">
    <thead>
      <tr><th>画像</th><th>名前</th><th>説明</th><th>値段（円）</th><th>販売</th><th></th></tr>
    </thead>
    <tbody id="list"><tr><td colspan="6">読み込み中…</td></tr></tbody>
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

async function loadList() {
  const res = await callApi('GET', '../api/juices.php?all=1');
  if (!res.success) { say(res.error, false); return; }
  let rows = '';
  for (const j of res.data) {
    rows += `
      <tr>
        <td>
          <div style="width:52px;height:52px;border-radius:8px;overflow:hidden;background:var(--soft);display:grid;place-items:center">
            ${j.image_url ? `<img src="${esc(j.image_url)}" alt="" style="width:100%;height:100%;object-fit:cover">` : '<span class="muted" style="font-size:11px">なし</span>'}
          </div>
          <label class="btn btn-sm btn-ghost mt-1" style="cursor:pointer">画像<input type="file" accept="image/*" data-img="${j.id}" hidden></label>
        </td>
        <td><input class="form-control form-control-sm" id="name-${j.id}" value="${esc(j.name)}" maxlength="100" style="min-width:130px"></td>
        <td><input class="form-control form-control-sm" id="desc-${j.id}" value="${esc(j.description)}" maxlength="255"></td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:100px" id="price-${j.id}" value="${j.price}"></td>
        <td>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="on-${j.id}" ${j.is_available ? 'checked' : ''}>
            <label class="form-check-label" for="on-${j.id}">${j.is_available ? 'ON' : 'OFF'}</label>
          </div>
        </td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline" data-save="${j.id}">保存</button>
          <button class="btn btn-sm btn-ghost text-danger" data-del="${j.id}" data-name="${esc(j.name)}">削除</button>
        </td>
      </tr>`;
  }
  document.getElementById('list').innerHTML = rows || '<tr><td colspan="6" class="muted">ジュースがありません</td></tr>';
}

// 画像アップロード (api/home.php の画像アップロードを使い回す → URL を juice に保存)
document.getElementById('list').addEventListener('change', async (e) => {
  const inp = e.target.closest('input[data-img]');
  if (!inp || !inp.files.length) return;
  const id = inp.dataset.img;
  say('画像をアップロード中…', true);
  const fd = new FormData();
  fd.append('image', inp.files[0]);
  let up;
  try {
    up = await fetch('../api/home.php?action=upload', { method: 'POST', body: fd, credentials: 'same-origin' }).then(r => r.json());
  } catch (_) {
    up = { success: false, error: 'アップロードに失敗しました' };
  }
  if (!up.success) { say(up.error || 'アップロードに失敗しました', false); return; }
  const res = await callApi('PUT', `../api/juices.php?id=${id}`, { image_url: up.data.url });
  say(res.success ? '画像を保存しました' : res.error, res.success);
  if (res.success) loadList();
});

document.getElementById('list').addEventListener('click', async (e) => {
  const save = e.target.closest('button[data-save]');
  const del  = e.target.closest('button[data-del]');

  if (save) {
    const id = save.dataset.save;
    const res = await callApi('PUT', `../api/juices.php?id=${id}`, {
      name: document.getElementById(`name-${id}`).value,
      description: document.getElementById(`desc-${id}`).value,
      price: Number(document.getElementById(`price-${id}`).value),
      is_available: document.getElementById(`on-${id}`).checked,
    });
    say(res.success ? '保存しました' : res.error, res.success);
    if (res.success) {
      const on = document.getElementById(`on-${id}`);
      on.nextElementSibling.textContent = on.checked ? 'ON' : 'OFF';
    }
  }

  if (del) {
    if (!confirm(`「${del.dataset.name}」を削除しますか？`)) return;
    const res = await callApi('DELETE', `../api/juices.php?id=${del.dataset.del}`);
    say(res.success ? '削除しました' : res.error, res.success);
    if (res.success) loadList();
  }
});

document.getElementById('addForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const res = await callApi('POST', '../api/juices.php', {
    name: form.name.value,
    description: form.description.value,
    price: Number(form.price.value),
  });
  if (res.success) { say(`「${form.name.value}」を追加しました`, true); form.reset(); loadList(); }
  else { say(res.error, false); }
});

window.addEventListener('load', loadList);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
