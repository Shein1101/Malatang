<?php
// public/customize.php — S04 カスタマイズ (具材・味・スープ・辛さ) + リアルタイム料金. Owner: Zan (FEAT-11, FEAT-12)
// 画面だけ。SQL なし。動きは ../assets/js/customize.js
// 来かた: menu.php のカード → customize.php?menu_id=3
// 行き先: 「カートに追加」→ POST ../api/cart.php → cart.php
$pageTitle = 'カスタマイズ | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <p id="error" class="field-error d-none"></p>

  <!-- 読み込み中に見せる -->
  <p id="loading" class="muted">読み込み中…</p>

  <div id="builder" class="cz-layout d-none">
    <div class="stack">
      <!-- 1. メニュー -->
      <section class="card card-body">
        <h1 id="menuName" class="heading"></h1>
        <p id="menuDesc" class="caption"></p>
        <div class="price" id="menuPrice"></div>
      </section>

      <!-- 2. 味 (必ず1つ) -->
      <section>
        <p class="caption">味を選ぶ</p>
        <div id="soupChips" class="chip-group" role="group" aria-label="味"></div>
      </section>

      <!-- 3. スープ あり / なし -->
      <section>
        <p class="caption">スープ</p>
        <div id="brothChips" class="chip-group" role="group" aria-label="スープ">
          <button type="button" class="chip is-selected" data-broth="1" aria-pressed="true">スープあり</button>
          <button type="button" class="chip" data-broth="0" aria-pressed="false">スープなし（汁なし）</button>
        </div>
      </section>

      <!-- 4. 辛さ -->
      <section>
        <p class="caption">辛さを選ぶ</p>
        <div id="spiceChips" class="chip-group" role="group" aria-label="辛さ"></div>
      </section>

      <!-- 5. 最初から入っている具材 (外せるものだけチェックを外せる) -->
      <section id="defaultsBox">
        <p class="caption">最初から入っている具材</p>
        <ul id="defaultList" class="list"></ul>
      </section>

      <!-- 6. 追加する具材 (カテゴリごと) -->
      <section>
        <p class="caption">具材を追加する</p>
        <div id="extraList" class="stack"></div>
      </section>
    </div>

    <!-- 7. 料金 (選ぶたびに変わる) + カートに追加 -->
    <aside class="card card-body cz-price">
      <h2 class="heading">料金</h2>
      <ul id="priceLines" class="list"></ul>
      <div class="row cz-qty">
        <span>杯数</span>
        <div class="stepper">
          <button type="button" id="qtyMinus" aria-label="減らす">−</button>
          <output id="qty">1</output>
          <button type="button" id="qtyPlus" aria-label="増やす">+</button>
        </div>
      </div>
      <div class="total-row"><span>合計</span><span id="total" class="price-lg">¥0</span></div>
      <button type="button" id="addToCart" class="btn btn-chili btn-block">カートに追加 <span class="arrow"></span></button>
      <p class="caption">※ 金額は注文のときにお店でもう一度計算します</p>
    </aside>
  </div>

  <div class="toast-area" id="toastArea"></div>
</main>

<!-- app.js (callApi, yen) は footer で読み込まれる → customize.js は window の load で動き始める -->
<script src="../assets/js/customize.js"></script>
<?php include '../includes/footer.php'; ?>
