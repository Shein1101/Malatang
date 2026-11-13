// assets/js/menus.js — メニューのカードとカテゴリのチップ (home.js と public/menu.php で同じものを使う). Owner: Lin
// esc() は それぞれのページにある

// DB にカテゴリがないので、名前で分ける
const MENU_CATS = [
  { key: 'malatang', label: '麻辣湯',   match: m => m.name.includes('麻辣湯') },
  { key: 'xiangguo', label: '麻辣香鍋', match: m => m.name.includes('麻辣香鍋') },
  { key: 'limited',  label: '期間限定', match: m => m.name.includes('期間限定') },
];

const LIMITED_MARK = '【期間限定】';

// 写真がないときの色 (id で決まる)
const MENU_ART = [
  'linear-gradient(135deg,#3c5a2e,#6b8e3a)',
  'linear-gradient(135deg,#1d3a4a,#3f6d80)',
  'linear-gradient(135deg,#5c2a1c,#8f3420)',
  'linear-gradient(135deg,#8a6a1f,#c9a05c)',
];

function menuCard(m) {
  const img = m.image_url ? `<img src="${esc(m.image_url)}" alt="${esc(m.name)}" onerror="this.remove()">` : '';
  const art = `style="background:${MENU_ART[m.id % MENU_ART.length]}"`;
  const ribbon = m.name.includes('期間限定') ? '<span class="ribbon">期間限定</span>' : '';
  const name = esc(m.name.replace(LIMITED_MARK, ''));

  // 売り切れ: 押せない
  if (!m.is_available) {
    return `
      <div class="card-bowl is-soldout">
        <div class="card-media" ${art}>${img}${ribbon}</div>
        <div class="card-info">
          <div class="card-name">${name}</div>
          <div class="card-desc">${esc(m.description)}</div>
          <div class="card-foot"><span class="badge badge-soldout">売り切れ</span></div>
        </div>
      </div>`;
  }

  return `
    <a class="card-bowl" href="customize.php?menu_id=${m.id}">
      <div class="card-media" ${art}>${img}${ribbon}</div>
      <div class="card-info">
        <div class="card-name">${name}</div>
        <div class="card-desc">${esc(m.description)}</div>
        <div class="card-foot">
          <span class="price">${yen(m.price)}</span>
          <span class="add-mark" aria-hidden="true">+</span>
        </div>
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
