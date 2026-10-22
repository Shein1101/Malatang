// S04 カスタマイズ — Owner: Zan (FEAT-11)
// Step 1: sample data で表示。API ができたら callApi() に置き換える。

// ---------- 1. Sample data (API list と同じ形) ----------
// GET /api/menus.php?id=3
const sampleMenu = {
    id: 3, name: '定番麻辣湯', description: '春雨と野菜たっぷりの基本の一杯', price: 980,
    ingredients: [
      { ingredient_id: 1, name: '春雨', quantity: 1, is_removable: false },
      { ingredient_id: 2, name: '白菜', quantity: 1, is_removable: true },
      { ingredient_id: 5, name: '豆腐', quantity: 1, is_removable: true }
    ]
  };
  
  // GET /api/options.php
  const sampleOptions = {
    soups: [
      { id: 1, name: '麻辣スープ',   price: 0 },
      { id: 2, name: 'トマトスープ', price: 100 },
      { id: 3, name: '白湯スープ',   price: 100 }
    ],
    spice_levels: [
      { id: 1, name: '辛くない', level: 0, extra_price: 0 },
      { id: 2, name: '小辛',     level: 1, extra_price: 0 },
      { id: 3, name: '中辛',     level: 2, extra_price: 0 },
      { id: 4, name: '大辛',     level: 3, extra_price: 0 },
      { id: 5, name: '激辛',     level: 4, extra_price: 50 }
    ]
  };
  
  // ---------- 2. URL から menu_id を取る ----------
  const params = new URLSearchParams(location.search);   // "?menu_id=3" を読む
  const menuId = Number(params.get('menu_id'));          // "3" → 3
  if (!menuId) {
    location.href = 'menu.php';                          // Page hand-off #2: 無ければ戻す
  }
  
  // ---------- 3. 表示する関数 ----------
  function renderMenu(menu) {
    document.getElementById('menu-name').textContent  = menu.name;
    document.getElementById('menu-desc').textContent  = menu.description;
    document.getElementById('menu-price').textContent = yen(menu.price);   // app.js
  }
  
  // items = [{ id, label, disabled }] をボタン(chip)にして並べる
  function renderChips(boxId, items) {
    const box = document.getElementById(boxId);
    box.innerHTML = '';                          // 前の中身を消す
    for (const item of items) {
      const btn = document.createElement('button');
      btn.type = 'button';                       // form の送信にならないように
      btn.className = 'chip';
      btn.dataset.id = item.id;                  // <button data-id="2"> — Step 3 で使う
      btn.textContent = item.label;              // innerHTML ではなく textContent (安全)
      btn.disabled = !!item.disabled;
      box.appendChild(btn);
    }
  }
  
  // ---------- 4. 最初に1回だけ動く ----------
  function init() {
    const menu = sampleMenu;          // Step 4: await callApi('GET', `../api/menus.php?id=${menuId}`)
    const options = sampleOptions;    // Step 4: await callApi('GET', '../api/options.php')
  
    renderMenu(menu);
  
    renderChips('soup-list', options.soups.map(s => ({
      id: s.id,
      label: s.price > 0 ? `${s.name} +${yen(s.price)}` : s.name
    })));
  
    renderChips('spice-list', options.spice_levels.map(sp => ({
      id: sp.id,
      label: '🌶'.repeat(sp.level) + ' ' + sp.name
    })));
  
    renderChips('default-list', menu.ingredients.map(i => ({
      id: i.ingredient_id,
      label: i.name,
      disabled: !i.is_removable        // 春雨は抜けない
    })));
  }
  
  init();