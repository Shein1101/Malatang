<?php
// admin/stock.php — 具材・在庫 (owner). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/ingredients.php
// 具材の追加 / 値段・在庫・残りの変更 (変えたら自動で保存) / 販売 ON・OFF / 削除 を 1 ページで。
// 在庫 = 用意した数。残り = 今の数 (お客さんが注文すると DB の trigger で減る)。
// 売れた = 在庫 − 残り。1つの量 (例: 50g) はお客さんに見せる。
// (前の admin/ingredients.php はここにまとめた。メニューの ON / OFF は メニュー ページ)
$pageTitle = '具材・在庫 | Chilli 鍋';
include '../includes/admin_header.php';
?>

<main class="container">
  <h1>具材・在庫</h1>
  <p class="caption">お客さんが注文すると「残り」が自動で減ります（メニューに最初から入っている具材も）。「売れた」= 在庫 − 残り。残りが 0 になると自動で品切れになります。
    この表は 30 秒ごとに自動で新しくなります（入力中は止まります）。<span id="updatedAt"></span></p>
  <p id="message"></p>

  <!-- 具材を追加 -->
  <form id="addForm" class="card card-body mb-4">
    <h2 class="h5">具材を追加</h2>
    <div class="row g-2">
      <div class="col-md-2">
        <label class="form-label">カテゴリ</label>
        <select name="category_id" class="form-select" required></select>
      </div>
      <div class="col-md-2">
        <label class="form-label">名前</label>
        <input name="name" class="form-control" required maxlength="100">
      </div>
      <div class="col-md-2">
        <label class="form-label">値段（円）</label>
        <input name="price" type="number" min="0" class="form-control" required>
      </div>
      <div class="col-md-1">
        <label class="form-label">1つの量</label>
        <input name="portion" class="form-control" maxlength="20" placeholder="50g">
      </div>
      <div class="col-md-2">
        <label class="form-label">在庫</label>
        <input name="weekly_stock" type="number" min="0" value="100" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">残り</label>
        <input name="stock" type="number" min="0" value="100" class="form-control">
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <button class="btn btn-chili w-100">追加</button>
      </div>
    </div>
  </form>

  <!-- カテゴリ + しぼりこみ + 検索 (検索と しぼりこみ は全部のカテゴリから探す) -->
  <div class="adm-filter">
    <div class="tabs" id="catTabs" aria-label="カテゴリ"></div>
    <div class="adm-filter-row">
      <div class="tabs">
        <button type="button" class="tab is-active" data-filter="all">状態: すべて</button>
        <button type="button" class="tab" data-filter="low">残りわずか<span class="tab-count" id="countLow"></span></button>
        <button type="button" class="tab" data-filter="out">品切れ<span class="tab-count" id="countOut"></span></button>
      </div>
      <input id="search" type="search" class="form-control" style="max-width:240px"
             placeholder="具材を検索 (例: えび、きのこ)" autocomplete="off">
      <span id="searchCount" class="text-muted small"></span>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>名前</th><th>値段（円）</th><th title="お客さんのカスタマイズ画面に出ます">1つの量</th><th>在庫</th><th>残り</th><th>売れた</th><th>販売</th><th>操作</th>
        </tr>
      </thead>
      <tbody id="list"><tr><td colspan="8">読み込み中…</td></tr></tbody>
    </table>
  </div>
</main>

<script>
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function say(text, ok) {
  const msg = document.getElementById('message');
  msg.className = ok ? 'text-success' : 'text-danger';
  msg.textContent = text;
}

// 検索用に文字をそろえる: 小文字にする / ひらがな → カタカナ / 前後の空白をとる
function normalize(s) {
  return String(s ?? '').toLowerCase().trim()
    .replace(/[ぁ-ゖ]/g, c => String.fromCharCode(c.charCodeAt(0) + 0x60));
}

// 残りわずか = 残りが在庫の 20% 以下 (在庫が 0 のときは 5 以下)
function lowLine(i) {
  return i.weekly_stock > 0 ? Math.ceil(i.weekly_stock * 0.2) : 5;
}

function saleLabel(on) {
  return on ? '販売中' : '<span class="badge badge-soldout">品切れ</span>';
}

// 1. 具材を全部もらって表にする
async function loadList() {
  const res = await callApi('GET', '../api/ingredients.php');
  if (!res.success) {
    say(res.error, false);
    return;
  }

  // カテゴリごとにまとめて並べる
  const cats = [...new Set(res.data.map(i => i.category_name))];
  const sorted = cats.flatMap(c => res.data.filter(i => i.category_name === c));
  const alerts = {};
  let countLow = 0, countOut = 0;

  let rows = '';
  let lastCat = null;
  for (const i of sorted) {
    if (i.category_name !== lastCat) {
      lastCat = i.category_name;
      rows += `<tr class="cat-row" data-cat="${esc(lastCat)}"><th colspan="8">${esc(lastCat)}</th></tr>`;
    }
    // out = 品切れ / low = 販売中だけど残りわずか / ok
    const state = !i.is_available ? 'out' : (i.stock <= lowLine(i) ? 'low' : 'ok');
    if (state === 'out') countOut++;
    if (state === 'low') countLow++;
    if (state !== 'ok') alerts[i.category_name] = (alerts[i.category_name] || 0) + 1;
    const red = state === 'ok' ? '' : 'border-danger text-danger';
    const sold = Math.max(i.weekly_stock - i.stock, 0);
    const pct = i.weekly_stock > 0 ? Math.min(Math.round(i.stock / i.weekly_stock * 100), 100) : 0;
    rows += `
      <tr data-state="${state}" data-cat="${esc(i.category_name)}" data-name="${esc(i.name)}" data-search="${esc(normalize(i.name + ' ' + i.category_name))}">
        <td>${esc(i.name)}</td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:90px"
                   id="price-${i.id}" value="${i.price}"></td>
        <td><input class="form-control form-control-sm" style="width:80px" maxlength="20" placeholder="例: 50g"
                   id="portion-${i.id}" value="${esc(i.portion)}"></td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:80px"
                   id="weekly-${i.id}" value="${i.weekly_stock}"></td>
        <td>
          <input type="number" min="0" class="form-control form-control-sm ${red}" style="width:80px"
                 id="stock-${i.id}" value="${i.stock}">
          <div class="progress mt-1" style="height:4px;width:80px" title="残り ${pct}%">
            <div class="progress-bar ${state === 'ok' ? 'bg-success' : 'bg-danger'}" style="width:${pct}%"></div>
          </div>
        </td>
        <td>${sold}</td>
        <td>
          <div class="form-check form-switch" ${i.stock <= 0 ? 'title="残りを増やすと販売できます"' : ''}>
            <input class="form-check-input" type="checkbox" role="switch"
                   data-switch="${i.id}" ${i.is_available ? 'checked' : ''} ${i.stock <= 0 ? 'disabled' : ''}>
            <label class="form-check-label">${saleLabel(i.is_available)}</label>
          </div>
        </td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-ghost" data-action="delete" data-id="${i.id}"
                  data-name="${esc(i.name)}">削除</button>
        </td>
      </tr>`;
  }
  document.getElementById('list').innerHTML = rows;
  document.getElementById('countLow').textContent = countLow;
  document.getElementById('countOut').textContent = countOut;
  catList = cats.map(name => ({
    name,
    count: res.data.filter(i => i.category_name === name).length,
    alerts: alerts[name] || 0,
  }));
  if (category !== 'all' && !cats.includes(category)) setCategory(cats[0] ?? 'all');
  dirty = false;
  filterList();   // 保存して表を作り直しても、しぼりこみ・検索はそのまま
  document.getElementById('updatedAt').textContent =
    `（最終更新 ${new Date().toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}）`;

  // カテゴリの選択肢を作る (同じカテゴリは1回だけ)
  const select = document.querySelector('#addForm [name=category_id]');
  if (select.options.length === 0) {
    const seen = {};
    for (const i of res.data) {
      if (!seen[i.category_id]) {
        seen[i.category_id] = true;
        select.innerHTML += `<option value="${i.category_id}">${esc(i.category_name)}</option>`;
      }
    }
  }
}

// 2. カテゴリのタブ。今のカテゴリは覚えておく (ページを開き直しても同じ所)
let catList = [];
let category = savedCategory();
function savedCategory() {
  try {
    return sessionStorage.getItem('stockCategory') || '';
  } catch (e) {
    return '';
  }
}
function setCategory(name) {
  category = name;
  try {
    sessionStorage.setItem('stockCategory', name);
  } catch (e) {
    // 覚えられなくても 切り替えは動く
  }
}
function renderCatTabs(searching) {
  const tab = (key, label, count, alerts) => `
    <button type="button" class="tab ${!searching && category === key ? 'is-active' : ''}" data-cat="${esc(key)}">${esc(label)}<span class="tab-count">${count}</span>${alerts ? `<span class="tab-alert" title="残りわずか・品切れ">${alerts}</span>` : ''}</button>`;
  document.getElementById('catTabs').innerHTML =
    catList.map(c => tab(c.name, c.name, c.count, c.alerts)).join('')
    + tab('all', '全部', catList.reduce((n, c) => n + c.count, 0), catList.reduce((n, c) => n + c.alerts, 0));
}
document.getElementById('catTabs').addEventListener('click', (e) => {
  const btn = e.target.closest('[data-cat]');
  if (!btn) return;
  setCategory(btn.dataset.cat);
  document.getElementById('search').value = '';
  filterList();
});

// 今のカテゴリ・しぼりこみ (all / low / out)・検索の文字に合う行だけ見せる
// 検索しているときは 全部のカテゴリから探す
let filter = 'all';
function filterList() {
  const word = normalize(document.getElementById('search').value);
  const rows = document.querySelectorAll('#list tr[data-state]');
  let shown = 0;
  rows.forEach(tr => {
    const hit = (filter === 'all' || tr.dataset.state === filter)
             && (word !== '' ? tr.dataset.search.includes(word) : (category === 'all' || tr.dataset.cat === category));
    tr.hidden = !hit;
    if (hit) shown++;
  });
  // カテゴリの見出し: 2つ以上のカテゴリが見えるときだけ
  document.querySelectorAll('#list .cat-row').forEach(head => {
    const any = [...rows].some(tr => !tr.hidden && tr.dataset.cat === head.dataset.cat);
    head.hidden = !any || (word === '' && category !== 'all');
  });
  renderCatTabs(word !== '');

  const empty = document.getElementById('noResult');
  if (shown === 0 && rows.length > 0) {
    if (!empty) {
      document.getElementById('list').insertAdjacentHTML('beforeend',
        '<tr id="noResult"><td colspan="8" class="text-muted">該当する具材はありません</td></tr>');
    }
  } else if (empty) {
    empty.remove();
  }
  const all = filter === 'all' && word === '';
  document.getElementById('searchCount').textContent = all ? '' : `${shown} / ${rows.length} 件`;
}
document.getElementById('search').addEventListener('input', filterList);

document.querySelectorAll('.tab[data-filter]').forEach(btn => {
  btn.addEventListener('click', () => {
    filter = btn.dataset.filter;
    document.querySelectorAll('.tab[data-filter]').forEach(t =>
      t.classList.toggle('is-active', t.dataset.filter === filter));
    // 残りわずか・品切れ は 全部のカテゴリから見る
    if (filter !== 'all') setCategory('all');
    filterList();
  });
});

// 3. 「追加」
document.getElementById('addForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const res = await callApi('POST', '../api/ingredients.php', {
    category_id: Number(form.category_id.value),
    name: form.name.value,
    price: Number(form.price.value),
    portion: form.portion.value,
    weekly_stock: Number(form.weekly_stock.value),
    stock: Number(form.stock.value)
  });
  if (res.success) {
    say(`「${form.name.value}」を追加しました`, true);
    setCategory(form.category_id.selectedOptions[0].textContent);   // 追加した物が見えるように
    form.name.value = '';
    form.price.value = '';
    form.portion.value = '';
    form.weekly_stock.value = 100;
    form.stock.value = 100;
    loadList();
  } else {
    say(res.error, false);
  }
});

// 4. 削除ボタン
document.getElementById('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-action="delete"]');
  if (!btn) return;
  if (!confirm(`「${btn.dataset.name}」を削除しますか？`)) return;
  const res = await callApi('DELETE', `../api/ingredients.php?id=${btn.dataset.id}`);
  say(res.success ? `「${btn.dataset.name}」を削除しました` : res.error, res.success);
  loadList();
});

// 5. 値段・1つの量・在庫・残り は、変えたら自動で保存 (保存ボタンなし)
//    在庫・残り … 打ち終わって 0.8 秒たったら保存 (残り 0 → すぐ 品切れ)
//    値段・1つの量  … 欄から出たとき / Enter で保存 (打ちかけのまま残らないように)
const FIELDS = {
  'price-':   ['price', '値段'],
  'portion-': ['portion', '1つの量'],      // 文字 (例: 50g)。空 = 消す
  'weekly-':  ['weekly_stock', '在庫'],
  'stock-':   ['stock', '残り']
};
const timers = {};

async function saveField(input) {
  const prefix = Object.keys(FIELDS).find(p => input.id.startsWith(p));
  const [field, label] = FIELDS[prefix];
  const id = input.id.slice(prefix.length);
  const name = input.closest('tr').dataset.name;
  const isText = field === 'portion';
  if (!isText && input.value === '') return;   // 数字を消しただけのときは待つ

  const value = isText ? input.value.trim() : Number(input.value);
  const res = await callApi('PUT', `../api/ingredients.php?id=${id}`, { [field]: value });
  const shown = value === '' ? 'なし' : value;
  say(res.success ? `「${name}」の${label}を ${shown} にしました` : res.error, res.success);

  // 表を作り直す (品切れ・売れた・バーも新しくなる)。打っていた欄にカーソルを戻す
  const focusId = document.activeElement ? document.activeElement.id : '';
  await loadList();
  if (focusId) document.getElementById(focusId)?.focus();
}

document.getElementById('list').addEventListener('input', (e) => {
  const el = e.target;
  if (!el.id || !(el.id.startsWith('weekly-') || el.id.startsWith('stock-'))) return;
  clearTimeout(timers[el.id]);
  timers[el.id] = setTimeout(() => saveField(el), 800);
});

document.getElementById('list').addEventListener('change', async (e) => {
  const el = e.target;

  // 販売 ON / OFF スイッチは、押したらすぐ保存
  if (el.dataset.switch) {
    const res = await callApi('PUT', `../api/ingredients.php?id=${el.dataset.switch}`, {
      is_available: el.checked
    });
    say(res.success ? '保存しました' : res.error, res.success);
    loadList();
    return;
  }

  // 値段・1つの量は欄から出たとき。在庫・残りも、0.8 秒待たずに出たらここで保存
  if (el.id && Object.keys(FIELDS).some(p => el.id.startsWith(p))) {
    clearTimeout(timers[el.id]);
    saveField(el);
  }
});

// 6. 自動で新しくする: お客さんの注文で減った 残り / 売れた を 30 秒ごとに読み直す
//    (表の欄を書きかけのときは、消さないように待つ)
let dirty = false;
document.getElementById('list').addEventListener('input', (e) => {
  dirty = true;
  // 残り を 0 にしたら、すぐ 販売 を 品切れ に見せる (0.8 秒後に自動で保存 → DB でも 品切れ)
  if (e.target.id && e.target.id.startsWith('stock-') && e.target.value !== '') {
    const id = e.target.id.slice(6);
    const sw = document.querySelector(`[data-switch="${id}"]`);
    const zero = Number(e.target.value) <= 0;
    if (zero) sw.checked = false;
    sw.disabled = zero;
    sw.nextElementSibling.innerHTML = saleLabel(sw.checked);
  }
});
setInterval(() => {
  const typing = document.activeElement && document.activeElement.matches('#list input:not([type=checkbox])');
  if (!dirty && !typing && !document.hidden) loadList();
}, 30000);

window.addEventListener('load', loadList);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
