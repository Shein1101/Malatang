// Shared JavaScript helpers. Owner: Zan
// callApi() sends a request to our PHP API and returns the JSON answer.
// Example: const res = await callApi('GET', '../api/menus.php');
//          const res = await callApi('POST', '../api/cart.php', bowl);
// Always returns { success, data, error } — even when the network fails.
async function callApi(method, url, body = null) {
  const options = {
    method: method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin'               // send the session cookie (login, cart)
  };
  if (body !== null) {
    options.body = JSON.stringify(body);     // turn a JS object into JSON text
  }
  try {
    const response = await fetch(url, options); // send the request
    return await response.json();              // { success, data, error }
  } catch (e) {
    return { success: false, data: null, error: 'Network or server error' };
  }
}

// 1200 → "¥1,200"
function yen(n) {
  return '¥' + Number(n).toLocaleString('ja-JP');
}

// Show an error the same way on every page:
// showError(document.getElementById('error'), res.error)
function showError(el, message) {
  el.textContent = message;
  el.classList.remove('d-none');
}

// ヘッダーのカートの数を本当の数にする (ページを開くたびに動く)
// header.php の <span class="cart-count"> を使う。0 なら隠す。
async function refreshCartCount() {
  const badge = document.querySelector('.site-header .cart-count');
  if (!badge) return;                       // 管理画面などカートがないページ
  const res = await callApi('GET', '../api/cart.php');
  const count = res.success ? (res.data.item_count ?? res.data.count) : 0;
  badge.textContent = count;
  badge.style.display = count > 0 ? '' : 'none';
}
window.addEventListener('load', refreshCartCount);

// 友達から届いたレシピの数 (通知バッジ)。ヘッダーの人アイコンとマイページのタブに出す。
// .share-count はログインしている人のページにだけある。無ければ何もしない (guest / 管理画面)。
async function refreshShareCount() {
  const badges = document.querySelectorAll('.share-count');
  if (!badges.length) return;
  const res = await callApi('GET', '../api/shares.php?action=count');
  const count = res.success ? res.data.count : 0;
  badges.forEach(b => {
    b.textContent = count > 9 ? '9+' : count;
    b.style.display = count > 0 ? 'grid' : 'none';
  });
}
window.addEventListener('load', refreshShareCount);
// 別のタブから戻ってきたとき & ときどき 更新する (見ていない間は止める)
document.addEventListener('visibilitychange', () => { if (!document.hidden) refreshShareCount(); });
setInterval(() => { if (!document.hidden) refreshShareCount(); }, 25000);

// ヘッダーのアカウントメニュー (お気に入り・注文履歴・ファミリー&フレンズ・ログアウト)
// ボタンで開く / 外を押す・Esc で閉じる。ログインしていないページには無いので、無ければ何もしない。
function setupAccountMenu() {
  const menu = document.getElementById('acctMenu');
  if (!menu) return;
  const btn = document.getElementById('acctBtn');
  const list = document.getElementById('acctList');
  const open = (show) => { list.hidden = !show; btn.setAttribute('aria-expanded', show); };
  btn.addEventListener('click', (e) => { e.stopPropagation(); open(list.hidden); });
  document.addEventListener('click', (e) => { if (!e.target.closest('#acctMenu')) open(false); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !list.hidden) { open(false); btn.focus(); } });
}
window.addEventListener('load', setupAccountMenu);

// フッターの「お店について」「アクセス」を、お店が設定した内容で埋める (api/store.php)。
// フッターのある公開ページだけ。無ければ何もしない。
async function fillFooter() {
  const about = document.getElementById('footerAbout');
  if (!about) return;
  const res = await callApi('GET', '../api/store.php');
  if (!res.success || !res.data || !res.data.store) return;
  const s = res.data.store;
  if (s.description) about.textContent = s.description;
  const put = (id, label, value) => {
    const el = document.getElementById(id);
    if (!el) return;
    if (value) { el.textContent = label + value; el.classList.remove('d-none'); }
    else { el.classList.add('d-none'); }
  };
  put('footerAddress', '', s.address);
  put('footerHours', '営業時間：', s.opening_hours);
  put('footerPhone', 'TEL：', s.phone);

  // SNS リンク (お店が設定したものだけ出す)。
  // "instagram.com/.." のように http(s) を省いて入れても出せるように、無ければ付ける。
  const sns = (id, url) => {
    const el = document.getElementById(id);
    if (!el) return;
    let u = String(url || '').trim();
    if (u && !/^https?:\/\//i.test(u)) u = 'https://' + u;
    if (u && /^https?:\/\/.+/i.test(u)) { el.href = u; el.classList.remove('d-none'); }
    else el.classList.add('d-none');
  };
  sns('snsLine', s.line_url);
  sns('snsIg', s.instagram_url);
  sns('snsX', s.x_url);
}
window.addEventListener('load', fillFooter);
