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
  const count = res.success ? res.data.count : 0;
  badge.textContent = count;
  badge.style.display = count > 0 ? '' : 'none';
}
window.addEventListener('load', refreshCartCount);
