<?php
// public/friends.php — ファミリー&フレンズ. Owner: Lin (FEAT-20)
// Screen only, NO SQL. Data comes from ../api/shares.php and ../api/friends.php
// 1. 届いたレシピ: 友達・家族から届いた bowl → 同じ内容で注文 (カートへ)
// 2. 友達リスト:   メールアドレスで追加。レシピを送れるのはこのリストの人だけ
// 3. 送ったレシピ: 相手が見たか・注文したか
// レシピを送るのは お気に入り / 注文履歴 の「友達にシェア」から (assets/js/share.js)
// friends.php?share=5 → そのレシピを目立たせる (届いたレシピのリンク用)
require_once '../includes/auth.php';
require_login();

$pageTitle = 'ファミリー&フレンズ | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <h1 class="title">ファミリー&フレンズ</h1>
  <p class="caption">自分のカスタムを大切な人にシェア。届いたレシピは同じ内容でそのまま注文できます。</p>

  <div id="error" class="alert alert-danger d-none"></div>
  <div id="done" class="alert alert-success d-none">カートに入れました。<a href="cart.php">カートを見る →</a></div>

  <!-- ========== 1. 届いたレシピ ========== -->
  <section class="mb-5" id="inbox">
    <h2 class="heading">届いたレシピ</h2>
    <div id="inboxList" class="stack"><p>読み込み中…</p></div>
  </section>

  <!-- ========== 2. 友達リスト ========== -->
  <section class="mb-5" id="friends">
    <h2 class="heading">友達リスト</h2>
    <p class="caption">相手の登録メールアドレスで追加します。レシピを送れるのは、このリストにいる人だけです。</p>
    <ul id="friendList" class="list card px-3 mb-3"><li>読み込み中…</li></ul>

    <form id="addForm" class="card card-body">
      <div class="grid-2">
        <div class="field">
          <label class="field-label" for="addEmail"><svg><use href="#i-mail"></use></svg>メールアドレス</label>
          <input class="input" id="addEmail" type="email" required placeholder="friend@example.com">
        </div>
        <div class="field">
          <label class="field-label" for="addNickname">呼び名（なくてもOK）</label>
          <input class="input" id="addNickname" maxlength="50" placeholder="例: お母さん">
        </div>
      </div>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="chip-group" role="radiogroup" aria-label="関係">
          <label class="check"><input type="radio" name="relation" value="friend" checked> 友達</label>
          <label class="check"><input type="radio" name="relation" value="family"> 家族</label>
        </div>
        <button class="btn btn-chili btn-sm" id="addButton">＋ 追加する</button>
      </div>
    </form>
  </section>

  <!-- ========== 3. 送ったレシピ ========== -->
  <section id="sent">
    <h2 class="heading">送ったレシピ</h2>
    <p class="caption">お気に入りや注文履歴の「友達にシェア」から送れます。</p>
    <div id="sentList" class="stack"><p>読み込み中…</p></div>
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

function dateText(s) {
  return String(s).slice(0, 16).replace(/-/g, '/');
}

// 麻辣湯（麻辣味・スープあり）/ 辛さ / 追加 / 抜き  (favorites.php と同じ形)
function bowlLines(r) {
  const lines = [`${esc(r.menu_name)}（${esc(r.soup_name)}・${r.has_soup ? 'スープあり' : 'スープなし'}）`];
  if (r.spice_name) lines.push(`辛さ: ${r.spice_level}（${esc(r.spice_name)}）`);
  const added   = r.items.filter(i => i.action === 'added').map(i => `${esc(i.name)} ×${i.quantity}`);
  const removed = r.items.filter(i => i.action === 'removed').map(i => esc(i.name));
  if (added.length)   lines.push('追加: ' + added.join(', '));
  if (removed.length) lines.push('抜き: ' + removed.join(', '));
  return lines.join('<br>');
}

function messageBlock(r) {
  return r.message ? `<p class="m-0">「${esc(r.message)}」</p>` : '';
}

function priceBlock(r) {
  return r.price !== null
    ? `<span class="price">${yen(r.price)}</span>`
    : `<span class="badge badge-soldout">${esc(r.unavailable)}</span>`;
}

// ==================== 1. 届いたレシピ ====================
const highlight = Number(new URLSearchParams(location.search).get('share'));

function inboxCard(r) {
  const badge = r.status === 'new'     ? '<span class="badge badge-label">NEW</span>'
              : r.status === 'ordered' ? '<span class="badge badge-done">注文済み</span>'
              : '';
  const mark = r.id === highlight ? ' style="border-color: var(--chili)"' : '';
  return `
    <div class="card order-card" id="share-${r.id}"${mark}>
      <div class="order-top">
        <div><b>${esc(r.person_name)} さんからのレシピ</b> ${badge}<div class="caption">${dateText(r.created_at)}</div></div>
        <button class="icon-btn" data-delete="${r.id}" aria-label="削除" title="削除"><svg><use href="#i-trash"></use></svg></button>
      </div>
      ${messageBlock(r)}
      <div class="caption">${bowlLines(r)}</div>
      <div class="order-foot">
        <span>今の値段 ${priceBlock(r)}</span>
        <button class="btn btn-chili btn-sm" data-cart="${r.id}" ${r.price === null ? 'disabled' : ''}>同じ内容で注文</button>
      </div>
    </div>`;
}

async function loadInbox() {
  const res = await callApi('GET', '../api/shares.php');
  const list = document.getElementById('inboxList');
  if (!res.success) {
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  if (res.data.length === 0) {
    list.innerHTML = `
      <div class="empty">
        <svg><use href="#i-users"></use></svg>
        <p>まだ届いたレシピはありません。<br>友達にあなたのメールアドレスを教えて、追加してもらいましょう。</p>
      </div>`;
    return;
  }
  list.innerHTML = res.data.map(inboxCard).join('');
  const target = document.getElementById('share-' + highlight);
  if (target) target.scrollIntoView({ block: 'center' });
}

document.getElementById('inboxList').addEventListener('click', async function (e) {
  const btn = e.target.closest('button');
  if (!btn) return;
  hideMessages();

  let res;
  if (btn.dataset.cart) {
    btn.disabled = true;
    res = await callApi('POST', '../api/shares.php?action=cart&id=' + btn.dataset.cart);
    btn.disabled = false;
    if (res.success) {
      document.getElementById('done').classList.remove('d-none');
      document.getElementById('done').scrollIntoView({ block: 'center' });
      refreshCartCount();
      return;
    }
  } else if (btn.dataset.delete) {
    if (!confirm('このレシピを削除しますか？')) return;
    res = await callApi('DELETE', '../api/shares.php?id=' + btn.dataset.delete);
  } else {
    return;
  }

  if (!res.success) {
    showError(document.getElementById('error'), res.error);
  }
  loadInbox();   // 値段・売り切れも最新にする
});

// ==================== 2. 友達リスト ====================
let friends = [];

function friendRow(f) {
  const relation = f.relation === 'family'
    ? '<span class="badge badge-cooking">家族</span>'
    : '<span class="badge badge-received">友達</span>';
  const sub = f.nickname ? `<div class="caption">${esc(f.name)}</div>` : '';
  return `
    <li>
      <div><b>${esc(f.nickname || f.name)}</b> ${relation}${sub}</div>
      <div class="flex-shrink-0">
        <button class="icon-btn" data-edit="${f.friend_id}" aria-label="呼び名を変える" title="呼び名・関係を変える">✎</button>
        <button class="icon-btn" data-remove="${f.friend_id}" aria-label="外す" title="リストから外す"><svg><use href="#i-trash"></use></svg></button>
      </div>
    </li>`;
}

async function loadFriends() {
  const res = await callApi('GET', '../api/friends.php');
  const list = document.getElementById('friendList');
  if (!res.success) {
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  friends = res.data;
  list.innerHTML = friends.length
    ? friends.map(friendRow).join('')
    : '<li class="caption">まだいません。下のフォームから追加できます。</li>';
}

document.getElementById('addForm').addEventListener('submit', async function (e) {
  e.preventDefault();
  hideMessages();
  const btn = document.getElementById('addButton');
  btn.disabled = true;
  const res = await callApi('POST', '../api/friends.php', {
    email:    document.getElementById('addEmail').value,
    nickname: document.getElementById('addNickname').value,
    relation: document.querySelector('input[name="relation"]:checked').value,
  });
  btn.disabled = false;
  if (!res.success) {
    showError(document.getElementById('error'), res.error);
    return;
  }
  this.reset();
  loadFriends();
});

document.getElementById('friendList').addEventListener('click', async function (e) {
  const btn = e.target.closest('button');
  if (!btn) return;
  hideMessages();

  let res;
  if (btn.dataset.edit) {
    const f = friends.find(x => x.friend_id === Number(btn.dataset.edit));
    const name = prompt(`${f.name} さんの呼び名（空にすると登録名）`, f.nickname || '');
    if (name === null) return;
    const family = confirm('家族として登録しますか？\n（OK = 家族 / キャンセル = 友達）');
    res = await callApi('PUT', '../api/friends.php?friend_id=' + f.friend_id, {
      nickname: name, relation: family ? 'family' : 'friend',
    });
  } else if (btn.dataset.remove) {
    if (!confirm('友達リストから外しますか？（送ったレシピは消えません）')) return;
    res = await callApi('DELETE', '../api/friends.php?friend_id=' + btn.dataset.remove);
  } else {
    return;
  }

  if (!res.success) {
    showError(document.getElementById('error'), res.error);
  }
  loadFriends();
  loadSent();   // 送ったレシピの名前も変わる
});

// ==================== 3. 送ったレシピ ====================
const SENT_STATUS = {
  new:     ['未読',     'badge-soldout'],
  seen:    ['見ました', 'badge-received'],
  ordered: ['注文しました', 'badge-done'],
};

function sentCard(r) {
  const [label, cls] = SENT_STATUS[r.status] || [r.status, 'badge-received'];
  return `
    <div class="card order-card">
      <div class="order-top">
        <div><b>${esc(r.person_name)} さんへ</b> <span class="badge ${cls}">${label}</span><div class="caption">${dateText(r.created_at)}</div></div>
        <button class="icon-btn" data-delete="${r.id}" aria-label="削除" title="削除"><svg><use href="#i-trash"></use></svg></button>
      </div>
      ${messageBlock(r)}
      <div class="caption">${bowlLines(r)}</div>
    </div>`;
}

async function loadSent() {
  const res = await callApi('GET', '../api/shares.php?box=sent');
  const list = document.getElementById('sentList');
  if (!res.success) {
    list.innerHTML = '';
    showError(document.getElementById('error'), res.error);
    return;
  }
  list.innerHTML = res.data.length
    ? res.data.map(sentCard).join('')
    : '<p class="caption">まだ送ったレシピはありません。</p>';
}

document.getElementById('sentList').addEventListener('click', async function (e) {
  const btn = e.target.closest('[data-delete]');
  if (!btn) return;
  hideMessages();
  if (!confirm('このレシピを削除しますか？（相手の「届いたレシピ」からも消えます）')) return;
  const res = await callApi('DELETE', '../api/shares.php?id=' + btn.dataset.delete);
  if (!res.success) {
    showError(document.getElementById('error'), res.error);
  }
  loadSent();
});

window.addEventListener('load', function () {
  loadInbox();
  loadFriends();
  loadSent();
});
</script>

<?php include '../includes/footer.php'; ?>
