// assets/js/theme.js — ライト / ダーク の切り替え. Owner: Lin
// <head> で読み込む: 画面を描く前に色を決めるので、ちらつかない。デフォルトは ライト。
// 色は赤 (チリレッド) 1色。data-bs-theme は Bootstrap の表・フォーム (管理画面) のため。
//   class="theme-toggle" のボタン → ライト ⇔ ダーク
(function () {
  var root = document.documentElement;

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
  function savedTheme() {
    return read('theme') === 'dark' ? 'dark' : 'light';
  }

  applyTheme(savedTheme());

  document.addEventListener('click', function (e) {
    if (e.target.closest('.theme-toggle')) {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      applyTheme(next);
      write('theme', next);
    }
  });

  // ほかのタブ・管理画面のプレビューも同じ色にする
  window.addEventListener('storage', function (e) {
    if (e.key === 'theme') applyTheme(savedTheme());
  });
})();
