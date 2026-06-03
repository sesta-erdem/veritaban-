<?php

declare(strict_types=1);

class AdminCode
{
    public ?PDO $db = null;
    private AdminStoredProcedureDal $dal;
    private const LOGIN_MAX_FAILED_ATTEMPTS = 5;
    private const LOGIN_LOCK_MINUTES = 15;
    private const GENERIC_LOGIN_ERROR = 'Giris basarisiz. Bilgilerinizi kontrol edip tekrar deneyin.';

    public function __construct(?AdminStoredProcedureDal $dal = null)
    {
        $this->dal = $dal ?? new AdminStoredProcedureDal();
        $this->db = $this->dal->connection();
    }

    public function currentUser(): ?array
    {
        return $_SESSION['admin_user'] ?? null;
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['admin_user']);
    }

    public function login(string $email, string $password): array
    {
        $user = $this->callProcedureOne('CALL sesta_admin_giris_kullanici_getir(?)', [$email]);

        if (!$user) {
            $this->slowFailedLoginResponse();

            return [
                'ok' => false,
                'message' => self::GENERIC_LOGIN_ERROR,
            ];
        }

        $user = $this->clearExpiredLockIfNeeded($user);

        if ($this->isUserTemporarilyLocked($user)) {
            $this->slowFailedLoginResponse();

            return [
                'ok' => false,
                'message' => self::GENERIC_LOGIN_ERROR,
            ];
        }

        if (!password_verify($password, (string) $user['sifre_hash'])) {
            $this->recordFailedLoginAttempt((int) $user['kullanici_id'], (int) ($user['failed_attempts'] ?? 0));
            $this->slowFailedLoginResponse();

            return [
                'ok' => false,
                'message' => self::GENERIC_LOGIN_ERROR,
            ];
        }

        if ($this->passwordNeedsRehash((string) $user['sifre_hash'])) {
            $this->upgradePasswordHash((int) $user['kullanici_id'], $password);
        }

        $this->resetLoginFailures((int) $user['kullanici_id']);

        if ((int) ($user['mfa_enabled'] ?? 0) === 1) {
            $this->startPendingMfaSession($user);

            return [
                'ok' => true,
                'mfa_required' => true,
            ];
        }

        $_SESSION['admin_user'] = [
            'id' => (int) $user['kullanici_id'],
            'ad_soyad' => (string) $user['ad_soyad'],
            'eposta' => (string) $user['eposta'],
            'rutbe' => (int) $user['rutbe'],
        ];
        $_SESSION['admin_session_created_at'] = time();
        $_SESSION['admin_session_last_seen'] = time();
        $_SESSION['admin_session_fingerprint'] = current_admin_session_fingerprint();
        session_regenerate_id(true);

        return [
            'ok' => true,
            'mfa_required' => false,
        ];
    }

    public function logout(): void
    {
        destroy_active_session();
    }

    public function getAdminUsers(): array
    {
        return $this->callProcedureAll('CALL sesta_admin_kullanicilar_hepsi()');
    }

    public function getAdminUser(int $id): ?array
    {
        return $this->callProcedureOne('CALL sesta_admin_kullanici_getir(?)', [$id]);
    }

    public function adminEmailExists(string $email, ?int $ignoreId = null): bool
    {
        $result = $this->callProcedureOne('CALL sesta_admin_eposta_var_mi(?, ?)', [$email, $ignoreId]);

        return (int) ($result['adet'] ?? 0) > 0;
    }

    public function verifyAdminPassword(int $id, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        $user = $this->getAdminUser($id);
        if (!$user) {
            return false;
        }

        $loginUser = $this->callProcedureOne('CALL sesta_admin_giris_kullanici_getir(?)', [(string) ($user['eposta'] ?? '')]);
        if (!$loginUser || (int) ($loginUser['kullanici_id'] ?? 0) !== $id) {
            return false;
        }

        return password_verify($password, (string) ($loginUser['sifre_hash'] ?? ''));
    }

    public function saveAdminUser(array $payload, ?int $id = null): void
    {
        $payload = AdminInputGuard::sanitizeAdminUserPayload($payload, $id !== null);
        $name = $payload['ad_soyad'];
        $email = $payload['eposta'];
        $role = (int) $payload['rutbe'];
        $sil = (int) $payload['sil'];
        $password = (string) $payload['password'];

        if ($id === null) {
            $this->callProcedureAll('CALL sesta_admin_kullanici_ekle(?, ?, ?, ?, ?)', [
                $name,
                $email,
                $this->hashPassword($password),
                $role,
                $sil,
            ]);
            return;
        }

        if ($password !== '') {
            $this->callProcedureAll('CALL sesta_admin_kullanici_sifreli_guncelle(?, ?, ?, ?, ?, ?)', [
                $id,
                $name,
                $email,
                $this->hashPassword($password),
                $role,
                $sil,
            ]);
            return;
        }

        $this->callProcedureAll('CALL sesta_admin_kullanici_guncelle(?, ?, ?, ?, ?)', [
            $id,
            $name,
            $email,
            $role,
            $sil,
        ]);
    }

    private function passwordAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    private function passwordOptions(): array
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return [
                'memory_cost' => 19456,
                'time_cost' => 2,
                'threads' => 1,
            ];
        }

        return ['cost' => 12];
    }

    private function passwordNeedsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->passwordAlgorithm(), $this->passwordOptions());
    }

    private function hashPassword(string $password): string
    {
        $hash = password_hash($password, $this->passwordAlgorithm(), $this->passwordOptions());

        if ($hash === false) {
            throw new RuntimeException('Parola hash olusturulamadi.');
        }

        return $hash;
    }

    private function upgradePasswordHash(int $userId, string $password): void
    {
        $this->callProcedureAll('CALL sesta_admin_kullanici_sifre_hash_guncelle(?, ?)', [
            $userId,
            $this->hashPassword($password),
        ]);
    }

    private function clearExpiredLockIfNeeded(array $user): array
    {
        $lockUntil = trim((string) ($user['lock_until'] ?? ''));

        if ($lockUntil === '') {
            return $user;
        }

        $lockTimestamp = strtotime($lockUntil);
        if ($lockTimestamp === false || $lockTimestamp > time()) {
            return $user;
        }

        $this->callProcedureAll('CALL sesta_admin_kullanici_kilit_temizle(?)', [(int) $user['kullanici_id']]);

        $user['failed_attempts'] = 0;
        $user['lock_until'] = null;

        return $user;
    }

    private function isUserTemporarilyLocked(array $user): bool
    {
        $lockUntil = trim((string) ($user['lock_until'] ?? ''));
        if ($lockUntil === '') {
            return false;
        }

        $lockTimestamp = strtotime($lockUntil);

        return $lockTimestamp !== false && $lockTimestamp > time();
    }

    private function recordFailedLoginAttempt(int $userId, int $failedAttempts): void
    {
        $newFailedAttempts = $failedAttempts + 1;
        $lockUntil = null;

        if ($newFailedAttempts >= self::LOGIN_MAX_FAILED_ATTEMPTS) {
            $lockUntil = (new DateTimeImmutable('+' . self::LOGIN_LOCK_MINUTES . ' minutes'))->format('Y-m-d H:i:s');
        }

        $this->callProcedureAll('CALL sesta_admin_kullanici_basarisiz_giris(?, ?, ?)', [
            $userId,
            $newFailedAttempts,
            $lockUntil,
        ]);
    }

    private function resetLoginFailures(int $userId): void
    {
        $this->callProcedureAll('CALL sesta_admin_kullanici_giris_sifirla(?)', [$userId]);
    }

    private function slowFailedLoginResponse(): void
    {
        usleep(random_int(120000, 220000));
    }

    private function startPendingMfaSession(array $user): void
    {
        $_SESSION['admin_mfa_pending_user'] = [
            'id' => (int) $user['kullanici_id'],
            'eposta' => (string) $user['eposta'],
        ];
        $_SESSION['admin_mfa_pending_started_at'] = time();
        session_regenerate_id(true);
    }

    public function dashboardCounts(): array
    {
        try {
            $counts = $this->callProcedureOne('CALL sesta_dashboard_sayilari()');
        } catch (Throwable $exception) {
            $counts = $this->fallbackDashboardCounts();
        }

        if ($counts === null) {
            return [
                'projeler' => 0,
                'hizmetler' => 0,
                'blog' => 0,
                'talepler' => 0,
            ];
        }

        return [
            'projeler' => (int) ($counts['projeler'] ?? 0),
            'hizmetler' => (int) ($counts['hizmetler'] ?? 0),
            'blog' => (int) ($counts['blog'] ?? 0),
            'talepler' => (int) ($counts['talepler'] ?? 0),
        ];
    }

    private function fallbackDashboardCounts(): array
    {
        return [
            'projeler' => $this->safeResultCount(fn (): array => $this->getProjects()),
            'hizmetler' => $this->safeResultCount(fn (): array => $this->getServices()),
            'blog' => $this->safeResultCount(fn (): array => $this->getBlogPosts()),
            'talepler' => $this->safeResultCount(fn (): array => $this->getContactRequests()),
        ];
    }

    private function fallbackContactRequestsPath(): string
    {
        return ADMIN_RUNTIME_PATH . '/contact_requests.json';
    }

    private function getFallbackContactRequests(): array
    {
        $path = $this->fallbackContactRequestsPath();

        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = [];

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }

            $items[] = [
                'talep_id' => (int) ($item['talep_id'] ?? 0),
                'ad_soyad' => (string) ($item['ad_soyad'] ?? ''),
                'telefon' => (string) ($item['telefon'] ?? ''),
                'eposta' => (string) ($item['eposta'] ?? ''),
                'proje_tipi' => (string) ($item['proje_tipi'] ?? ''),
                'hizmet_alani' => (string) ($item['hizmet_alani'] ?? ''),
                'lokasyon' => (string) ($item['lokasyon'] ?? ''),
                'mesaj' => (string) ($item['mesaj'] ?? ''),
                'okundu' => (int) ($item['okundu'] ?? 2),
                'admin_notu' => (string) ($item['admin_notu'] ?? ''),
                'kayit_tarihi' => (string) ($item['kayit_tarihi'] ?? ''),
                'ip_adresi' => (string) ($item['ip_adresi'] ?? ''),
                'user_agent' => (string) ($item['user_agent'] ?? ''),
                'sil' => (int) ($item['sil'] ?? 2),
            ];
        }

        return array_values(array_filter($items, static fn (array $item): bool => (int) ($item['sil'] ?? 2) === 2));
    }

    private function saveFallbackContactRequests(array $items): void
    {
        $path = $this->fallbackContactRequestsPath();
        $json = json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Yedek talep kaydı oluşturulamadı.');
        }

        if (@file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Yedek talep kaydı yazılamadı.');
        }

        @chmod($path, 0660);
    }

    private function nextFallbackContactRequestId(array $items): int
    {
        $maxId = 900000000;

        foreach ($items as $item) {
            $maxId = max($maxId, (int) ($item['talep_id'] ?? 0));
        }

        return $maxId + 1;
    }

    private function findFallbackContactRequestIndex(array $items, int $id): ?int
    {
        foreach ($items as $index => $item) {
            if ((int) ($item['talep_id'] ?? 0) === $id) {
                return $index;
            }
        }

        return null;
    }

    private function mergeContactRequestsWithFallback(array $databaseItems): array
    {
        $merged = [];

        foreach ($databaseItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $merged[(int) ($item['talep_id'] ?? 0)] = $item;
        }

        foreach ($this->getFallbackContactRequests() as $item) {
            $merged[(int) ($item['talep_id'] ?? 0)] = $item;
        }

        $items = array_values($merged);

        usort($items, static function (array $left, array $right): int {
            return (int) ($right['talep_id'] ?? 0) <=> (int) ($left['talep_id'] ?? 0);
        });

        return $items;
    }

    private function callProcedureAll(string $sql, array $params = []): array
    {
        return $this->dal->all($this->extractProcedureName($sql), $params);
    }

    private function callProcedureOne(string $sql, array $params = []): ?array
    {
        return $this->dal->one($this->extractProcedureName($sql), $params);
    }

    private function callProcedurePage(string $sql, array $params, int $page, int $limit): array
    {
        return $this->dal->page($this->extractProcedureName($sql), $params, $page, $limit);
    }

    private function extractProcedureName(string $sql): string
    {
        if (preg_match('/^\s*CALL\s+([a-zA-Z0-9_]+)/i', $sql, $matches) !== 1) {
            throw new InvalidArgumentException('Gecersiz procedure cagri ifadesi.');
        }

        return (string) $matches[1];
    }

    private function safeResultCount(callable $resolver): int
    {
        try {
            $items = $resolver();
        } catch (Throwable $exception) {
            return 0;
        }

        return is_array($items) ? count($items) : 0;
    }

    public function getAdminUsersPage(int $page = 1, int $limit = 25): array
    {
        return $this->callProcedurePage('CALL sesta_admin_kullanicilar_sayfa(?, ?)', [$page, $limit], $page, $limit);
    }
    public function getSiteSettings(): array
    {
        $settings = $this->callProcedureOne('CALL sesta_site_ayarlari_getir()');

        return $settings ?: [
            'id' => null,
            'site_url' => '',
            'site_baslik' => '',
            'site_desc' => '',
            'marka_adi' => '',
            'marka_ust_tanim' => '',
            'telefon' => '',
            'eposta' => '',
            'adres' => '',
            'harita_linki' => '',
            'bakim_modu' => 2,
        ];
    }

    public function saveSiteSettings(array $data): void
    {
        $fields = [
            'site_url', 'site_baslik', 'site_desc', 'marka_adi', 'marka_ust_tanim',
            'telefon', 'eposta', 'adres', 'harita_linki', 'bakim_modu'
        ];

        $data = AdminInputGuard::sanitizeSiteSettingsPayload($data);
        $values = array_map(fn ($field) => $data[$field] ?? null, $fields);
        $this->callProcedureAll('CALL sesta_site_ayarlari_kaydet(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $values);
    }

    public function getProjects(): array
    {
        return $this->callProcedureAll('CALL sesta_projeler_hepsi()');
    }

    public function getProjectsPage(int $page = 1, int $limit = 25): array
    {
        return $this->callProcedurePage('CALL sesta_projeler_sayfa(?, ?)', [$page, $limit], $page, $limit);
    }

    public function getProject(int $id): ?array
    {
        return $this->callProcedureOne('CALL sesta_proje_getir(?)', [$id]);
    }

    public function projectSlugExists(string $slug, ?int $ignoreId = null): bool
    {
        $result = $this->callProcedureOne('CALL sesta_proje_slug_var_mi(?, ?)', [$slug, $ignoreId]);

        return (int) ($result['adet'] ?? 0) > 0;
    }

    public function saveProject(array $data, ?int $id = null): void
    {
        $fields = [
            'sirasi', 'proje_adi', 'slug', 'kategori', 'sehir', 'yapi_tipi',
            'kapsam', 'rol', 'teknik_cikti', 'tonaj', 'metrekare',
            'teslim_suresi', 'kisa_ozet', 'veri_durumu', 'veri_notu',
            'one_cikan', 'tasarim_gorseli', 'final_gorseli', 'galeri_gorselleri', 'durum'
        ];

        $data = AdminInputGuard::sanitizeProjectPayload($data);

        $values = array_map(fn ($field) => $data[$field] ?? null, $fields);

        if ($id) {
            $this->callProcedureAll(
                'CALL sesta_proje_guncelle(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge([$id], $values)
            );
            return;
        }

        $this->callProcedureAll(
            'CALL sesta_proje_ekle(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($values, [(int) ($this->currentUser()['id'] ?? 0)])
        );
    }

    public function deleteProject(int $id): void
    {
        $this->callProcedureAll('CALL sesta_proje_sil(?)', [$id]);
    }

    public function getServices(): array
    {
        return $this->callProcedureAll('CALL sesta_hizmetler_hepsi()');
    }

    public function getServicesPage(int $page = 1, int $limit = 25): array
    {
        return $this->callProcedurePage('CALL sesta_hizmetler_sayfa(?, ?)', [$page, $limit], $page, $limit);
    }

    public function getService(int $id): ?array
    {
        return $this->callProcedureOne('CALL sesta_hizmet_getir(?)', [$id]);
    }

    public function serviceSlugExists(string $slug, ?int $ignoreId = null): bool
    {
        $result = $this->callProcedureOne('CALL sesta_hizmet_slug_var_mi(?, ?)', [$slug, $ignoreId]);

        return (int) ($result['adet'] ?? 0) > 0;
    }

    public function getServiceDeliverables(int $serviceId): array
    {
        return $this->callProcedureAll('CALL sesta_hizmet_teslimleri_getir(?)', [$serviceId]);
    }

    public function saveService(array $data, array $deliverables = [], ?int $id = null): int
    {
        $fields = [
            'sirasi', 'baslik', 'slug', 'kisa_ozet', 'detay', 'gorsel',
            'ne_yapiyoruz', 'hangi_ciktilar', 'kimler_icin', 'surece_katkisi', 'durum'
        ];

        $validated = AdminInputGuard::sanitizeServicePayload($data, $deliverables);
        $data = $validated['payload'];
        $deliverables = $validated['deliverables'];

        $values = array_map(fn ($field) => $data[$field] ?? null, $fields);

        if ($id) {
            $this->callProcedureAll(
                'CALL sesta_hizmet_guncelle(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge([$id], $values)
            );
            $serviceId = $id;
        } else {
            $result = $this->callProcedureOne(
                'CALL sesta_hizmet_ekle(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge($values, [(int) ($this->currentUser()['id'] ?? 0)])
            );
            $serviceId = (int) ($result['hizmet_id'] ?? 0);
        }

        $this->saveServiceDeliverables($serviceId, $deliverables);
        return $serviceId;
    }

    private function saveServiceDeliverables(int $serviceId, array $deliverables): void
    {
        $this->callProcedureAll('CALL sesta_hizmet_teslimleri_temizle(?)', [$serviceId]);

        foreach ($deliverables as $index => $item) {
            $title = clean_text($item['baslik'] ?? '');
            $description = clean_text($item['aciklama'] ?? '');
            if ($title === '' && $description === '') {
                continue;
            }

            $this->callProcedureAll('CALL sesta_hizmet_teslim_ekle(?, ?, ?, ?)', [$serviceId, $index + 1, $title, $description]);
        }
    }

    public function deleteService(int $id): void
    {
        $this->callProcedureAll('CALL sesta_hizmet_sil(?)', [$id]);
    }

    public function getHeroSlides(): array
    {
        return $this->callProcedureAll('CALL sesta_hero_hepsi()');
    }

    public function getHeroSlidesPage(int $page = 1, int $limit = 25): array
    {
        return $this->callProcedurePage('CALL sesta_hero_sayfa(?, ?)', [$page, $limit], $page, $limit);
    }

    public function getHeroSlide(int $id): ?array
    {
        return $this->callProcedureOne('CALL sesta_hero_getir(?)', [$id]);
    }

    public function saveHeroSlide(array $data, ?int $id = null): void
    {
        $fields = [
            'sirasi', 'eyebrow', 'baslik', 'alt_baslik', 'aciklama', 'gorsel',
            'cta_bir_metin', 'cta_bir_link', 'cta_iki_metin', 'cta_iki_link', 'durum'
        ];

        $data = AdminInputGuard::sanitizeHeroPayload($data);

        $values = array_map(fn ($field) => $data[$field] ?? null, $fields);

        if ($id) {
            $this->callProcedureAll(
                'CALL sesta_hero_guncelle(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge([$id], $values)
            );
            return;
        }

        $this->callProcedureAll(
            'CALL sesta_hero_ekle(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($values, [(int) ($this->currentUser()['id'] ?? 0)])
        );
    }

    public function deleteHeroSlide(int $id): void
    {
        $this->callProcedureAll('CALL sesta_hero_sil(?)', [$id]);
    }

    public function getBlogPosts(): array
    {
        return $this->callProcedureAll('CALL sesta_blog_hepsi()');
    }

    public function getBlogPostsPage(int $page = 1, int $limit = 25): array
    {
        return $this->callProcedurePage('CALL sesta_blog_sayfa(?, ?)', [$page, $limit], $page, $limit);
    }

    public function getBlogPost(int $id): ?array
    {
        return $this->callProcedureOne('CALL sesta_blog_getir(?)', [$id]);
    }

    public function blogSlugExists(string $slug, ?int $ignoreId = null): bool
    {
        $result = $this->callProcedureOne('CALL sesta_blog_slug_var_mi(?, ?)', [$slug, $ignoreId]);

        return (int) ($result['adet'] ?? 0) > 0;
    }

    public function saveBlogPost(array $data, ?int $id = null): void
    {
        $fields = [
            'baslik', 'slug', 'excerpt', 'kapak_gorseli', 'yazar', 'yayin_tarihi',
            'etiketler', 'icerik', 'seo_title', 'seo_desc', 'durum'
        ];

        $data = AdminInputGuard::sanitizeBlogPayload($data);

        $values = array_map(fn ($field) => $data[$field] ?? null, $fields);

        if ($id) {
            $this->callProcedureAll(
                'CALL sesta_blog_guncelle(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                array_merge([$id], $values)
            );
            return;
        }

        $this->callProcedureAll(
            'CALL sesta_blog_ekle(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($values, [(int) ($this->currentUser()['id'] ?? 0)])
        );
    }

    public function deleteBlogPost(int $id): void
    {
        $this->callProcedureAll('CALL sesta_blog_sil(?)', [$id]);
    }

    public function getFaqs(): array
    {
        return $this->callProcedureAll('CALL sesta_faq_hepsi()');
    }

    public function getFaqsPage(int $page = 1, int $limit = 25): array
    {
        return $this->callProcedurePage('CALL sesta_faq_sayfa(?, ?)', [$page, $limit], $page, $limit);
    }

    public function getFaq(int $id): ?array
    {
        return $this->callProcedureOne('CALL sesta_faq_getir(?)', [$id]);
    }

    public function saveFaq(array $data, ?int $id = null): void
    {
        $fields = ['sirasi', 'soru', 'cevap', 'durum'];

        $data = AdminInputGuard::sanitizeFaqPayload($data);

        $values = array_map(fn ($field) => $data[$field] ?? null, $fields);

        if ($id) {
            $this->callProcedureAll('CALL sesta_faq_guncelle(?, ?, ?, ?, ?)', array_merge([$id], $values));
            return;
        }

        $this->callProcedureAll('CALL sesta_faq_ekle(?, ?, ?, ?)', $values);
    }

    public function deleteFaq(int $id): void
    {
        $this->callProcedureAll('CALL sesta_faq_sil(?)', [$id]);
    }

    public function getMediaItems(): array
    {
        return $this->callProcedureAll('CALL sesta_medya_hepsi()');
    }

    public function getMediaCategories(): array
    {
        return array_map(
            static fn (array $row): string => (string) $row['kategori'],
            $this->callProcedureAll('CALL sesta_medya_kategorileri()')
        );
    }

    public function getMediaItemsFiltered(?string $category = null): array
    {
        return $this->callProcedureAll('CALL sesta_medya_filtreli(?)', [trim((string) $category)]);
    }

    public function getMediaItemsFilteredPage(?string $category = null, int $page = 1, int $limit = 25): array
    {
        return $this->callProcedurePage(
            'CALL sesta_medya_filtreli_sayfa(?, ?, ?)',
            [trim((string) $category), $page, $limit],
            $page,
            $limit
        );
    }

    public function getActiveMediaItemsByCategory(array $categories = []): array
    {
        $normalized = array_values(array_filter(array_map('trim', $categories), static fn (string $value): bool => $value !== ''));

        return $this->callProcedureAll('CALL sesta_medya_aktif_kategorilere_gore(?)', [implode(',', $normalized)]);
    }

    public function getMediaItem(int $id): ?array
    {
        return $this->callProcedureOne('CALL sesta_medya_getir(?)', [$id]);
    }

    public function saveMediaItem(array $data, ?int $id = null): void
    {
        $fields = ['dosya_adi', 'dosya_yolu', 'alt_metin', 'kategori', 'durum'];

        $data = AdminInputGuard::sanitizeMediaPayload($data);

        $values = array_map(fn ($field) => $data[$field] ?? null, $fields);

        if ($id) {
            $this->callProcedureAll('CALL sesta_medya_guncelle(?, ?, ?, ?, ?, ?)', array_merge([$id], $values));
            return;
        }

        $this->callProcedureAll(
            'CALL sesta_medya_ekle(?, ?, ?, ?, ?, ?)',
            array_merge($values, [(int) ($this->currentUser()['id'] ?? 0)])
        );
    }

    public function deleteMediaItem(int $id): void
    {
        $this->callProcedureAll('CALL sesta_medya_sil(?)', [$id]);
    }

    public function getContactRequests(): array
    {
        $items = [];

        try {
            $items = $this->callProcedureAll('CALL sesta_talepler_hepsi()');
        } catch (Throwable $exception) {
            $items = [];
        }

        return $this->mergeContactRequestsWithFallback($items);
    }

    public function getContactRequestsPage(int $page = 1, int $limit = 25): array
    {
        $normalizedPage = max($page, 1);
        $normalizedLimit = min(max($limit, 1), 100);
        $items = $this->getContactRequests();
        $pagination = pagination_meta(count($items), $normalizedPage, $normalizedLimit);

        return [
            'items' => array_slice($items, (int) $pagination['offset'], (int) $pagination['limit']),
            'pagination' => $pagination,
        ];
    }

    public function getContactRequest(int $id): ?array
    {
        foreach ($this->getFallbackContactRequests() as $item) {
            if ((int) ($item['talep_id'] ?? 0) === $id) {
                return $item;
            }
        }

        try {
            return $this->callProcedureOne('CALL sesta_talep_getir(?)', [$id]);
        } catch (Throwable $exception) {
            return null;
        }
    }

    public function updateContactRequest(int $id, array $data): void
    {
        $sanitized = AdminInputGuard::sanitizeContactRequestUpdatePayload($data);
        $okundu = (int) $sanitized['okundu'];
        $adminNotu = (string) $sanitized['admin_notu'];
        $fallbackItems = $this->getFallbackContactRequests();
        $fallbackIndex = $this->findFallbackContactRequestIndex($fallbackItems, $id);

        if ($fallbackIndex !== null) {
            $fallbackItems[$fallbackIndex]['okundu'] = $okundu;
            $fallbackItems[$fallbackIndex]['admin_notu'] = $adminNotu;
            $this->saveFallbackContactRequests($fallbackItems);
            return;
        }

        try {
            $this->callProcedureAll('CALL sesta_talep_guncelle(?, ?, ?)', [$id, $okundu, $adminNotu]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Talep guncellenemedi.', 0, $exception);
        }
    }

    public function deleteContactRequest(int $id): void
    {
        $fallbackItems = $this->getFallbackContactRequests();
        $fallbackIndex = $this->findFallbackContactRequestIndex($fallbackItems, $id);

        if ($fallbackIndex !== null) {
            $fallbackItems[$fallbackIndex]['sil'] = 1;
            $this->saveFallbackContactRequests($fallbackItems);
            return;
        }

        try {
            $this->callProcedureAll('CALL sesta_talep_sil(?)', [$id]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Talep silinemedi.', 0, $exception);
        }
    }

    public function createContactRequest(array $data): void
    {
        $record = AdminInputGuard::sanitizeContactRequestPayload($data);
        $payload = array_values($record);

        try {
            $this->callProcedureAll('CALL sesta_talep_ekle(?, ?, ?, ?, ?, ?, ?, ?, ?)', $payload);
            return;
        } catch (Throwable $exception) {
            $items = $this->getFallbackContactRequests();
            $items[] = array_merge($record, [
                'talep_id' => $this->nextFallbackContactRequestId($items),
                'okundu' => 2,
                'admin_notu' => '',
                'kayit_tarihi' => date('Y-m-d H:i:s'),
                'sil' => 2,
            ]);
            $this->saveFallbackContactRequests($items);
        }
    }
}
