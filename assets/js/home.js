// assets/js/home.js — ホーム (public/store.php). Owner: Lin
// ① API からブロックを読む → ② 並び順のとおりに HTML を作る

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
}
function lines(s) { return esc(s).replace(/\n/g, '<br>'); }

function linkBtn(b, cls) {
  return b.link_label && b.link_url
    ? `<a class="btn ${cls}" href="${esc(b.link_url)}">${esc(b.link_label)}</a>` : '';
}
function textLink(b) {
  return b.link_label && b.link_url
    ? `<a class="link-more" href="${esc(b.link_url)}">${esc(b.link_label)} →</a>` : '';
}

// ---------- ブロックごとの HTML ----------
const render = {
  hero(b, d) {
    const s = d.store;
    const image = b.image_url || (s && s.banner_url);
    const action = linkBtn(b, 'btn-chili');
    const dishes = d.menus.filter(m => !m.is_custom).slice(0, 8);
    return `
      <section class="hero">
        <div class="container h-hero-grid ${image ? 'has-image' : ''}">
          <div>
            ${s ? `<p class="h-status"><span class="${s.is_open ? 'is-open' : ''}">${s.is_open ? '営業中' : '本日はお休み'}</span>${s.opening_hours ? ' · ' + esc(s.opening_hours) : ''}</p>` : ''}
            <h1 class="title">${lines(b.title)}</h1>
            <p>${lines(b.body)}</p>
            ${action ? `<div class="h-actions">${action}</div>` : ''}
            ${menuChips(d.menus, null)}
          </div>
          ${image ? `<img src="${esc(image)}" alt="" onerror="this.parentElement.classList.remove('has-image');this.remove()">` : ''}
        </div>
      </section>
      ${dishes.length ? `
      <section class="h-section container">
        <div class="section-head">
          <h2 class="heading">おすすめのメニュー 🔥</h2>
          <a class="link-more" href="menu.php">すべて見る →</a>
        </div>
        <div class="grid-4">${dishes.map(menuCard).join('')}</div>
      </section>` : ''}`;
  },

  notices(b, d) {
    if (!d.announcements.length) return '';
    return `
      <section class="h-notices" aria-label="お知らせ">
        <ul class="container">
          ${d.announcements.map(a => `<li><b>${esc(a.title)}</b>${a.body ? `<span>${esc(a.body.replace(/\n/g, ' '))}</span>` : ''}</li>`).join('')}
        </ul>
      </section>`;
  },

  // 続いている割引は まとめて横に並べる (下の renderBlocks)
  promos(list) {
    return `
      <section class="h-section container">
        <div class="h-promos">
          ${list.map(b => `
            <article class="h-promo">
              ${b.badge ? `<span class="badge badge-label">${esc(b.badge)}</span>` : ''}
              <h2 class="h-promo-title">${esc(b.title)}</h2>
              ${b.body ? `<p class="muted">${lines(b.body)}</p>` : ''}
              ${b.end_date ? `<p class="caption">${esc(b.end_date.replace(/-/g, '/'))} まで</p>` : ''}
              ${textLink(b)}
            </article>`).join('')}
        </div>
      </section>`;
  },

  ad(b) {
    return `
      <section class="h-section container">
        <article class="h-ad ${b.image_url ? 'has-image' : ''}">
          <div class="h-ad-copy">
            <p class="caption">PR</p>
            <h2 class="heading">${esc(b.title)}</h2>
            ${b.body ? `<p class="muted">${lines(b.body)}</p>` : ''}
            ${linkBtn(b, 'btn-chili')}
          </div>
          ${b.image_url ? `<img src="${esc(b.image_url)}" alt="" onerror="this.closest('.h-ad').classList.remove('has-image');this.remove()">` : ''}
        </article>
      </section>`;
  },

  custom(b, d) {
    if (!d.customMenu || !d.customMenu.is_available) return '';
    const names = (list) => list.slice(0, 3).map(x => esc(x.name)).join('・');
    return `
      <section class="h-section container">
        <div class="h-head">
          <div>
            <h2 class="heading">${esc(b.title)}</h2>
            <p class="muted">${lines(b.body)}</p>
          </div>
          <div class="h-head-action">
            <span class="price">${yen(d.customMenu.price)}〜</span>
            ${linkBtn(b, 'btn-chili')}
          </div>
        </div>
        <ol class="h-steps">
          <li><strong>スープを選ぶ</strong><span>${names(d.soups)}</span></li>
          <li><strong>具材を選ぶ</strong><span>野菜・肉・海鮮を好きなだけ</span></li>
          <li><strong>辛さを選ぶ</strong><span>${names(d.spices)}</span></li>
        </ol>
      </section>`;
  },
};

function renderBlocks(blocks, data) {
  let html = '';
  for (let i = 0; i < blocks.length; i++) {
    if (blocks[i].type === 'promo') {
      const list = [];
      while (i < blocks.length && blocks[i].type === 'promo') list.push(blocks[i++]);
      i--;
      html += render.promos(list);
    } else if (render[blocks[i].type]) {
      html += render[blocks[i].type](blocks[i], data);
    }
  }
  return html;
}

// ---------- 読み込み ----------
async function loadHome() {
  const [homeRes, storeRes, menuRes, optRes] = await Promise.all([
    callApi('GET', '../api/home.php'),
    callApi('GET', '../api/store.php'),
    callApi('GET', '../api/menus.php'),
    callApi('GET', '../api/options.php'),
  ]);
  const failed = [homeRes, storeRes, menuRes, optRes].find(r => !r.success);
  if (failed) {
    showError(document.getElementById('error'), failed.error);
    return;
  }
  document.getElementById('blocks').innerHTML = renderBlocks(homeRes.data, {
    store: storeRes.data.store,
    announcements: storeRes.data.announcements,
    menus: menuRes.data,
    customMenu: menuRes.data.find(m => m.is_custom),
    soups: optRes.data.soups,
    spices: optRes.data.spice_levels,
  });
}

window.addEventListener('load', loadHome);
