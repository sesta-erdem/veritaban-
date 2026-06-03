<?php

if ($code->isLoggedIn()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf() || !is_same_origin_request()) {
        $error = 'Gecersiz oturum istegi. Sayfayi yenileyip tekrar deneyin.';
    } else {
        $email = trim((string) post('email'));
        $password = (string) post('password');
        $clientIp = rate_limit_client_ip();

        require_rate_limit_or_redirect('admin-login-ip', $clientIp, 10, 15 * 60, 'index.php');
        require_rate_limit_or_redirect('admin-login-email-ip', strtolower($email) . '|' . $clientIp, 5, 15 * 60, 'index.php');

        $loginResult = $code->login($email, $password);

        if (!empty($loginResult['ok'])) {
            if (!empty($loginResult['mfa_required'])) {
                $error = 'Bu hesap icin ikinci dogrulama adimi henuz aktif akisa baglanmadi.';
            } else {
                redirect('index.php');
            }
        } else {
            $error = (string) ($loginResult['message'] ?? 'Giris basarisiz. Bilgilerinizi kontrol edip tekrar deneyin.');
        }
    }
}

?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dolunay Admin Giriş</title>
  <link rel="stylesheet" href="assets/vendor/css/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="assets/css/adminlte.css" />
</head>
<body class="login-page bg-body-secondary">
  <div class="login-box">
    <div class="login-logo">
      <b>DOLUNAY</b> Admin
    </div>
    <div class="card">
      <div class="card-body login-card-body">
        <p class="login-box-msg">Yönetim paneline giriş yapın</p>
        <?php if ((string) ($_GET['expired'] ?? '') === '1'): ?>
          <div class="alert alert-warning">Oturum suresi doldu. Lutfen yeniden giris yapin.</div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post">
          <?= csrf_input() ?>
          <div class="input-group mb-3">
            <input type="email" name="email" class="form-control" placeholder="E-posta" required />
            <div class="input-group-text"><span class="bi bi-envelope"></span></div>
          </div>
          <div class="input-group mb-3">
            <input type="password" name="password" class="form-control" placeholder="Şifre" required />
            <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
          </div>
          <button type="submit" class="btn btn-primary w-100">Giriş Yap</button>
        </form>
      </div>
    </div>
  </div>
  <script src="assets/vendor/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/adminlte.js"></script>
</body>
</html>
