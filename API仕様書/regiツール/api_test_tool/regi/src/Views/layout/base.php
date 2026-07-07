<?php
// regi 固定（必要ならここだけ変える）
$BASE_URL = '/regi/public';
?>
<!doctype html>
<html lang="ja">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <meta name="base-url" content="<?= htmlspecialchars($BASE_URL ?? '/regi/public', ENT_QUOTES, 'UTF-8') ?>">
    <title><?= htmlspecialchars($title ?? '居酒屋レジシステム', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= $BASE_URL ?>/assets/css/app.css">
    <script src="<?= $BASE_URL ?>/assets/js/app.js" defer></script>
  </head>
<body>
<?php // ここから本文を出す?>
