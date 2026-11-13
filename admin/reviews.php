<?php
// admin/reviews.php — お客さまの声の管理 (owner). Owner: Lin
// 画面だけ。SQL なし。データは ../api/reviews.php。
// お店は: 隠す / 見せる を切り替える、ひどいものは消す。書くのはお客さん (ホーム画面)。
$pageTitle = 'レビュー管理 | Chilli 鍋';
include '../includes/admin_header.php';
?>

<main class="container">
  <h1>お客さまの声</h1>
  <p class="caption">お客さんがホーム画面で書いたレビューです。<strong>表示</strong>を OFF にするとホームから消えます。ひどい内容は削除できます（書くのはお客さんだけ）。</p>
  <p id="message"></p>

  <div class="card card-body mb-3" id="summary"></div>

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr><th>評価</th><th>コメント</th><th>お名前</th><th>日付</th><th>表示</th><th></th></tr>
      </thead>
      <tbody id="list"><tr><td colspan="6">読み込み中…</td></tr></tbody>
    </table>
  </div>
</main>

<script>
const API = '../api/reviews.php';
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function say(text, ok) {
  const msg = document.getElementById('message');
  msg.className = ok ? 'text-success' : 'text-danger';
  msg.textContent = text;
  window.scrollTo(0, 0);
}
const stars = (n) => '★★★★★'.slice(0, n) + '☆☆☆☆☆'.slice(0, 5 - n);
const ymd = (dt) => String(dt).slice(0, 10);

async function loadAll() {
  const res = await callApi('GET', API + '?all=1');
  if (!res.success) { say(res.error, false); return; }
  const { summary, reviews } = res.data;

  document.getElementById('summary').innerHTML = reviews.length
    ? `<div class="row cz-between"><span><b>${summary.count}</b> 件を表示中</span><span>平均 <b class="text-chili">★ ${summary.avg}</b></span></div>`
    : '<span class="text-muted">レビューはまだありません。</span>';

  document.getElementById('list').innerHTML = reviews.length ? reviews.map(r => `
    <tr>
      <td class="text-nowrap" style="color:#f5a623;font-size:16px">${stars(r.rating)}</td>
      <td>${r.comment ? esc(r.comment) : '<span class="text-muted">（コメントなし）</span>'}</td>
      <td class="text-nowrap">${esc(r.name)}</td>
      <td class="text-nowrap caption">${esc(ymd(r.created_at))}</td>
      <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" data-vis="${r.id}" ${r.is_visible ? 'checked' : ''}></div></td>
      <td><button class="btn btn-sm btn-ghost" data-del="${r.id}">削除</button></td>
    </tr>`).join('') : '<tr><td colspan="6" class="text-muted">まだありません</td></tr>';
}

document.getElementById('list').addEventListener('change', async (e) => {
  const el = e.target;
  if (!el.dataset.vis) return;
  const res = await callApi('PUT', `${API}?id=${el.dataset.vis}`, { is_visible: el.checked });
  say(res.success ? '保存しました' : res.error, res.success);
  if (!res.success) loadAll();
});

document.getElementById('list').addEventListener('click', async (e) => {
  const btn = e.target.closest('button[data-del]');
  if (!btn) return;
  if (!confirm('このレビューを削除しますか？')) return;
  const res = await callApi('DELETE', `${API}?id=${btn.dataset.del}`);
  say(res.success ? '削除しました' : res.error, res.success);
  loadAll();
});

window.addEventListener('load', loadAll);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
