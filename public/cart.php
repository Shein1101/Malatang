<?php
// public/cart.php — カート. Owner: Zan (FEAT-13)
// 画面だけ。SQL なし。cart は DB (cart_bowls) にある → ../api/cart.php で読む・変える。
// 行き先: 「注文手続きへ」→ checkout.php
$pageTitle = 'カート | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page narrow">
  <ol class="steps">
    <li class="is-current">カート</li>
    <li>注文情報</li>
    <li>支払い</li>
    <li>完了</li>
  </ol>

  <h1 class="heading">カート</h1>
  <p id="error" class="field-error d-none"></p>

  <div id="cartList" class="stack"><p class="muted">読み込み中…</p></div>

  <div id="cartFoot" class="stack d-none">
    <div class="total-row"><span>合計（<span id="count">0</span>杯）</span><span id="total" class="price-lg">¥0</span></div>
    <p id="blocked" class="field-error d-none">売り切れなどで注文できない商品があります。削除してから進んでください。</p>
    <a id="toCheckout" class="btn btn-chili btn-block" href="checkout.php">注文手続きへ <span class="arrow"></span></a>
    <a class="btn btn-outline btn-block" href="menu.php">メニューに戻る</a>
  </div>
</main>

<script>
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

// bowl の中身を1行の文にする: 味・スープ・辛さ / ＋追加 / −抜き
function bowlText(b) {
  const added   = b.ingredients.filter(i => i.action === 'added').map(i => `${esc(i.name)}×${i.quantity}`);
  const removed = b.ingredients.filter(i => i.action === 'removed').map(i => esc(i.name));
  let t = `${esc(b.soup_name)}・${b.has_soup ? 'スープあり' : 'スープなし'}${b.spice_name ? '・' + esc(b.spice_name) : ''}`;
  if (added.length)   t += `<br>＋ ${added.join('、')}`;
  if (removed.length) t += `<br>− ${removed.join('、')} 抜き`;
  return t;
}

function bowlCard(b) {
  return `
    <div class="card card-body stack-sm">
      <div class="row cz-between">
        <b>${esc(b.menu_name)}</b>
        <button class="icon-btn" data-delete="${b.id}" aria-label="削除"><svg><use href="#i-trash"/></svg></button>
      </div>
      <div class="caption">${bowlText(b)}</div>
      ${b.error ? `<p class="field-error">${esc(b.error)}</p>` : ''}
      <div class="row cz-between">
        <div class="stepper">
          <button type="button" data-qty="${b.id}" data-to="${b.quantity - 1}" ${b.quantity <= 1 ? 'disabled' : ''} aria-label="減らす">−</button>
          <output>${b.quantity}</output>
          <button type="button" data-qty="${b.id}" data-to="${b.quantity + 1}" ${b.quantity >= 20 ? 'disabled' : ''} aria-label="増やす">+</button>
        </div>
        <span class="price">${b.price === null ? '—' : yen(b.price)}</span>
      </div>
    </div>`;
}

async function loadCart() {
  const res = await callApi('GET', '../api/cart.php');
  const list = document.getElementById('cartList');
  if (!res.success) {
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  const cart = res.data;

  if (cart.bowls.length === 0) {
    list.innerHTML = `
      <div class="empty card">
        <svg><use href="#i-bowl"/></svg>
        <p>カートは空です</p>
        <a class="btn btn-outline" href="menu.php">メニューを見る</a>
      </div>`;
    document.getElementById('cartFoot').classList.add('d-none');
    return;
  }

  list.innerHTML = cart.bowls.map(bowlCard).join('');
  document.getElementById('count').textContent = cart.count;
  document.getElementById('total').textContent = yen(cart.total);
  document.getElementById('blocked').classList.toggle('d-none', cart.can_checkout);
  document.getElementById('toCheckout').classList.toggle('d-none', !cart.can_checkout);
  document.getElementById('cartFoot').classList.remove('d-none');
  refreshCartCount();
}

// 杯数の − / + と 削除
document.addEventListener('click', async (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  let res = null;
  if (b.dataset.qty) {
    res = await callApi('PUT', '../api/cart.php?id=' + b.dataset.qty, { quantity: Number(b.dataset.to) });
  } else if (b.dataset.delete) {
    if (!confirm('この商品をカートから削除しますか？')) return;
    res = await callApi('DELETE', '../api/cart.php?id=' + b.dataset.delete);
  } else {
    return;
  }
  if (!res.success) {
    showError(document.getElementById('error'), res.error);
  }
  loadCart();
});

window.addEventListener('load', loadCart);
</script>

<?php include '../includes/footer.php'; ?>
