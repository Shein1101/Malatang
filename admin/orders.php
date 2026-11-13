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

  <!-- カテゴリ (6つ) -->
  <div class="tabs" id="catTabs" role="tablist"></div>
  <!-- そのカテゴリの中でさらにしぼる (テイクアウト・配達・要対応 など) -->
  <div class="tabs adm-sub" id="subTabs"></div>

  <p id="error" class="field-error d-none"></p>
  <div id="orderList" class="grid-2"><p class="muted">読み込み中…</p></div>
</main>

<script>
// ステータスの日本語・バッジの色・ボタンの文字 (button = そのステータスに「進める」ボタンの文字)
// お店の操作は2つだけ: 出来上がり → 受け渡し完了。
const STATUS = {
  pending:   { label: '調理待ち',     badge: 'badge-label' },
  confirmed: { label: '受付済',       badge: 'badge-received' },  // 旧データ
  preparing: { label: '調理中',       badge: 'badge-cooking' },   // 旧データ
  ready:     { label: '出来上がり',   badge: 'badge-cooking',  button: '出来上がり' },
  completed: { label: '受け渡し完了', badge: 'badge-done',     button: '受け渡し完了' },
  cancelled: { label: 'キャンセル',   badge: 'badge-soldout' },
};
const PAY_METHOD = { mock_card: 'カード', paypay: 'PayPay', cod: '代金引換' };
const FIN = ['completed', 'cancelled'];   // 「対応が終わった」注文

// 6つのカテゴリ。match = その注文がこのカテゴリに入るか
const CATS = [
  { key: 'awaiting',  label: '要対応',      match: o => o.status === 'pending' },
  { key: 'takeaway',  label: 'テイクアウト', match: o => o.order_type === 'takeout'  && !FIN.includes(o.status) },
  { key: 'delivery',  label: '配達',        match: o => o.order_type === 'delivery' && !FIN.includes(o.status) },
  { key: 'paid',      label: '支払い済み',   match: o => o.payment_status === 'paid' },
  { key: 'cancelled', label: 'キャンセル',   match: o => o.status === 'cancelled' },
  { key: 'finished',  label: '完了',        match: o => o.status === 'completed' },
];

// カテゴリごとの「さらにしぼる」ボタン (なければ出さない)
const SUBS = {
  awaiting: [
    { key: 'all',      label: 'すべて',          match: () => true },
    { key: 'takeout',  label: 'テイクアウト',     match: o => o.order_type === 'takeout' },
    { key: 'delivery', label: '配達',            match: o => o.order_type === 'delivery' },
    { key: 'codunpaid',label: '代金引換・未回収', match: o => o.payment_method === 'cod' && o.payment_status !== 'paid' },
  ],
  takeaway: [
    { key: 'all',    label: 'すべて',              match: () => true },
    { key: 'cooking',label: '調理待ち',            match: o => o.status === 'pending' },
    { key: 'pickup', label: 'お客さまの受け取り待ち', match: o => o.status === 'ready' },
  ],
  delivery: [
    { key: 'all',     label: 'すべて',          match: () => true },
    { key: 'cooking', label: '調理待ち',        match: o => o.status === 'pending' },
    { key: 'courier', label: '配達員の集荷待ち', match: o => o.status === 'ready' },
  ],
  paid: [
    { key: 'all',      label: 'すべて',      match: () => true },
    { key: 'takeout',  label: 'テイクアウト', match: o => o.order_type === 'takeout' },
    { key: 'delivery', label: '配達',        match: o => o.order_type === 'delivery' },
  ],
};

let allOrders = [];
let cat = 'awaiting';
let sub = 'all';

// 新しい注文の音 + タブのタイトルの数 [Myat]
//   seenIds = 一度でも見た注文の id。null = まだ一度も読んでいない (最初は鳴らさない)
//   タイトル = 受付待ちの数。音は「受付待ちの新しい注文」が来たときだけ
let seenIds = null;
const baseTitle = document.title;

// 音を作る (ファイルなし。WebAudio で「ピッ」)。ブラウザの決まりで、
// お店が1回どこかをクリックするまで音は出ないことがある (クリックで直る)
let audioCtx = null;
function playBeep() {
  try {
    audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
    if (audioCtx.state === 'suspended') audioCtx.resume();
    const t = audioCtx.currentTime;
    const osc = audioCtx.createOscillator();
    const gain = audioCtx.createGain();
    osc.type = 'sine';
    osc.frequency.value = 880;
    osc.connect(gain);
    gain.connect(audioCtx.destination);
    gain.gain.setValueAtTime(0.001, t);
    gain.gain.exponentialRampToValueAtTime(0.3, t + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.001, t + 0.45);
    osc.start(t);
    osc.stop(t + 0.45);
  } catch (e) {
    // 音が出せなくても注文の処理は続ける
  }
}
// 最初のクリックで音を使えるようにする (ブラウザの決まり対策)
document.addEventListener('click', () => {
  if (audioCtx && audioCtx.state === 'suspended') audioCtx.resume();
});

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
  // 進めるボタン (出来上がり / 受け渡し完了)。キャンセルは目立たない ghost ボタン。
  const buttons = o.next_status.map(n => n === 'cancelled'
    ? `<button class="btn btn-ghost btn-sm" data-id="${o.id}" data-status="cancelled">キャンセル</button>`
    : `<button class="btn btn-chili btn-sm" data-id="${o.id}" data-status="${n}">${STATUS[n].button}</button>`
  ).join('');
  // 完了・キャンセルの注文は 記録から削除できる
  const delBtn = (o.status === 'completed' || o.status === 'cancelled')
    ? `<button class="btn btn-ghost btn-sm text-danger" data-del="${o.id}">削除</button>` : '';
  const payLabel = PAY_METHOD[o.payment_method] || 'カード';

  return `
    <div class="card order-card">
      <div class="order-top">
        <div>
          <b>注文 ${hhmm(o.created_at)}</b>
          <div class="caption">#${esc(o.order_number)}・${esc(o.customer_name)}${o.is_guest ? '（ゲスト）' : ''}${o.customer_phone ? '・' + esc(o.customer_phone) : ''}・${o.order_type === 'delivery' ? '🛵 配達' : '🏪 店頭'}</div>
        </div>
        <span class="badge ${s.badge}">${s.label}</span>
      </div>
      <ul class="list">${bowlLines(o)}</ul>
      ${o.drinks && o.drinks.length ? `<ul class="list">${o.drinks.map(d => `<li class="caption">🥤 ${esc(d.name)} ×${d.quantity}</li>`).join('')}</ul>` : ''}
      ${o.allergy_note ? `<p class="caption text-chili"><b>⚠ アレルギー：${esc(o.allergy_note)}</b></p>` : ''}
      ${o.note ? `<p class="caption">備考：${esc(o.note)}</p>` : ''}
      ${o.customer_address ? `<p class="caption">住所：${o.customer_postal ? esc(o.customer_postal) + ' ' : ''}${esc(o.customer_address)}</p>` : ''}
      <div class="order-foot">
        <span>合計 <span class="price">${yen(o.total_price)}</span>
          <span class="badge badge-label">${esc(payLabel)}</span>
          <span class="badge ${o.payment_status === 'paid' ? 'badge-done' : 'badge-soldout'}">${o.payment_status === 'paid' ? '支払済' : '未払い'}</span></span>
        <div class="row">${buttons}${delBtn}</div>
      </div>
    </div>`;
}

// 今のカテゴリ・しぼりこみに合う注文だけ返す
function currentList() {
  const c = CATS.find(c => c.key === cat);
  const subs = SUBS[cat] || [];
  const sm = (subs.find(s => s.key === sub) || subs[0] || { match: () => true }).match;
  let rows = allOrders.filter(c.match).filter(sm);
  // 対応中の仕事は「古い順」(先に来た注文から)。終わったものは「新しい順」。
  const workish = ['awaiting', 'takeaway', 'delivery'].includes(cat);
  rows.sort((a, b) => workish
    ? (a.created_at < b.created_at ? -1 : 1)
    : (a.created_at > b.created_at ? -1 : 1));
  return rows;
}

// カテゴリのタブ (件数つき)
function renderCatTabs() {
  document.getElementById('catTabs').innerHTML = CATS.map(c => {
    const n = allOrders.filter(c.match).length;
    return `<button type="button" class="tab ${c.key === cat ? 'is-active' : ''}" data-cat="${c.key}">${c.label}<span class="tab-count">${n}</span></button>`;
  }).join('');
}

// 「さらにしぼる」タブ (件数つき。無いカテゴリでは隠す)
function renderSubTabs() {
  const subs = SUBS[cat];
  const box = document.getElementById('subTabs');
  if (!subs) { box.innerHTML = ''; box.classList.add('d-none'); return; }
  const base = allOrders.filter(CATS.find(c => c.key === cat).match);
  box.classList.remove('d-none');
  box.innerHTML = subs.map(s =>
    `<button type="button" class="tab ${s.key === sub ? 'is-active' : ''}" data-sub="${s.key}">${s.label}<span class="tab-count">${base.filter(s.match).length}</span></button>`
  ).join('');
}

function renderList() {
  const rows = currentList();
  const list = document.getElementById('orderList');
  list.innerHTML = rows.length
    ? rows.map(orderCard).join('')
    : '<div class="empty card"><svg><use href="#i-trash"/></svg><p>この分類の注文はありません</p></div>';
}

function renderAll() {
  renderCatTabs();
  renderSubTabs();
  renderList();
}

async function loadOrders() {
  const res = await callApi('GET', '../api/orders.php?status=all');
  if (!res.success) {
    showError(document.getElementById('error'), res.error);
    return;
  }
  document.getElementById('error').classList.add('d-none');
  allOrders = res.data;
  renderAll();
  document.getElementById('updated').textContent = '最終更新 ' + new Date().toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

  // 受付待ちの新しい注文が来たら音を鳴らす (最初に読んだときは鳴らさない)
  const pending = res.data.filter(o => o.status === 'pending');
  if (seenIds !== null && pending.some(o => !seenIds.has(o.id))) {
    playBeep();
  }
  if (seenIds === null) seenIds = new Set();
  for (const o of res.data) seenIds.add(o.id);

  // タブのタイトルに 受付待ち の数を出す (ほかのタブを見ていても気づけるように)
  document.title = pending.length ? `(${pending.length}) ${baseTitle}` : baseTitle;
}

// カテゴリを切り替え
document.getElementById('catTabs').addEventListener('click', (e) => {
  const t = e.target.closest('[data-cat]');
  if (!t) return;
  cat = t.dataset.cat;
  sub = 'all';   // カテゴリを変えたら しぼりこみは最初から
  renderAll();
});
// しぼりこみを切り替え
document.getElementById('subTabs').addEventListener('click', (e) => {
  const t = e.target.closest('[data-sub]');
  if (!t) return;
  sub = t.dataset.sub;
  renderAll();
});

// ステータスを進める / キャンセル / 削除
document.getElementById('orderList').addEventListener('click', async (e) => {
  const del = e.target.closest('button[data-del]');
  if (del) {
    if (!confirm('この注文を記録から削除しますか？（元に戻せません）')) return;
    del.disabled = true;
    const res = await callApi('DELETE', '../api/orders.php?id=' + del.dataset.del);
    if (!res.success) showError(document.getElementById('error'), res.error);
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
