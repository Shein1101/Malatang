// assets/js/home.js — ホーム (public/store.php). Owner: Lin
// ① API からブロックを読む → ② 並び順のとおりに HTML を作る
// デザイン: 大きなヒーロー + メニューのスライダー + お店が決めるブロック (お知らせ・割引・広告)

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

// ---------- メニューのスライダー (横スクロール。写真の上に名前・値段・ボタン) ----------
// 写真を主役にして、文字とボタンは写真の上に重ねる。濃いグラデーションで文字を読みやすく。
function slideCard(m) {
  const media = m.image_url
    ? `<img src="${esc(m.image_url)}" alt="" onerror="this.closest('.slide-media').classList.add('no-img');this.remove()">`
    : '';
  const name = esc(m.name.replace('【期間限定】', ''));
  const foot = m.is_available
    ? `<span class="slide-price">${yen(m.price)}</span>
       <a class="slide-order" href="customize.php?menu_id=${m.id}">注文する</a>`
    : `<span class="slide-tag">売り切れ</span>`;
  return `
    <article class="slide-card${m.is_available ? '' : ' is-soldout'}">
      <div class="slide-media${m.image_url ? '' : ' no-img'}">${media}<span class="slide-emoji">🍜</span></div>
      <div class="slide-overlay">
        <h3 class="slide-name">${name}</h3>
        <div class="slide-foot">${foot}</div>
      </div>
    </article>`;
}

function menuSlider(menus) {
  const dishes = menus.filter(m => !m.is_custom).slice(0, 10);
  if (!dishes.length) return '';
  return `
    <section class="h-section container">
      <div class="section-head">
        <h2 class="heading">トップメニュー 🔥</h2>
        <a class="link-more" href="menu.php">すべて見る →</a>
      </div>
      <div class="slider">
        <button type="button" class="slider-arrow prev" aria-label="前へ"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/></svg></button>
        <div class="slider-track" id="menuTrack">${dishes.map(slideCard).join('')}</div>
        <button type="button" class="slider-arrow next" aria-label="次へ"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
      </div>
    </section>`;
}

// ---------- ブロックごとの HTML ----------
const render = {
  // メインのヒーロー: 左にことば+ボタン+受け取り案内、右に大きな料理の写真 + 割引バッジ
  hero(b, d) {
    const s = d.store;
    const image = b.image_url || (s && s.banner_url);
    const action = linkBtn(b, 'btn-chili btn-lg') || `<a class="btn btn-chili btn-lg" href="menu.php">メニューを見る <span class="arrow"></span></a>`;
    const discount = d.promoBadge
      ? `<span class="hh-discount">${esc(d.promoBadge)}</span>` : '';
    const photo = image
      ? `<img src="${esc(image)}" alt="" onerror="this.closest('.hh-photo').classList.add('no-img');this.remove()">`
      : '';
    return `
      <section class="home-hero">
        <div class="container hh-grid">
          <div class="hh-copy">
            ${s ? `<p class="hh-status"><span class="dot ${s.is_open ? 'is-open' : ''}"></span>${s.is_open ? '営業中' : '本日はお休み'}${s.opening_hours ? ' · ' + esc(s.opening_hours) : ''}</p>` : ''}
            <h1 class="hh-title">${lines(b.title)}</h1>
            <p class="hh-sub">${lines(b.body)}</p>
            <div class="hh-actions">${action}</div>
            <div class="hh-badges">
              <div class="hh-badge">
                <span class="hh-badge-ico"><svg viewBox="0 0 24 24" width="20" height="20"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                <span class="hh-badge-txt"><b>かんたん注文</b>最短3分で完了</span>
              </div>
              <div class="hh-badge">
                <span class="hh-badge-ico"><svg viewBox="0 0 24 24" width="20" height="20"><use href="#i-store"></use></svg></span>
                <span class="hh-badge-txt"><b>テイクアウト</b>店頭でお受け取り</span>
              </div>
            </div>
          </div>
          <div class="hh-media">
            <div class="hh-photo ${image ? '' : 'no-img'}">
              ${photo}
              ${discount}
            </div>
          </div>
        </div>
      </section>
      ${menuSlider(d.menus)}`;
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

// スライダーの矢印 (← →) で横に動かす
function setupSliders() {
  document.querySelectorAll('.slider').forEach(slider => {
    const track = slider.querySelector('.slider-track');
    const step = () => Math.max(220, track.clientWidth * 0.8);
    slider.querySelector('.prev')?.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
    slider.querySelector('.next')?.addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));
  });
}

// ---------- お客さまの声 (reviews) ----------
const starStr = (n) => '★★★★★'.slice(0, n) + '☆☆☆☆☆'.slice(0, 5 - n);

function reviewCard(r) {
  const d = String(r.created_at).slice(0, 10).replace(/-/g, '/');
  return `
    <article class="review-card">
      <div class="r-stars" aria-label="${r.rating}点">${starStr(r.rating)}</div>
      ${r.comment ? `<p class="r-comment">${esc(r.comment)}</p>` : ''}
      <div class="r-name">${esc(r.name)}</div>
      <div class="r-date">${esc(d)}</div>
    </article>`;
}

function reviewFormHtml(mine) {
  const sel = (n) => mine && mine.rating === n ? ' selected' : '';
  const opt = (n) => `<option value="${n}"${sel(n)}>${starStr(n)}</option>`;
  return `
    <form class="review-form" id="reviewForm">
      <div class="rf-row">
        <label class="field-label m-0" for="revRating">評価</label>
        <select class="input" id="revRating">${[5, 4, 3, 2, 1].map(opt).join('')}</select>
      </div>
      <textarea class="input" id="revComment" rows="2" maxlength="300" placeholder="お店の感想をどうぞ（任意）">${mine && mine.comment ? esc(mine.comment) : ''}</textarea>
      <div class="rf-row">
        <button type="submit" class="btn btn-chili btn-sm">${mine ? 'レビューを更新する' : 'レビューを送る'}</button>
        <span id="revMsg" class="caption"></span>
      </div>
    </form>`;
}

async function loadReviews(loggedIn) {
  let wrap = document.getElementById('reviewsWrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.id = 'reviewsWrap';
    document.getElementById('blocks').insertAdjacentElement('afterend', wrap);
  }
  const [res, mineRes] = await Promise.all([
    callApi('GET', '../api/reviews.php'),
    loggedIn ? callApi('GET', '../api/reviews.php?mine=1') : Promise.resolve({ success: true, data: null }),
  ]);
  if (!res.success) { wrap.innerHTML = ''; return; }
  const { summary, reviews } = res.data;
  const mine = mineRes.success ? mineRes.data : null;
  const avgN = Math.round(summary.avg);

  const header = `
    <div class="section-head"><h2 class="heading">お客さまの声 ★</h2></div>
    ${summary.count ? `<div class="reviews-summary"><span class="reviews-avg">${summary.avg}</span><span><div class="reviews-stars">${starStr(avgN)}</div><div class="caption">${summary.count}件のレビュー</div></span></div>` : ''}`;
  const form = loggedIn
    ? reviewFormHtml(mine)
    : '<div class="review-prompt caption">レビューを書くには <a href="login.php">ログイン</a> してください。</div>';
  const list = reviews.length
    ? `<div class="review-grid">${reviews.map(reviewCard).join('')}</div>`
    : '<p class="review-empty">まだレビューはありません。最初のひとことをどうぞ！</p>';
  wrap.innerHTML = `<section class="h-section container">${header}${form}${list}</section>`;

  const f = document.getElementById('reviewForm');
  if (f) f.addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('revMsg');
    const r2 = await callApi('POST', '../api/reviews.php', {
      rating: Number(document.getElementById('revRating').value),
      comment: document.getElementById('revComment').value.trim(),
    });
    if (!r2.success) { msg.textContent = r2.error; msg.className = 'caption text-chili'; return; }
    loadReviews(true);   // 送ったら一覧を更新
  });
}

// ---------- 読み込み ----------
async function loadHome() {
  const [homeRes, storeRes, menuRes, optRes, meRes] = await Promise.all([
    callApi('GET', '../api/home.php'),
    callApi('GET', '../api/store.php'),
    callApi('GET', '../api/menus.php'),
    callApi('GET', '../api/options.php'),
    callApi('GET', '../api/auth.php?action=me'),
  ]);
  const failed = [homeRes, storeRes, menuRes, optRes].find(r => !r.success);
  if (failed) {
    showError(document.getElementById('error'), failed.error);
    return;
  }
  // 割引ブロックに「バッジ」があれば、ヒーローの写真の上に出す (例: 20% OFF)
  const promo = homeRes.data.find(x => x.type === 'promo' && x.badge);
  document.getElementById('blocks').innerHTML = renderBlocks(homeRes.data, {
    store: storeRes.data.store,
    announcements: storeRes.data.announcements,
    menus: menuRes.data,
    customMenu: menuRes.data.find(m => m.is_custom),
    soups: optRes.data.soups,
    spices: optRes.data.spice_levels,
    promoBadge: promo ? promo.badge : null,
  });
  setupSliders();
  loadReviews(!!(meRes.success && meRes.data));   // お客さまの声
}

window.addEventListener('load', loadHome);
