<?php
// admin/stock.php — 具材・在庫 (owner). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/ingredients.php
// 具材の追加 / 値段・週の在庫・残りの変更 (保存ボタン) / 販売 ON・OFF / 削除 を 1 ページで。
// 週の在庫 = 1 週間に用意する数。残り = 今の数 (お客さんが注文すると DB の trigger で減る)。
// 売れた = 週の在庫 − 残り。新しい週は「今週の在庫をセット」で 残り = 週の在庫 に戻す。
// (前の admin/ingredients.php はここにまとめた。メニューの ON / OFF は メニュー ページ)
$pageTitle = '具材・在庫 | Chilli 鍋';
include '../includes/admin_header.php';
?>

<main class="container">
  <h1>具材・在庫</h1>
  <p class="caption">お客さんが注文すると「残り」が自動で減ります（メニューに最初から入っている具材も）。「売れた」= 週の在庫 − 残り。残りが 0 になると自動で品切れになります。
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
      <div class="col-md-3">
        <label class="form-label">名前</label>
        <input name="name" class="form-control" required maxlength="100">
      </div>
      <div class="col-md-2">
        <label class="form-label">値段（円）</label>
        <input name="price" type="number" min="0" class="form-control" required>
      </div>
      <div class="col-md-2">
        <label class="form-label">週の在庫</label>
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

  <!-- しぼりこみ + 検索 + 新しい週 -->
  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <div class="tabs">
      <button type="button" class="tab is-active" data-filter="all">全部</button>
      <button type="button" class="tab" data-filter="low">残りわずか</button>
      <button type="button" class="tab" data-filter="out">品切れ</button>
    </div>
    <input id="search" type="search" class="form-control" style="max-width:240px"
           placeholder="具材を検索 (例: えび、きのこ)" autocomplete="off">
    <span id="searchCount" class="text-muted small"></span>
    <button type="button" id="restockBtn" class="btn btn-outline ms-auto">今週の在庫をセット（残り = 週の在庫）</button>
  </div>
  <!-- 「今週の在庫をセット」を押したときの確認 (confirm() の代わり) -->
  <div id="restockConfirm" class="alert alert-warning d-flex flex-wrap align-items-center gap-2" hidden>
    <span>すべての具材の「残り」を「週の在庫」の数に戻します。よろしいですか？</span>
    <button type="button" id="restockYes" class="btn btn-chili btn-sm">はい、セットする</button>
    <button type="button" id="restockNo" class="btn btn-ghost btn-sm">やめる</button>
  </div>

  <table class="table align-middle">
    <thead>
      <tr>
        <th>カテゴリ</th><th>名前</th><th>値段（円）</th><th>週の在庫</th><th>残り</th><th>売れた</th><th>販売</th><th>操作</th>
      </tr>
    </thead>
    <tbody id="list"><tr><td colspan="8">読み込み中…</td></tr></tbody>
  </table>
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

// 残りわずか = 残りが週の在庫の 20% 以下 (週の在庫が 0 のときは 5 以下)
function lowLine(i) {
  return i.weekly_stock > 0 ? Math.ceil(i.weekly_stock * 0.2) : 5;
}

// 1. 具材を全部もらって表にする
async function loadList() {
  const res = await callApi('GET', '../api/ingredients.php');
  if (!res.success) {
    say(res.error, false);
    return;
  }

  let rows = '';
  for (const i of res.data) {
    // out = 品切れ / low = 販売中だけど残りわずか / ok
    const state = !i.is_available ? 'out' : (i.stock <= lowLine(i) ? 'low' : 'ok');
    const red = state === 'ok' ? '' : 'border-danger text-danger';
    const sold = Math.max(i.weekly_stock - i.stock, 0);
    const pct = i.weekly_stock > 0 ? Math.min(Math.round(i.stock / i.weekly_stock * 100), 100) : 0;
    rows += `
      <tr data-state="${state}" data-search="${esc(normalize(i.name + ' ' + i.category_name))}">
        <td>${esc(i.category_name)}</td>
        <td>${esc(i.name)}</td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:90px"
                   id="price-${i.id}" value="${i.price}"></td>
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
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch"
                   data-switch="${i.id}" ${i.is_available ? 'checked' : ''}>
            <label class="form-check-label">${i.is_available ? '販売中' : '<span class="badge badge-soldout">品切れ</span>'}</label>
          </div>
        </td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline" data-action="save" data-id="${i.id}">保存</button>
          <button class="btn btn-sm btn-ghost" data-action="delete" data-id="${i.id}"
                  data-name="${esc(i.name)}">削除</button>
        </td>
      </tr>`;
  }
  document.getElementById('list').innerHTML = rows;
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

// 2. 今のしぼりこみ (all / low / out) と検索の文字に合う行だけ見せる
let filter = 'all';
function filterList() {
  const word = normalize(document.getElementById('search').value);
  const rows = document.querySelectorAll('#list tr[data-state]');
  let shown = 0;
  rows.forEach(tr => {
    const hit = (filter === 'all' || tr.dataset.state === filter)
             && (word === '' || tr.dataset.search.includes(word));
    tr.hidden = !hit;
    if (hit) shown++;
  });

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
    weekly_stock: Number(form.weekly_stock.value),
    stock: Number(form.stock.value)
  });
  if (res.success) {
    say(`「${form.name.value}」を追加しました`, true);
    form.name.value = '';
    form.price.value = '';
    form.weekly_stock.value = 100;
    form.stock.value = 100;
    loadList();
  } else {
    say(res.error, false);
  }
});

// 4. 表の中のボタン (保存 / 削除)
document.getElementById('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-action]');
  if (!btn) return;
  const id = btn.dataset.id;
  let res;

  if (btn.dataset.action === 'save') {
    // 値段・週の在庫・残り を PUT で送る
    res = await callApi('PUT', `../api/ingredients.php?id=${id}`, {
      price: Number(document.getElementById(`price-${id}`).value),
      weekly_stock: Number(document.getElementById(`weekly-${id}`).value),
      stock: Number(document.getElementById(`stock-${id}`).value)
    });
    say(res.success ? '保存しました' : res.error, res.success);
  }

  if (btn.dataset.action === 'delete') {
    if (!confirm(`「${btn.dataset.name}」を削除しますか？`)) return;
    res = await callApi('DELETE', `../api/ingredients.php?id=${id}`);
    say(res.success ? `「${btn.dataset.name}」を削除しました` : res.error, res.success);
  }

  loadList();   // 品切れ・売れた の表示も新しくなる
});

// 5. 販売 ON / OFF スイッチは、押したらすぐ保存
document.getElementById('list').addEventListener('change', async (e) => {
  const el = e.target;
  if (!el.dataset.switch) return;
  const res = await callApi('PUT', `../api/ingredients.php?id=${el.dataset.switch}`, {
    is_available: el.checked
  });
  say(res.success ? '保存しました' : res.error, res.success);
  loadList();
});

// 6. 新しい週: 全部の 残り を 週の在庫 に戻す (確認してから)
const restockConfirm = document.getElementById('restockConfirm');
document.getElementById('restockBtn').addEventListener('click', () => { restockConfirm.hidden = false; });
document.getElementById('restockNo').addEventListener('click', () => { restockConfirm.hidden = true; });
document.getElementById('restockYes').addEventListener('click', async () => {
  restockConfirm.hidden = true;
  const res = await callApi('PUT', '../api/ingredients.php?action=restock');
  say(res.success ? `${res.data.restocked} 件の具材の残りを週の在庫にセットしました` : res.error, res.success);
  loadList();
});

// 7. 自動で新しくする: お客さんの注文で減った 残り / 売れた を 30 秒ごとに読み直す
//    (表の数字を書きかけ・確認ボックスが出ているときは、消さないように待つ)
let dirty = false;
document.getElementById('list').addEventListener('input', () => { dirty = true; });
setInterval(() => {
  const typing = document.activeElement && document.activeElement.matches('#list input[type=number]');
  if (!dirty && !typing && restockConfirm.hidden && !document.hidden) loadList();
}, 30000);

window.addEventListener('load', loadList);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
