// assets/js/customize.js — S04 カスタマイズ + リアルタイム料金. Owner: Zan (FEAT-11, FEAT-12)
// 自分で作る・具材の検索・お気に入りの具材・マイレシピ: Lin
//
// 流れ: ① API から menu・具材・味/辛さ・お気に入り を読む → ② 画面を作る → ③ 選ぶたびに calcPrice() → ④ カートに追加
//
// どの menu から始めるか:
//   customize.php?menu_id=3      → その ready-made dish (menu.php のカード)
//   customize.php?favorite_id=5  → マイレシピの menu + 中身 (味・辛さ・具材) をセット
//   customize.php                → 自分で作る (GET menus.php?custom=1 の base menu)
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
  loggedIn: false,     // お気に入りの API が使えたら true
  picks: new Set(),    // お気に入りの具材の id
  recipes: [],         // マイレシピ (GET favorites.php)
  recipeId: null,      // マイレシピから始めたとき、その id
};

const MAX_EXTRA = 10;  // 具材1つの最大個数 (api/cart.php と同じ)
const MAX_BOWLS = 20;  // 杯数の最大

const $ = (id) => document.getElementById(id);

// 文字を安全に HTML に入れる (お店が入力した名前に < などがあっても壊れない)
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// 検索用: ひらがな → カタカナ、英字は小文字 (「えび」で「エビ」が見つかる)
function normalize(s) {
  return String(s ?? '').toLowerCase().replace(/[ぁ-ゖ]/g, c => String.fromCharCode(c.charCodeAt(0) + 0x60)).trim();
}

// ---------- 2. データを読む ----------
async function load() {
  const params = new URLSearchParams(location.search);
  const menuId = params.get('menu_id');
  const favoriteId = Number(params.get('favorite_id')) || null;

  // 具材・味/辛さ・お気に入り を同時に読む (Promise.all = 全部終わるまで待つ)
  // お気に入りは login していないと 401 → guest として続ける
  const [ingRes, optRes, recipeRes, pickRes] = await Promise.all([
    callApi('GET', '../api/ingredients.php'),
    callApi('GET', '../api/options.php'),
    callApi('GET', '../api/favorites.php'),
    callApi('GET', '../api/favorites.php?type=ingredient'),
  ]);
  state.loggedIn = recipeRes.success && pickRes.success;
  if (state.loggedIn) {
    state.recipes = recipeRes.data;
    state.picks = new Set(pickRes.data.map(p => p.ingredient_id));
  }

  // どの menu から始めるか
  let recipe = null;
  let menuUrl = '../api/menus.php?custom=1';
  if (favoriteId) {
    recipe = state.recipes.find(r => r.id === favoriteId);
    if (!recipe) {
      $('loading').classList.add('d-none');
      showError($('error'), state.loggedIn ? 'マイレシピが見つかりません' : 'マイレシピを使うにはログインしてください');
      return;
    }
    menuUrl = '../api/menus.php?id=' + recipe.menu_id;
  } else if (menuId) {
    menuUrl = '../api/menus.php?id=' + encodeURIComponent(menuId);
  }
  const menuRes = await callApi('GET', menuUrl);
  $('loading').classList.add('d-none');

  const failed = [menuRes, ingRes, optRes].find(r => !r.success);
  if (failed) {
    showError($('error'), failed === menuRes && !menuId && !favoriteId
      ? '「自分で作る」は今はご利用いただけません。メニュー一覧から選んでください。'
      : (failed.error || '読み込めませんでした'));
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
  if (recipe) applyRecipe(recipe);

  renderStatic();
  render();
  $('builder').classList.remove('d-none');
}

// マイレシピの中身を state に入れる。今は選べないものは入れずに知らせる
function applyRecipe(r) {
  state.recipeId = r.id;
  state.hasSoup = r.has_soup;
  const skipped = [];

  if (state.soups.some(s => s.id === r.soup_id)) state.soupId = r.soup_id;
  else skipped.push('味: ' + r.soup_name);
  if (r.spice_level_id !== null) {
    if (state.spices.some(s => s.id === r.spice_level_id)) state.spiceId = r.spice_level_id;
    else skipped.push('辛さ: ' + r.spice_name);
  }

  for (const it of r.items) {
    if (it.action === 'removed') {
      const d = state.menu.ingredients.find(i => i.ingredient_id === it.ingredient_id);
      if (d && d.is_removable) state.removed.add(it.ingredient_id);
      continue;
    }
    const ing = state.extras.find(i => i.id === it.ingredient_id);
    if (ing && ing.is_available && ing.stock > 0) {
      state.added.set(ing.id, Math.min(MAX_EXTRA, it.quantity));
    } else {
      skipped.push(it.name);
    }
  }

  if (skipped.length) {
    $('notice').textContent = '売り切れ・販売終了のため入れていないもの: ' + skipped.join('、');
    $('notice').classList.remove('d-none');
  }
}

// ---------- 3. 画面を作る ----------
// 変わらない部分 (メニュー名・具材リストの形) は1回だけ作る
function renderStatic() {
  const m = state.menu;
  $('menuName').textContent = m.name;
  $('menuDesc').textContent = m.description || '';
  $('menuPrice').textContent = yen(m.price);
  document.title = m.name + ' | Chilli 鍋';

  // 自分で作る ⇔ メニューから選ぶ
  $('modeLink').innerHTML = m.is_custom
    ? 'おすすめの組み合わせから始めるなら → <a href="menu.php">メニューから選ぶ</a>'
    : '一から全部選びたいなら → <a href="customize.php">自分で作る</a>';

  // 最初から入っている具材
  if (m.ingredients.length === 0) {
    $('defaultsBox').classList.add('d-none');
  }
  $('defaultList').innerHTML = m.ingredients.map(i => `
    <li>
      <label class="check">
        <input type="checkbox" data-default="${i.ingredient_id}" ${state.removed.has(i.ingredient_id) ? '' : 'checked'} ${i.is_removable ? '' : 'disabled'}>
        ${esc(i.name)}${i.quantity > 1 ? ' ×' + i.quantity : ''}
      </label>
      <span class="caption">${i.is_removable ? '外せます' : '外せません'}</span>
    </li>`).join('');

  // マイレシピ / guest への案内
  $('recipeBox').classList.toggle('d-none', !state.loggedIn || state.recipes.length === 0);
  $('saveRecipe').classList.toggle('d-none', !state.loggedIn);
  $('loginHint').classList.toggle('d-none', state.loggedIn);
  $('loginHint').querySelector('a').href = 'login.php?redirect=' + encodeURIComponent('customize.php' + location.search);
  renderRecipes();

  renderExtras();
}

// マイレシピのチップ (押すと そのレシピで作り直す)
function renderRecipes() {
  $('recipeChips').innerHTML = state.recipes.map(r => {
    const name = esc(r.nickname || r.menu_name);
    const selected = r.id === state.recipeId;
    if (r.price === null) {
      return `<button type="button" class="chip" disabled title="${esc(r.unavailable)}">${name}<span class="caption">売り切れ</span></button>`;
    }
    return `<button type="button" class="chip ${selected ? 'is-selected' : ''}" aria-pressed="${selected}"
              data-recipe="${r.id}">${name}<span class="caption">${yen(r.price)}</span></button>`;
  }).join('');
}

// 具材1行 (お気に入りの所とカテゴリの所で同じものを使う)
function extraRow(i) {
  const soldOut = !i.is_available || i.stock <= 0;
  const heart = state.loggedIn
    ? `<button type="button" class="icon-btn" data-pick="${i.id}" aria-pressed="${state.picks.has(i.id)}"
         aria-label="${esc(i.name)}をお気に入り${state.picks.has(i.id) ? 'から外す' : 'に追加'}">${state.picks.has(i.id) ? '♥' : '♡'}</button>`
    : '';
  return `
    <li class="${soldOut ? 'cz-soldout' : ''}" data-name="${esc(normalize(i.name + ' ' + i.category_name))}">
      <div class="d-flex align-items-center gap-1">${heart}<div>${esc(i.name)}<div class="caption">+${yen(i.price)}</div></div></div>
      ${soldOut
        ? '<span class="badge badge-soldout">売り切れ</span>'
        : `<div class="stepper">
             <button type="button" data-extra="${i.id}" data-step="-1" aria-label="${esc(i.name)}を減らす">−</button>
             <output data-out="${i.id}">0</output>
             <button type="button" data-extra="${i.id}" data-step="1" aria-label="${esc(i.name)}を増やす">+</button>
           </div>`}
    </li>`;
}

// 追加できる具材 (お気に入り + カテゴリごと)。お気に入りを変えたときも作り直す
function renderExtras() {
  const favs = state.extras.filter(i => state.picks.has(i.id));
  $('favList').innerHTML = favs.map(extraRow).join('');
  $('favBox').classList.toggle('d-none', favs.length === 0);

  const groups = new Map();
  for (const i of state.extras) {
    if (!groups.has(i.category_name)) groups.set(i.category_name, []);
    groups.get(i.category_name).push(i);
  }
  let html = '';
  for (const [cat, items] of groups) {
    html += `<div data-group><p class="caption"><b>${esc(cat)}</b></p><ul class="list">${items.map(extraRow).join('')}</ul></div>`;
  }
  $('extraList').innerHTML = html || '<p class="caption">追加できる具材はありません</p>';
  applySearch();
}

// 検索: 名前かカテゴリに入っている具材だけ見せる
function applySearch() {
  const q = normalize($('ingSearch').value);
  let hits = 0;
  document.querySelectorAll('#favList li, #extraList li').forEach(li => {
    const show = q === '' || li.dataset.name.includes(q);
    li.classList.toggle('d-none', !show);
    if (show) hits++;
  });
  // 1つも見えないカテゴリ・お気に入りの見出しは隠す
  document.querySelectorAll('#extraList [data-group]').forEach(g => {
    g.classList.toggle('d-none', !g.querySelector('li:not(.d-none)'));
  });
  const favVisible = $('favList').querySelector('li:not(.d-none)');
  $('favBox').classList.toggle('d-none', !favVisible);

  $('noHitWord').textContent = $('ingSearch').value;
  $('noHit').classList.toggle('d-none', q === '' || hits > 0);
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

  // 同じ具材がお気に入りとカテゴリの2か所にあるので、両方の個数を変える
  document.querySelectorAll('[data-out]').forEach(out => {
    out.textContent = state.added.get(Number(out.dataset.out)) || 0;
  });
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

  if (b.dataset.recipe) {                     // マイレシピで作り直す (menu が変わることもあるのでページごと)
    location.href = 'customize.php?favorite_id=' + b.dataset.recipe;
    return;
  }
  if (b.dataset.pick) {
    togglePick(Number(b.dataset.pick));
    return;
  }
  if (b.id === 'saveRecipe') {
    saveRecipe();
    return;
  }

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

// 具材の検索 (打つたびに絞り込む)
$('ingSearch').addEventListener('input', applySearch);

// ---------- 6. お気に入り (Lin) ----------
// ♡ / ♥ : お気に入りの具材に入れる・外す (api/favorites.php?type=ingredient)
async function togglePick(id) {
  const on = state.picks.has(id);
  const res = on
    ? await callApi('DELETE', '../api/favorites.php?type=ingredient&ingredient_id=' + id)
    : await callApi('POST', '../api/favorites.php?type=ingredient', { ingredient_id: id });
  if (!res.success) {
    showError($('error'), res.error);
    return;
  }
  if (on) state.picks.delete(id); else state.picks.add(id);
  renderExtras();
  render();
}

// 今の bowl を マイレシピに保存 (api/favorites.php POST)
async function saveRecipe() {
  const name = prompt('レシピの名前（例: いつもの）。空でも保存できます。', '');
  if (name === null) return;
  const res = await callApi('POST', '../api/favorites.php', {
    menu_id: state.menu.id,
    has_soup: state.hasSoup,
    soup_id: state.soupId,
    spice_level_id: state.spiceId,
    items: bowlItems(),
    nickname: name,
  });
  if (!res.success) {
    showError($('error'), res.error);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  toast('マイレシピに保存しました');
  const again = await callApi('GET', '../api/favorites.php');
  if (again.success) {
    state.recipes = again.data;
    state.recipeId = res.data.id;
    $('recipeBox').classList.remove('d-none');
    renderRecipes();
  }
}

function toast(text) {
  $('toastArea').innerHTML = `<div class="toast" role="status">${esc(text)}</div>`;
  setTimeout(() => { $('toastArea').innerHTML = ''; }, 2500);
}

// ---------- 7. カートに追加 ----------
// 追加した具材 + 外した具材 (cart と マイレシピ で同じ形)
function bowlItems() {
  return [
    ...[...state.added].map(([id, qty]) => ({ ingredient_id: id, action: 'added', quantity: qty })),
    ...[...state.removed].map(id => ({ ingredient_id: id, action: 'removed', quantity: 1 })),
  ];
}

// Team Rules の Cart format と同じ形で送る。値段は送らない。
async function addToCart() {
  const bowl = {
    menu_id: state.menu.id,
    has_soup: state.hasSoup,
    soup_id: state.soupId,
    spice_level_id: state.spiceId,
    quantity: state.quantity,
    ingredients: bowlItems(),
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
