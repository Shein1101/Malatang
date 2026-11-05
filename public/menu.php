<?php
// public/menu.php — メニュー一覧 (customer). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/menus.php
// Card click → customize.php?menu_id=... (Zan)
// Cards = ready-made dishes only. 自分で作る (menus.is_custom) is the banner on top → customize.php (Lin)
$pageTitle = 'メニュー | Chilli 鍋';
include '../includes/header.php';
?>

<main>
  <section class="hero">
    <div class="container">
      <h1 class="title">メニュー</h1>
      <p>お好きな一杯を選んで、スープ・辛さ・具材をカスタマイズできます。</p>
      <form class="site-search search-in-page" action="menu.php" role="search">
        <svg aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" name="q" value="<?= $searchWord ?>" placeholder="メニューや具材を検索" aria-label="メニューや具材を検索">
      </form>
      <div id="chips"></div>
    </div>
  </section>

  <div class="container page">
    <p id="error" class="field-error d-none"></p>
    <!-- 自分で作る (一から具材・味を選ぶ) → customize.php -->
    <div id="customCta"></div>
    <p id="noHit" class="muted d-none"></p>
    <!-- menu のカードがここに並ぶ -->
    <div id="menuList" class="grid-4">
      <p class="muted">読み込み中…</p>
    </div>
  </div>
</main>

<script src="<?= asset_url('assets/js/menus.js') ?>"></script>
<script>
// 文字を安全に HTML に入れる
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}

// 自分で作る の案内 (売り切れ・削除のときは出さない)
function customCta(m) {
  if (!m || !m.is_available) return '';
  return `
    <a class="custom-cta" href="customize.php">
      <div>
        <p class="eyebrow">具材を一から選ぶ</p>
        <div class="heading m-0">${esc(m.name)}</div>
        <div class="caption">${esc(m.description)}</div>
      </div>
      <div class="text-nowrap"><span class="price">${yen(m.price)}〜</span> <span class="text-chili">→</span></div>
    </a>`;
}

<<<<<<< HEAD
// いま画面に出している HTML (null = まだ一度も出していない)
let shownHtml = null;

// API から menu をもらって、カードを並べる
=======
// API から menu をもらって、?cat= と ?q= で絞って、カードを並べる
>>>>>>> e1552280092981ba5f87bacaf0365f29b168471a
async function loadMenus() {
  const res = await callApi('GET', '../api/menus.php');
  const list = document.getElementById('menuList');
  if (!res.success) {
    if (shownHtml !== null) return;   // 自動更新の失敗: いまのカードを消さない (次の更新でまた読む)
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
<<<<<<< HEAD
  const cta = customCta(res.data.find(m => m.is_custom));
  let html = '';
  for (const m of res.data.filter(m => !m.is_custom)) {   // ready-made dish だけ
    html += menuCard(m);
  }
  if (cta + html === shownHtml) return;   // 変わっていなければ作り直さない (写真がちらつかない)
  shownHtml = cta + html;
  document.getElementById('customCta').innerHTML = cta;
  list.innerHTML = html;
=======
  const params = new URLSearchParams(location.search);
  const cat = MENU_CATS.find(c => c.key === params.get('cat'));
  const word = (params.get('q') || '').trim().toLowerCase();

  let dishes = res.data.filter(m => !m.is_custom);   // ready-made dish だけ
  if (cat) dishes = dishes.filter(cat.match);
  if (word) dishes = dishes.filter(m => (m.name + ' ' + (m.description || '')).toLowerCase().includes(word));

  document.getElementById('chips').innerHTML = menuChips(res.data, cat ? cat.key : null);
  // 探しているときは案内を出さない
  document.getElementById('customCta').innerHTML = cat || word ? '' : customCta(res.data.find(m => m.is_custom));
  list.innerHTML = dishes.map(menuCard).join('');

  const noHit = document.getElementById('noHit');
  noHit.textContent = word ? `「${params.get('q')}」に合うメニューはありません` : 'このカテゴリのメニューはありません';
  noHit.classList.toggle('d-none', dishes.length > 0);
>>>>>>> e1552280092981ba5f87bacaf0365f29b168471a
}

// app.js (callApi, yen) はフッターで読み込まれるので、ページを全部読んでから動かす
window.addEventListener('load', loadMenus);

// 自動で新しくする: owner が 残り を補充したら、再読み込みなしで 売り切れ が消える
//   5 秒ごと (別のタブを見ているあいだは止める) / タブに戻ったとき / 「戻る」で来たとき
setInterval(() => {
  if (!document.hidden) loadMenus();
}, 5000);
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) loadMenus();
});
window.addEventListener('pageshow', (e) => {
  if (e.persisted) loadMenus();
});
</script>

<?php include '../includes/footer.php'; ?>