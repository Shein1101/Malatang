<?php
// public/mypage.php — マイページ. Owner: Lin (FEAT-16)
// Screen only, NO SQL. Data comes from ../api/auth.php?action=me
// tabbar の「マイページ」から来る。自分の情報 + 注文履歴・お気に入り・ファミリー&フレンズ への入口
require_once '../includes/auth.php';
require_login();

$pageTitle = 'マイページ | Chilli 鍋';
include '../includes/header.php';
?>

<main class="container page">
  <h1 class="title">マイページ</h1>
  <div id="error" class="alert alert-danger d-none"></div>

  <section class="card card-body mb-4">
    <h2 class="heading" id="name">読み込み中…</h2>
    <div class="caption"><svg width="14" height="14"><use href="#i-mail"></use></svg> <span id="email"></span></div>
    <div class="caption" id="phone"></div>
  </section>

  <div class="tile-grid mb-4">
    <a class="tile" href="history.php"><svg><use href="#i-receipt"></use></svg>注文履歴</a>
    <a class="tile" href="favorites.php"><svg><use href="#i-heart"></use></svg>お気に入り</a>
    <a class="tile" href="friends.php"><svg><use href="#i-users"></use></svg>ファミリー&フレンズ</a>
    <a class="tile" href="customize.php"><svg><use href="#i-bowl"></use></svg>自分で作る</a>
    <a class="tile" href="cart.php"><svg><use href="#i-cart"></use></svg>カート</a>
    <a class="tile" href="logout.php"><svg><use href="#i-logout"></use></svg>ログアウト</a>
  </div>
</main>

<script>
async function loadMe() {
  const res = await callApi('GET', '../api/auth.php?action=me');
  if (!res.success || !res.data) {
    showError(document.getElementById('error'), res.error || 'ログインし直してください');
    return;
  }
  document.getElementById('name').textContent = res.data.name + ' さん';
  document.getElementById('email').textContent = res.data.email;
  document.getElementById('phone').textContent = res.data.phone ? '電話: ' + res.data.phone : '';
}

window.addEventListener('load', loadMe);
</script>

<?php include '../includes/footer.php'; ?>
