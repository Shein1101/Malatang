<?php
// public/checkout.php — 注文情報 (名前・電話・住所・アレルギー・備考). Owner: Zan (FEAT-13)
// 画面だけ。SQL なし。受け取り時間は使いません。
// 「注文を確定する」→ POST ../api/orders.php → payment.php?order_id=…
$pageTitle = '注文情報 | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page narrow">
  <ol class="steps">
    <li class="is-done">カート</li>
    <li class="is-current">注文情報</li>
    <li>支払い</li>
    <li>完了</li>
  </ol>

  <h1 class="heading">注文情報</h1>
  <p id="error" class="field-error d-none"></p>

  <!-- 注文の中身 (短く) -->
  <section class="card card-body stack-sm">
    <ul id="summary" class="list"><li class="muted">読み込み中…</li></ul>
    <div class="total-row"><span>合計</span><span id="total" class="price-lg">¥0</span></div>
    <a class="caption" href="cart.php">カートを変更する</a>
  </section>

  <form id="orderForm" class="stack" novalidate>
    <div class="field">
      <span class="field-label">受け取り方法 <span class="req">必須</span></span>
      <div class="seg" role="radiogroup" aria-label="受け取り方法">
        <label class="seg-item"><input type="radio" name="otype" value="takeout" checked> 🏪 店頭受け取り</label>
        <label class="seg-item"><input type="radio" name="otype" value="delivery"> 🛵 配達</label>
      </div>
      <p class="caption" id="otypeHint">店頭でのお受け取りです（住所は任意）。</p>
    </div>

    <div class="field">
      <label class="field-label" for="name">お名前 <span class="req">必須</span></label>
      <input class="input" id="name" maxlength="100" required autocomplete="name">
    </div>
    <div class="field">
      <label class="field-label" for="phone">電話番号 <span class="req">必須</span></label>
      <input class="input" id="phone" type="tel" placeholder="090-1234-5678" autocomplete="tel" required>
      <p class="caption">ご注文の確認につかいます。</p>
    </div>

    <!-- ご住所・ご連絡先 (会員はマイページの内容が入ります。ゲストはここで入力) -->
    <div class="grid-2">
      <div class="field">
        <label class="field-label" for="postal">郵便番号</label>
        <input class="input" id="postal" maxlength="10" placeholder="123-4567" autocomplete="postal-code">
      </div>
      <div class="field">
        <label class="field-label" for="address">ご住所 <span class="req d-none" id="addrReq">必須</span></label>
        <input class="input" id="address" maxlength="255" placeholder="東京都…" autocomplete="street-address">
      </div>
    </div>

    <div class="field">
      <label class="field-label" for="allergy">アレルギー（ある場合）</label>
      <input class="input" id="allergy" maxlength="255" placeholder="例：えび・かに・卵">
    </div>
    <div class="field">
      <label class="field-label" for="note">特別なご要望（任意）</label>
      <textarea class="input" id="note" rows="2" maxlength="200" placeholder="例：お箸を2つください／辛さ控えめで"></textarea>
    </div>

    <div class="field">
      <span class="field-label">お支払い方法 <span class="req">必須</span></span>
      <div class="seg seg-pay" role="radiogroup" aria-label="お支払い方法">
        <label class="seg-item"><input type="radio" name="pay" value="card" checked> カード</label>
        <label class="seg-item"><input type="radio" name="pay" value="paypay"> PayPay</label>
        <label class="seg-item"><input type="radio" name="pay" value="cod"> 代金引換</label>
      </div>
      <p class="caption" id="payHint">次の画面でカード（テスト用）でお支払いします。</p>
    </div>

    <button type="submit" id="submit" class="btn btn-chili btn-block"><span id="submitLabel">注文を確定して支払いへ</span> <span class="arrow"></span></button>
  </form>
</main>

<script>
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
const errorBox = () => document.getElementById('error');
let storeClosed = false;

// 配達をえらんでいて 住所が空なら、支払いへ進めない (ボタンを押せなくする)。
function orderType() {
  const sel = document.querySelector('input[name="otype"]:checked');
  return sel ? sel.value : 'takeout';
}
function refreshSubmit() {
  const addr = document.getElementById('address').value.trim();
  const needAddr = orderType() === 'delivery' && addr === '';
  const btn = document.getElementById('submit');
  btn.disabled = storeClosed || needAddr;
  btn.title = needAddr ? '配達のご住所を入力してください' : '';
}

async function init() {
  // cart・会員情報・お店の状態 を同時に読む
  const [cartRes, meRes, storeRes] = await Promise.all([
    callApi('GET', '../api/cart.php'),
    callApi('GET', '../api/auth.php?action=me'),
    callApi('GET', '../api/store.php'),
  ]);

  // 1. cart が空・買えない商品がある → カートへ戻す
  if (!cartRes.success || !cartRes.data.can_checkout) {
    location.href = 'cart.php';
    return;
  }
  const cart = cartRes.data;
  const bowlLines  = cart.bowls.map(b =>
    `<li><span>${esc(b.menu_name)}（${esc(b.soup_name)}）×${b.quantity}</span><span>${yen(b.price)}</span></li>`).join('');
  const drinkLines = cart.drinks.map(d =>
    `<li><span>🥤 ${esc(d.name)} ×${d.quantity}</span><span>${yen(d.line_total)}</span></li>`).join('');
  document.getElementById('summary').innerHTML = bowlLines + drinkLines;
  document.getElementById('total').textContent = yen(cart.total);

  // 2. お店が閉まっていたら注文できない
  if (storeRes.success && storeRes.data.store && storeRes.data.store.is_open === false) {
    showError(errorBox(), 'ただいま注文を受け付けていません（営業時間外）。');
    storeClosed = true;
  }

  // 3. login している人は名前・電話・住所を入れておく
  if (meRes.success && meRes.data) {
    document.getElementById('name').value    = meRes.data.name    || '';
    document.getElementById('phone').value   = meRes.data.phone   || '';
    document.getElementById('postal').value  = meRes.data.postal_code || '';
    document.getElementById('address').value = meRes.data.address || '';
  }
  refreshSubmit();   // 配達×住所なし のときは最初からボタンを押せない
}

// お支払い方法: 案内文とボタンの文字を変える (代金引換は支払い画面なし)
function payMethod() {
  const sel = document.querySelector('input[name="pay"]:checked');
  return sel ? sel.value : 'card';
}
const PAY_HINT = {
  card:   '次の画面でカード（テスト用）でお支払いします。',
  paypay: '次の画面で PayPay（テスト用）でお支払いします。',
  cod:    'お受け取り／お届けのときに、現金でお支払いください。',
};
function refreshPay() {
  const m = payMethod();
  document.getElementById('payHint').textContent = PAY_HINT[m];
  document.getElementById('submitLabel').textContent = m === 'cod' ? '注文を確定する' : '注文を確定して支払いへ';
}
document.querySelectorAll('input[name="pay"]').forEach(r => r.addEventListener('change', refreshPay));

// 受け取り方法: 配達のときだけ住所を必須にする
document.querySelectorAll('input[name="otype"]').forEach(r => r.addEventListener('change', () => {
  const delivery = orderType() === 'delivery';
  document.getElementById('addrReq').classList.toggle('d-none', !delivery);
  document.getElementById('otypeHint').textContent = delivery
    ? '入力されたご住所にお届けします（住所は必須）。'
    : '店頭でのお受け取りです（住所は任意）。';
  refreshSubmit();
}));
// 住所を打つたびに ボタンの ON/OFF を見直す
document.getElementById('address').addEventListener('input', refreshSubmit);

document.getElementById('orderForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const name  = document.getElementById('name').value.trim();
  const phone = document.getElementById('phone').value.trim();
  const address = document.getElementById('address').value.trim();
  if (name === '') {
    showError(errorBox(), 'お名前を入力してください');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  if (phone === '') {
    showError(errorBox(), '電話番号を入力してください');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  if (orderType() === 'delivery' && address === '') {
    showError(errorBox(), '配達をえらんだ場合は、ご住所を入力してください');
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }

  const btn = document.getElementById('submit');
  btn.disabled = true;   // 2回押し → 二重注文 を防ぐ
  const res = await callApi('POST', '../api/orders.php', {
    order_type:       orderType(),
    payment_method:   payMethod(),
    customer_name:    name,
    customer_phone:   phone,
    customer_postal:  document.getElementById('postal').value.trim(),
    customer_address: address,
    allergy_note:     document.getElementById('allergy').value.trim(),
    note:             document.getElementById('note').value.trim(),
  });

  if (!res.success) {
    btn.disabled = false;
    showError(errorBox(), res.error);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  location.href = 'payment.php?order_id=' + res.data.order_id;
});

window.addEventListener('load', init);
</script>

<?php include '../includes/footer.php'; ?>
