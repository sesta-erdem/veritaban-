<?php

declare(strict_types=1);

final class AdminStoredProcedureDal
{
    private ?PDO $db = null;

    public function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        try {
            $this->db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            $this->db = null;

            if (function_exists('log_security_event')) {
                log_security_event('db_connect_failed', [
                    'message' => $exception->getMessage(),
                    'host' => DB_HOST,
                    'port' => DB_PORT,
                    'database' => DB_NAME,
                ]);
            }
        }
    }

    public function connection(): ?PDO
    {
        return $this->db;
    }

    public function isConnected(): bool
    {
        return $this->db instanceof PDO;
    }

    public function all(string $procedure, array $params = []): array
    {
        $stmt = $this->executeProcedure($procedure, $params);
        $rows = $stmt->fetchAll();
        $this->closeProcedureCursor($stmt);

        return $rows;
    }

    public function one(string $procedure, array $params = []): ?array
    {
        $stmt = $this->executeProcedure($procedure, $params);
        $row = $stmt->fetch();
        $this->closeProcedureCursor($stmt);

        return $row ?: null;
    }

    public function page(string $procedure, array $params, int $page, int $limit): array
    {
        $stmt = $this->executeProcedure($procedure, $params);

        $countRow = $stmt->fetch() ?: ['total' => 0];
        $stmt->nextRowset();
        $items = $stmt->fetchAll();
        $this->closeProcedureCursor($stmt);

        return [
            'items' => $items,
            'pagination' => pagination_meta((int) ($countRow['total'] ?? 0), max($page, 1), min(max($limit, 1), 100)),
        ];
    }

    public function getAdminLoginUserByEmail(string $email): ?array
    {
        return $this->one('sesta_admin_giris_kullanici_getir', [$email]);
    }

    public function getAdminUsers(): array
    {
        return $this->all('sesta_admin_kullanicilar_hepsi');
    }

    public function getAdminUserById(int $id): ?array
    {
        return $this->one('sesta_admin_kullanici_getir', [$id]);
    }

    public function adminEmailExists(string $email, ?int $ignoreId = null): bool
    {
        $row = $this->one('sesta_admin_eposta_var_mi', [$email, $ignoreId]);

        return (int) ($row['adet'] ?? 0) > 0;
    }

    public function createAdminUser(string $name, string $email, string $passwordHash, int $role, int $sil): void
    {
        $this->all('sesta_admin_kullanici_ekle', [$name, $email, $passwordHash, $role, $sil]);
    }

    public function updateAdminUserWithPassword(int $id, string $name, string $email, string $passwordHash, int $role, int $sil): void
    {
        $this->all('sesta_admin_kullanici_sifreli_guncelle', [$id, $name, $email, $passwordHash, $role, $sil]);
    }

    public function updateAdminUser(int $id, string $name, string $email, int $role, int $sil): void
    {
        $this->all('sesta_admin_kullanici_guncelle', [$id, $name, $email, $role, $sil]);
    }

    public function updateAdminPasswordHash(int $id, string $passwordHash): void
    {
        $this->all('sesta_admin_kullanici_sifre_hash_guncelle', [$id, $passwordHash]);
    }

    public function clearAdminUserLock(int $id): void
    {
        $this->all('sesta_admin_kullanici_kilit_temizle', [$id]);
    }

    public function saveAdminFailedLogin(int $id, int $failedAttempts, ?string $lockUntil): void
    {
        $this->all('sesta_admin_kullanici_basarisiz_giris', [$id, $failedAttempts, $lockUntil]);
    }

    public function resetAdminLoginFailures(int $id): void
    {
        $this->all('sesta_admin_kullanici_giris_sifirla', [$id]);
    }

    public function getDashboardCounts(): ?array
    {
        return $this->one('sesta_dashboard_sayilari');
    }

    public function getAdminUsersPage(int $page, int $limit): array
    {
        return $this->page('sesta_admin_kullanicilar_sayfa', [$page, $limit], $page, $limit);
    }

    public function getSiteSettings(): ?array
    {
        return $this->one('sesta_site_ayarlari_getir');
    }

    public function saveSiteSettings(array $values): void
    {
        $this->all('sesta_site_ayarlari_kaydet', $values);
    }

    public function getProjects(): array
    {
        return $this->all('sesta_projeler_hepsi');
    }

    public function getProjectsPage(int $page, int $limit): array
    {
        return $this->page('sesta_projeler_sayfa', [$page, $limit], $page, $limit);
    }

    public function getProjectById(int $id): ?array
    {
        return $this->one('sesta_proje_getir', [$id]);
    }

    public function projectSlugExists(string $slug, ?int $ignoreId = null): bool
    {
        $row = $this->one('sesta_proje_slug_var_mi', [$slug, $ignoreId]);

        return (int) ($row['adet'] ?? 0) > 0;
    }

    public function updateProject(int $id, array $values): void
    {
        $this->all('sesta_proje_guncelle', array_merge([$id], $values));
    }

    public function createProject(array $values, int $createdBy): void
    {
        $this->all('sesta_proje_ekle', array_merge($values, [$createdBy]));
    }

    public function deleteProject(int $id): void
    {
        $this->all('sesta_proje_sil', [$id]);
    }

    public function getServices(): array
    {
        return $this->all('sesta_hizmetler_hepsi');
    }

    public function getServicesPage(int $page, int $limit): array
    {
        return $this->page('sesta_hizmetler_sayfa', [$page, $limit], $page, $limit);
    }

    public function getServiceById(int $id): ?array
    {
        return $this->one('sesta_hizmet_getir', [$id]);
    }

    public function serviceSlugExists(string $slug, ?int $ignoreId = null): bool
    {
        $row = $this->one('sesta_hizmet_slug_var_mi', [$slug, $ignoreId]);

        return (int) ($row['adet'] ?? 0) > 0;
    }

    public function getServiceDeliverables(int $serviceId): array
    {
        return $this->all('sesta_hizmet_teslimleri_getir', [$serviceId]);
    }

    public function updateService(int $id, array $values): void
    {
        $this->all('sesta_hizmet_guncelle', array_merge([$id], $values));
    }

    public function createService(array $values, int $createdBy): int
    {
        $row = $this->one('sesta_hizmet_ekle', array_merge($values, [$createdBy]));

        return (int) ($row['hizmet_id'] ?? 0);
    }

    public function clearServiceDeliverables(int $serviceId): void
    {
        $this->all('sesta_hizmet_teslimleri_temizle', [$serviceId]);
    }

    public function createServiceDeliverable(int $serviceId, int $order, string $title, string $description): void
    {
        $this->all('sesta_hizmet_teslim_ekle', [$serviceId, $order, $title, $description]);
    }

    public function deleteService(int $id): void
    {
        $this->all('sesta_hizmet_sil', [$id]);
    }

    public function getHeroSlides(): array
    {
        return $this->all('sesta_hero_hepsi');
    }

    public function getHeroSlidesPage(int $page, int $limit): array
    {
        return $this->page('sesta_hero_sayfa', [$page, $limit], $page, $limit);
    }

    public function getHeroSlideById(int $id): ?array
    {
        return $this->one('sesta_hero_getir', [$id]);
    }

    public function updateHeroSlide(int $id, array $values): void
    {
        $this->all('sesta_hero_guncelle', array_merge([$id], $values));
    }

    public function createHeroSlide(array $values, int $createdBy): void
    {
        $this->all('sesta_hero_ekle', array_merge($values, [$createdBy]));
    }

    public function deleteHeroSlide(int $id): void
    {
        $this->all('sesta_hero_sil', [$id]);
    }

    public function getBlogPosts(): array
    {
        return $this->all('sesta_blog_hepsi');
    }

    public function getBlogPostsPage(int $page, int $limit): array
    {
        return $this->page('sesta_blog_sayfa', [$page, $limit], $page, $limit);
    }

    public function getBlogPostById(int $id): ?array
    {
        return $this->one('sesta_blog_getir', [$id]);
    }

    public function blogSlugExists(string $slug, ?int $ignoreId = null): bool
    {
        $row = $this->one('sesta_blog_slug_var_mi', [$slug, $ignoreId]);

        return (int) ($row['adet'] ?? 0) > 0;
    }

    public function updateBlogPost(int $id, array $values): void
    {
        $this->all('sesta_blog_guncelle', array_merge([$id], $values));
    }

    public function createBlogPost(array $values, int $createdBy): void
    {
        $this->all('sesta_blog_ekle', array_merge($values, [$createdBy]));
    }

    public function deleteBlogPost(int $id): void
    {
        $this->all('sesta_blog_sil', [$id]);
    }

    public function getFaqs(): array
    {
        return $this->all('sesta_faq_hepsi');
    }

    public function getFaqsPage(int $page, int $limit): array
    {
        return $this->page('sesta_faq_sayfa', [$page, $limit], $page, $limit);
    }

    public function getFaqById(int $id): ?array
    {
        return $this->one('sesta_faq_getir', [$id]);
    }

    public function updateFaq(int $id, array $values): void
    {
        $this->all('sesta_faq_guncelle', array_merge([$id], $values));
    }

    public function createFaq(array $values): void
    {
        $this->all('sesta_faq_ekle', $values);
    }

    public function deleteFaq(int $id): void
    {
        $this->all('sesta_faq_sil', [$id]);
    }

    public function getMediaItems(): array
    {
        return $this->all('sesta_medya_hepsi');
    }

    public function getMediaCategories(): array
    {
        return $this->all('sesta_medya_kategorileri');
    }

    public function getFilteredMediaItems(?string $category = null): array
    {
        return $this->all('sesta_medya_filtreli', [trim((string) $category)]);
    }

    public function getFilteredMediaItemsPage(?string $category, int $page, int $limit): array
    {
        return $this->page('sesta_medya_filtreli_sayfa', [trim((string) $category), $page, $limit], $page, $limit);
    }

    public function getActiveMediaItemsByCategories(array $categories = []): array
    {
        $normalized = array_values(array_filter(array_map('trim', $categories), static fn (string $value): bool => $value !== ''));

        return $this->all('sesta_medya_aktif_kategorilere_gore', [implode(',', $normalized)]);
    }

    public function getMediaItemById(int $id): ?array
    {
        return $this->one('sesta_medya_getir', [$id]);
    }

    public function updateMediaItem(int $id, array $values): void
    {
        $this->all('sesta_medya_guncelle', array_merge([$id], $values));
    }

    public function createMediaItem(array $values, int $createdBy): void
    {
        $this->all('sesta_medya_ekle', array_merge($values, [$createdBy]));
    }

    public function deleteMediaItem(int $id): void
    {
        $this->all('sesta_medya_sil', [$id]);
    }

    public function getContactRequests(): array
    {
        return $this->all('sesta_talepler_hepsi');
    }

    public function getContactRequestById(int $id): ?array
    {
        return $this->one('sesta_talep_getir', [$id]);
    }

    public function updateContactRequest(int $id, int $okundu, string $adminNotu): void
    {
        $this->all('sesta_talep_guncelle', [$id, $okundu, $adminNotu]);
    }

    public function deleteContactRequest(int $id): void
    {
        $this->all('sesta_talep_sil', [$id]);
    }

    public function createContactRequest(array $payload): void
    {
        $this->all('sesta_talep_ekle', $payload);
    }

    private function executeProcedure(string $procedure, array $params = []): PDOStatement
    {
        if (!$this->db instanceof PDO) {
            throw new RuntimeException('Veritabanı bağlantısı kurulamadı.');
        }

        $placeholders = [];
        foreach ($params as $_) {
            $placeholders[] = '?';
        }

        $sql = 'CALL ' . $procedure . '(' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    private function closeProcedureCursor(PDOStatement $stmt): void
    {
        do {
            // MariaDB procedures can leave extra result sets open after CALL.
        } while ($stmt->nextRowset());

        $stmt->closeCursor();
    }
}
