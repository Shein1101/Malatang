// assets/js/theme.js — ライト / ダーク の切り替え. Owner: Lin
// <head> で読み込む: 画面を描く前に色を決めるので、ちらつかない。デフォルトは ライト
// data-bs-theme は Bootstrap の表・フォーム (管理画面) のため
// どのボタンでも class="theme-toggle" なら動く (includes/header.php, includes/admin_header.php)
(function () {
  var root = document.documentElement;

  function apply(theme) {
    root.setAttribute('data-theme', theme);
    root.setAttribute('data-bs-theme', theme);
  }

  // private window などでは localStorage が使えないことがある
  function saved() {
    try {
      return localStorage.getItem('theme') === 'dark' ? 'dark' : 'light';
    } catch (e) {
      return 'light';
    }
  }

  apply(saved());

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.theme-toggle')) return;
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    apply(next);
    try {
      localStorage.setItem('theme', next);
    } catch (err) {
      // 保存できなくても、このページの切り替えは動く
    }
  });

  // ほかのタブ・管理画面のプレビューも同じ色にする
  window.addEventListener('storage', function (e) {
    if (e.key === 'theme') apply(saved());
  });
})();
