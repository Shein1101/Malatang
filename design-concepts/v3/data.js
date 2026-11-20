// design-concepts/v3/data.js — 3つのデザイン案で使う 本物のデータ. Owner: Lin
// メニュー・味・辛さ・具材を API から読む。読めないとき (サーバーが止まっている等) は見本のデータ。
// 見本ページなので 注文はしない (カートに入れる API は呼ばない)。

const CHILLI_API = '../../api/';

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function yen(n) {
  return '¥' + Number(n).toLocaleString('ja-JP');
}

const CHILLI_SAMPLE = {
  menus: [
    { id: 12, name: '自分で作る麻辣湯', description: '春雨とスープから、好きな具材を自由に選んで作る', price: 300, is_custom: true, is_available: true },
    { id: 3, name: '野菜たっぷり麻辣湯', description: '白菜・青梗菜・ほうれん草・きのこ入り', price: 850, is_custom: false, is_available: true },
    { id: 4, name: '海鮮麻辣湯', description: 'エビ・イカ・ホタテ・あさりの旨みたっぷり', price: 1180, is_custom: false, is_available: true },
    { id: 5, name: '団子いろいろ麻辣湯', description: '牛肉団子・えび団子・魚団子の食べくらべ', price: 950, is_custom: false, is_available: true },
    { id: 6, name: '豆腐と湯葉の麻辣湯', description: '豆腐・湯葉・厚揚げで、やさしいたんぱく質', price: 880, is_custom: false, is_available: true },
    { id: 8, name: 'チーズ麻辣湯', description: 'とろけるチーズとトッポギ', price: 980, is_custom: false, is_available: true },
    { id: 10, name: '【期間限定】羊肉麻辣湯', description: '羊肉とパクチーの本場の味', price: 1280, is_custom: false, is_available: true },
  ],
  soups: [
    { id: 1, name: '麻辣スープ', description: 'しびれる辛さの四川風スープ', price: 0 },
    { id: 2, name: '白湯スープ', description: 'まろやかな豚骨スープ（辛くない）', price: 0 },
    { id: 3, name: 'トマトスープ', description: '甘酸っぱいトマトスープ', price: 50 },
  ],
  spices: [
    { id: 2, name: 'ピリ辛', level: 2, extra_price: 0 },
    { id: 3, name: '中辛', level: 3, extra_price: 0 },
    { id: 4, name: '大辛', level: 4, extra_price: 0 },
    { id: 5, name: '激辛', level: 5, extra_price: 0 },
  ],
  ingredients: [
    { id: 1, name: 'チンゲン菜', price: 100, portion: '60g', category_name: '野菜', is_available: true },
    { id: 2, name: 'もやし', price: 80, portion: '80g', category_name: '野菜', is_available: true },
    { id: 20, name: 'えのき', price: 100, portion: '50g', category_name: 'きのこ', is_available: true },
    { id: 25, name: '牛肉', price: 250, portion: '50g', category_name: '肉', is_available: true },
    { id: 30, name: 'エビ', price: 250, portion: '3尾', category_name: '海鮮', is_available: true },
    { id: 40, name: '豆腐', price: 100, portion: '100g', category_name: '豆腐・湯葉', is_available: true },
    { id: 45, name: 'トッポギ', price: 150, portion: '4個', category_name: '麺', is_available: true },
    { id: 50, name: '味玉', price: 100, portion: '1個', category_name: 'トッピング', is_available: true },
  ],
};

async function chilliData() {
  try {
    const get = (u) => fetch(CHILLI_API + u, { credentials: 'same-origin' }).then(r => r.json());
    const [m, o, i] = await Promise.all([get('menus.php'), get('options.php'), get('ingredients.php')]);
    if (!m.success || !o.success || !i.success) throw new Error('api');
    return {
      menus: m.data,
      soups: o.data.soups,
      spices: o.data.spice_levels,
      ingredients: i.data.filter(x => x.is_available && (x.stock === undefined || x.stock > 0)),
      live: true,
    };
  } catch (e) {
    return { ...CHILLI_SAMPLE, live: false };
  }
}

// 【期間限定】 を名前から外して、印だけ返す
function menuLabel(m) {
  const limited = m.name.includes('期間限定');
  return { name: m.name.replace('【期間限定】', ''), limited };
}
