<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログアウト</title>
</head>

<body>

<script src="../assets/js/app.js"></script>

<script>
async function logout() {

    await callApi(
        '../api/auth.php?action=logout',
        'POST',
        {}
    );

    window.location.href = 'menu.php';
}

logout();
</script>

</body>
</html>