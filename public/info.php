<?php
// public/info.php — 店舗情報 (customer). Owner: Myat (FEAT-10), ページ分け: Lin
// Screen only, NO SQL. Data comes from ../api/store.php (編集は admin/store.php)
$pageTitle = '店舗情報 | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <h1 class="title">店舗情報</h1>
  <p id="error" class="field-error d-none"></p>
  <div id="info" class="stack"><p class="muted">読み込み中…</p></div>
</main>

<script>
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
// 改行をそのまま見せる
function lines(s) { return esc(s).replace(/\n/g, '<br>'); }

// 画像が読めないときは その写真を消す (全部消えたら見出しごと)
function photoFail(img) {
  const section = img.closest('section');
  img.closest('figure').remove();
  if (section && !section.querySelector('figure')) section.remove();
}

async function loadInfo() {
  const res = await callApi('GET', '../api/store.php');
  const box = document.getElementById('info');
  if (!res.success) {
    box.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  const { store: s, photos, announcements } = res.data;
  const rows = [
    s.opening_hours ? `<div><dt>営業時間</dt><dd>${esc(s.opening_hours)}</dd></div>` : '',
    s.address ? `<div><dt>住所</dt><dd>${esc(s.address)}</dd></div>` : '',
    s.phone ? `<div><dt>電話</dt><dd><a href="tel:${esc(s.phone)}">${esc(s.phone)}</a></dd></div>` : '',
  ].join('');

  box.innerHTML = `
    <section class="info-card">
      <div>
        <h2 class="info-name">${esc(s.store_name)}
          <span class="badge ${s.is_open ? 'badge-done' : 'badge-soldout'}">${s.is_open ? '営業中' : '本日はお休み'}</span>
        </h2>
        ${s.description ? `<p class="muted">${lines(s.description)}</p>` : ''}
      </div>
      ${rows ? `<dl class="info-list">${rows}</dl>` : ''}
    </section>
    ${announcements.length ? `
      <section>
        <h2 class="heading">お知らせ</h2>
        <ul class="notice-list">
          ${announcements.map(a => `
            <li>
              <span class="notice-dot" aria-hidden="true"></span>
              <div><strong>${esc(a.title)}</strong>${a.body ? `<div class="notice-body">${lines(a.body)}</div>` : ''}</div>
            </li>`).join('')}
        </ul>
      </section>` : ''}
    ${photos.length ? `
      <section>
        <h2 class="heading">お店の様子</h2>
        <div class="grid-4">
          ${photos.map(p => `
            <figure class="photo-tile">
              <img src="${esc(p.image_url)}" alt="${esc(p.caption)}" onerror="photoFail(this)">
              <figcaption class="caption">${esc(p.caption)}</figcaption>
            </figure>`).join('')}
        </div>
      </section>` : ''}`;
}

window.addEventListener('load', loadInfo);
</script>

<?php include '../includes/footer.php'; ?>
