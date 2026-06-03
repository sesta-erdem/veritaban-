<?php

$id = (int) getv('id', 0);
$adminUser = $id ? $code->getAdminUser($id) : null;
$currentAdminUser = $code->currentUser() ?? [];
$currentAdminUserId = (int) ($currentAdminUser['id'] ?? 0);
$isSelfEdit = $id > 0 && $currentAdminUserId === $id;
$formActionUrl = 'index.php?do=kullanici_form' . ($id ? '&id=' . $id : '');
$errors = [];

if ($id && !$adminUser) {
    flash_set('danger', 'Kullanici bulunamadi.');
    redirect('index.php?do=kullanicilar');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf($formActionUrl);

    $payload = [
        'ad_soyad' => trim((string) post('ad_soyad')),
        'eposta' => trim((string) post('eposta')),
        'rutbe' => (int) post('rutbe', ADMIN_ROLE_EDITOR),
        'sil' => (int) post('sil', 2),
        'password' => (string) post('password'),
        'password_confirm' => (string) post('password_confirm'),
        'current_password' => (string) post('current_password'),
    ];

    if (!validate_required($payload['ad_soyad'])) {
        $errors[] = 'Ad soyad zorunludur.';
    }

    if (!filter_var($payload['eposta'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Gecerli bir e-posta girin.';
    }

    if (!in_array($payload['rutbe'], [ADMIN_ROLE_SUPER, ADMIN_ROLE_EDITOR], true)) {
        $errors[] = 'Gecerli bir rol secin.';
    }

    if ($code->adminEmailExists($payload['eposta'], $id ?: null)) {
        $errors[] = 'Bu e-posta adresi zaten kullaniliyor.';
    }

    $passwordRequired = $id === 0;
    if ($passwordRequired && trim($payload['password']) === '') {
        $errors[] = 'Yeni kullanici icin sifre zorunludur.';
    }

    if (trim($payload['password']) !== '' && strlen($payload['password']) < 10) {
        $errors[] = 'Sifre en az 10 karakter olmali.';
    }

    if ($payload['password'] !== $payload['password_confirm']) {
        $errors[] = 'Sifre tekrari eslesmiyor.';
    }

    if ($isSelfEdit) {
        if ((int) $payload['rutbe'] !== (int) ($adminUser['rutbe'] ?? ADMIN_ROLE_EDITOR) || (int) $payload['sil'] !== (int) ($adminUser['sil'] ?? 2)) {
            $errors[] = 'Kendi rolunuz ve durumunuz bu ekrandan degistirilemez.';
        }

        $emailChanged = strcasecmp((string) $payload['eposta'], (string) ($adminUser['eposta'] ?? '')) !== 0;
        $passwordChanged = trim($payload['password']) !== '';

        if ($emailChanged || $passwordChanged) {
            if (trim($payload['current_password']) === '') {
                $errors[] = 'E-posta veya sifre degisikligi icin mevcut sifrenizi girin.';
            } elseif (!$code->verifyAdminPassword($currentAdminUserId, $payload['current_password'])) {
                $errors[] = 'Mevcut sifre dogrulanamadi.';
            }
        }

        $payload['rutbe'] = (int) ($adminUser['rutbe'] ?? ADMIN_ROLE_EDITOR);
        $payload['sil'] = (int) ($adminUser['sil'] ?? 2);
    }

    if (!$errors) {
        try {
            $code->saveAdminUser($payload, $id ?: null);

            if ($isSelfEdit) {
                $_SESSION['admin_user'] = [
                    'id' => $currentAdminUserId,
                    'ad_soyad' => $payload['ad_soyad'],
                    'eposta' => strtolower($payload['eposta']),
                    'rutbe' => (int) ($adminUser['rutbe'] ?? $payload['rutbe']),
                ];
            }

            flash_set('success', $id ? 'Kullanici guncellendi.' : 'Kullanici eklendi.');
            redirect('index.php?do=kullanicilar');
        } catch (InvalidArgumentException $error) {
            $errors[] = $error->getMessage();
        } catch (Throwable $error) {
            log_exception($error, ['page' => 'kullanici_form', 'id' => $id]);
            $errors[] = 'Kullanici kaydi kaydedilemedi.';
        }
    }

    $adminUser = [
        'ad_soyad' => $payload['ad_soyad'],
        'eposta' => $payload['eposta'],
        'rutbe' => $payload['rutbe'],
        'sil' => $payload['sil'],
    ];
}

$defaults = [
    'ad_soyad' => '',
    'eposta' => '',
    'rutbe' => ADMIN_ROLE_EDITOR,
    'sil' => 2,
];

$adminUser = array_merge($defaults, $adminUser ?? []);
$apiEndpoint = $id > 0 ? 'admin/api/admin-user.php' : '';

?>
<div class="app-content-header">
  <div class="container-fluid">
    <div class="row">
      <div class="col-sm-6"><h3 class="mb-0"><?= $id ? 'Kullanıcı Düzenle' : 'Yeni Kullanıcı' ?></h3></div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
          <li class="breadcrumb-item"><a href="index.php?do=kullanicilar">Kullanıcılar</a></li>
          <li class="breadcrumb-item active"><?= $id ? 'Duzenle' : 'Yeni' ?></li>
        </ol>
      </div>
    </div>
  </div>
</div>
<div class="app-content">
  <div class="container-fluid">
    <?php if ($errors): ?>
      <div class="alert alert-danger" data-admin-user-status>
        <?= e(implode(' ', $errors)) ?>
      </div>
    <?php else: ?>
      <div class="alert d-none" data-admin-user-status role="status" aria-live="polite"></div>
    <?php endif; ?>
    <form
      method="post"
      class="card"
      novalidate
      data-admin-user-form
      data-user-id="<?= e((string) $id) ?>"
      data-api-endpoint="<?= e($apiEndpoint) ?>"
      data-is-self-edit="<?= $isSelfEdit ? '1' : '0' ?>"
    >
      <?= csrf_input() ?>
      <div class="card-header"><h3 class="card-title">Hesap Bilgileri</h3></div>
      <div class="card-body">
        <p class="text-secondary small mb-4">
          Bu formdaki istemci kontrollerine ek olarak tum guncellemeler sunucuda da dogrulanir.
        </p>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="admin-user-name">Ad Soyad</label>
            <input
              id="admin-user-name"
              type="text"
              name="ad_soyad"
              class="form-control"
              value="<?= e($adminUser['ad_soyad']) ?>"
              required
              maxlength="120"
              autocomplete="name"
              data-field="ad_soyad"
            />
            <div class="invalid-feedback" data-field-error="ad_soyad"></div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label" for="admin-user-email">E-posta</label>
            <input
              id="admin-user-email"
              type="email"
              name="eposta"
              class="form-control"
              value="<?= e($adminUser['eposta']) ?>"
              required
              maxlength="190"
              autocomplete="email"
              inputmode="email"
              data-field="eposta"
            />
            <div class="invalid-feedback" data-field-error="eposta"></div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label" for="admin-user-role">Rol</label>
            <select
              id="admin-user-role"
              name="rutbe"
              class="form-select"
              <?= $isSelfEdit ? 'disabled aria-disabled="true"' : '' ?>
              data-field="rutbe"
            >
              <option value="<?= ADMIN_ROLE_EDITOR ?>" <?= selected_value($adminUser['rutbe'], ADMIN_ROLE_EDITOR) ?>>Editor</option>
              <option value="<?= ADMIN_ROLE_SUPER ?>" <?= selected_value($adminUser['rutbe'], ADMIN_ROLE_SUPER) ?>>Yonetici</option>
            </select>
            <?php if ($isSelfEdit): ?>
              <input type="hidden" name="rutbe" value="<?= e((string) $adminUser['rutbe']) ?>" />
            <?php endif; ?>
          </div>
          <div class="col-md-4 mb-3">
            <label class="form-label" for="admin-user-status-select">Durum</label>
            <select
              id="admin-user-status-select"
              name="sil"
              class="form-select"
              <?= $isSelfEdit ? 'disabled aria-disabled="true"' : '' ?>
              data-field="sil"
            >
              <option value="2" <?= selected_value($adminUser['sil'], 2) ?>>Aktif</option>
              <option value="1" <?= selected_value($adminUser['sil'], 1) ?>>Pasif</option>
            </select>
            <?php if ($isSelfEdit): ?>
              <input type="hidden" name="sil" value="<?= e((string) $adminUser['sil']) ?>" />
              <div class="form-text">Kendi rol ve durum degisikligi sunucu tarafinda engellenir.</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label" for="admin-user-password"><?= $id ? 'Yeni Şifre (opsiyonel)' : 'Şifre' ?></label>
            <input
              id="admin-user-password"
              type="password"
              name="password"
              class="form-control"
              <?= $id ? '' : 'required' ?>
              minlength="10"
              maxlength="255"
              autocomplete="<?= $id ? 'new-password' : 'new-password' ?>"
              data-field="password"
            />
            <div class="form-text">En az 10 karakter kullan.</div>
            <div class="invalid-feedback" data-field-error="password"></div>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label" for="admin-user-password-confirm">Şifre Tekrar</label>
            <input
              id="admin-user-password-confirm"
              type="password"
              name="password_confirm"
              class="form-control"
              <?= $id ? '' : 'required' ?>
              minlength="10"
              maxlength="255"
              autocomplete="new-password"
              data-field="password_confirm"
            />
            <div class="invalid-feedback" data-field-error="password_confirm"></div>
          </div>
        </div>
        <?php if ($isSelfEdit): ?>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label" for="admin-user-current-password">Mevcut Şifre</label>
              <input
                id="admin-user-current-password"
                type="password"
                name="current_password"
                class="form-control"
                autocomplete="current-password"
                maxlength="255"
                data-field="current_password"
              />
              <div class="form-text">E-posta veya sifre degisikliginde sunucuda tekrar dogrulanir.</div>
              <div class="invalid-feedback" data-field-error="current_password"></div>
            </div>
          </div>
        <?php endif; ?>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <a href="index.php?do=kullanicilar" class="btn btn-outline-secondary">Geri Don</a>
        <button type="submit" class="btn btn-primary" data-submit-button><?= $id ? 'Kaydet' : 'Kullanici Ekle' ?></button>
      </div>
    </form>
  </div>
</div>
<script src="assets/js/admin-user-form.js"></script>
