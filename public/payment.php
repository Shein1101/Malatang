<?php
// public/payment.php — 支払い (モック) と 注文完了. Owner: Zan (FEAT-15)
// 画面だけ。SQL なし。
// 来かた: checkout.php → payment.php?order_id=12
// カード番号はこの画面でチェックして、server には下4けただけ送る (全部は送らない・保存しない)。
$pageTitle = 'お支払い | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page narrow">
  <ol class="steps" id="steps">
    <li class="is-done">カート</li>
    <li class="is-done">注文情報</li>
    <li class="is-current">支払い</li>
    <li>完了</li>
  </ol>

  <p id="error" class="field-error d-none"></p>
  <p id="loading" class="muted">読み込み中…</p>

  <!-- 支払い前 -->
  <section id="payBox" class="stack d-none">
    <h1 class="heading">お支払い</h1>
    <div class="card card-body stack-sm">
      <div class="row cz-between"><span>注文番号</span><b id="orderNumber"></b></div>
      <div class="total-row"><span>お支払い金額</span><span id="amount" class="price-lg"></span></div>
    </div>

    <form id="cardForm" class="stack" novalidate>
      <div class="field">
        <label class="field-label" for="cardNumber">カード番号</label>
        <input class="input" id="cardNumber" inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242">
      </div>
      <div class="grid-2">
        <div class="field">
          <label class="field-label" for="expiry">有効期限（MM/YY）</label>
          <input class="input" id="expiry" inputmode="numeric" autocomplete="cc-exp" placeholder="12/28">
        </div>
        <div class="field">
          <label class="field-label" for="cvc">セキュリティコード</label>
          <input class="input" id="cvc" inputmode="numeric" autocomplete="cc-csc" placeholder="123">
        </div>
      </div>
      <p class="caption">※ テスト用の支払いです。本当のお金は動きません。<br>
        成功：4242 4242 4242 4242 ／ 失敗のテスト：4000 0000 0000 0002</p>
      <button type="submit" id="payBtn" class="btn btn-chili btn-block">支払う <span class="arrow"></span></button>
    </form>
  </section>

  <!-- PayPay (モック) -->
  <section id="paypayBox" class="stack d-none">
    <h1 class="heading">PayPay でお支払い</h1>
    <div class="card card-body stack-sm">
      <div class="row cz-between"><span>注文番号</span><b id="ppNumber"></b></div>
      <div class="total-row"><span>お支払い金額</span><span id="ppAmount" class="price-lg"></span></div>
    </div>
    <div class="paypay-mock">
      <div class="paypay-qr" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="40" height="40"><path fill="currentColor" d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm13-2h3v2h-3v-2zm0 4h3v4h-5v-2h2v-2zm-5 0h3v2h-3v-2z"/></svg>
        <span>PayPay</span>
      </div>
      <p class="caption">※ テスト用です。本当のお金は動きません。ボタンを押すと支払い完了になります。</p>
    </div>
    <button type="button" id="paypayBtn" class="btn btn-chili btn-block">PayPay で支払う <span class="arrow"></span></button>
  </section>

  <!-- 支払い後 -->
  <section id="doneBox" class="empty card d-none">
    <svg><use href="#i-bowl"/></svg>
    <h1 class="heading">ご注文ありがとうございます</h1>
    <p>注文番号 <b id="doneNumber"></b></p>
    <p id="doneMethod">店頭でお受け取りください。準備ができましたら、お店からご連絡します。</p>
    <div class="done-actions">
      <button class="btn btn-share d-none" id="shareOrderBtn" type="button">友達にシェア</button>
      <a class="btn btn-ghost d-none" id="toHistory" href="history.php">注文履歴を見る</a>
      <a class="btn btn-outline" href="menu.php">メニューに戻る</a>
    </div>
  </section>
</main>

<script>
const orderId = new URLSearchParams(location.search).get('order_id');
let order = null;

function showDone() {
  document.getElementById('payBox').classList.add('d-none');
  document.getElementById('paypayBox').classList.add('d-none');
  document.getElementById('doneNumber').textContent = order.order_number;
  let msg = order.order_type === 'delivery'
    ? 'ご入力の住所にお届けします。準備ができましたら、お店からご連絡します。'
    : '店頭でお受け取りください。準備ができましたら、お店からご連絡します。';
  // 代金引換でまだ払っていないとき: 受け取り時の支払いを案内
  if (order.payment_method === 'cod' && order.payment_status !== 'paid') {
    msg += order.order_type === 'delivery'
      ? ' お支払いは、お届け時に現金でお願いします。'
      : ' お支払いは、お受け取り時に現金でお願いします。';
  }
  document.getElementById('doneMethod').textContent = msg;
  document.getElementById('doneBox').classList.remove('d-none');
  const steps = document.querySelectorAll('#steps li');
  steps[2].className = 'is-done';
  steps[3].className = 'is-current';
  refreshCartCount();   // 支払い後はカートが空になるので ヘッダーの数字も 0 に
}

async function init() {
  if (!orderId) { location.href = 'cart.php'; return; }
  const [res, me] = await Promise.all([
    callApi('GET', '../api/orders.php?id=' + encodeURIComponent(orderId)),
    callApi('GET', '../api/auth.php?action=me'),
  ]);
  document.getElementById('loading').classList.add('d-none');
  if (!res.success) {
    showError(document.getElementById('error'), res.error);
    return;
  }
  order = res.data;
  // ログインしている人には「注文履歴」と「友達にシェア」を出す
  if (me.success && me.data) {
    document.getElementById('toHistory').classList.remove('d-none');
    const sBtn = document.getElementById('shareOrderBtn');
    if (order.bowls && order.bowls.length && order.bowls[0].order_bowl_id) {
      sBtn.classList.remove('d-none');
      sBtn.addEventListener('click', () =>
        openShareDialog({ order_bowl_id: order.bowls[0].order_bowl_id }, order.bowls[0].menu_name));
    }
  }

  if (order.status === 'cancelled') {
    showError(document.getElementById('error'), 'この注文はキャンセルされました');
    return;
  }
  if (order.payment_status === 'paid') { showDone(); return; }

  // 支払い方法ごとに画面を出し分ける
  if (order.payment_method === 'cod') {
    showDone();   // 代金引換: オンライン決済なし → すぐ「注文できた」画面
    return;
  }
  if (order.payment_method === 'paypay') {
    document.getElementById('ppNumber').textContent = order.order_number;
    document.getElementById('ppAmount').textContent = yen(order.total_price);
    document.getElementById('paypayBox').classList.remove('d-none');
    return;
  }
  // カード (mock_card)
  document.getElementById('orderNumber').textContent = order.order_number;
  document.getElementById('amount').textContent = yen(order.total_price);
  document.getElementById('payBox').classList.remove('d-none');
}

// PayPay (モック): 押すだけで成功
document.getElementById('paypayBtn').addEventListener('click', async (e) => {
  e.target.disabled = true;
  const res = await callApi('POST', '../api/payments.php', { order_id: order.id });
  e.target.disabled = false;
  if (!res.success) { showError(document.getElementById('error'), res.error); return; }
  document.getElementById('error').classList.add('d-none');
  showDone();
});

// カード番号のチェック (Luhn = カード番号の最後の数字が正しいかの計算)
function luhnOk(num) {
  let sum = 0;
  for (let i = 0; i < num.length; i++) {
    let d = Number(num[num.length - 1 - i]);
    if (i % 2 === 1) { d *= 2; if (d > 9) d -= 9; }
    sum += d;
  }
  return sum % 10 === 0;
}

function checkCard() {
  const num = document.getElementById('cardNumber').value.replace(/\D/g, '');
  const exp = document.getElementById('expiry').value.trim();
  const cvc = document.getElementById('cvc').value.trim();
  if (num.length < 14 || num.length > 19 || !luhnOk(num)) return 'カード番号を確認してください';
  const m = exp.match(/^(\d{2})\/(\d{2})$/);
  if (!m || Number(m[1]) < 1 || Number(m[1]) > 12) return '有効期限は MM/YY で入力してください';
  const endOfMonth = new Date(2000 + Number(m[2]), Number(m[1]), 1);   // 翌月1日
  if (endOfMonth <= new Date()) return '有効期限が切れています';
  if (!/^\d{3,4}$/.test(cvc)) return 'セキュリティコードを確認してください';
  return null;
}

document.getElementById('cardForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const problem = checkCard();
  if (problem) { showError(document.getElementById('error'), problem); return; }

  const num = document.getElementById('cardNumber').value.replace(/\D/g, '');
  const btn = document.getElementById('payBtn');
  btn.disabled = true;
  const res = await callApi('POST', '../api/payments.php', { order_id: order.id, card_last4: num.slice(-4) });
  btn.disabled = false;

  // 入力したカード情報はすぐ消す
  ['cardNumber', 'expiry', 'cvc'].forEach(id => document.getElementById(id).value = '');

  if (!res.success) { showError(document.getElementById('error'), res.error); return; }
  document.getElementById('error').classList.add('d-none');
  showDone();
});

window.addEventListener('load', init);
</script>

<script src="<?= asset_url('assets/js/share.js') ?>"></script>
<?php include '../includes/footer.php'; ?>
