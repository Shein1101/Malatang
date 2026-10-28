<?php
// admin/orders.php — 注文一覧 (お店用). Owner: Zan (FEAT-14)
// 画面だけ。SQL なし。admin_header.php が require_admin() するので admin しか見られない。
// 受け取り時間の早い順に並べて、ボタンでステータスを進める。30秒ごとに自動で更新。
$pageTitle = '注文一覧 | Chilli 鍋 管理';
include '../includes/admin_header.php';
?>

<main class="container page">
  <div class="section-head">
    <h1 class="heading">注文一覧</h1>
    <span class="caption" id="updated"></span>
  </div>

  <div class="tabs" role="tablist">
    <button class="tab is-active" role="tab" data-filter="active">対応中</button>
    <button class="tab" role="tab" data-filter="all">すべて</button>
  </div>

  <p id="error" class="field-error d-none"></p>
  <div id="orderList" class="grid-2"><p class="muted">読み込み中…</p></div>
</main>

<script>
// ステータスの日本語・バッジの色・ボタンの文字
const STATUS = {
  pending:   { label: '受付待ち',   badge: 'badge-label',    button: '受付する' },
  confirmed: { label: '受付済',     badge: 'badge-received', button: '受付する' },
  preparing: { label: '調理中',     badge: 'badge-cooking',  button: '調理を始める' },
  ready:     { label: '受取待ち',   badge: 'badge-received', button: 'できあがり' },
  completed: { label: '受取済',     badge: 'badge-done',     button: '受け渡し完了' },
  cancelled: { label: 'キャンセル', badge: 'badge-soldout',  button: 'キャンセル' },
};
let filter = 'active';

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
const hhmm = (dt) => String(dt).slice(11, 16);

function bowlLines(o) {
  return o.bowls.map(b => {
    const added   = b.items.filter(i => i.action === 'added').map(i => `${esc(i.ingredient_name)}×${i.quantity}`);
    const removed = b.items.filter(i => i.action === 'removed').map(i => esc(i.ingredient_name));
    return `<li><div>
        <b>${esc(b.menu_name)} ×${b.quantity}</b>
        <div class="caption">${esc(b.soup_name)}・${b.has_soup ? 'スープあり' : 'スープなし'}${b.spice_name ? '・' + esc(b.spice_name) : ''}</div>
        ${added.length ? `<div class="caption">＋ ${added.join('、')}</div>` : ''}
        ${removed.length ? `<div class="caption">− ${removed.join('、')} 抜き</div>` : ''}
      </div></li>`;
  }).join('');
}

function orderCard(o) {
  const s = STATUS[o.status];
  // 次に進むボタン (キャンセルは別の色)
  const buttons = o.next_status.map(n => n === 'cancelled'
    ? `<button class="btn btn-ghost btn-sm" data-id="${o.id}" data-status="cancelled">キャンセル</button>`
    : `<button class="btn btn-chili btn-sm" data-id="${o.id}" data-status="${n}">${STATUS[n].button}</button>`
  ).join('');

  return `
    <div class="card order-card">
      <div class="order-top">
        <div>
          <b>受取 ${hhmm(o.pickup_time)}</b>
          <div class="caption">#${esc(o.order_number)}・${esc(o.customer_name)}${o.is_guest ? '（ゲスト）' : ''}${o.customer_phone ? '・' + esc(o.customer_phone) : ''}</div>
        </div>
        <span class="badge ${s.badge}">${s.label}</span>
      </div>
      <ul class="list">${bowlLines(o)}</ul>
      ${o.note ? `<p class="caption">備考：${esc(o.note)}</p>` : ''}
      <div class="order-foot">
        <span>合計 <span class="price">${yen(o.total_price)}</span>
          <span class="badge ${o.payment_status === 'paid' ? 'badge-done' : 'badge-soldout'}">${o.payment_status === 'paid' ? '支払済' : '未払い'}</span></span>
        <div class="row">${buttons}</div>
      </div>
    </div>`;
}

async function loadOrders() {
  const res = await callApi('GET', '../api/orders.php' + (filter === 'all' ? '?status=all' : ''));
  const list = document.getElementById('orderList');
  if (!res.success) {
    showError(document.getElementById('error'), res.error);
    return;
  }
  document.getElementById('error').classList.add('d-none');
  list.innerHTML = res.data.length
    ? res.data.map(orderCard).join('')
    : '<div class="empty card"><svg><use href="#i-trash"/></svg><p>対応中の注文はありません</p></div>';
  document.getElementById('updated').textContent = '最終更新 ' + new Date().toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

document.addEventListener('click', async (e) => {
  const tab = e.target.closest('[data-filter]');
  if (tab) {
    filter = tab.dataset.filter;
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('is-active', t === tab));
    loadOrders();
    return;
  }

  const b = e.target.closest('button[data-status]');
  if (!b) return;
  if (b.dataset.status === 'cancelled' && !confirm('この注文をキャンセルしますか？（在庫は元に戻ります）')) return;
  b.disabled = true;
  const res = await callApi('PUT', '../api/orders.php?id=' + b.dataset.id, { status: b.dataset.status });
  if (!res.success) showError(document.getElementById('error'), res.error);
  loadOrders();
});

window.addEventListener('load', () => {
  loadOrders();
  setInterval(loadOrders, 30000);   // 30秒ごとに新しい注文を見る
});
</script>

<?php include '../includes/footer.php'; ?>
