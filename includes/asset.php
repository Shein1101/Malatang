<?php
// includes/asset.php — CSS / JS の URL に「更新した時間」を付ける. Owner: Lin
// ファイルを変えると URL が変わるので、ブラウザが古いキャッシュを使わない (Ctrl+F5 がいらない)
// 例: asset_url('assets/js/app.js') → ../assets/js/app.js?v=1793769349
function asset_url(string $path): string {
    $time = @filemtime(__DIR__ . '/../' . $path);
    return '../' . $path . ($time ? '?v=' . $time : '');
}
