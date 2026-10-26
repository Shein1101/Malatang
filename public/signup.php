<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新規登録 - Chilli 鍋</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="container mt-5" style="max-width: 500px;">

    <h1 class="text-center mb-4">新規登録</h1>

    <div id="errorMessage" class="alert alert-danger d-none"></div>

    <form id="signupForm">

        <div class="mb-3">
            <label for="name" class="form-label">名前</label>
            <input
                type="text"
                class="form-control"
                id="name"
                required
            >
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">メールアドレス</label>
            <input
                type="email"
                class="form-control"
                id="email"
                required
            >
        </div>

        <div class="mb-3">
            <label for="phone" class="form-label">電話番号</label>
            <input
                type="tel"
                class="form-control"
                id="phone"
                required
            >
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">パスワード</label>
            <input
                type="password"
                class="form-control"
                id="password"
                required
            >
        </div>

        <div class="mb-3">
            <label for="passwordConfirm" class="form-label">
                パスワード（確認）
            </label>

            <input
                type="password"
                class="form-control"
                id="passwordConfirm"
                required
            >
        </div>

        <button type="submit" class="btn btn-danger w-100">
            新規登録
        </button>

    </form>

    <div class="text-center mt-3">
        <a href="login.php">
            すでにアカウントをお持ちですか？ ログイン
        </a>
    </div>

</div>

<script src="../assets/js/app.js"></script>

<script>
document.getElementById('signupForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const password = document.getElementById('password').value;
    const passwordConfirm = document.getElementById('passwordConfirm').value;

    const errorMessage = document.getElementById('errorMessage');

    errorMessage.classList.add('d-none');

    if (password !== passwordConfirm) {
        errorMessage.textContent = 'パスワードが一致しません。';
        errorMessage.classList.remove('d-none');
        return;
    }

    const result = await callApi(
        '../api/auth.php?action=signup',
        'POST',
        {
            name: name,
            email: email,
            phone: phone,
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