<?php
// public/cart.php — カート (bowl + ドリンク). Owner: Zan (FEAT-13)
// 画面だけ。SQL なし。cart は DB (cart_bowls / cart_drinks) → ../api/cart.php。
// ドリンクはこのカート画面で追加する。行き先: 「注文手続きへ」→ checkout.php
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

  <!-- ドリンクを追加 (ジュース) -->
  <section id="drinkAdd" class="cart-drinks d-none">
    <h2 class="heading mt-4">ドリンクを追加 🥤</h2>
    <div id="drinkMenu" class="juice-grid"></div>
  </section>

  <div id="cartFoot" class="stack d-none">
    <div class="total-row"><span>合計（<span id="count">0</span>）</span><span id="total" class="price-lg">¥0</span></div>
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
  const media = b.image_url
    ? `<img src="${esc(b.image_url)}" alt="" onerror="this.parentElement.classList.add('no-img');this.remove()">`
    : '';
  return `
    <div class="card card-body stack-sm">
      <div class="cart-item">
        <div class="cart-thumb${b.image_url ? '' : ' no-img'}">${media}<span>🍜</span></div>
        <div class="cart-item-main">
          <div class="row cz-between">
            <b>${esc(b.menu_name)}</b>
            <button class="icon-btn" data-delete="${b.id}" aria-label="削除"><svg><use href="#i-trash"/></svg></button>
          </div>
          <div class="caption">${bowlText(b)}</div>
        </div>
      </div>
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

// カートに入っているドリンク (1行)
function drinkRow(d) {
  const media = d.image_url
    ? `<img src="${esc(d.image_url)}" alt="" onerror="this.parentElement.classList.add('no-img');this.remove()">`
    : '';
  return `
    <div class="card card-body stack-sm">
      <div class="cart-item">
        <div class="cart-thumb${d.image_url ? '' : ' no-img'}">${media}<span>🥤</span></div>
        <div class="cart-item-main">
          <div class="row cz-between">
            <b>${esc(d.name)}</b>
            <button class="icon-btn" data-ddelete="${d.id}" aria-label="削除"><svg><use href="#i-trash"/></svg></button>
          </div>
        </div>
      </div>
      ${d.error ? `<p class="field-error">${esc(d.error)}</p>` : ''}
      <div class="row cz-between">
        <div class="stepper">
          <button type="button" data-dqty="${d.id}" data-to="${d.quantity - 1}" ${d.quantity <= 1 ? 'disabled' : ''} aria-label="減らす">−</button>
          <output>${d.quantity}</output>
          <button type="button" data-dqty="${d.id}" data-to="${d.quantity + 1}" ${d.quantity >= 20 ? 'disabled' : ''} aria-label="増やす">+</button>
        </div>
        <span class="price">${yen(d.line_total)}</span>
      </div>
    </div>`;
}

// 追加できるドリンク (メニュー) のカード
function drinkMenuCard(j) {
  const media = j.image_url
    ? `<img src="${esc(j.image_url)}" alt="${esc(j.name)}" onerror="this.parentElement.classList.add('no-img');this.remove()">`
    : '';
  return `
    <div class="juice-card">
      <div class="juice-media${j.image_url ? '' : ' no-img'}">${media}<span class="juice-emoji">🥤</span></div>
      <div class="juice-body">
        <div class="juice-name">${esc(j.name)}</div>
        ${j.description ? `<div class="caption">${esc(j.description)}</div>` : ''}
        <div class="row cz-between">
          <span class="price">${yen(j.price)}</span>
          <button type="button" class="btn btn-chili btn-sm" data-dadd="${j.id}">＋ 追加</button>
        </div>
      </div>
    </div>`;
}

async function loadCart() {
  const [res, juiceRes] = await Promise.all([
    callApi('GET', '../api/cart.php'),
    callApi('GET', '../api/juices.php'),
  ]);
  const list = document.getElementById('cartList');
  if (!res.success) {
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  const cart = res.data;

  // 追加できるドリンク (販売中のもの)
  const juices = juiceRes.success ? juiceRes.data : [];
  if (juices.length) {
    document.getElementById('drinkMenu').innerHTML = juices.map(drinkMenuCard).join('');
    document.getElementById('drinkAdd').classList.remove('d-none');
  }

  // カートが空 (bowl も drink もなし)
  if (cart.bowls.length === 0 && cart.drinks.length === 0) {
    list.innerHTML = `
      <div class="empty card">
        <svg><use href="#i-bowl"/></svg>
        <p>カートは空です</p>
        <a class="btn btn-outline" href="menu.php">メニューを見る</a>
      </div>`;
    document.getElementById('cartFoot').classList.add('d-none');
    refreshCartCount();
    return;
  }

  list.innerHTML = cart.bowls.map(bowlCard).join('') + cart.drinks.map(drinkRow).join('');
  const countTxt = `${cart.count}杯` + (cart.drink_count ? ` + ドリンク${cart.drink_count}点` : '');
  document.getElementById('count').textContent = countTxt;
  document.getElementById('total').textContent = yen(cart.total);
  document.getElementById('blocked').classList.toggle('d-none', cart.can_checkout);
  document.getElementById('toCheckout').classList.toggle('d-none', !cart.can_checkout);
  document.getElementById('cartFoot').classList.remove('d-none');
  refreshCartCount();
}

// bowl / drink の − / + ・削除・ドリンク追加
document.addEventListener('click', async (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  let res = null;
  if (b.dataset.qty) {
    res = await callApi('PUT', '../api/cart.php?id=' + b.dataset.qty, { quantity: Number(b.dataset.to) });
  } else if (b.dataset.delete) {
    if (!confirm('この商品をカートから削除しますか？')) return;
    res = await callApi('DELETE', '../api/cart.php?id=' + b.dataset.delete);
  } else if (b.dataset.dadd) {
    res = await callApi('POST', '../api/cart.php?type=drink', { juice_id: Number(b.dataset.dadd), quantity: 1 });
  } else if (b.dataset.dqty) {
    res = await callApi('PUT', '../api/cart.php?type=drink&id=' + b.dataset.dqty, { quantity: Number(b.dataset.to) });
  } else if (b.dataset.ddelete) {
    res = await callApi('DELETE', '../api/cart.php?type=drink&id=' + b.dataset.ddelete);
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
