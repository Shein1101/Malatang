// assets/js/customize.js — S04 カスタマイズ + リアルタイム料金. Owner: Zan (FEAT-11, FEAT-12)
//
// 流れ: ① API から menu・具材・味/辛さ を読む → ② 画面を作る → ③ 選ぶたびに calcPrice() → ④ カートに追加
//
// 料金の計算 (DB の fn_bowl_price と同じ):
//   1杯 = メニュー + 味 + 辛さ + (追加した具材の値段 × 個数)
//   合計 = 1杯 × 杯数
//   ・最初から入っている具材はメニューの値段に入っている (外しても安くならない)
// ここで出す金額は「見せるため」。本当の金額は server (api/cart.php) がもう一度計算する。

// ---------- 1. 画面の状態 (state) ----------
// 画面で選んだものは全部ここに入れる。表示はいつも state から作り直す。
const state = {
  menu: null,          // GET menus.php?id= の data
  soups: [],           // 味
  spices: [],          // 辛さ
  extras: [],          // 追加できる具材 (最初から入っている具材は除く)
  soupId: null,
  hasSoup: true,
  spiceId: null,
  removed: new Set(),  // 外した具材の id
  added: new Map(),    // 追加した具材 id → 個数
  quantity: 1,
};

const MAX_EXTRA = 10;  // 具材1つの最大個数 (api/cart.php と同じ)
const MAX_BOWLS = 20;  // 杯数の最大

const $ = (id) => document.getElementById(id);

// 文字を安全に HTML に入れる (お店が入力した名前に < などがあっても壊れない)
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// ---------- 2. データを読む ----------
async function load() {
  const menuId = new URLSearchParams(location.search).get('menu_id');
  if (!menuId) {
    location.href = 'menu.php';       // menu_id がない → メニュー一覧へ戻す
    return;
  }

  // 3つの API を同時に呼ぶ (Promise.all = 全部終わるまで待つ)
  const [menuRes, ingRes, optRes] = await Promise.all([
    callApi('GET', '../api/menus.php?id=' + encodeURIComponent(menuId)),
    callApi('GET', '../api/ingredients.php'),
    callApi('GET', '../api/options.php'),
  ]);
  $('loading').classList.add('d-none');

  const failed = [menuRes, ingRes, optRes].find(r => !r.success);
  if (failed) {
    showError($('error'), failed.error || '読み込めませんでした');
    return;
  }
  if (!menuRes.data.is_available) {
    showError($('error'), 'このメニューは売り切れです。メニュー一覧から選び直してください。');
    return;
  }

  state.menu = menuRes.data;
  state.soups = optRes.data.soups;
  state.spices = optRes.data.spice_levels;
  const defaultIds = new Set(state.menu.ingredients.map(i => i.ingredient_id));
  state.extras = ingRes.data.filter(i => !defaultIds.has(i.id));

  // はじめの選択: 最初の味・最初の辛さ
  state.soupId = state.soups.length ? state.soups[0].id : null;
  state.spiceId = state.spices.length ? state.spices[0].id : null;

  renderStatic();
  render();
  $('builder').classList.remove('d-none');
}

// ---------- 3. 画面を作る ----------
// 変わらない部分 (メニュー名・具材リストの形) は1回だけ作る
function renderStatic() {
  const m = state.menu;
  $('menuName').textContent = m.name;
  $('menuDesc').textContent = m.description || '';
  $('menuPrice').textContent = yen(m.price);
  document.title = m.name + ' | Chilli 鍋';

  // 最初から入っている具材
  if (m.ingredients.length === 0) {
    $('defaultsBox').classList.add('d-none');
  }
  $('defaultList').innerHTML = m.ingredients.map(i => `
    <li>
      <label class="check">
        <input type="checkbox" data-default="${i.ingredient_id}" checked ${i.is_removable ? '' : 'disabled'}>
        ${esc(i.name)}${i.quantity > 1 ? ' ×' + i.quantity : ''}
      </label>
      <span class="caption">${i.is_removable ? '外せます' : '外せません'}</span>
    </li>`).join('');

  // 追加できる具材をカテゴリごとにまとめる
  const groups = new Map();
  for (const i of state.extras) {
    if (!groups.has(i.category_name)) groups.set(i.category_name, []);
    groups.get(i.category_name).push(i);
  }
  let html = '';
  for (const [cat, items] of groups) {
    html += `<div><p class="caption"><b>${esc(cat)}</b></p><ul class="list">`;
    for (const i of items) {
      const soldOut = !i.is_available || i.stock <= 0;
      html += `
        <li class="${soldOut ? 'cz-soldout' : ''}">
          <div>${esc(i.name)}<div class="caption">+${yen(i.price)}</div></div>
          ${soldOut
            ? '<span class="badge badge-soldout">売り切れ</span>'
            : `<div class="stepper">
                 <button type="button" data-extra="${i.id}" data-step="-1" aria-label="${esc(i.name)}を減らす">−</button>
                 <output id="extra-${i.id}">0</output>
                 <button type="button" data-extra="${i.id}" data-step="1" aria-label="${esc(i.name)}を増やす">+</button>
               </div>`}
        </li>`;
    }
    html += '</ul></div>';
  }
  $('extraList').innerHTML = html || '<p class="caption">追加できる具材はありません</p>';
}

// 選ぶたびに変わる部分 (チップの選択・個数・料金)
function render() {
  $('soupChips').innerHTML = state.soups.map(s => chip('soup', s.id, s.name, s.price, s.id === state.soupId)).join('');
  $('spiceChips').innerHTML = state.spices.map(s => chip('spice', s.id, s.name, s.extra_price, s.id === state.spiceId)).join('');

  document.querySelectorAll('#brothChips .chip').forEach(b => {
    const on = (b.dataset.broth === '1') === state.hasSoup;
    b.classList.toggle('is-selected', on);
    b.setAttribute('aria-pressed', on);
  });

  for (const i of state.extras) {
    const out = $('extra-' + i.id);
    if (out) out.textContent = state.added.get(i.id) || 0;
  }
  $('qty').textContent = state.quantity;

  renderPrice();
}

// チップ1つ (選択中なら is-selected)
function chip(kind, id, name, price, selected) {
  return `<button type="button" class="chip ${selected ? 'is-selected' : ''}" aria-pressed="${selected}"
            data-${kind}="${id}">${esc(name)}${price > 0 ? `<span class="caption">+${yen(price)}</span>` : ''}</button>`;
}

// ---------- 4. 料金の計算 (FEAT-12) ----------
// 戻り値: { lines: [[名前, 金額], …], unit: 1杯の値段, total: 合計 }
function calcPrice() {
  const lines = [];
  lines.push([state.menu.name, state.menu.price]);

  const soup = state.soups.find(s => s.id === state.soupId);
  if (soup && soup.price > 0) lines.push(['味：' + soup.name, soup.price]);

  const spice = state.spices.find(s => s.id === state.spiceId);
  if (spice && spice.extra_price > 0) lines.push(['辛さ：' + spice.name, spice.extra_price]);

  for (const [id, qty] of state.added) {
    const ing = state.extras.find(i => i.id === id);
    lines.push([`${ing.name} ×${qty}`, ing.price * qty]);
  }

  const unit = lines.reduce((sum, [, price]) => sum + price, 0);
  return { lines, unit, total: unit * state.quantity };
}

function renderPrice() {
  const p = calcPrice();
  let html = p.lines.map(([name, price]) => `<li><span>${esc(name)}</span><span>${yen(price)}</span></li>`).join('');
  html += `<li><b>1杯</b><b>${yen(p.unit)}</b></li>`;
  $('priceLines').innerHTML = html;
  $('total').textContent = yen(p.total);
  $('addToCart').disabled = state.soupId === null;
}

// ---------- 5. クリックをまとめて受け取る ----------
document.addEventListener('click', (e) => {
  const b = e.target.closest('button');
  if (!b || !$('builder').contains(b)) return;

  if (b.dataset.soup)  state.soupId  = Number(b.dataset.soup);
  if (b.dataset.spice) state.spiceId = Number(b.dataset.spice);
  if (b.dataset.broth) state.hasSoup = b.dataset.broth === '1';

  if (b.dataset.extra) {
    const id = Number(b.dataset.extra);
    const next = Math.min(MAX_EXTRA, Math.max(0, (state.added.get(id) || 0) + Number(b.dataset.step)));
    if (next === 0) state.added.delete(id); else state.added.set(id, next);
  }
  if (b.id === 'qtyMinus') state.quantity = Math.max(1, state.quantity - 1);
  if (b.id === 'qtyPlus')  state.quantity = Math.min(MAX_BOWLS, state.quantity + 1);

  if (b.id === 'addToCart') {
    addToCart();
    return;
  }
  render();
});

// 最初から入っている具材のチェック (外す / 戻す)
document.addEventListener('change', (e) => {
  const id = e.target.dataset && e.target.dataset.default;
  if (!id) return;
  if (e.target.checked) state.removed.delete(Number(id)); else state.removed.add(Number(id));
  render();
});

// ---------- 6. カートに追加 ----------
// Team Rules の Cart format と同じ形で送る。値段は送らない。
async function addToCart() {
  const bowl = {
    menu_id: state.menu.id,
    has_soup: state.hasSoup,
    soup_id: state.soupId,
    spice_level_id: state.spiceId,
    quantity: state.quantity,
    ingredients: [
      ...[...state.added].map(([id, qty]) => ({ ingredient_id: id, action: 'added', quantity: qty })),
      ...[...state.removed].map(id => ({ ingredient_id: id, action: 'removed', quantity: 1 })),
    ],
  };

  const btn = $('addToCart');
  btn.disabled = true;                         // 2回押し防止
  const res = await callApi('POST', '../api/cart.php', bowl);
  btn.disabled = false;

  if (!res.success) {
    showError($('error'), res.error);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  if (typeof refreshCartCount === 'function') refreshCartCount();
  $('toastArea').innerHTML = `<div class="toast" role="status">カートに追加しました（${yen(res.data.price)}）</div>`;
  setTimeout(() => { location.href = 'cart.php'; }, 900);
}

window.addEventListener('load', load);
