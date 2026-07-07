<?php
$title = 'ログイン';
require dirname(__DIR__) . '/layout/base.php';

$error = $_SESSION['login_error'] ?? null;
unset($_SESSION['login_error']);
?>

<div class="container">
  <div class="card" style="max-width:520px">
    <div class="card-header" style="text-align:center; padding-top:10px;">
      <h1>みどり亭 レジシステム</h1>
    </div>

    <div class="card-body">
      <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:16px;">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= $BASE_URL ?>/login">
        <div class="field">
          <div class="label">ログインID</div>
          <input
            class="input"
            id="loginId"
            name="loginId"
            placeholder="ログインIDを入力"
            autocomplete="username"
            required
          >
        </div>

        <div class="field">
          <div class="label">パスワード</div>
          <input
            class="input"
            name="password"
            type="password"
            placeholder="パスワードを入力"
            autocomplete="current-password"
            required
          >
        </div>

        <div style="margin-top:18px">
          <button class="btn btn-primary btn-lg btn-block" type="submit">ログイン</button>
        </div>
      </form>
    </div>
  </div>
</div>