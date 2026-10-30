<?php
// admin/supplies.php — 備品・消耗品 (owner). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/supplies.php
// 塩・油・ティッシュ・スプーンなど、売り物ではないお店の物。お客さんの画面には出ない。
// 数・最低数・メモは変えたらすぐ保存 (保存ボタンなし)。数 <= 最低数 → 残りわずか
$pageTitle = '備品・消耗品 | Chilli 鍋';
include '../includes/admin_header.php';
?>

<main class="container">
  <h1>備品・消耗品</h1>
  <p class="caption">調味料・ティッシュ・スプーンなど、お店で使う物の数を管理します（お客さんの画面には出ません）。数字を変えるとすぐ保存されます。数が「最低数」以下になると 残りわずか になります。</p>
  <p id="message"></p>

  <!-- まとめ: 今すぐ買いに行く物の数 (押すとその一覧にしぼる) -->
  <div class="d-flex flex-wrap gap-2 mb-3">
    <button type="button" class="btn btn-outline text-danger" data-filter="low">⚠ 残りわずか <strong id="countLow">-</strong> 件</button>
    <button type="button" class="btn btn-outline" data-filter="out">✖ なくなった <strong id="countOut">-</strong> 件</button>
  </div>

  <!-- 追加フォーム -->
  <form id="addForm" class="card card-body mb-4">
    <h2 class="h5">備品を追加</h2>
    <div class="row g-2">
      <div class="col-md-2">
        <label class="form-label">カテゴリ</label>
        <select name="category" class="form-select" required>
          <option>調味料</option>
          <option>消耗品</option>
          <option>道具</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">名前</label>
        <input name="name" class="form-control" required maxlength="100" placeholder="例: 塩">
      </div>
      <div class="col-md-2">
        <label class="form-label">単位</label>
        <input name="unit" class="form-control" required maxlength="20" value="個" list="unitList">
        <datalist id="unitList">
          <option value="個"><option value="本"><option value="箱"><option value="袋">
          <option value="枚"><option value="膳"><option value="kg"><option value="g"><option value="L">
        </datalist>
      </div>
      <div class="col-md-2">
        <label class="form-label">数</label>
        <input name="quantity" type="number" min="0" step="0.1" value="0" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">最低数 <span class="text-muted small">(0 = 通知なし)</span></label>
        <input name="min_quantity" type="number" min="0" step="0.1" value="0" class="form-control">
      </div>
      <div class="col-md-1 d-flex align-items-end">
        <button class="btn btn-chili w-100">追加</button>
      </div>
    </div>
  </form>

  <!-- しぼりこみ (全部 / 残りわずか / なくなった) + 検索 -->
  <h2 class="h5" id="listTitle">一覧</h2>
  <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <div class="tabs">
      <button type="button" class="tab is-active" data-filter="all">全部</button>
      <button type="button" class="tab" data-filter="low">残りわずか</button>
      <button type="button" class="tab" data-filter="out">なくなった</button>
    </div>
    <input id="search" type="search" class="form-control" style="max-width:260px"
           placeholder="検索 (例: しお、すぷーん)" autocomplete="off">
    <span id="searchCount" class="text-muted small"></span>
  </div>

  <table class="table align-middle">
    <thead>
      <tr><th>カテゴリ</th><th>名前</th><th>数</th><th>最低数</th><th>メモ</th><th></th></tr>
    </thead>
    <tbody id="list"><tr><td colspan="6">読み込み中…</td></tr></tbody>
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

// out = 0 になった / low = 最低数以下 (最低数 0 の物は通知しない) / ok
function stateOf(s) {
  if (s.quantity <= 0) return 'out';
  if (s.min_quantity > 0 && s.quantity <= s.min_quantity) return 'low';
  return 'ok';
}

// 1. 一覧を読み込んで表にする
async function loadList() {
  const res = await callApi('GET', '../api/supplies.php');
  if (!res.success) {
    say(res.error, false);
    return;
  }

  let rows = '';
  let countLow = 0, countOut = 0;
  for (const s of res.data) {
    const state = stateOf(s);
    if (state === 'out') countOut++;
    if (state === 'low') countLow++;
    const red = state === 'ok' ? '' : 'border-danger text-danger';
    rows += `
      <tr data-state="${state}" data-search="${esc(normalize(s.name + ' ' + s.category + ' ' + (s.note ?? '')))}">
        <td>${esc(s.category)}</td>
        <td>${esc(s.name)}</td>
        <td class="text-nowrap">
          <input type="number" min="0" step="0.1" class="form-control form-control-sm d-inline-block ${red}" style="width:90px"
                 data-id="${s.id}" data-field="quantity" value="${s.quantity}"> ${esc(s.unit)}
        </td>
        <td class="text-nowrap">
          <input type="number" min="0" step="0.1" class="form-control form-control-sm d-inline-block" style="width:80px"
                 data-id="${s.id}" data-field="min_quantity" value="${s.min_quantity}"> ${esc(s.unit)}
        </td>
        <td><input class="form-control form-control-sm" maxlength="255"
                   data-id="${s.id}" data-field="note" value="${esc(s.note)}"></td>
        <td><button class="btn btn-sm btn-ghost" data-delete="${s.id}" data-name="${esc(s.name)}">削除</button></td>
      </tr>`;
  }
  document.getElementById('list').innerHTML = rows || '<tr><td colspan="6" class="text-muted">まだありません</td></tr>';
  document.getElementById('countLow').textContent = countLow;
  document.getElementById('countOut').textContent = countOut;
  filterList();   // 保存して表を作り直しても、しぼりこみ・検索はそのまま
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
        '<tr id="noResult"><td colspan="6" class="text-muted">該当する物はありません</td></tr>');
    }
  } else if (empty) {
    empty.remove();
  }
  const all = filter === 'all' && word === '';
  document.getElementById('searchCount').textContent = all ? '' : `${shown} / ${rows.length} 件`;
}
document.getElementById('search').addEventListener('input', filterList);

// しぼりこみボタン (上のまとめボタンも同じ動き)
document.querySelectorAll('[data-filter]').forEach(btn => {
  btn.addEventListener('click', () => {
    filter = btn.dataset.filter;
    document.querySelectorAll('.tab[data-filter]').forEach(t =>
      t.classList.toggle('is-active', t.dataset.filter === filter));
    filterList();
    if (!btn.classList.contains('tab')) {
      document.getElementById('listTitle').scrollIntoView({ behavior: 'smooth' });
    }
  });
});

// 3. 数・最低数・メモを変えたら、すぐ PUT で保存
document.getElementById('list').addEventListener('change', async (e) => {
  const el = e.target;
  if (!el.dataset.field) return;
  const value = el.dataset.field === 'note' ? el.value : Number(el.value);
  const res = await callApi('PUT', `../api/supplies.php?id=${el.dataset.id}`, { [el.dataset.field]: value });
  say(res.success ? '保存しました' : res.error, res.success);
  loadList();   // 残りわずか の表示と件数を新しくする
});

// 4. 削除
document.getElementById('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-delete]');
  if (!btn) return;
  if (!confirm(`「${btn.dataset.name}」を削除しますか？`)) return;
  const res = await callApi('DELETE', `../api/supplies.php?id=${btn.dataset.delete}`);
  say(res.success ? `「${btn.dataset.name}」を削除しました` : res.error, res.success);
  loadList();
});

// 5. 追加
document.getElementById('addForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const res = await callApi('POST', '../api/supplies.php', {
    category: form.category.value,
    name: form.name.value,
    unit: form.unit.value,
    quantity: Number(form.quantity.value),
    min_quantity: Number(form.min_quantity.value)
  });
  if (res.success) {
    say(`「${form.name.value}」を追加しました`, true);
    form.name.value = '';
    form.quantity.value = 0;
    form.min_quantity.value = 0;
    loadList();
  } else {
    say(res.error, false);
  }
});

window.addEventListener('load', loadList);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
