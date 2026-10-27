<?php
// public/store.php — 店舗ページ (customer). Owner: Myat (FEAT-10)
// Screen only, NO SQL. Data comes from ../api/store.php
$pageTitle = 'お店について | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container">
  <p id="error" class="text-danger d-none"></p>
  <div id="store"><p>読み込み中…</p></div>
</main>

<script>
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
// 改行をそのまま見せる
function lines(s) { return esc(s).replace(/\n/g, '<br>'); }

async function loadStore() {
  const res = await callApi('GET', '../api/store.php');
  if (!res.success) {
    document.getElementById('store').innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  const { store, photos, announcements } = res.data;

  // お知らせ
  let news = '';
  for (const a of announcements) {
    news += `<div class="card card-body mb-2"><strong>${esc(a.title)}</strong><div>${lines(a.body)}</div></div>`;
  }

  // 写真
  let gallery = '';
  for (const p of photos) {
    gallery += `
      <figure class="m-0">
        <img src="${esc(p.image_url)}" alt="${esc(p.caption)}" style="width:100%;aspect-ratio:4/3;object-fit:cover;background:#ddd;border-radius:8px"
             onerror="this.style.visibility='hidden'">
        <figcaption class="caption">${esc(p.caption)}</figcaption>
      </figure>`;
  }

  document.getElementById('store').innerHTML = `
    ${store.banner_url ? `<img src="${esc(store.banner_url)}" alt="" style="width:100%;max-height:320px;object-fit:cover;border-radius:12px" onerror="this.remove()">` : ''}
    <h1 class="mt-3">${esc(store.store_name)}
      <span class="badge ${store.is_open ? 'badge-done' : 'badge-soldout'}">${store.is_open ? '営業中' : '本日はお休み'}</span>
    </h1>
    <p>${lines(store.description)}</p>

    ${news ? `<h2 class="h5 mt-4">お知らせ</h2>${news}` : ''}

    <h2 class="h5 mt-4">店舗情報</h2>
    <table class="table">
      ${store.opening_hours ? `<tr><th style="width:120px">営業時間</th><td>${esc(store.opening_hours)}</td></tr>` : ''}
      ${store.address ? `<tr><th>住所</th><td>${esc(store.address)}</td></tr>` : ''}
      ${store.phone ? `<tr><th>電話</th><td><a href="tel:${esc(store.phone)}">${esc(store.phone)}</a></td></tr>` : ''}
    </table>

    ${gallery ? `<h2 class="h5 mt-4">写真</h2><div class="grid-4">${gallery}</div>` : ''}

    <p class="mt-4"><a class="btn btn-chili" href="menu.php">メニューを見る →</a></p>`;
}

window.addEventListener('load', loadStore);
</script>

<?php include '../includes/footer.php'; ?>
