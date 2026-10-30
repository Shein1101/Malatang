<?php
// admin/menus.php — メニュー管理 (owner). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/menus.php
// 追加 / 名前・説明・値段の変更 / 販売 ON・OFF / 削除
// 具材ボタン → そのメニューに最初から入っている具材 (数・外せない) をえらんで保存
$pageTitle = 'メニュー管理 | Chilli 鍋';
include '../includes/admin_header.php';
?>
 
<main class="container">
  <h1>メニュー管理</h1>
  <p id="message"></p>
 
  <!-- menu を追加 -->
  <form id="addForm" class="card card-body mb-4">
    <h2 class="h5">メニューを追加</h2>
    <div class="row g-2">
      <div class="col-md-3">
        <label class="form-label">名前</label>
        <input name="name" class="form-control" required maxlength="100">
      </div>
      <div class="col-md-5">
        <label class="form-label">説明</label>
        <input name="description" class="form-control">
      </div>
      <div class="col-md-2">
        <label class="form-label">値段（円）</label>
        <input name="price" type="number" min="0" class="form-control" required>
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-chili w-100">追加</button>
      </div>
    </div>
    <p class="caption mt-2 mb-0">※ 春雨は自動で入ります（お客さんは外せません）。
      メニューの具材がひとつでも品切れになると、そのメニューはお客さんには「売り切れ」になります。</p>
  </form>
 
  <!-- menu の一覧 -->
  <table class="table align-middle">
    <thead>
      <tr><th>名前</th><th>説明</th><th>値段（円）</th><th>販売</th><th></th></tr>
    </thead>
    <tbody id="list"><tr><td colspan="5">読み込み中…</td></tr></tbody>
  </table>

  <!-- 具材ボタンを押すと出る: 最初から入っている具材をえらぶ -->
  <div id="ingPanel" class="card card-body mb-4" hidden>
    <h2 class="h5">「<span id="ingMenuName"></span>」の具材</h2>
    <p class="caption">チェックした具材がこのメニューに最初から入ります（値段はメニューの値段に入っています）。
      「外せない」にすると、お客さんはカスタマイズで外せません。</p>
    <input id="ingSearch" type="search" class="form-control mb-2" style="max-width:240px"
           placeholder="具材を検索" autocomplete="off">
    <div id="ingList"></div>
    <div class="d-flex gap-2 mt-3">
      <button type="button" id="ingSave" class="btn btn-chili">具材を保存</button>
      <button type="button" id="ingClose" class="btn btn-ghost">閉じる</button>
      <span id="ingCount" class="text-muted small align-self-center"></span>
    </div>
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
 
// 1. 一覧を表示
async function loadList() {
  const res = await callApi('GET', '../api/menus.php');
  if (!res.success) { say(res.error, false); return; }
  let rows = '';
  for (const m of res.data) {
    rows += `
      <tr>
        <td><input class="form-control form-control-sm" id="name-${m.id}" value="${esc(m.name)}">${m.is_custom ? '<span class="caption">自分で作る のベース（メニュー一覧には出ません）</span>' : ''}</td>
        <td><input class="form-control form-control-sm" id="desc-${m.id}" value="${esc(m.description)}"></td>
        <td><input type="number" min="0" class="form-control form-control-sm" style="width:100px"
                   id="price-${m.id}" value="${m.price}"></td>
        <td>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="on-${m.id}" ${m.is_on ? 'checked' : ''}>
            <label class="form-check-label" for="on-${m.id}">${m.is_on ? 'ON' : 'OFF'}</label>
          </div>
          ${m.soldout_ingredients.length > 0
            ? `<div class="small text-danger" title="具材・在庫 で残りを増やすと、また注文できます">
                 <span class="badge badge-soldout">売り切れ中</span> 品切れ: ${esc(m.soldout_ingredients.join('、'))}
               </div>`
            : ''}
        </td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline" data-action="save" data-id="${m.id}">保存</button>
          <button class="btn btn-sm btn-outline" data-action="ingredients" data-id="${m.id}">具材</button>
          <button class="btn btn-sm btn-ghost" data-action="delete" data-id="${m.id}">削除</button>
        </td>
      </tr>`;
  }
  document.getElementById('list').innerHTML = rows;
}
 
// 2. 保存 / 削除
document.getElementById('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button');
  if (!btn) return;
  const id = btn.dataset.id;
  const name = document.getElementById(`name-${id}`).value;
 
  if (btn.dataset.action === 'save') {
    const res = await callApi('PUT', `../api/menus.php?id=${id}`, {
      name: name,
      description: document.getElementById(`desc-${id}`).value,
      price: Number(document.getElementById(`price-${id}`).value),
      is_available: document.getElementById(`on-${id}`).checked
    });
    say(res.success ? `「${name}」を保存しました` : res.error, res.success);
    if (res.success) {
      const on = document.getElementById(`on-${id}`);
      on.nextElementSibling.textContent = on.checked ? 'ON' : 'OFF';
    }
  }
 
  if (btn.dataset.action === 'ingredients') {
    openIngredients(id);
  }

  if (btn.dataset.action === 'delete') {
    if (!confirm(`「${name}」を削除しますか？`)) return;
    const res = await callApi('DELETE', `../api/menus.php?id=${id}`);
    say(res.success ? `「${name}」を削除しました` : res.error, res.success);
    if (res.success) {
      if (ingMenuId === id) document.getElementById('ingPanel').hidden = true;
      loadList();
    }
  }
});

// 4. 具材パネル: 全部の具材を出して、このメニューに入っているものにチェック
let ingMenuId = null;
async function openIngredients(id) {
  const [menuRes, allRes] = await Promise.all([
    callApi('GET', `../api/menus.php?id=${id}`),
    callApi('GET', '../api/ingredients.php')
  ]);
  if (!menuRes.success) { say(menuRes.error, false); return; }
  if (!allRes.success)  { say(allRes.error, false); return; }

  ingMenuId = id;
  const now = {};   // ingredient_id → { quantity, is_removable }
  for (const i of menuRes.data.ingredients) now[i.ingredient_id] = i;

  // カテゴリごとにまとめる
  let html = '';
  let lastCat = null;
  for (const i of allRes.data) {
    if (i.category_name !== lastCat) {
      if (lastCat !== null) html += '</div>';
      html += `<h3 class="h6 mt-3">${esc(i.category_name)}</h3><div class="row g-2">`;
      lastCat = i.category_name;
    }
    const cur = now[i.id];
    html += `
      <div class="col-md-4" data-ing="${i.id}" data-search="${esc(i.name)}">
        <div class="border rounded p-2 d-flex align-items-center gap-2">
          <input class="form-check-input mt-0" type="checkbox" id="use-${i.id}" ${cur ? 'checked' : ''}>
          <label class="flex-grow-1" for="use-${i.id}">${esc(i.name)}
            ${i.is_available ? '' : '<span class="badge badge-soldout">品切れ</span>'}</label>
          <input type="number" min="1" class="form-control form-control-sm" style="width:60px"
                 id="qty-${i.id}" value="${cur ? cur.quantity : 1}" title="数">
          <label class="small text-nowrap">
            <input class="form-check-input" type="checkbox" id="fixed-${i.id}" ${cur && !cur.is_removable ? 'checked' : ''}> 外せない
          </label>
        </div>
      </div>`;
  }
  if (lastCat !== null) html += '</div>';

  document.getElementById('ingMenuName').textContent = menuRes.data.name;
  document.getElementById('ingList').innerHTML = html;
  document.getElementById('ingSearch').value = '';
  document.getElementById('ingPanel').hidden = false;
  countIngredients();
  document.getElementById('ingPanel').scrollIntoView({ behavior: 'smooth' });
}

function countIngredients() {
  const n = document.querySelectorAll('#ingList input[id^="use-"]:checked').length;
  document.getElementById('ingCount').textContent = `${n} 個えらんでいます`;
}
document.getElementById('ingList').addEventListener('change', countIngredients);

// 具材の検索 (名前に入っているものだけ見せる)
document.getElementById('ingSearch').addEventListener('input', (e) => {
  const word = e.target.value.trim();
  document.querySelectorAll('#ingList [data-ing]').forEach(el => {
    el.hidden = word !== '' && !el.dataset.search.includes(word);
  });
});

document.getElementById('ingClose').addEventListener('click', () => {
  document.getElementById('ingPanel').hidden = true;
});

// 具材を保存: チェックしたものだけを新しいリストとして送る
document.getElementById('ingSave').addEventListener('click', async () => {
  const ingredients = [];
  document.querySelectorAll('#ingList [data-ing]').forEach(el => {
    const ingId = el.dataset.ing;
    if (!document.getElementById(`use-${ingId}`).checked) return;
    ingredients.push({
      ingredient_id: Number(ingId),
      quantity: Number(document.getElementById(`qty-${ingId}`).value),
      is_removable: !document.getElementById(`fixed-${ingId}`).checked
    });
  });
  const res = await callApi('PUT', `../api/menus.php?id=${ingMenuId}`, { ingredients });
  const menuName = document.getElementById('ingMenuName').textContent;
  say(res.success ? `「${menuName}」の具材を保存しました（${ingredients.length} 個）` : res.error, res.success);
});
 
// 5. 追加
document.getElementById('addForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const res = await callApi('POST', '../api/menus.php', {
    name: form.name.value,
    description: form.description.value,
    price: Number(form.price.value),
    sort_order: 99                       // 新しい menu は一番下
  });
  if (res.success) {
    say(`「${form.name.value}」を追加しました`, true);
    form.reset();
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
