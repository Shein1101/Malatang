// assets/js/theme.js — ライト / ダーク と カラー (フォレスト / エンバー) の切り替え. Owner: Lin
// <head> で読み込む: 画面を描く前に色を決めるので、ちらつかない。デフォルトは ライト + フォレスト
// data-bs-theme は Bootstrap の表・フォーム (管理画面) のため
//   class="theme-toggle" のボタン           → ライト ⇔ ダーク
//   data-palette-pick="forest" / "ember"   → カラー (includes/header.php のサイドバー)
(function () {
  var root = document.documentElement;
  var PALETTES = ['forest', 'ember'];

  function read(key) {
    try {
      return localStorage.getItem(key);
    } catch (e) {
      return null;   // private window などでは localStorage が使えないことがある
    }
  }
  function write(key, value) {
    try {
      localStorage.setItem(key, value);
    } catch (e) {
      // 保存できなくても、このページの切り替えは動く
    }
  }

  function applyTheme(theme) {
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-bs-theme', theme);
  }
  function applyPalette(palette) {
    root.setAttribute('data-palette', palette);
    var mark = function () {
      document.querySelectorAll('[data-palette-pick]').forEach(function (b) {
        b.setAttribute('aria-pressed', b.getAttribute('data-palette-pick') === palette ? 'true' : 'false');
      });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mark);
    else mark();
  }
  function savedTheme() {
    return read('theme') === 'dark' ? 'dark' : 'light';
  }
  function savedPalette() {
    var p = read('palette');
    return PALETTES.indexOf(p) >= 0 ? p : 'forest';
  }

  applyTheme(savedTheme());
  applyPalette(savedPalette());

  document.addEventListener('click', function (e) {
    if (e.target.closest('.theme-toggle')) {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      write('theme', next);
    }
    var pick = e.target.closest('[data-palette-pick]');
    if (pick) {
      var palette = pick.getAttribute('data-palette-pick');
      applyPalette(palette);
      write('palette', palette);
    }
  });

  // ほかのタブ・管理画面のプレビューも同じ色にする
  window.addEventListener('storage', function (e) {
    if (e.key === 'theme') applyTheme(savedTheme());
    if (e.key === 'palette') applyPalette(savedPalette());
  });
})();
