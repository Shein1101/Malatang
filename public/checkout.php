<?php
// public/checkout.php — 注文情報 (名前・電話・受け取り時間). Owner: Zan (FEAT-13)
// 画面だけ。SQL なし。
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
      <label class="field-label" for="name">お名前</label>
      <input class="input" id="name" maxlength="100" required autocomplete="name">
    </div>
    <div class="field">
      <label class="field-label" for="phone">電話番号（任意）</label>
      <input class="input" id="phone" type="tel" placeholder="090-1234-5678" autocomplete="tel">
    </div>
    <div class="field">
      <label class="field-label" for="pickup">受け取り時間（今日）</label>
      <select class="input" id="pickup" required></select>
      <p class="caption" id="hours"></p>
    </div>
    <div class="field">
      <label class="field-label" for="note">備考（任意）</label>
      <textarea class="input" id="note" rows="2" maxlength="200" placeholder="例：お箸を2つください"></textarea>
    </div>
    <p class="caption">お支払いは次の画面でカード（テスト用）で行います。店頭でのお受け取りです（配達はありません）。</p>
    <button type="submit" id="submit" class="btn btn-chili btn-block">注文を確定して支払いへ <span class="arrow"></span></button>
  </form>
</main>

<script>
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
const errorBox = () => document.getElementById('error');

async function init() {
  // cart・受け取り時間・会員情報 を同時に読む
  const [cartRes, slotRes, meRes] = await Promise.all([
    callApi('GET', '../api/cart.php'),
    callApi('GET', '../api/orders.php?slots=1'),
    callApi('GET', '../api/auth.php?action=me'),
  ]);

  // 1. cart が空・買えない商品がある → カートへ戻す
  if (!cartRes.success || !cartRes.data.can_checkout) {
    location.href = 'cart.php';
    return;
  }
  const cart = cartRes.data;
  document.getElementById('summary').innerHTML = cart.bowls.map(b =>
    `<li><span>${esc(b.menu_name)}（${esc(b.soup_name)}）×${b.quantity}</span><span>${yen(b.price)}</span></li>`
  ).join('');
  document.getElementById('total').textContent = yen(cart.total);

  // 2. 受け取り時間
  const select = document.getElementById('pickup');
  if (slotRes.success) {
    const s = slotRes.data;
    document.getElementById('hours').textContent = '営業時間：' + s.opening_hours;
    if (!s.is_open || s.slots.length === 0) {
      select.innerHTML = '<option value="">今日の受付は終了しました</option>';
      document.getElementById('submit').disabled = true;
    } else {
      select.innerHTML = s.slots.map(t => `<option value="${t}">${t}</option>`).join('');
    }
  }

  // 3. login している人は名前・電話を入れておく
  if (meRes.success && meRes.data) {
    document.getElementById('name').value  = meRes.data.name  || '';
    document.getElementById('phone').value = meRes.data.phone || '';
  }
}

document.getElementById('orderForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const name = document.getElementById('name').value.trim();
  if (name === '') {
    showError(errorBox(), 'お名前を入力してください');
    return;
  }

  const btn = document.getElementById('submit');
  btn.disabled = true;   // 2回押し → 二重注文 を防ぐ
  const res = await callApi('POST', '../api/orders.php', {
    customer_name:  name,
    customer_phone: document.getElementById('phone').value.trim(),
    pickup_time:    document.getElementById('pickup').value,
    note:           document.getElementById('note').value.trim(),
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
