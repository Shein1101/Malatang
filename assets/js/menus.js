// assets/js/menus.js — メニューのカードとカテゴリのチップ (home.js と public/menu.php で同じものを使う). Owner: Lin
// 写真がないときは bowl.js のどんぶりの絵 (bowl.js がないページでは 🍜)
// esc() は それぞれのページにある

// DB にカテゴリがないので、名前で分ける
const MENU_CATS = [
  { key: 'malatang', label: '麻辣湯',   match: m => m.name.includes('麻辣湯') },
  { key: 'xiangguo', label: '麻辣香鍋', match: m => m.name.includes('麻辣香鍋') },
  { key: 'limited',  label: '期間限定', match: m => m.name.includes('期間限定') },
];

const LIMITED_MARK = '【期間限定】';

function menuCard(m) {
  const media = m.image_url
    ? `<img src="${esc(m.image_url)}" alt="${esc(m.name)}" onerror="this.closest('.card-media').classList.add('no-img');this.remove()">`
    : '';
  const ribbon = m.name.includes('期間限定') ? '<span class="ribbon">期間限定</span>' : '';
  const name = esc(m.name.replace(LIMITED_MARK, ''));
  const art = typeof bowlSVG === 'function' ? bowlSVG({ seed: m.id, rim: '#FBF6EE' }) : '<span class="card-emoji" aria-hidden="true">🍜</span>';
  const tag = m.is_available ? `<span class="price-tag">${yen(m.price)}${m.is_custom ? '〜' : ''}</span>` : '';
  const mediaBox = `<div class="card-media${m.image_url ? '' : ' no-img'}">${media}${art}${ribbon}${tag}</div>`;

  // 売り切れ: 押せない
  if (!m.is_available) {
    return `
      <div class="card-bowl is-soldout">
        ${mediaBox}
        <div class="card-info">
          <div class="card-name">${name}</div>
          <div class="card-desc">${esc(m.description)}</div>
          <div class="card-foot"><span class="pick">売り切れ</span></div>
        </div>
      </div>`;
  }

  return `
    <a class="card-bowl" href="customize.php?menu_id=${m.id}">
      ${mediaBox}
      <div class="card-info">
        <div class="card-name">${name}</div>
        <div class="card-desc">${esc(m.description)}</div>
        <div class="card-foot"><span class="pick">選んでカスタマイズ<span class="arrow" aria-hidden="true"></span></span></div>
      </div>
    </a>`;
}

// すべて / 麻辣湯 / … / 自分で作る (メニューがないカテゴリは出さない)
function menuChips(menus, active) {
  const dishes = menus.filter(m => !m.is_custom);
  const chip = (href, label, on) => `<a class="chip ${on ? 'is-selected' : ''}" href="${href}"${on ? ' aria-current="true"' : ''}>${esc(label)}</a>`;
  const custom = menus.find(m => m.is_custom && m.is_available);
  return `
    <div class="chip-row">
      ${chip('menu.php', 'すべて', !active)}
      ${MENU_CATS.filter(c => dishes.some(c.match)).map(c => chip(`menu.php?cat=${c.key}`, c.label, active === c.key)).join('')}
      ${custom ? chip('customize.php', '自分で作る', false) : ''}
    </div>`;
}
