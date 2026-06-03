<?php

declare(strict_types=1);

final class AdminInputGuard
{
    public static function sanitizeAdminUserPayload(array $payload, bool $isUpdate = false): array
    {
        $sanitized = [
            'ad_soyad' => self::text($payload['ad_soyad'] ?? '', 'Ad soyad', 2, 120),
            'eposta' => self::email($payload['eposta'] ?? '', true),
            'rutbe' => self::enumInt($payload['rutbe'] ?? 2, [1, 2], 'Gecerli bir rol secin.'),
            'sil' => self::enumInt($payload['sil'] ?? 2, [1, 2], 'Gecerli bir durum secin.'),
            'password' => '',
        ];

        $password = (string) ($payload['password'] ?? '');
        if ($password !== '') {
            $sanitized['password'] = self::password($password);
        } elseif (!$isUpdate) {
            throw new InvalidArgumentException('Yeni kullanici icin sifre zorunludur.');
        }

        return $sanitized;
    }

    public static function sanitizeSiteSettingsPayload(array $payload): array
    {
        return [
            'site_url' => self::absoluteHttpUrl($payload['site_url'] ?? '', 'Site URL', true, 2048),
            'site_baslik' => self::text($payload['site_baslik'] ?? '', 'Site basligi', 3, 160),
            'site_desc' => self::multiline($payload['site_desc'] ?? '', 'Site aciklamasi', 0, 320, true),
            'marka_adi' => self::text($payload['marka_adi'] ?? '', 'Marka adi', 0, 120, true),
            'marka_ust_tanim' => self::text($payload['marka_ust_tanim'] ?? '', 'Marka ust tanimi', 0, 120, true),
            'telefon' => self::phone($payload['telefon'] ?? '', false),
            'eposta' => self::email($payload['eposta'] ?? '', false),
            'adres' => self::multiline($payload['adres'] ?? '', 'Adres', 0, 500, true),
            'harita_linki' => self::absoluteHttpUrl($payload['harita_linki'] ?? '', 'Harita linki', false, 2048),
            'bakim_modu' => self::enumInt($payload['bakim_modu'] ?? 2, [1, 2], 'Bakim modu degeri gecersiz.'),
        ];
    }

    public static function sanitizeProjectPayload(array $payload): array
    {
        return [
            'sirasi' => self::intRange($payload['sirasi'] ?? 0, 'Sira', 0, 9999),
            'proje_adi' => self::text($payload['proje_adi'] ?? '', 'Proje adi', 2, 160),
            'slug' => self::slug($payload['slug'] ?? ''),
            'kategori' => self::text($payload['kategori'] ?? '', 'Kategori', 2, 80),
            'sehir' => self::text($payload['sehir'] ?? '', 'Sehir', 2, 80),
            'yapi_tipi' => self::text($payload['yapi_tipi'] ?? '', 'Yapi tipi', 0, 80, true),
            'kapsam' => self::multiline($payload['kapsam'] ?? '', 'Kapsam', 0, 4000, true),
            'rol' => self::multiline($payload['rol'] ?? '', 'Rol', 0, 1000, true),
            'teknik_cikti' => self::multiline($payload['teknik_cikti'] ?? '', 'Teknik cikti', 0, 2000, true),
            'tonaj' => self::text($payload['tonaj'] ?? '', 'Tonaj', 0, 60, true),
            'metrekare' => self::text($payload['metrekare'] ?? '', 'Metrekare', 0, 60, true),
            'teslim_suresi' => self::text($payload['teslim_suresi'] ?? '', 'Teslim suresi', 0, 80, true),
            'kisa_ozet' => self::multiline($payload['kisa_ozet'] ?? '', 'Kisa ozet', 10, 800),
            'veri_durumu' => self::text($payload['veri_durumu'] ?? '', 'Veri durumu', 0, 120, true),
            'veri_notu' => self::multiline($payload['veri_notu'] ?? '', 'Veri notu', 0, 1000, true),
            'one_cikan' => self::enumInt($payload['one_cikan'] ?? 2, [1, 2], 'One cikan degeri gecersiz.'),
            'tasarim_gorseli' => self::assetPath($payload['tasarim_gorseli'] ?? '', 'Tasarim gorseli', true),
            'final_gorseli' => self::assetPath($payload['final_gorseli'] ?? '', 'Final gorseli', true),
            'galeri_gorselleri' => implode("\n", self::assetPathList($payload['galeri_gorselleri'] ?? '', 30)),
            'durum' => self::enumInt($payload['durum'] ?? 1, [1, 2], 'Durum degeri gecersiz.'),
        ];
    }

    public static function sanitizeServicePayload(array $payload, array $deliverables = []): array
    {
        $sanitizedDeliverables = [];

        foreach (array_slice($deliverables, 0, 10) as $index => $deliverable) {
            if (!is_array($deliverable)) {
                throw new InvalidArgumentException('Teslim bilgileri gecersiz.');
            }

            $title = self::text($deliverable['baslik'] ?? '', 'Teslim basligi', 0, 120, true);
            $description = self::text($deliverable['aciklama'] ?? '', 'Teslim aciklamasi', 0, 220, true);

            if ($title === '' && $description === '') {
                continue;
            }

            $sanitizedDeliverables[] = [
                'baslik' => $title,
                'aciklama' => $description,
                'sirasi' => $index + 1,
            ];
        }

        return [
            'payload' => [
                'sirasi' => self::intRange($payload['sirasi'] ?? 0, 'Sira', 0, 9999),
                'baslik' => self::text($payload['baslik'] ?? '', 'Hizmet basligi', 2, 160),
                'slug' => self::slug($payload['slug'] ?? ''),
                'kisa_ozet' => self::multiline($payload['kisa_ozet'] ?? '', 'Kisa ozet', 10, 800),
                'detay' => self::multiline($payload['detay'] ?? '', 'Detay', 10, 5000),
                'gorsel' => self::assetPath($payload['gorsel'] ?? '', 'Gorsel', true),
                'ne_yapiyoruz' => self::multiline($payload['ne_yapiyoruz'] ?? '', 'Ne yapiyoruz', 0, 1500, true),
                'hangi_ciktilar' => self::multiline($payload['hangi_ciktilar'] ?? '', 'Hangi ciktilar', 0, 1500, true),
                'kimler_icin' => self::multiline($payload['kimler_icin'] ?? '', 'Kimler icin', 0, 1500, true),
                'surece_katkisi' => self::multiline($payload['surece_katkisi'] ?? '', 'Surece katkisi', 0, 1500, true),
                'durum' => self::enumInt($payload['durum'] ?? 1, [1, 2], 'Durum degeri gecersiz.'),
            ],
            'deliverables' => $sanitizedDeliverables,
        ];
    }

    public static function sanitizeHeroPayload(array $payload): array
    {
        return [
            'sirasi' => self::intRange($payload['sirasi'] ?? 0, 'Sira', 0, 9999),
            'eyebrow' => self::text($payload['eyebrow'] ?? '', 'Eyebrow', 0, 80, true),
            'baslik' => self::text($payload['baslik'] ?? '', 'Baslik', 2, 160),
            'alt_baslik' => self::text($payload['alt_baslik'] ?? '', 'Alt baslik', 0, 180, true),
            'aciklama' => self::multiline($payload['aciklama'] ?? '', 'Aciklama', 0, 2000, true),
            'gorsel' => self::assetPath($payload['gorsel'] ?? '', 'Gorsel', true),
            'cta_bir_metin' => self::text($payload['cta_bir_metin'] ?? '', 'Birinci buton metni', 0, 60, true),
            'cta_bir_link' => self::safeLink($payload['cta_bir_link'] ?? '', 'Birinci buton linki', false),
            'cta_iki_metin' => self::text($payload['cta_iki_metin'] ?? '', 'Ikinci buton metni', 0, 60, true),
            'cta_iki_link' => self::safeLink($payload['cta_iki_link'] ?? '', 'Ikinci buton linki', false),
            'durum' => self::enumInt($payload['durum'] ?? 1, [1, 2], 'Durum degeri gecersiz.'),
        ];
    }

    public static function sanitizeBlogPayload(array $payload): array
    {
        return [
            'baslik' => self::text($payload['baslik'] ?? '', 'Baslik', 2, 180),
            'slug' => self::slug($payload['slug'] ?? ''),
            'excerpt' => self::multiline($payload['excerpt'] ?? '', 'Ozet', 10, 500),
            'kapak_gorseli' => self::assetPath($payload['kapak_gorseli'] ?? '', 'Kapak gorseli', true),
            'yazar' => self::text($payload['yazar'] ?? '', 'Yazar', 2, 120),
            'yayin_tarihi' => self::date($payload['yayin_tarihi'] ?? '', 'Yayin tarihi'),
            'etiketler' => self::text($payload['etiketler'] ?? '', 'Etiketler', 0, 250, true),
            'icerik' => self::multiline($payload['icerik'] ?? '', 'Icerik', 30, 20000),
            'seo_title' => self::text($payload['seo_title'] ?? '', 'SEO basligi', 0, 180, true),
            'seo_desc' => self::multiline($payload['seo_desc'] ?? '', 'SEO aciklamasi', 0, 320, true),
            'durum' => self::enumInt($payload['durum'] ?? 2, [1, 2], 'Durum degeri gecersiz.'),
        ];
    }

    public static function sanitizeFaqPayload(array $payload): array
    {
        return [
            'sirasi' => self::intRange($payload['sirasi'] ?? 0, 'Sira', 0, 9999),
            'soru' => self::text($payload['soru'] ?? '', 'Soru', 4, 240),
            'cevap' => self::multiline($payload['cevap'] ?? '', 'Cevap', 4, 2000),
            'durum' => self::enumInt($payload['durum'] ?? 1, [1, 2], 'Durum degeri gecersiz.'),
        ];
    }

    public static function sanitizeMediaPayload(array $payload): array
    {
        return [
            'dosya_adi' => self::text($payload['dosya_adi'] ?? '', 'Dosya adi', 2, 160),
            'dosya_yolu' => self::assetPath($payload['dosya_yolu'] ?? '', 'Dosya yolu', true),
            'alt_metin' => self::text($payload['alt_metin'] ?? '', 'Alt metin', 0, 255, true),
            'kategori' => self::text($payload['kategori'] ?? '', 'Kategori', 2, 80),
            'durum' => self::enumInt($payload['durum'] ?? 1, [1, 2], 'Durum degeri gecersiz.'),
        ];
    }

    public static function sanitizeContactRequestPayload(array $payload): array
    {
        return [
            'ad_soyad' => self::text($payload['ad_soyad'] ?? '', 'Ad soyad', 2, 120),
            'telefon' => self::phone($payload['telefon'] ?? '', true),
            'eposta' => self::email($payload['eposta'] ?? '', true),
            'proje_tipi' => self::text($payload['proje_tipi'] ?? '', 'Proje tipi', 0, 120, true),
            'hizmet_alani' => self::text($payload['hizmet_alani'] ?? '', 'Hizmet alani', 0, 120, true),
            'lokasyon' => self::text($payload['lokasyon'] ?? '', 'Lokasyon', 0, 160, true),
            'mesaj' => self::multiline($payload['mesaj'] ?? '', 'Mesaj', 10, 3000),
            'ip_adresi' => self::ipAddress($payload['ip_adresi'] ?? ''),
            'user_agent' => self::text($payload['user_agent'] ?? '', 'User agent', 0, 500, true),
        ];
    }

    public static function sanitizeContactRequestUpdatePayload(array $payload): array
    {
        return [
            'okundu' => self::enumInt($payload['okundu'] ?? 2, [1, 2], 'Okundu degeri gecersiz.'),
            'admin_notu' => self::multiline($payload['admin_notu'] ?? '', 'Admin notu', 0, 2000, true),
        ];
    }

    private static function text(mixed $value, string $label, int $min, int $max, bool $allowEmpty = false): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException($label . ' alaninin tipi gecersiz.');
        }

        $raw = trim((string) $value);
        if ($allowEmpty && $raw === '') {
            return '';
        }

        if ($raw === '') {
            throw new InvalidArgumentException($label . ' zorunludur.');
        }

        if (preg_match('/[<>\x00-\x1F\x7F]/u', $raw) === 1) {
            throw new InvalidArgumentException($label . ' gecersiz karakterler iceriyor.');
        }

        $clean = clean_text($raw);
        $length = self::length($clean);

        if ($length < $min || $length > $max) {
            throw new InvalidArgumentException($label . ' ' . $min . '-' . $max . ' karakter araliginda olmali.');
        }

        return $clean;
    }

    private static function multiline(mixed $value, string $label, int $min, int $max, bool $allowEmpty = false): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new InvalidArgumentException($label . ' alaninin tipi gecersiz.');
        }

        $raw = trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
        if ($allowEmpty && $raw === '') {
            return '';
        }

        if ($raw === '') {
            throw new InvalidArgumentException($label . ' zorunludur.');
        }

        if (preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $raw) === 1) {
            throw new InvalidArgumentException($label . ' gecersiz karakterler iceriyor.');
        }

        $clean = clean_multiline_text($raw);
        $length = self::length($clean);

        if ($length < $min || $length > $max) {
            throw new InvalidArgumentException($label . ' ' . $min . '-' . $max . ' karakter araliginda olmali.');
        }

        return $clean;
    }

    private static function slug(mixed $value): string
    {
        $slug = trim((string) $value);
        if ($slug === '') {
            throw new InvalidArgumentException('Slug zorunludur.');
        }

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException('Slug yalnizca kucuk harf, rakam ve tire icerebilir.');
        }

        $length = self::length($slug);
        if ($length < 2 || $length > 160) {
            throw new InvalidArgumentException('Slug 2-160 karakter araliginda olmali.');
        }

        return $slug;
    }

    private static function email(mixed $value, bool $required): string
    {
        $email = trim((string) $value);
        if (!$required && $email === '') {
            return '';
        }

        if ($email === '' || self::length($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Gecerli bir e-posta adresi girin.');
        }

        return strtolower($email);
    }

    private static function password(mixed $value): string
    {
        $password = (string) $value;
        $length = function_exists('mb_strlen') ? mb_strlen($password, '8bit') : strlen($password);

        if ($length < 10 || $length > 255) {
            throw new InvalidArgumentException('Sifre en az 10 karakter olmali.');
        }

        return $password;
    }

    private static function phone(mixed $value, bool $required): string
    {
        $phone = trim((string) $value);
        if (!$required && $phone === '') {
            return '';
        }

        if ($phone === '' || preg_match('/[<>\x00-\x1F\x7F]/u', $phone) === 1) {
            throw new InvalidArgumentException('Gecerli bir telefon numarasi girin.');
        }

        $normalized = preg_replace('/\s+/', ' ', $phone) ?? $phone;
        $digits = preg_replace('/\D+/', '', $normalized) ?? '';

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Gecerli bir telefon numarasi girin.');
        }

        return $normalized;
    }

    private static function enumInt(mixed $value, array $allowedValues, string $message): int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);
        if ($normalized === false) {
            throw new InvalidArgumentException($message);
        }

        $normalized = (int) $normalized;
        if (!in_array($normalized, $allowedValues, true)) {
            throw new InvalidArgumentException($message);
        }

        return $normalized;
    }

    private static function intRange(mixed $value, string $label, int $min, int $max): int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT);
        if ($normalized === false) {
            throw new InvalidArgumentException($label . ' sayisal bir deger olmali.');
        }

        $normalized = (int) $normalized;
        if ($normalized < $min || $normalized > $max) {
            throw new InvalidArgumentException($label . ' ' . $min . '-' . $max . ' araliginda olmali.');
        }

        return $normalized;
    }

    private static function assetPath(mixed $value, string $label, bool $required): string
    {
        $path = trim((string) $value);
        if (!$required && $path === '') {
            return '';
        }

        if (!validate_asset_path($path)) {
            throw new InvalidArgumentException($label . ' izin verilen /images veya /uploads altinda gecerli bir dosya olmali.');
        }

        return $path;
    }

    private static function assetPathList(mixed $value, int $maxItems): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", trim((string) $value));
        if ($raw === '') {
            return [];
        }

        $lines = preg_split('/\n/', $raw) ?: [];
        $paths = [];

        foreach ($lines as $line) {
            $path = trim((string) $line);
            if ($path === '') {
                continue;
            }

            $paths[] = self::assetPath($path, 'Galeri gorseli', true);
        }

        $paths = array_values(array_unique($paths));

        if (count($paths) > $maxItems) {
            throw new InvalidArgumentException('Galeriye en fazla ' . $maxItems . ' gorsel eklenebilir.');
        }

        return $paths;
    }

    private static function safeLink(mixed $value, string $label, bool $required): string
    {
        $link = trim((string) $value);
        if (!$required && $link === '') {
            return '';
        }

        if ($link === '' || preg_match('/[\x00-\x1F\x7F]/u', $link) === 1) {
            throw new InvalidArgumentException($label . ' gecersiz.');
        }

        if (str_starts_with($link, '/')) {
            if (str_starts_with($link, '//') || str_contains($link, '..')) {
                throw new InvalidArgumentException($label . ' gecersiz.');
            }

            return $link;
        }

        if (preg_match('/^(https?:|mailto:|tel:)/i', $link) !== 1) {
            throw new InvalidArgumentException($label . ' yalnizca dahili yol veya guvenli URL olabilir.');
        }

        return clean_url_value($link);
    }

    private static function absoluteHttpUrl(mixed $value, string $label, bool $required, int $maxLength): string
    {
        $url = trim((string) $value);
        if (!$required && $url === '') {
            return '';
        }

        if ($url === '' || self::length($url) > $maxLength) {
            throw new InvalidArgumentException($label . ' gecersiz.');
        }

        $clean = clean_url_value($url);
        $parts = parse_url($clean);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException($label . ' tam bir http/https adresi olmali.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException($label . ' yalnizca http/https protokollerini kullanabilir.');
        }

        if (!filter_var($clean, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException($label . ' gecersiz.');
        }

        return $clean;
    }

    private static function date(mixed $value, string $label): string
    {
        $date = trim((string) $value);
        if (!validate_date_string($date)) {
            throw new InvalidArgumentException($label . ' gecersiz.');
        }

        return $date;
    }

    private static function ipAddress(mixed $value): string
    {
        $ip = trim((string) $value);
        if ($ip === '') {
            return '';
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('IP adresi gecersiz.');
        }

        return $ip;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
