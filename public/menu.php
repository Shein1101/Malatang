<?php
// public/menu.php — メニュー一覧 (customer). Owner: Myat
// Screen only, NO SQL. Data comes from ../api/menus.php
// Card click → customize.php?menu_id=... (Zan)
// Cards = ready-made dishes only. 自分で作る (menus.is_custom) is the banner on top → customize.php (Lin)
$pageTitle = 'メニュー | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <div class="menu-layout">
    <!-- 左: 検索 + カテゴリ (PC では スクロールしても残る) -->
    <aside class="menu-side">
      <h1 class="menu-side-title">メニュー</h1>
      <form class="site-search search-in-page" action="menu.php" role="search">
        <svg aria-hidden="true"><use href="#i-search"></use></svg>
        <input type="search" name="q" value="<?= $searchWord ?>" placeholder="メニューや具材を検索" aria-label="メニューや具材を検索">
      </form>
      <div id="chips"></div>
    </aside>

    <!-- 右: 自分で作る の案内 + メニューのカード -->
    <div class="menu-main">
      <p id="error" class="field-error d-none"></p>
      <div id="customCta"></div>
      <p id="noHit" class="muted d-none"></p>
      <div id="menuList" class="menu-rows">
        <p class="muted">読み込み中…</p>
      </div>

      <!-- ドリンク (表示だけ。お店が admin/juices.php で管理) -->
      <section class="pt-0 mt-5" id="juiceSection" hidden>
        <div class="section-head"><h2 class="heading">ドリンク</h2></div>
        <div id="juiceList" class="juice-grid"></div>
      </section>
    </div>
  </div>
</main>

<script src="<?= asset_url('assets/js/bowl.js') ?>"></script>
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

// いま画面に出している HTML (null = まだ一度も出していない)
let shownHtml = null;

// API から menu をもらって、?cat= と ?q= で絞って、カードを並べる
async function loadMenus() {
  const res = await callApi('GET', '../api/menus.php');
  const list = document.getElementById('menuList');
  if (!res.success) {
    if (shownHtml !== null) return;   // 自動更新の失敗: いまのカードを消さない (次の更新でまた読む)
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  const params = new URLSearchParams(location.search);
  const catKey = params.get('cat');
  const isDrink = catKey === 'drink';                 // 「ドリンク」を選んだとき [Myat]
  const cat = MENU_CATS.find(c => c.key === catKey);
  const word = (params.get('q') || '').trim().toLowerCase();

  let dishes = res.data.filter(m => !m.is_custom);   // ready-made dish だけ
  if (isDrink) dishes = [];                           // ドリンクのときは 料理を出さない
  if (cat) dishes = dishes.filter(cat.match);
  if (word) dishes = dishes.filter(m => (m.name + ' ' + (m.description || '')).toLowerCase().includes(word));

  // 「ドリンク」は 左のメインメニュー (メニューの下) から選ぶので、カテゴリには 出さない
  const chips = menuChips(res.data, isDrink ? 'drink' : (cat ? cat.key : null));
  // 探しているときは案内を出さない
  const cta = cat || word || isDrink ? '' : customCta(res.data.find(m => m.is_custom));
  const html = dishes.map(menuCard).join('');
  if (chips + cta + html === shownHtml) return;   // 変わっていなければ作り直さない (写真がちらつかない)
  shownHtml = chips + cta + html;

  document.getElementById('chips').innerHTML = chips;
  document.getElementById('customCta').innerHTML = cta;
  list.innerHTML = html;
  list.hidden = isDrink;

  // ドリンクを出すのは「すべて」と「ドリンク」のときだけ (麻辣湯 などを選んでいるときは 出さない)
  document.getElementById('juiceSection').hidden = !(juices && juices.length) || !!cat || !!word;

  const noHit = document.getElementById('noHit');
  noHit.textContent = word ? `「${params.get('q')}」に合うメニューはありません` : 'このカテゴリのメニューはありません';
  noHit.classList.toggle('d-none', isDrink || dishes.length > 0);
}

// ドリンク (ジュース)。お店が OFF にしていたり 1つもなければ、セクションも カテゴリも 出さない。
let juices = null;
async function loadJuices() {
  const res = await callApi('GET', '../api/juices.php');
  juices = res.success ? res.data : [];
  if (!juices.length) return;
  document.getElementById('juiceList').innerHTML = juices.map(j => `
    <div class="juice-card">
      <div class="juice-media${j.image_url ? '' : ' no-img'}">${j.image_url ? `<img src="${esc(j.image_url)}" alt="${esc(j.name)}" onerror="this.parentElement.classList.add('no-img');this.remove()">` : ''}<span class="juice-emoji">🥤</span></div>
      <div class="juice-body">
        <div class="juice-name">${esc(j.name)}</div>
        ${j.description ? `<div class="caption">${esc(j.description)}</div>` : ''}
        <div class="row cz-between">
          <span class="price">${yen(j.price)}</span>
          <button type="button" class="btn btn-chili btn-sm" data-dadd="${j.id}">＋ 追加</button>
        </div>
      </div>
    </div>`).join('');
  shownHtml = null;   // 「ドリンク」のカテゴリを出すために 作り直す
  loadMenus();
}

// ドリンクを カートに入れる (カート画面と同じ API)
document.getElementById('juiceList').addEventListener('click', async (e) => {
  const b = e.target.closest('button[data-dadd]');
  if (!b) return;
  b.disabled = true;
  const res = await callApi('POST', '../api/cart.php?type=drink', { juice_id: Number(b.dataset.dadd), quantity: 1 });
  if (res.success) {
    b.textContent = '✓ 追加しました';
    refreshCartCount();
    setTimeout(() => { b.textContent = '＋ 追加'; b.disabled = false; }, 1500);
  } else {
    b.disabled = false;
    showError(document.getElementById('error'), res.error);
  }
});

// app.js (callApi, yen) はフッターで読み込まれるので、ページを全部読んでから動かす
window.addEventListener('load', loadMenus);
window.addEventListener('load', loadJuices);

// 自動で新しくする: owner が 残り を補充したら、再読み込みなしで 売り切れ が消える
//   1 秒ごと (別のタブを見ているあいだは止める) / タブに戻ったとき / 「戻る」で来たとき
setInterval(() => {
  if (!document.hidden) loadMenus();
}, 1000);
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) loadMenus();
});
window.addEventListener('pageshow', (e) => {
  if (e.persisted) loadMenus();
});
</script>

<?php include '../includes/footer.php'; ?>