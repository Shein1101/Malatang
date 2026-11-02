<?php
// public/history.php — 注文履歴 + 再注文 + マイレシピに保存 + 友達にシェア. Owner: Lin (FEAT-17)
// Screen only, NO SQL. Data comes from ../api/history.php, ../api/favorites.php and ../api/shares.php
require_once '../includes/auth.php';
require_login();

$pageTitle = '注文履歴 | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <h1 class="title">注文履歴</h1>
  <p class="caption">再注文は今の値段でカートに入ります。「友達にシェア」で送ったレシピは <a href="friends.php">ファミリー&フレンズ</a> で見られます。</p>

  <div id="error" class="alert alert-danger d-none"></div>
  <div id="done" class="alert alert-success d-none"></div>

  <div id="list" class="stack">
    <p>読み込み中…</p>
  </div>
</main>

<script>
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

const STATUS = {
  pending:   ['受付待ち', 'badge-received'],
  confirmed: ['受付済',   'badge-received'],
  preparing: ['調理中',   'badge-cooking'],
  ready:     ['受取待ち', 'badge-cooking'],
  completed: ['受取済',   'badge-done'],
  cancelled: ['キャンセル', 'badge-soldout'],
};

// '2026-10-15 12:30:00' → '2026/10/15 12:30'
function dateText(s) {
  return String(s).slice(0, 16).replace(/-/g, '/');
}

function bowlRow(b) {
  const gift  = b.from_shared_recipe ? ' <span class="badge badge-cooking">友達のレシピ</span>' : '';
  const lines = [`<b>${esc(b.menu_name)}</b>（${esc(b.soup_name)}・${b.has_soup ? 'スープあり' : 'スープなし'}）×${b.quantity}${gift}`];
  if (b.spice_name)          lines.push('辛さ: ' + esc(b.spice_name));
  if (b.added_ingredients)   lines.push('追加: ' + esc(b.added_ingredients));
  if (b.removed_ingredients) lines.push('抜き: ' + esc(b.removed_ingredients));
  lines.push(yen(b.bowl_total));
  return `
    <li class="flex-wrap">
      <div class="caption">${lines.join('<br>')}</div>
      <div class="d-flex flex-wrap justify-content-end gap-1 ms-auto">
        <button class="btn btn-ghost btn-sm" data-favorite="${b.order_bowl_id}">♡ マイレシピに保存</button>
        <button class="btn btn-ghost btn-sm" data-share="${b.order_bowl_id}" data-label="${esc(b.menu_name)}">友達にシェア</button>
        <button class="btn btn-outline btn-sm" data-reorder-bowl="${b.order_bowl_id}">再注文</button>
      </div>
    </li>`;
}

// 受け取り時間 + 支払い (未払いならお支払いへ)
function pickupLine(o) {
  let pay = '';
  if (o.payment_status === 'paid')          pay = ' ・ 支払い済み';
  else if (o.payment_status === 'refunded') pay = ' ・ 返金済み';
  else if (o.status !== 'cancelled')        pay = ` ・ <a href="payment.php?order_id=${o.order_id}">未払い（お支払いへ）</a>`;
  return `<div class="caption">受け取り ${dateText(o.pickup_time)}${pay}</div>`;
}

function orderCard(o) {
  const [label, cls] = STATUS[o.status] || [o.status, 'badge-received'];
  return `
    <div class="card order-card">
      <div class="order-top">
        <div><b>注文番号：${esc(o.order_number)}</b><div class="caption">注文 ${dateText(o.ordered_at)}</div>${pickupLine(o)}</div>
        <span class="badge ${cls}">${label}</span>
      </div>
      <ul class="list">${o.bowls.map(bowlRow).join('')}</ul>
      <div class="order-foot">
        <span>合計 <span class="price">${yen(o.total_price)}</span></span>
        <button class="btn btn-chili btn-sm" data-reorder-order="${o.order_id}">もう一度注文する</button>
      </div>
    </div>`;
}

async function loadHistory() {
  const res = await callApi('GET', '../api/history.php');
  const list = document.getElementById('list');
  if (!res.success) {
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  if (res.data.length === 0) {
    list.innerHTML = `
      <div class="empty">
        <svg><use href="#i-receipt"></use></svg>
        <p>まだ注文はありません。</p>
        <a class="btn btn-chili" href="menu.php">メニューを見る</a>
      </div>`;
    return;
  }
  list.innerHTML = res.data.map(orderCard).join('');
}

document.getElementById('list').addEventListener('click', async function (e) {
  const btn = e.target.closest('button');
  if (!btn) return;
  const errorBox = document.getElementById('error');
  const doneBox  = document.getElementById('done');
  errorBox.classList.add('d-none');
  doneBox.classList.add('d-none');

  if (btn.dataset.share) {
    openShareDialog({ order_bowl_id: Number(btn.dataset.share) }, btn.dataset.label);
    return;
  }

  let res, message;
  btn.disabled = true;
  if (btn.dataset.favorite) {
    const name = prompt('レシピの名前（例: いつもの）。空でも保存できます。', '');
    if (name === null) { btn.disabled = false; return; }
    res = await callApi('POST', '../api/favorites.php', { order_bowl_id: Number(btn.dataset.favorite), nickname: name });
    message = 'マイレシピに保存しました。<a href="favorites.php">お気に入りを見る →</a>';
  } else if (btn.dataset.reorderBowl) {
    res = await callApi('POST', '../api/history.php?action=reorder', { order_bowl_id: Number(btn.dataset.reorderBowl) });
    message = 'カートに入れました。<a href="cart.php">カートを見る →</a>';
  } else if (btn.dataset.reorderOrder) {
    res = await callApi('POST', '../api/history.php?action=reorder', { order_id: Number(btn.dataset.reorderOrder) });
    message = 'カートに入れました。<a href="cart.php">カートを見る →</a>';
  }
  btn.disabled = false;
  if (!res) return;

  if (!res.success) {
    showError(errorBox, res.error);
    return;
  }
  if (res.data && res.data.price !== undefined) {
    message = `カートに入れました（今の値段 ${yen(res.data.price)}）。<a href="cart.php">カートを見る →</a>`;
    refreshCartCount();
  }
  doneBox.innerHTML = message;
  doneBox.classList.remove('d-none');
});

window.addEventListener('load', loadHistory);
</script>
<script src="../assets/js/share.js"></script>

<?php include '../includes/footer.php'; ?>
