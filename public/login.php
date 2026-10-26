<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - Chilli 鍋</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="container mt-5" style="max-width: 500px;">

    <h1 class="text-center mb-4">ログイン</h1>

    <div id="errorMessage" class="alert alert-danger d-none"></div>

    <form id="loginForm">

        <div class="mb-3">
            <label for="email" class="form-label">
                メールアドレス
            </label>

            <input
                type="email"
                class="form-control"
                id="email"
                required
            >
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">
                パスワード
            </label>

            <input
                type="password"
                class="form-control"
                id="password"
                required
            >
        </div>

        <button type="submit" class="btn btn-danger w-100">
            ログイン
        </button>

    </form>

    <div class="text-center mt-3">
        <a href="signup.php">
            アカウントをお持ちでない方はこちら
        </a>
    </div>

</div>

<script src="../assets/js/app.js"></script>

<script>
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    const errorMessage = document.getElementById('errorMessage');

    errorMessage.classList.add('d-none');

    const result = await callApi(
        '../api/auth.php?action=login',
        'POST',
        {
            email: email,
            password: password
        }
    );

    if (!result.success) {
        errorMessage.textContent = result.error;
        errorMessage.classList.remove('d-none');
        return;
    }

    window.location.href = 'mypage.php';
});
</script>

</body>
</html>