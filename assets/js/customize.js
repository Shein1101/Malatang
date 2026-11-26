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
  noodleTo: null,      // 麺を入れかえたら その具材 id (null = メニューの麺のまま) [Myat]
  added: new Map(),    // 追加した具材 id → 個数
  extraCat: null,      // 見ている具材のカテゴリ (タブ)。'all' = 全部
  quantity: 1,
  loggedIn: false,     // お気に入りの API が使えたら true
  picks: new Set(),    // お気に入りの具材の id
  recipes: [],         // マイレシピ (GET favorites.php)
  recipeId: null,      // マイレシピから始めたとき、その id
  favOpen: false,      // お気に入りのドロップダウンを お客さんが開いたか
  maxAdd: 0,           // 追加できる具材の最大個数 (お店が決める。0 = 無制限)
  minPrice: 0,         // 自分で作る: 1杯の最低注文金額 (お店が決める。0 = 制限なし)
  editId: null,        // カートの bowl を編集中なら その cart_bowl_id (cart.php の「編集」から)
};

const MAX_EXTRA = 10;  // 具材1つの最大個数 (api/cart.php と同じ)

// ---------- 麺をえらぶ [Myat] ----------
// メニューに入っている麺 (春雨 など) を、この中の 別の麺に かえられる。includes/bowl.php の NOODLE_CHOICES と 同じ。
// サーバーには「元の麺 removed + えらんだ麺 added」で送る。値段は 差額だけ (サーバーも 同じ計算)。
const NOODLE_CHOICES = ['春雨', 'インスタント麺', 'トウモロコシ麺'];
// メニューに入っていても 値段に入らない トッピング (メニューでは「無料」と出す)。api/menus.php の FREE_GARNISH_SQL と同じ
const FREE_GARNISH = ['パクチー'];
// このメニューに 最初から入っている「えらべる麺」(なければ null)
function defaultNoodle() {
  return state.menu ? state.menu.ingredients.find(d => NOODLE_CHOICES.includes(d.name)) || null : null;
}
// えらべる麺 (具材の一覧から。売り切れも 入れておき、ボタンを 押せなくする)
function noodleOptions() {
  return NOODLE_CHOICES.map(n => state.extras.find(i => i.name === n)).filter(Boolean);
}
// 入れかえ中の麺の 具材データ (なければ null)
function swappedNoodle() {
  return state.noodleTo ? state.extras.find(i => i.id === state.noodleTo) || null : null;
}
// 保存した中身 (カート・下書き・マイレシピ) から 麺の入れかえを 見つけて state に入れる
//   removedIds = 外した id の配列。見つけたら state.added から 1つ分 引く (入れかえの1つは 追加ではないので)
function detectNoodleSwap(removedIds) {
  const d = defaultNoodle();
  if (!d || !removedIds.includes(d.ingredient_id)) return;
  for (const opt of noodleOptions()) {
    if (opt.id === d.ingredient_id) continue;
    const q = state.added.get(opt.id) || 0;
    if (q > 0) {
      state.noodleTo = opt.id;
      if (q - 1 > 0) state.added.set(opt.id, q - 1); else state.added.delete(opt.id);
      state.removed.delete(d.ingredient_id);   // 外したのではなく 入れかえ
      return;
    }
  }
}
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

// 1つの量 (お店が 具材・在庫 で書いたもの。例: 50g) → 「（1つ 50g）」。書いてなければ何も出さない [Myat]
function portionText(p) {
  return p ? ` <small class="text-muted">（1つ ${esc(p)}）</small>` : '';
}

// ---------- 2. データを読む ----------
async function load() {
  const params = new URLSearchParams(location.search);
  const menuId = params.get('menu_id');
  const favoriteId = Number(params.get('favorite_id')) || null;

  // 具材・味/辛さ・お気に入り を同時に読む (Promise.all = 全部終わるまで待つ)
  // お気に入りは login していないと 401 → guest として続ける
  const [ingRes, optRes, recipeRes, pickRes, storeRes] = await Promise.all([
    callApi('GET', '../api/ingredients.php'),
    callApi('GET', '../api/options.php'),
    callApi('GET', '../api/favorites.php'),
    callApi('GET', '../api/favorites.php?type=ingredient'),
    callApi('GET', '../api/store.php'),
  ]);
  state.loggedIn = recipeRes.success && pickRes.success;
  state.maxAdd = (storeRes.success && storeRes.data.store) ? (storeRes.data.store.max_custom_ingredients || 0) : 0;
  // 「自分で作る」の 1杯の最低注文金額 (0 = 制限なし)。サーバー (includes/bowl.php) でも 同じチェックをする [Myat]
  state.minPrice = (storeRes.success && storeRes.data.store) ? (storeRes.data.store.min_custom_price || 0) : 0;
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
  // 追加できる具材 = 全部の具材。メニューに最初から入っている具材も「もっと入れる」ために 追加できる [Myat]
  // (前は 最初から入っている具材を 外していたので、選んだメニューの 豚バラ などを 増やせなかった)
  state.extras = ingRes.data;

  // はじめの選択: 最初の味・最初の辛さ
  state.soupId = state.soups.length ? state.soups[0].id : null;
  state.spiceId = state.spices.length ? state.spices[0].id : null;
  if (recipe) applyRecipe(recipe);

  // カートの bowl を編集 (cart.php の「編集」ボタン): その中身を読み込む
  const editId = Number(params.get('edit')) || null;
  if (editId && !recipe) {
    const cartRes = await callApi('GET', '../api/cart.php');
    const cb = cartRes.success ? cartRes.data.bowls.find(x => x.id === editId) : null;
    if (cb) { state.editId = editId; applyCartBowl(cb); }
  } else if (!recipe) {
    // 前回の続き (別のページへ移っても 選んだ内容が残る) [item 4]
    restoreDraft();
  }

  renderStatic();
  render();
  $('builder').classList.remove('d-none');

  // カートの「＋ 具材を追加」から来たとき (#czExtras): 具材のところまで スクロールして 検索に カーソル [Myat]
  // (画面ができる前は 中身が隠れているので、ブラウザの自動スクロールが効かない → ここで動かす)
  if (location.hash === '#czExtras' && $('czExtras')) {
    requestAnimationFrame(() => {
      $('czExtras').scrollIntoView({ behavior: 'smooth', block: 'start' });
      $('ingSearch')?.focus({ preventScroll: true });
    });
  }
}

// カートの bowl (api/cart.php の形) を state に入れる (編集)
function applyCartBowl(cb) {
  state.hasSoup = cb.has_soup;
  if (state.soups.some(s => s.id === cb.soup_id)) state.soupId = cb.soup_id;
  if (cb.spice_level_id !== null && state.spices.some(s => s.id === cb.spice_level_id)) state.spiceId = cb.spice_level_id;
  state.quantity = Math.min(MAX_BOWLS, Math.max(1, cb.quantity || 1));
  for (const it of cb.ingredients || []) {
    if (it.action === 'removed') {
      const d = state.menu.ingredients.find(i => i.ingredient_id === it.ingredient_id);
      if (d && d.is_removable) state.removed.add(it.ingredient_id);
    } else {
      const ing = state.extras.find(i => i.id === it.ingredient_id);
      if (ing && ing.is_available && ing.stock > 0) state.added.set(ing.id, Math.min(MAX_EXTRA, it.quantity));
    }
  }
  // 麺の入れかえ (元の麺 removed + ほかの麺 added) を もどす
  detectNoodleSwap((cb.ingredients || []).filter(it => it.action === 'removed').map(it => it.ingredient_id));
}

// ---- 下書き (localStorage): 別ページへ移っても 選んだ内容を残す [item 4] ----
function draftKey() { return state.menu ? 'cz_draft_' + state.menu.id : null; }
function saveDraft() {
  if (!state.menu || state.editId) return;   // 編集中は下書きを上書きしない
  try {
    localStorage.setItem(draftKey(), JSON.stringify({
      soupId: state.soupId, hasSoup: state.hasSoup, spiceId: state.spiceId,
      removed: [...state.removed], added: [...state.added], quantity: state.quantity, noodleTo: state.noodleTo,
    }));
  } catch (e) { /* localStorage が使えなくても動く */ }
}
function clearDraft() { try { localStorage.removeItem(draftKey()); } catch (e) {} }
function restoreDraft() {
  let d;
  try { d = JSON.parse(localStorage.getItem(draftKey()) || 'null'); } catch (e) { return; }
  if (!d) return;
  if (state.soups.some(s => s.id === d.soupId)) state.soupId = d.soupId;
  if (typeof d.hasSoup === 'boolean') state.hasSoup = d.hasSoup;
  if (d.spiceId !== null && state.spices.some(s => s.id === d.spiceId)) state.spiceId = d.spiceId;
  state.quantity = Math.min(MAX_BOWLS, Math.max(1, d.quantity || 1));
  for (const id of d.removed || []) {
    const def = state.menu.ingredients.find(i => i.ingredient_id === id);
    if (def && def.is_removable) state.removed.add(id);
  }
  for (const [id, qty] of d.added || []) {
    const ing = state.extras.find(i => i.id === id);
    if (ing && ing.is_available && ing.stock > 0) state.added.set(id, Math.min(MAX_EXTRA, qty));
  }
  // 下書きの 麺の入れかえ (今も えらべる・売り切れでない ときだけ)
  const nt = d.noodleTo && noodleOptions().find(o => o.id === d.noodleTo);
  if (nt && defaultNoodle() && nt.is_available && nt.stock > 0) state.noodleTo = nt.id;
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
  // マイレシピの 麺の入れかえ も もどす
  detectNoodleSwap(r.items.filter(it => it.action === 'removed').map(it => it.ingredient_id));

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
  document.title = m.name + ' | Chilli 鍋';
  $('addToCart').innerHTML = (state.editId ? 'カートを更新' : 'カートに追加') + ' <span class="arrow"></span>';

  // 「もどる」ボタンがあるので、ここは「一から自分で作る」への軽いリンクだけにする
  $('modeLink').innerHTML = m.is_custom
    ? ''
    : '<a class="cz-switch" href="customize.php">一から自分で作る →</a>';

  // 最初から入っている具材 (無ければセクションごと隠す)
  $('defaultsBox').classList.toggle('d-none', m.ingredients.length === 0);
  const dn = defaultNoodle();
  $('defaultList').innerHTML = m.ingredients.map(i => (dn && i.ingredient_id === dn.ingredient_id)
    // 麺の行: 外す かわりに 麺を えらぶ チップ (中身は renderNoodle が 入れる) [Myat]
    ? `
    <li class="noodle-row">
      <span class="noodle-label">麺を選ぶ</span>
      <div id="noodleChips" class="chip-group noodle-chips" role="group" aria-label="麺を選ぶ"></div>
    </li>`
    : `
    <li>
      <label class="check">
        <input type="checkbox" data-default="${i.ingredient_id}" ${state.removed.has(i.ingredient_id) ? '' : 'checked'} ${i.is_removable ? '' : 'disabled'}>
        ${esc(i.name)}${i.quantity > 1 ? ' ×' + i.quantity : ''}${portionText(i.portion)}
        ${FREE_GARNISH.includes(i.name) ? '<span class="free-badge">無料</span>' : ''}
      </label>
      <span class="caption">${i.is_removable ? '外せます' : '外せません'}</span>
    </li>`).join('');

  // マイレシピ / guest への案内
  $('recipeBox').classList.toggle('d-none', !state.loggedIn || state.recipes.length === 0);
  $('saveRecipe').classList.toggle('d-none', !state.loggedIn);
  $('shareRecipe').classList.toggle('d-none', !state.loggedIn);
  $('loginHint').classList.toggle('d-none', state.loggedIn);
  $('loginHint').querySelector('a').href = 'login.php?redirect=' + encodeURIComponent('customize.php' + location.search);
  renderRecipes();

  renderExtras();
  renumberSteps();
}

// 手順の番号をふり直す: 見えているセクションだけを 1,2,3… と数える。
// (「最初から入っている具材」が無い menu でも 1,2,3,5 のように飛ばない) [Zan fix]
function renumberSteps() {
  const sections = [...document.querySelectorAll('#builder .cz-section')];
  const num = new Map();   // section id → 番号
  let n = 0;
  sections.forEach(sec => {
    if (sec.classList.contains('d-none')) return;
    n++;
    num.set(sec.id, n);
    const label = sec.querySelector('.section-label .n');
    if (label) label.textContent = n;
  });
  document.querySelectorAll('#czRail a').forEach(a => {
    const id = (a.getAttribute('href') || '').slice(1);
    const n2 = num.get(id);
    const dot = a.querySelector('.n');
    a.hidden = !n2;
    if (n2 && dot) dot.textContent = n2;
  });
}

// マイレシピのチップ (押すと そのレシピで作り直す)
function renderRecipes() {
  const current = state.recipes.find(r => r.id === state.recipeId);
  $('recipeCount').textContent = current ? (current.nickname || current.menu_name) + ' を選択中' : state.recipes.length + '件';
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

// 写真がないときの丸の色 (カテゴリで決まる)
function ingArt(category) {
  const name = String(category || '');
  if (/野菜/.test(name)) return 'linear-gradient(135deg,#9bbf6b,#3c5a2e)';
  if (/きのこ/.test(name)) return 'linear-gradient(135deg,#e7c08a,#b98a4a)';
  if (/肉/.test(name)) return 'linear-gradient(135deg,#d99a6d,#8a4a2a)';
  if (/海鮮|魚/.test(name)) return 'linear-gradient(135deg,#7fb0c4,#1d3a4a)';
  return 'linear-gradient(135deg,#e2c98a,#a5802c)';
}

// 写真がない具材 (または 写真が読めない) ときに出す 絵文字 [Myat]
// まず名前で探す → なければ カテゴリで → それもなければ 🍲。新しい具材にも 自動で つく。
const ING_EMOJI_BY_NAME = [
  [/トマト/, '🍅'], [/れんこん/, '🪷'], [/昆布|わかめ/, '🌿'], [/空芯菜|青菜/, '🥬'], [/パクチー/, '🌿'],
  [/ランチョンミート/, '🥫'], [/肉味噌/, '🥩'], [/チーズ/, '🧀'], [/卵|玉/, '🥚'],
  [/あさり|はまぐり|貝/, '🐚'], [/えび|エビ/, '🍤'], [/いか|イカ/, '🦑'], [/魚|つみれ/, '🐟'],
  [/団子|ちくわ|練り/, '🍢'], [/トッポギ/, '🍡'], [/餃子|しゅうまい|小籠包|点心/, '🥟'],
  [/豆腐|湯葉|厚揚げ|腐竹/, '🫘'], [/春雨|ラーメン|うどん|麺/, '🍜'], [/たけ|茸/, '🍄'],
];
const ING_EMOJI_BY_CAT = [
  [/野菜/, '🥬'], [/きのこ/, '🍄'], [/肉/, '🥩'], [/海鮮|魚/, '🦐'], [/団子|練り物/, '🍢'],
  [/豆腐|湯葉/, '🫘'], [/麺/, '🍜'], [/点心/, '🥟'], [/トッピング/, '🥚'],
];
function ingEmoji(name, category) {
  const hit = ING_EMOJI_BY_NAME.find(([re]) => re.test(name || ''))
           || ING_EMOJI_BY_CAT.find(([re]) => re.test(category || ''));
  return hit ? hit[1] : '🍲';
}

// 具材1枚 (丸い写真 + 名前 + 値段 + − 1 +。お気に入りの所とカテゴリの所で同じものを使う)
// メニューに 最初から入っている具材か
function isDefaultIng(id) {
  return !!(state.menu && state.menu.ingredients.some(d => d.ingredient_id === id));
}

function extraRow(i) {
  const soldOut = !i.is_available || i.stock <= 0;
  const qty = state.added.get(i.id) || 0;
  const heart = state.loggedIn
    ? `<button type="button" class="icon-btn" data-pick="${i.id}" aria-pressed="${state.picks.has(i.id)}"
         aria-label="${esc(i.name)}をお気に入り${state.picks.has(i.id) ? 'から外す' : 'に追加'}">${state.picks.has(i.id) ? '♥' : '♡'}</button>`
    : '';
  // 写真の後ろに いつも 絵文字 を置く → 写真がない / 読めない ときは 絵文字が見える
  const photo = `<span class="ing-emoji" aria-hidden="true">${ingEmoji(i.name, i.category_name)}</span>`
    + (i.image_url ? `<img src="${esc(i.image_url)}" alt="" loading="lazy" onerror="this.remove()">` : '');
  return `
    <li class="ing-tile ${soldOut ? 'is-soldout' : ''} ${qty > 0 ? 'is-added' : ''}" data-name="${esc(normalize(i.name + ' ' + i.category_name))}">
      ${heart}
      <div class="ing-photo" style="background:${ingArt(i.category_name)}">${photo}</div>
      <span class="ing-name">${esc(i.name)}</span>
      ${isDefaultIng(i.id) ? '<span class="ing-in-menu">メニューに入っています</span>' : ''}
      ${i.portion ? `<span class="ing-portion">1つ ${esc(i.portion)}</span>` : ''}
      <span class="caption">+${yen(i.price)}</span>
      ${soldOut
        ? '<span class="badge badge-soldout">売り切れ</span>'
        : `<div class="stepper">
             <button type="button" data-extra="${i.id}" data-step="-1" aria-label="${esc(i.name)}を減らす">−</button>
             <output data-out="${i.id}">${qty}</output>
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
  // カテゴリのタブ (野菜・肉・麺…)。押したカテゴリだけ見せる (長いスクロールをなくす) [Myat]
  // タブの数字 = そのカテゴリで追加した個数
  const cats = [...groups.keys()];
  if (!cats.includes(state.extraCat)) state.extraCat = cats[0] ?? 'all';
  let html = cats.length > 1
    ? `<div class="tabs" role="tablist" aria-label="具材のカテゴリ">
         ${cats.map(c => `<button type="button" class="tab" role="tab" data-cat="${esc(c)}">${esc(c)}<b class="cz-cat-count" data-cat-count="${esc(c)}"></b></button>`).join('')}
         <button type="button" class="tab" role="tab" data-cat="all">全部</button>
       </div>`
    : '';
  for (const [cat, items] of groups) {
    html += `<div data-cat-group="${esc(cat)}"><p class="caption"><b>${esc(cat)}</b></p><ul class="ing-grid">${items.map(extraRow).join('')}</ul></div>`;
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
  document.querySelectorAll('#extraList [data-cat-group]').forEach(g => {
    g.classList.toggle('d-none', !g.querySelector('li:not(.d-none)'));
  });
  // お気に入りはドロップダウン。検索で見つかったときだけ自動で開く
  const favVisible = $('favList').querySelector('li:not(.d-none)');
  $('favBox').classList.toggle('d-none', !favVisible);
  $('favBox').open = q !== '' ? Boolean(favVisible) : state.favOpen;

  $('noHitWord').textContent = $('ingSearch').value;
  $('noHit').classList.toggle('d-none', q === '' || hits > 0);
  renderCatTabs();
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
    const qty = state.added.get(Number(out.dataset.out)) || 0;
    out.textContent = qty;
    const tile = out.closest('.ing-tile');
    if (tile) tile.classList.toggle('is-added', qty > 0);
  });
  renderCatTabs();
  renderFavCount();
  // 追加した具材の個数 / 上限
  const used = addedUnits();
  $('addCount').textContent = state.maxAdd > 0 ? `（${used} / ${state.maxAdd}個）` : (used > 0 ? `（${used}個）` : '');
  // 上限に 着いたら ＋ を うすくして「もう入れられない」と 見てわかるように [Myat]
  const full = state.maxAdd > 0 && used >= state.maxAdd;
  $('addCount').classList.toggle('is-full', full);
  document.querySelectorAll('#builder [data-extra][data-step="1"]').forEach(btn => btn.classList.toggle('is-locked', full));
  $('qty').textContent = state.quantity;

  renderNoodle();
  renderPrice();
  saveDraft();
}

// 「最初から入っている具材」の 麺の行に、えらべる麺の チップを出す
function renderNoodle() {
  const box = $('noodleChips');
  const d = defaultNoodle();
  if (!box || !d) return;
  const from = state.extras.find(i => i.id === d.ingredient_id);
  const fromPrice = from ? from.price : 0;
  const current = state.noodleTo || d.ingredient_id;
  box.innerHTML = noodleOptions().map(o => {
    const isDefault = o.id === d.ingredient_id;
    const soldOut = !isDefault && (!o.is_available || o.stock <= 0);
    const diff = Math.max(0, o.price - fromPrice);
    const on = o.id === current;
    return `<button type="button" class="chip chip-sm ${on ? 'is-selected' : ''}" data-noodle="${o.id}" aria-pressed="${on}" ${soldOut ? 'disabled' : ''}>
      ${esc(o.name)}${!isDefault && diff > 0 ? ` <span class="chip-price">+${yen(diff)}</span>` : ''}${soldOut ? '（売り切れ）' : ''}
    </button>`;
  }).join('');
}

// 追加した具材の合計個数
function addedUnits() {
  let n = 0;
  for (const q of state.added.values()) n += q;
  return n;
}

// 閉じていても 何件あって 何個入れたか わかるように
function renderFavCount() {
  let inBowl = 0;
  for (const id of state.picks) inBowl += state.added.get(id) || 0;
  const count = state.extras.filter(i => state.picks.has(i.id)).length;
  $('favCount').textContent = count + '件' + (inBowl > 0 ? ` ・ ${inBowl}個 追加中` : '');
}

// 具材のカテゴリタブ: 選んだタブを赤く、そのカテゴリだけ見せる、追加した個数を出す [Myat]
// 検索中はタブを隠して、全部のカテゴリから探す (野菜タブのままでも エビ が見つかるように) [Lin]
function renderCatTabs() {
  const searching = normalize($('ingSearch').value) !== '';
  const tabs = document.querySelector('#extraList .tabs');
  if (tabs) tabs.classList.toggle('d-none', searching);
  document.querySelectorAll('#extraList [data-cat]').forEach(t => {
    const on = t.dataset.cat === state.extraCat;
    t.classList.toggle('is-active', on);
    t.setAttribute('aria-selected', on);
  });
  document.querySelectorAll('#extraList [data-cat-group]').forEach(g => {
    g.hidden = !searching && state.extraCat !== 'all' && g.dataset.catGroup !== state.extraCat;
  });
  document.querySelectorAll('#extraList [data-cat-count]').forEach(el => {
    let n = 0;
    for (const [id, qty] of state.added) {
      const ing = state.extras.find(i => i.id === id);
      if (ing && ing.category_name === el.dataset.catCount) n += qty;
    }
    el.textContent = n > 0 ? ` ${n}` : '';
  });
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

  // 麺を入れかえたら 差額だけ (サーバーの includes/bowl.php と 同じ計算)
  const to = swappedNoodle();
  const d = defaultNoodle();
  if (to && d) {
    const from = state.extras.find(i => i.id === d.ingredient_id);
    lines.push([`麺：${to.name}に変更`, Math.max(0, to.price - (from ? from.price : 0))]);
  }

  for (const [id, qty] of state.added) {
    const ing = state.extras.find(i => i.id === id);
    lines.push([`${ing.name} ×${qty}`, ing.price * qty]);
  }

  const unit = lines.reduce((sum, [, price]) => sum + price, 0);
  return { lines, unit, total: unit * state.quantity };
}

function bump(el) {
  el.classList.remove('is-bump');
  void el.offsetWidth;                         // アニメーションをもう一度始めるため
  el.classList.add('is-bump');
}

function renderPrice() {
  const p = calcPrice();
  let html = p.lines.map(([name, price]) => `<li><span>${esc(name)}</span><span>${yen(price)}</span></li>`).join('');
  html += `<li><b>1杯</b><b>${yen(p.unit)}</b></li>`;
  $('priceLines').innerHTML = html;
  if ($('total').textContent !== yen(p.total)) {   // 金額が変わったら ぴょんと動く
    bump($('total'));
  }
  $('total').textContent = yen(p.total);

  // 「自分で作る」で 1杯が 最低金額に足りないときは カートに入れられない
  const short = (state.menu && state.menu.is_custom && state.minPrice > 0) ? Math.max(0, state.minPrice - p.unit) : 0;
  let hint = $('minPriceHint');
  if (!hint) {
    hint = document.createElement('p');
    hint.id = 'minPriceHint';
    hint.className = 'min-price-hint';
    hint.setAttribute('role', 'status');
    $('addToCart').before(hint);
  }
  hint.hidden = short === 0;
  hint.innerHTML = short ? `「自分で作る」は 1杯 <b>${yen(state.minPrice)}</b> 以上から注文できます。<br>あと <b>${yen(short)}</b> 分 具材を追加してください。` : '';

  // 追加した具材が 上限より多い (上限ができる前に カートに入れた bowl など) → 注文できない。何個へらせばいいか 出す
  const over = state.maxAdd > 0 ? Math.max(0, addedUnits() - state.maxAdd) : 0;
  let overHint = $('maxAddHint');
  if (!overHint) {
    overHint = document.createElement('p');
    overHint.id = 'maxAddHint';
    overHint.className = 'min-price-hint';
    overHint.setAttribute('role', 'status');
    $('addToCart').before(overHint);
  }
  overHint.hidden = over === 0;
  overHint.innerHTML = over ? `追加できる具材は 最大 <b>${state.maxAdd}個</b> までです（いま ${addedUnits()}個）。<br>あと <b>${over}個</b> へらしてください。` : '';

  $('addToCart').disabled = state.soupId === null || short > 0 || over > 0;
  $('shareRecipe').disabled = state.soupId === null;
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

  // 今の bowl を友達に送る (ダイアログは share.js)
  if (b.id === 'shareRecipe') {
    openShareDialog({
      menu_id: state.menu.id,
      has_soup: state.hasSoup,
      soup_id: state.soupId,
      spice_level_id: state.spiceId,
      items: bowlItems(),
    }, state.menu.name);
    return;
  }

  if (b.dataset.noodle) {   // 麺をえらぶ: メニューの麺を押したら 元にもどす
    const id = Number(b.dataset.noodle);
    const d = defaultNoodle();
    state.noodleTo = (d && id === d.ingredient_id) ? null : id;
  }
  if (b.dataset.soup)  state.soupId  = Number(b.dataset.soup);
  if (b.dataset.spice) state.spiceId = Number(b.dataset.spice);
  if (b.dataset.broth) state.hasSoup = b.dataset.broth === '1';
  if (b.dataset.cat)   state.extraCat = b.dataset.cat;   // 具材のカテゴリタブ

  if (b.dataset.extra) {
    const id = Number(b.dataset.extra);
    const step = Number(b.dataset.step);
    // 外した「最初からの具材」に ＋ を押したら、まず 元に戻す (外す と 追加 を 同時にはしない)
    if (step > 0 && state.removed.has(id)) {
      state.removed.delete(id);
      toast('メニューの具材を元に戻しました');
      render();
      return;
    }
    // 追加できる具材の最大個数を超えないように (お店が決める。0 = 無制限)
    if (step > 0 && state.maxAdd > 0 && addedUnits() >= state.maxAdd) {
      toast(`追加できる具材は最大 ${state.maxAdd} 個までです`);
      return;
    }
    const next = Math.min(MAX_EXTRA, Math.max(0, (state.added.get(id) || 0) + step));
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
  if (e.target.checked) state.removed.delete(Number(id));
  else {
    state.removed.add(Number(id));
    state.added.delete(Number(id));   // 外すなら 追加した分も なし (サーバーは 同じ具材の 外す+追加 を受けない)
  }
  render();
});

// 具材の検索 (打つたびに絞り込む)
$('ingSearch').addEventListener('input', applySearch);

// お客さんが開け閉めした状態を覚える (検索が終わったら元に戻す)
$('favBox').querySelector('summary').addEventListener('click', () => { state.favOpen = !$('favBox').open; });

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
  // 麺の入れかえ: 元の麺 removed + えらんだ麺 added (追加でも 同じ麺を入れていたら その数に +1)
  const d = defaultNoodle();
  const sw = (d && state.noodleTo) ? state.noodleTo : null;
  const added = new Map(state.added);
  if (sw) added.set(sw, (added.get(sw) || 0) + 1);
  const removed = new Set(state.removed);
  if (sw) removed.add(d.ingredient_id);
  return [
    // 念のため: 外した具材は 追加に入れない (同じ具材が2回あると サーバーが エラーにする)
    ...[...added].filter(([id]) => !removed.has(id)).map(([id, qty]) => ({ ingredient_id: id, action: 'added', quantity: qty })),
    ...[...removed].map(id => ({ ingredient_id: id, action: 'removed', quantity: 1 })),
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

  if (!res.success) {
    btn.disabled = false;
    showError($('error'), res.error);
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return;
  }
  // 編集中なら 前の bowl を消す (= 入れ替え)。新しいのが入ってから消すので、失敗しても元は残る。
  if (state.editId) {
    await callApi('DELETE', '../api/cart.php?id=' + state.editId);
  }
  clearDraft();
  if (typeof refreshCartCount === 'function') refreshCartCount();
  const msg = state.editId ? 'カートを更新しました' : `カートに追加しました（${yen(res.data.price)}）`;
  $('toastArea').innerHTML = `<div class="toast" role="status">${esc(msg)}</div>`;
  setTimeout(() => { location.href = 'cart.php'; }, 900);
}

// ---------- 8. 自動で新しくする [Myat] ----------
// owner が 残り・販売・値段 を変えたら、再読み込みなしで具材の表示を変える (選んだものは残す)
async function refreshExtras() {
  if (!state.menu || document.hidden) return;   // 読み込み前 / 別のタブを見ているあいだは止める
  const res = await callApi('GET', '../api/ingredients.php');
  if (!res.success) return;                     // 失敗: いまの表示のまま (次の更新でまた読む)

  const before = state.extras.map(extraRow).join('');
  const extras = res.data;   // 最初から入っている具材も 追加できる (上の init と同じ)

  // 売り切れ・削除になった具材は bowl から外して知らせる
  const gone = [];
  for (const id of state.added.keys()) {
    const ing = extras.find(i => i.id === id);
    if (!ing || !ing.is_available || ing.stock <= 0) {
      gone.push(state.extras.find(i => i.id === id).name);
      state.added.delete(id);
    }
  }
  // えらんだ麺が 売り切れ → メニューの麺に もどす
  if (state.noodleTo) {
    const nt = extras.find(i => i.id === state.noodleTo);
    if (!nt || !nt.is_available || nt.stock <= 0) {
      gone.push((nt ? nt.name : '麺') + '（メニューの麺にもどしました）');
      state.noodleTo = null;
    }
  }
  state.extras = extras;
  if (gone.length) {
    $('notice').textContent = '売り切れになったので外しました: ' + gone.join('、');
    $('notice').classList.remove('d-none');
  }

  if (!gone.length && state.extras.map(extraRow).join('') === before) return;   // 変わっていなければ作り直さない
  renderExtras();
  render();
}

window.addEventListener('load', load);
setInterval(refreshExtras, 1000);
document.addEventListener('visibilitychange', refreshExtras);
window.addEventListener('pageshow', (e) => {
  if (e.persisted) refreshExtras();
});
