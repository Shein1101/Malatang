<?php // Shared footer: every public page includes this last. Owner: Lin
// 「お店について」「アクセス」はお店が admin/store.php で編集 → app.js が api/store.php から入れる ?>
<footer class="site-footer">
  <div class="container footer-top">
    <div class="footer-brand">
      <div class="logo"><svg><use href="#i-flame"/></svg><span class="logo-name">Chilli 鍋</span></div>
      <p class="footer-about" id="footerAbout">自分だけの麻辣湯を、スープ・辛さ・具材から。</p>
      <div class="footer-social" id="footerSocial">
        <a class="sns-link d-none" id="snsLine" target="_blank" rel="noopener noreferrer">LINE</a>
        <a class="sns-link d-none" id="snsIg" target="_blank" rel="noopener noreferrer">Instagram</a>
        <a class="sns-link d-none" id="snsX" target="_blank" rel="noopener noreferrer">X</a>
      </div>
    </div>

    <div class="footer-col" id="footerLocation">
      <h3>アクセス</h3>
      <p id="footerAddress" class="d-none"></p>
      <p id="footerHours" class="d-none"></p>
      <p id="footerPhone" class="d-none"></p>
    </div>
  </div>

  <div class="container footer-bottom">
    <span>© 2026 Chilli 鍋</span>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
