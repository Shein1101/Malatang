<?php
// public/favorites.php — お気に入り. Owner: Lin
// Screen only, NO SQL. Data comes from ../api/favorites.php and ../api/ingredients.php
// 1. お気に入りの具材: よく入れる具材 (カスタマイズ画面で上に出る)
// 2. マイレシピ: カスタムした bowl をまるごと保存 → ワンタップでカートへ
require_once '../includes/auth.php';
require_login();

$pageTitle = 'お気に入り | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <h1 class="title">お気に入り</h1>

  <div id="error" class="alert alert-danger d-none"></div>
  <div id="done" class="alert alert-success d-none">カートに入れました。<a href="cart.php">カートを見る →</a></div>

  <!-- ========== 1. お気に入りの具材 ========== -->
  <section class="mb-5">
    <h2 class="heading">お気に入りの具材</h2>
    <p class="caption">よく入れる具材を登録すると、カスタマイズのときに探さなくてすみます。</p>
    <div id="pickList" class="chip-group mb-3"><p>読み込み中…</p></div>

    <details>
      <summary class="btn btn-outline btn-sm">＋ 具材をえらぶ</summary>
      <div id="allIngredients" class="stack-sm mt-3"></div>
    </details>
  </section>

  <!-- ========== 2. マイレシピ ========== -->
  <section>
    <h2 class="heading">マイレシピ</h2>
    <p class="caption">いつものカスタムを、ワンタップでカートに入れられます。</p>
    <div id="recipeList" class="stack"><p>読み込み中…</p></div>
  </section>
</main>

<script>
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

function hideMessages() {
  document.getElementById('error').classList.add('d-none');
  document.getElementById('done').classList.add('d-none');
}

// ==================== 1. お気に入りの具材 ====================
let picks = [];          // お気に入りの具材
let ingredients = [];    // 全部の具材 (えらぶ用)

function pickChip(p) {
  const soldout = p.is_available ? '' : ' <span class="badge badge-soldout">売り切れ</span>';
  return `<button class="chip is-selected" data-unpick="${p.ingredient_id}" title="外す">
            ${esc(p.name)} ${yen(p.price)}${soldout} ✕
          </button>`;
}

// カテゴリごとに、全部の具材をチップで並べる (♡ = お気に入り)
function renderAllIngredients() {
  const picked = new Set(picks.map(p => p.ingredient_id));
  const groups = {};
  for (const i of ingredients) {
    (groups[i.category_name] ??= []).push(i);
  }
  let html = '';
  for (const [category, list] of Object.entries(groups)) {
    html += `<div><div class="caption mb-1">${esc(category)}</div><div class="chip-group">`;
    for (const i of list) {
      const on = picked.has(i.id);
      html += `<button class="chip" aria-pressed="${on}" data-toggle="${i.id}">${on ? '♥' : '♡'} ${esc(i.name)}</button>`;
    }
    html += '</div></div>';
  }
  document.getElementById('allIngredients').innerHTML = html;
}

function renderPicks() {
  document.getElementById('pickList').innerHTML = picks.length
    ? picks.map(pickChip).join('')
    : '<p class="caption">まだありません。「＋ 具材をえらぶ」から追加できます。</p>';
  renderAllIngredients();
}

async function loadPicks() {
  const [res, all] = await Promise.all([
    callApi('GET', '../api/favorites.php?type=ingredient'),
    callApi('GET', '../api/ingredients.php'),
  ]);
  if (!res.success || !all.success) {
    document.getElementById('pickList').innerHTML = '';
    showError(document.getElementById('error'), res.error || all.error);
    return;
  }
  picks = res.data;
  ingredients = all.data;
  renderPicks();
}

async function togglePick(ingredientId) {
  const on = picks.some(p => p.ingredient_id === ingredientId);
  const res = on
    ? await callApi('DELETE', '../api/favorites.php?type=ingredient&ingredient_id=' + ingredientId)
    : await callApi('POST', '../api/favorites.php?type=ingredient', { ingredient_id: ingredientId });
  if (!res.success) {
    showError(document.getElementById('error'), res.error);
  }
  const again = await callApi('GET', '../api/favorites.php?type=ingredient');
  if (again.success) {
    picks = again.data;
    renderPicks();
  }
}

document.getElementById('pickList').addEventListener('click', function (e) {
  const btn = e.target.closest('[data-unpick]');
  if (!btn) return;
  hideMessages();
  togglePick(Number(btn.dataset.unpick));
});

document.getElementById('allIngredients').addEventListener('click', function (e) {
  const btn = e.target.closest('[data-toggle]');
  if (!btn) return;
  hideMessages();
  togglePick(Number(btn.dataset.toggle));
});

// ==================== 2. マイレシピ ====================
// 麻辣湯（麻辣味・スープあり）/ 辛さ / 追加 / 抜き
function bowlLines(f) {
  const lines = [`${esc(f.menu_name)}（${esc(f.soup_name)}・${f.has_soup ? 'スープあり' : 'スープなし'}）`];
  if (f.spice_name) lines.push(`辛さ: ${f.spice_level}（${esc(f.spice_name)}）`);
  const added   = f.items.filter(i => i.action === 'added').map(i => `${esc(i.name)} ×${i.quantity}`);
  const removed = f.items.filter(i => i.action === 'removed').map(i => esc(i.name));
  if (added.length)   lines.push('追加: ' + added.join(', '));
  if (removed.length) lines.push('抜き: ' + removed.join(', '));
  return lines.join('<br>');
}

function recipeCard(f) {
  const price = f.price !== null
    ? `<span class="price">${yen(f.price)}</span>`
    : `<span class="badge badge-soldout">${esc(f.unavailable)}</span>`;
  return `
    <div class="card order-card">
      <div class="order-top">
        <b>${esc(f.nickname || f.menu_name)}</b>
        <div>
          <button class="icon-btn" data-rename="${f.id}" aria-label="名前を変える" title="名前を変える">✎</button>
          <button class="icon-btn" data-delete="${f.id}" aria-label="削除" title="削除"><svg><use href="#i-trash"></use></svg></button>
        </div>
      </div>
      <div class="caption">${bowlLines(f)}</div>
      <div class="order-foot">
        <span>今の値段 ${price}</span>
        <button class="btn btn-chili btn-sm" data-cart="${f.id}" ${f.price === null ? 'disabled' : ''}>カートに入れる</button>
      </div>
    </div>`;
}

let recipes = [];

async function loadRecipes() {
  const res = await callApi('GET', '../api/favorites.php');
  const list = document.getElementById('recipeList');
  if (!res.success) {
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  recipes = res.data;
  if (recipes.length === 0) {
    list.innerHTML = `
      <div class="empty">
        <svg><use href="#i-heart"></use></svg>
        <p>マイレシピはまだありません。<br>カスタマイズ画面や注文履歴の「♡」から保存できます。</p>
        <a class="btn btn-outline" href="history.php">注文履歴を見る</a>
      </div>`;
    return;
  }
  list.innerHTML = recipes.map(recipeCard).join('');
}

document.getElementById('recipeList').addEventListener('click', async function (e) {
  const btn = e.target.closest('button');
  if (!btn) return;
  hideMessages();

  let res;
  if (btn.dataset.cart) {
    btn.disabled = true;
    res = await callApi('POST', '../api/favorites.php?action=cart&id=' + btn.dataset.cart);
    btn.disabled = false;
    if (res.success) {
      document.getElementById('done').classList.remove('d-none');
      return;
    }
  } else if (btn.dataset.rename) {
    const f = recipes.find(x => x.id === Number(btn.dataset.rename));
    const name = prompt('レシピの名前（例: いつもの）', f.nickname || '');
    if (name === null) return;
    res = await callApi('PUT', '../api/favorites.php?id=' + f.id, { nickname: name });
  } else if (btn.dataset.delete) {
    if (!confirm('このレシピを削除しますか？')) return;
    res = await callApi('DELETE', '../api/favorites.php?id=' + btn.dataset.delete);
  } else {
    return;
  }

  if (!res.success) {
    showError(document.getElementById('error'), res.error);
  }
  loadRecipes();   // 値段・売り切れも最新にする
});

window.addEventListener('load', function () {
  loadPicks();
  loadRecipes();
});
</script>

<?php include '../includes/footer.php'; ?>
