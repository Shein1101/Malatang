<?php
// admin/home.php — ホームページ編集 (owner). Owner: Lin
// Screen only, NO SQL. Data comes from ../api/home.php
// ブロックの 並び順・表示 ON/OFF・文字・画像・期間 を変える。右のプレビューは public/store.php そのもの
$pageTitle = 'ホームページ編集 | Chilli 鍋';
include '../includes/admin_header.php';
?>

<main class="container he-page">
  <div class="he-head">
    <div>
      <h1 class="title">ホームページ編集</h1>
      <p class="caption">↑↓ で場所を変えられます。変更はすぐお客さまのページに反映されます。</p>
    </div>
    <div class="he-actions">
      <button type="button" class="btn btn-outline btn-sm" data-add="promo">＋ 割引情報</button>
      <button type="button" class="btn btn-outline btn-sm" data-add="ad">＋ 広告バナー</button>
      <button type="button" class="btn btn-ghost btn-sm" id="resetBtn">基本デザインに戻す</button>
    </div>
  </div>
  <p id="message" class="he-message d-none" role="status"></p>

  <div class="he-layout">
    <ol id="blockList" class="he-list"></ol>
    <aside class="he-preview" aria-label="プレビュー">
      <div class="he-preview-head">
        <span class="caption">プレビュー</span>
        <a class="caption" href="../public/store.php" target="_blank" rel="noopener">新しいタブで開く</a>
      </div>
      <div id="previewBox" class="he-preview-box"><iframe id="preview" src="../public/store.php" title="ホームページのプレビュー" tabindex="-1"></iframe></div>
    </aside>
  </div>
</main>

<script>
const API = '../api/home.php';
const FORM = {
  title: ['見出し', 'text'], body: ['説明', 'area'], badge: ['バッジ（例: 20% OFF）', 'text'],
  image_url: ['画像', 'image'], link_label: ['ボタンの文字', 'text'], link_url: ['リンク先（例: menu.php）', 'text'],
  start_date: ['いつから', 'date'], end_date: ['いつまで', 'date'],
};
const TYPES = {
  hero:    { label: 'メインバナー', fields: ['title', 'body', 'image_url', 'link_label', 'link_url'] },
  notices: { label: 'お知らせ', fields: [], note: 'お知らせの内容は <a href="store.php">店舗</a> で編集します。' },
  promo:   { label: '割引情報', fields: ['badge', 'title', 'body', 'link_label', 'link_url', 'start_date', 'end_date'], removable: true,
             note: '割引情報はお知らせとして表示されます。カートの金額は変わりません。' },
  ad:      { label: '広告バナー', fields: ['title', 'body', 'image_url', 'link_label', 'link_url', 'start_date', 'end_date'], removable: true },
  custom:  { label: '自分で作る', fields: ['title', 'body', 'link_label', 'link_url'] },
};
const list = document.getElementById('blockList');
let blocks = [];

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function say(text, ok) {
  const m = document.getElementById('message');
  m.textContent = text;
  m.className = 'he-message ' + (ok ? 'is-ok' : 'is-error');
  if (!ok) window.scrollTo({ top: 0, behavior: 'smooth' });
}
function refreshPreview() {
  document.getElementById('preview').contentWindow.location.reload();
}

// 表示中 / 非表示 / 期間外
function status(b) {
  if (!b.is_visible) return ['非表示', 'badge-soldout'];
  const today = new Date().toLocaleDateString('sv-SE');     // YYYY-MM-DD
  if ((b.start_date && b.start_date > today) || (b.end_date && b.end_date < today)) return ['期間外', 'badge-cooking'];
  return ['表示中', 'badge-done'];
}

function fieldHtml(b, key) {
  const [label, kind] = FORM[key];
  const value = esc(b[key]);
  if (kind === 'area') {
    return `<label class="he-field he-wide"><span>${label}</span><textarea class="input" name="${key}" rows="2" maxlength="1000">${value}</textarea></label>`;
  }
  if (kind === 'image') {
    return `
      <div class="he-field he-wide">
        <span>${label}</span>
        <div class="he-image">
          <input class="input" name="${key}" value="${value}" placeholder="画像を選ぶか URL を入力" aria-label="画像の URL">
          <label class="btn btn-outline btn-sm">画像を選ぶ<input type="file" accept="image/jpeg,image/png,image/webp" data-upload hidden></label>
        </div>
      </div>`;
  }
  const wide = key === 'title' ? ' he-wide' : '';
  const max = { title: 100, badge: 30, link_label: 30, link_url: 500 }[key];
  return `<label class="he-field${wide}"><span>${label}</span>
    <input class="input" name="${key}" type="${kind}" value="${value}" ${max ? `maxlength="${max}"` : ''}></label>`;
}

function rowHtml(b, open) {
  const t = TYPES[b.type];
  const [text, cls] = status(b);
  return `
    <li class="he-row" data-id="${b.id}">
      <div class="he-row-main">
        <div class="he-move">
          <button type="button" data-move="-1" aria-label="${t.label}を上へ">↑</button>
          <button type="button" data-move="1" aria-label="${t.label}を下へ">↓</button>
        </div>
        <div class="he-name">
          <b>${t.label}</b>
          <span class="caption">${esc((b.title || '').replace(/\n/g, ' '))}</span>
        </div>
        <span class="badge ${cls}">${text}</span>
        <label class="he-switch" title="表示 ON / OFF">
          <input type="checkbox" role="switch" data-visible ${b.is_visible ? 'checked' : ''} aria-label="${t.label}を表示">
          <span></span>
        </label>
        <button type="button" class="btn btn-outline btn-sm" data-edit aria-expanded="${open}">編集</button>
      </div>
      <form class="he-form" ${open ? '' : 'hidden'}>
        ${t.fields.map(k => fieldHtml(b, k)).join('')}
        ${t.note ? `<p class="caption he-wide mb-0">${t.note}</p>` : ''}
        ${t.fields.length ? `<div class="he-form-foot he-wide">
          ${t.removable ? '<button type="button" class="btn btn-ghost btn-sm" data-delete>削除</button>' : '<span></span>'}
          <button class="btn btn-chili btn-sm">保存</button>
        </div>` : ''}
      </form>
    </li>`;
}

async function loadAll() {
  const res = await callApi('GET', API + '?all=1');
  if (!res.success) { say(res.error, false); return; }
  blocks = res.data;
  list.innerHTML = blocks.map(b => rowHtml(b, false)).join('');
}

const blockOf = (row) => blocks.find(b => b.id === Number(row.dataset.id));

// ---------- クリック ----------
document.querySelector('main').addEventListener('click', async (e) => {
  const btn = e.target.closest('button');
  if (!btn) return;
  const row = btn.closest('.he-row');

  if (btn.dataset.add) {
    const type = btn.dataset.add;
    const res = await callApi('POST', API, {
      type, title: type === 'promo' ? '新しい割引' : '新しい広告', badge: type === 'promo' ? '20% OFF' : '',
      link_label: 'メニューを見る', link_url: 'menu.php', is_visible: false,
    });
    if (!res.success) { say(res.error, false); return; }
    await loadAll();
    const added = list.querySelector(`[data-id="${res.data.id}"]`);
    added.querySelector('.he-form').hidden = false;
    added.scrollIntoView({ behavior: 'smooth', block: 'center' });
    say('追加しました。内容を書いて保存し、表示を ON にしてください', true);
    return;
  }

  if (btn.id === 'resetBtn') {
    if (!confirm('基本デザインに戻しますか？\n並び順と文字が最初の状態になり、追加した割引情報・広告は消えます。')) return;
    const res = await callApi('POST', API + '?action=reset');
    if (!res.success) { say(res.error, false); return; }
    await loadAll();
    refreshPreview();
    say('基本デザインに戻しました', true);
    return;
  }

  if (!row) return;

  if (btn.dataset.move) {
    const other = Number(btn.dataset.move) < 0 ? row.previousElementSibling : row.nextElementSibling;
    if (!other) return;
    if (Number(btn.dataset.move) < 0) list.insertBefore(row, other); else list.insertBefore(other, row);
    const ids = [...list.children].map(li => Number(li.dataset.id));
    const res = await callApi('PUT', API + '?action=order', { ids });
    if (!res.success) { say(res.error, false); loadAll(); return; }
    refreshPreview();
    btn.focus();
    return;
  }

  if ('edit' in btn.dataset) {
    const form = row.querySelector('.he-form');
    form.hidden = !form.hidden;
    btn.setAttribute('aria-expanded', !form.hidden);
    return;
  }

  if ('delete' in btn.dataset) {
    const b = blockOf(row);
    if (!confirm(`「${b.title || TYPES[b.type].label}」を削除しますか？`)) return;
    const res = await callApi('DELETE', `${API}?id=${b.id}`);
    if (!res.success) { say(res.error, false); return; }
    blocks = blocks.filter(x => x.id !== b.id);
    row.remove();
    refreshPreview();
    say('削除しました', true);
  }
});

// ---------- 表示 ON/OFF・画像 ----------
list.addEventListener('change', async (e) => {
  const row = e.target.closest('.he-row');
  if ('visible' in e.target.dataset) {
    const b = blockOf(row);
    const res = await callApi('PUT', `${API}?id=${b.id}`, { is_visible: e.target.checked });
    if (!res.success) { e.target.checked = !e.target.checked; say(res.error, false); return; }
    b.is_visible = e.target.checked;
    const [text, cls] = status(b);
    const badge = row.querySelector('.badge');
    badge.textContent = text;
    badge.className = 'badge ' + cls;
    refreshPreview();
  }
  if ('upload' in e.target.dataset && e.target.files[0]) {
    const body = new FormData();
    body.append('image', e.target.files[0]);
    let res;
    try {
      res = await (await fetch(API + '?action=upload', { method: 'POST', body, credentials: 'same-origin' })).json();
    } catch (err) {
      res = { success: false, error: '画像をアップロードできませんでした' };
    }
    e.target.value = '';
    if (!res.success) { say(res.error, false); return; }
    row.querySelector('[name="image_url"]').value = res.data.url;
    say('画像を入れました。「保存」を押すと反映されます', true);
  }
});

// ---------- 保存 ----------
list.addEventListener('submit', async (e) => {
  e.preventDefault();
  const row = e.target.closest('.he-row');
  const b = blockOf(row);
  const body = {};
  for (const key of TYPES[b.type].fields) body[key] = e.target.elements[key].value;
  const res = await callApi('PUT', `${API}?id=${b.id}`, body);
  if (!res.success) { say(res.error, false); return; }
  Object.assign(b, body);
  row.outerHTML = rowHtml(b, true);
  refreshPreview();
  say('保存しました', true);
});

// プレビューは PC の幅のまま縮小して見せる
function fitPreview() {
  const box = document.getElementById('previewBox');
  box.style.setProperty('--scale', box.clientWidth / 1200);
}
window.addEventListener('resize', fitPreview);
window.addEventListener('load', () => { fitPreview(); loadAll(); });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
