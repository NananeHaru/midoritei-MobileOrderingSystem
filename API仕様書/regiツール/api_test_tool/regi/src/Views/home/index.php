<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

$staffName = $_SESSION['staffName']
    ?? $_SESSION['login_user_name']
    ?? 'ゲスト';

$role = strtoupper(trim((string)($_SESSION['role'] ?? '')));
?>
<!doctype html>
<html lang="ja">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ホーム</title>
  <link rel="stylesheet" href="/regi/public/assets/css/app.css">
  <link rel="stylesheet" href="/regi/public/assets/css/home.css">
</head>

<body class="home-page">
  <header class="app-header">
    <div class="app-header__left">
      <h1 class="app-title">ホーム</h1>
    </div>

    <div class="app-header__right">
      <span class="app-badge">
        <?= htmlspecialchars($staffName, ENT_QUOTES, 'UTF-8') ?>
        （<?= $role === 'MASTER' ? 'マスター' : 'スタッフ' ?>）
      </span>
    </div>
  </header>

  <main class="home-main">
    <?php if ($flashError): ?>
      <div class="flash flash-error"><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="home-grid">

      <?php if ($role === 'STAFF'): ?>
        <a class="tile" href="/regi/public/customer/select">
          <div class="icon"><img src="/regi/public/assets/img/home/regi.svg" alt="会計"></div>
          <div class="label">会計</div>
        </a>
      <?php endif; ?>

      <a class="tile purple" href="/regi/public/history">
        <div class="icon"><img src="/regi/public/assets/img/home/time3.svg" alt="会計履歴"></div>
        <div class="label">会計履歴</div>
      </a>

      <a class="tile green" href="/regi/public/sales/report">
        <div class="icon"><img src="/regi/public/assets/img/home/graph.svg" alt="売上レポート"></div>
        <div class="label">売上レポート</div>
      </a>

      <?php if ($role === 'STAFF'): ?>
        <a class="tile orange" href="/regi/public/close">
          <div class="icon"><img src="/regi/public/assets/img/home/key.svg" alt="レジ締め"></div>
          <div class="label">レジ締め</div>
        </a>
      <?php endif; ?>

      <?php if ($role === 'MASTER'): ?>
        <a class="tile gray" href="/regi/public/settings/master">
          <div class="icon"><img src="/regi/public/assets/img/home/settei6.svg" alt="設定"></div>
          <div class="label">設定</div>
        </a>
      <?php endif; ?>
    </section>

    <section class="home-actions">
      <form method="post" action="/regi/public/logout">
        <button class="btn btn-outline btn-lg btn-block" type="submit">ログアウト</button>
      </form>
    </section>
  </main>
</body>
</html>