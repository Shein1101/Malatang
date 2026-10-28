<?php
// admin/store.php — 店舗ページ編集 (owner). Owner: Myat (FEAT-10)
// Screen only, NO SQL. Data comes from ../api/store.php
// 1. お店の情報  2. 写真  3. お知らせ
$pageTitle = '店舗ページ編集 | Chilli 鍋';
include '../includes/admin_header.php';
?>

<main class="container">
  <h1>店舗ページ編集</h1>
  <p class="caption">ここで変えた内容は、お客さんの <a href="../public/store.php" target="_blank">店舗ページ</a> にすぐ出ます。</p>
  <p id="message"></p>

  <!-- ========== 1. お店の情報 ========== -->
  <form id="storeForm" class="card card-body mb-4">
    <h2 class="h5">お店の情報</h2>
    <div class="row g-2">
      <div class="col-md-4"><label class="form-label">店名</label><input name="store_name" class="form-control" required maxlength="100"></div>
      <div class="col-md-4"><label class="form-label">電話</label><input name="phone" class="form-control" maxlength="20"></div>
      <div class="col-md-4"><label class="form-label">営業時間</label><input name="opening_hours" class="form-control" placeholder="例: 11:00〜21:00 (火曜定休)"></div>
      <div class="col-md-8"><label class="form-label">住所</label><input name="address" class="form-control"></div>
      <div class="col-md-4"><label class="form-label">トップ写真の URL</label><input name="banner_url" class="form-control"></div>
      <div class="col-12"><label class="form-label">説明</label><textarea name="description" class="form-control" rows="2"></textarea></div>
      <div class="col-md-6 d-flex align-items-center">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" name="is_open" id="isOpen">
          <label class="form-check-label" for="isOpen">今日は営業中</label>
        </div>
      </div>
      <div class="col-md-6 text-end"><button class="btn btn-chili">保存</button></div>
    </div>
  </form>

  <!-- ========== 2. 写真 ========== -->
  <h2 class="h5">写真</h2>
  <table class="table align-middle">
    <thead><tr><th>写真</th><th>説明</th><th>順番</th><th>表示</th><th></th></tr></thead>
    <tbody id="photoList"></tbody>
  </table>
  <form id="photoForm" class="row g-2 mb-5">
    <div class="col-md-5"><input name="image_url" class="form-control" placeholder="写真の URL (例: ../assets/img/store/front.jpg)" required></div>
    <div class="col-md-4"><input name="caption" class="form-control" placeholder="説明 (例: 店の外観)"></div>
    <div class="col-md-3"><button class="btn btn-outline w-100">写真を追加</button></div>
  </form>

  <!-- ========== 3. お知らせ ========== -->
  <h2 class="h5">お知らせ</h2>
  <table class="table align-middle">
    <thead><tr><th>タイトル</th><th>期間</th><th>公開</th><th></th></tr></thead>
    <tbody id="newsList"></tbody>
  </table>
  <form id="newsForm" class="card card-body">
    <div class="row g-2">
      <div class="col-md-6"><label class="form-label">タイトル</label><input name="title" class="form-control" required maxlength="100"></div>
      <div class="col-md-3"><label class="form-label">いつから</label><input name="start_date" type="date" class="form-control"></div>
      <div class="col-md-3"><label class="form-label">いつまで</label><input name="end_date" type="date" class="form-control"></div>
      <div class="col-12"><label class="form-label">本文</label><textarea name="body" class="form-control" rows="2"></textarea></div>
      <div class="col-md-6 d-flex align-items-center">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_published" id="pub" checked>
          <label class="form-check-label" for="pub">すぐ公開する</label></div>
      </div>
      <div class="col-md-6 text-end"><button class="btn btn-chili">お知らせを追加</button></div>
    </div>
    <p class="caption mt-2 mb-0">期間を空にすると「今日から・ずっと」になります。</p>
  </form>
</main>

<script>
const API = '../api/store.php';
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function say(text, ok) {
  const msg = document.getElementById('message');
  msg.className = ok ? 'text-success' : 'text-danger';
  msg.textContent = text;
  window.scrollTo(0, 0);
}
function sw(attrs, on) {      // ON/OFF スイッチ
  return `<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" ${attrs} ${on ? 'checked' : ''}></div>`;
}

// ---------- 読み込み (?all=1 で非表示・下書きも) ----------
async function loadAll() {
  const res = await callApi('GET', API + '?all=1');
  if (!res.success) { say(res.error, false); return; }
  const { store, photos, announcements } = res.data;

  // 1. お店の情報をフォームに入れる
  const f = document.getElementById('storeForm');
  for (const key of ['store_name', 'phone', 'opening_hours', 'address', 'banner_url', 'description']) {
    f[key].value = store[key] ?? '';
  }
  f.is_open.checked = store.is_open;

  // 2. 写真
  let rows = '';
  for (const p of photos) {
    rows += `
      <tr>
        <td><img src="${esc(p.image_url)}" alt="" style="width:80px;height:60px;object-fit:cover;background:#ddd"
                 onerror="this.style.visibility='hidden'"><div class="caption">${esc(p.image_url)}</div></td>
        <td><input class="form-control form-control-sm" id="cap-${p.id}" value="${esc(p.caption)}"></td>
        <td><input type="number" class="form-control form-control-sm" style="width:70px" id="ord-${p.id}" value="${p.sort_order}"></td>
        <td>${sw(`id="vis-${p.id}"`, p.is_visible)}</td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline" data-photo-save="${p.id}">保存</button>
          <button class="btn btn-sm btn-ghost" data-photo-del="${p.id}">削除</button>
        </td>
      </tr>`;
  }
  document.getElementById('photoList').innerHTML = rows || '<tr><td colspan="5">写真はまだありません</td></tr>';

  // 3. お知らせ
  rows = '';
  for (const a of announcements) {
    const period = (a.start_date || '今日') + ' 〜 ' + (a.end_date || 'ずっと');
    rows += `
      <tr>
        <td><strong>${esc(a.title)}</strong><div class="caption">${esc(a.body)}</div></td>
        <td class="caption">${esc(period)}</td>
        <td>${sw(`data-news-pub="${a.id}"`, a.is_published)}</td>
        <td><button class="btn btn-sm btn-ghost" data-news-del="${a.id}" data-title="${esc(a.title)}">削除</button></td>
      </tr>`;
  }
  document.getElementById('newsList').innerHTML = rows || '<tr><td colspan="4">お知らせはまだありません</td></tr>';
}

// ---------- 1. お店の情報を保存 ----------
document.getElementById('storeForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const f = e.target;
  const res = await callApi('PUT', API, {
    store_name: f.store_name.value, phone: f.phone.value, opening_hours: f.opening_hours.value,
    address: f.address.value, banner_url: f.banner_url.value, description: f.description.value,
    is_open: f.is_open.checked
  });
  say(res.success ? 'お店の情報を保存しました' : res.error, res.success);
});

// ---------- 2. 写真を追加 ----------
document.getElementById('photoForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const f = e.target;
  const res = await callApi('POST', API + '?type=photo', { image_url: f.image_url.value, caption: f.caption.value, sort_order: 99 });
  say(res.success ? '写真を追加しました' : res.error, res.success);
  if (res.success) { f.reset(); loadAll(); }
});

// ---------- 3. お知らせを追加 ----------
document.getElementById('newsForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const f = e.target;
  const res = await callApi('POST', API + '?type=announcement', {
    title: f.title.value, body: f.body.value, is_published: f.is_published.checked,
    start_date: f.start_date.value, end_date: f.end_date.value
  });
  say(res.success ? 'お知らせを追加しました' : res.error, res.success);
  if (res.success) { f.reset(); loadAll(); }
});

// ---------- 表の中のボタン・スイッチ ----------
document.querySelector('main').addEventListener('click', async (e) => {
  const el = e.target;
  let res;

  if (el.dataset.photoSave) {
    const id = el.dataset.photoSave;
    res = await callApi('PUT', `${API}?type=photo&id=${id}`, {
      caption: document.getElementById(`cap-${id}`).value,
      sort_order: Number(document.getElementById(`ord-${id}`).value),
      is_visible: document.getElementById(`vis-${id}`).checked
    });
    say(res.success ? '写真を保存しました' : res.error, res.success);
    return;
  }
  if (el.dataset.photoDel) {
    if (!confirm('この写真を削除しますか？')) return;
    res = await callApi('DELETE', `${API}?type=photo&id=${el.dataset.photoDel}`);
  }
  if (el.dataset.newsPub) {      // 公開スイッチは押したらすぐ保存
    res = await callApi('PUT', `${API}?type=announcement&id=${el.dataset.newsPub}`, { is_published: el.checked });
  }
  if (el.dataset.newsDel) {
    if (!confirm(`「${el.dataset.title}」を削除しますか？`)) return;
    res = await callApi('DELETE', `${API}?type=announcement&id=${el.dataset.newsDel}`);
  }
  if (!res) return;
  say(res.success ? '保存しました' : res.error, res.success);
  loadAll();
});

window.addEventListener('load', loadAll);
</script>

<?php include '../includes/footer.php'; ?>
