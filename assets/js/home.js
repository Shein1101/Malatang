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

// ---------- メニューのスライダー (menus.js の menuCard と同じカード。矢印は見出しの横) ----------
const ARROW = (d) => `<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="${d}"/></svg>`;

function menuSlider(menus) {
  const dishes = menus.filter(m => !m.is_custom).slice(0, 10);
  if (!dishes.length) return '';
  return `
    <section class="h-section container slider">
      <div class="section-head">
        <div><p class="kicker">Signature bowls<small>おすすめ</small></p><h2 class="heading">おすすめのメニュー</h2></div>
        <div class="head-actions">
          <a class="link-more" href="menu.php">すべて見る <span aria-hidden="true">→</span></a>
          <div class="slider-nav">
            <button type="button" class="slider-arrow prev" aria-label="前へ">${ARROW('M15 5l-7 7 7 7')}</button>
            <button type="button" class="slider-arrow next" aria-label="次へ">${ARROW('M9 5l7 7-7 7')}</button>
          </div>
        </div>
      </div>
      <div class="slider-track" id="menuTrack">${dishes.map(menuCard).join('')}</div>
    </section>`;
}

// ---------- ブロックごとの HTML ----------
const render = {
  // メインのヒーロー: ベント (大きなグラデーションのタイル + 右に 2つのタイル)
  //   大: ことば・ボタン・どんぶり (お店が画像を入れたら その写真が背景)
  //   右上: 自分で作る (値段から)   右下: メニュー・スープ・辛さ の数
  hero(b, d) {
    const s = d.store;
    const action = linkBtn(b, 'btn-light btn-lg') || `<a class="btn btn-light btn-lg" href="menu.php">メニューを見る<span class="arrow" aria-hidden="true"></span></a>`;
    const discount = d.promoBadge ? `<span class="hh-discount">${esc(d.promoBadge)}</span>` : '';
    const art = b.image_url
      ? `<div class="b-photo"><img src="${esc(b.image_url)}" alt="" onerror="this.closest('.b-photo').remove()"></div>`
      : `<div class="b-art">${bowlSVG({ seed: 11, rim: '#F7F1E8', steam: true })}</div>`;
    const custom = d.customMenu && d.customMenu.is_available ? d.customMenu : null;
    const dishes = d.menus.filter(m => !m.is_custom).length;
    const stats = [[dishes, 'メニュー'], [d.soups.length, 'スープ'], [d.spices.length, '辛さ']]
      .filter(([n]) => n > 0)
      .map(([n, l]) => `<div class="b-stat"><b>${n}</b><span>${l}</span></div>`).join('');
    return `
      <section class="home-hero">
        <div class="container bento">
          <div class="b-hero">
            ${art}
            ${discount}
            ${s ? `<p class="b-pill"><span class="dot ${s.is_open ? 'is-open' : ''}"></span>${s.is_open ? '営業中' : '本日はお休み'}${s.opening_hours ? ' · ' + esc(s.opening_hours) : ''}</p>` : ''}
            <h1 class="hh-title">${lines(b.title)}<span class="hh-en">Fresh, spicy &amp; made your way.</span></h1>
            ${b.body ? `<p class="hh-sub">${lines(b.body)}</p>` : ''}
            <div class="hh-actions">${action}${custom ? `<a class="btn btn-glass btn-lg" href="customize.php">自分で作る</a>` : ''}</div>
          </div>
          <div class="b-side">
            ${custom ? `
            <a class="b-tile b-custom" href="customize.php">
              <span class="kicker">Build your bowl<small>自分で作る</small></span>
              <h2>好きな具材で、<br>一から。</h2>
              <p>スープ・辛さ・具材を ぜんぶ自分で。</p>
              <span class="b-from">${yen(custom.price)}<small>から</small></span>
              <div class="b-art2">${bowlSVG({ seed: 21, rim: '#FFFFFF' })}</div>
            </a>` : ''}
            ${stats ? `<div class="b-tile b-stats">${stats}</div>` : ''}
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
            ${linkBtn(b, 'btn-outline')}
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
            <p class="kicker">Build your bowl<small>自分で作る</small></p>
            <h2 class="heading">${esc(b.title)}</h2>
            <p class="muted">${lines(b.body)}</p>
          </div>
          <div class="h-head-action">
            <span class="price">${yen(d.customMenu.price)}〜</span>
            ${linkBtn(b, 'btn-outline')}
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
    if (!track) return;
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

function reviewFormHtml() {
  // 同じ人でも 何度でも 書ける (前のを上書きしない) → いつも 空のフォーム
  const opt = (n) => `<option value="${n}">${starStr(n)}</option>`;
  return `
    <form class="review-form" id="reviewForm">
      <div class="rf-row">
        <label class="field-label m-0" for="revRating">評価</label>
        <select class="input" id="revRating">${[5, 4, 3, 2, 1].map(opt).join('')}</select>
      </div>
      <textarea class="input" id="revComment" rows="2" maxlength="300" placeholder="お店の感想をどうぞ（任意）"></textarea>
      <div class="rf-row">
        <button type="submit" class="btn btn-chili btn-sm">レビューを送る</button>
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
  const res = await callApi('GET', '../api/reviews.php');
  if (!res.success) { wrap.innerHTML = ''; return; }
  const { summary, reviews } = res.data;
  const avgN = Math.round(summary.avg);

  const header = `
    <div class="section-head"><div><p class="kicker">Voices<small>お客さまの声</small></p><h2 class="heading">お客さまの声</h2></div></div>
    ${summary.count ? `<div class="reviews-summary"><span class="reviews-avg">${summary.avg}</span><span><div class="reviews-stars">${starStr(avgN)}</div><div class="caption">${summary.count}件のレビュー</div></span></div>` : ''}`;
  const form = loggedIn
    ? reviewFormHtml()
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
