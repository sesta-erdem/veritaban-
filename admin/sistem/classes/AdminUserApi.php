<?php

declare(strict_types=1);

interface AdminUserApiRepository
{
    public function findById(int $id): ?array;

    public function emailExists(string $email, ?int $ignoreId = null): bool;

    public function verifyPassword(int $id, string $password): bool;

    public function save(array $payload, int $id): void;
}

final class AdminCodeAdminUserApiRepository implements AdminUserApiRepository
{
    public function __construct(private readonly AdminCode $code)
    {
    }

    public function findById(int $id): ?array
    {
        return $this->code->getAdminUser($id);
    }

    public function emailExists(string $email, ?int $ignoreId = null): bool
    {
        return $this->code->adminEmailExists($email, $ignoreId);
    }

    public function verifyPassword(int $id, string $password): bool
    {
        return $this->code->verifyAdminPassword($id, $password);
    }

    public function save(array $payload, int $id): void
    {
        $this->code->saveAdminUser($payload, $id);
    }
}

final class AdminUserApiResponse
{
    public function __construct(
        public readonly int $status,
        public readonly array $body,
        public readonly ?array $sessionUserUpdate = null,
    ) {
    }
}

final class AdminUserApiHandler
{
    private const ROLE_SUPER = 1;
    private const ROLE_EDITOR = 2;

    public function __construct(private readonly AdminUserApiRepository $repository)
    {
    }

    public function handle(array $request): AdminUserApiResponse
    {
        $method = strtoupper((string) ($request['method'] ?? 'GET'));

        if (!in_array($method, ['GET', 'PATCH', 'POST'], true)) {
            return $this->error(405, 'Desteklenmeyen istek metodu.');
        }

        $actor = is_array($request['actor'] ?? null) ? $request['actor'] : null;
        $actorId = $this->normalizePositiveInt($actor['id'] ?? null);
        $actorRole = (int) ($actor['rutbe'] ?? 0);

        if ($actorId === null) {
            return $this->error(401, 'Oturum doğrulanamadı.');
        }

        $targetIdRaw = is_array($request['query'] ?? null) ? ($request['query']['id'] ?? null) : null;
        $targetId = $targetIdRaw === null || $targetIdRaw === ''
            ? $actorId
            : $this->normalizePositiveInt($targetIdRaw);

        if ($targetId === null) {
            return $this->error(422, 'Geçerli bir kullanıcı kimliği gönderin.');
        }

        $isSelf = $targetId === $actorId;
        $canManageOthers = $actorRole === self::ROLE_SUPER;

        if (!$isSelf && !$canManageOthers) {
            $this->audit('admin_user_api_forbidden', [
                'actor_id' => $actorId,
                'target_id' => $targetId,
                'method' => $method,
            ]);

            return $this->error(403, 'Bu kaynağa erişim yetkiniz yok.');
        }

        $targetUser = $this->repository->findById($targetId);
        if ($targetUser === null) {
            return $this->error(404, 'Kullanıcı bulunamadı.');
        }

        if ($method === 'GET') {
            return $this->success(200, [
                'data' => $this->presentUser($targetUser, $canManageOthers, $isSelf),
            ]);
        }

        if (!(bool) ($request['same_origin'] ?? false) || !(bool) ($request['csrf_valid'] ?? false)) {
            return $this->error(403, 'İstek doğrulanamadı.');
        }

        $body = $request['body'] ?? null;
        if (!is_array($body)) {
            return $this->error(422, 'Geçerli bir JSON gövdesi gönderin.');
        }

        $validation = $this->validateMutationPayload($body, $targetUser, $isSelf, $canManageOthers);
        if (($validation['ok'] ?? false) !== true) {
            return $this->error((int) ($validation['status'] ?? 422), (string) ($validation['message'] ?? 'İstek doğrulanamadı.'));
        }

        $payload = (array) ($validation['payload'] ?? []);

        if (($validation['requires_current_password'] ?? false) === true) {
            $currentPassword = (string) ($validation['current_password'] ?? '');
            if (!$this->repository->verifyPassword($actorId, $currentPassword)) {
                $this->audit('admin_user_api_invalid_current_password', [
                    'actor_id' => $actorId,
                    'target_id' => $targetId,
                ]);

                return $this->error(403, 'Mevcut şifre doğrulanamadı.');
            }
        }

        if (
            isset($payload['eposta'])
            && strcasecmp((string) $payload['eposta'], (string) ($targetUser['eposta'] ?? '')) !== 0
            && $this->repository->emailExists((string) $payload['eposta'], $targetId)
        ) {
            return $this->error(422, 'Bu e-posta adresi zaten kullanılıyor.');
        }

        $this->repository->save($payload, $targetId);
        $updatedUser = $this->repository->findById($targetId) ?? array_merge($targetUser, $payload, ['kullanici_id' => $targetId]);

        $this->audit('admin_user_api_updated', [
            'actor_id' => $actorId,
            'target_id' => $targetId,
            'method' => $method,
            'self_update' => $isSelf,
            'role_change' => array_key_exists('rutbe', $body),
            'status_change' => array_key_exists('sil', $body),
            'password_change' => array_key_exists('password', $body),
        ]);

        $sessionUserUpdate = $isSelf
            ? [
                'id' => (int) ($updatedUser['kullanici_id'] ?? $actorId),
                'ad_soyad' => (string) ($updatedUser['ad_soyad'] ?? ''),
                'eposta' => (string) ($updatedUser['eposta'] ?? ''),
                'rutbe' => (int) ($updatedUser['rutbe'] ?? $actorRole),
            ]
            : null;

        return $this->success(200, [
            'data' => $this->presentUser($updatedUser, $canManageOthers, $isSelf),
            'message' => 'Kullanıcı kaydı güncellendi.',
        ], $sessionUserUpdate);
    }

    private function validateMutationPayload(array $body, array $targetUser, bool $isSelf, bool $canManageOthers): array
    {
        $commonFields = ['ad_soyad', 'eposta', 'password', 'current_password'];
        $adminOnlyFields = ['rutbe', 'sil'];
        $allowedFields = $commonFields;

        if ($canManageOthers && !$isSelf) {
            $allowedFields = array_merge($allowedFields, $adminOnlyFields);
        }

        foreach (array_keys($body) as $key) {
            if (in_array($key, $adminOnlyFields, true) && !($canManageOthers && !$isSelf)) {
                return [
                    'ok' => false,
                    'status' => 403,
                    'message' => 'Bu alanı güncelleme yetkiniz yok.',
                ];
            }
        }

        $unknownFields = array_values(array_diff(array_keys($body), $allowedFields));
        if ($unknownFields !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'İstek gövdesinde desteklenmeyen alanlar var.',
            ];
        }

        $payload = [
            'ad_soyad' => (string) ($targetUser['ad_soyad'] ?? ''),
            'eposta' => (string) ($targetUser['eposta'] ?? ''),
            'rutbe' => (int) ($targetUser['rutbe'] ?? self::ROLE_EDITOR),
            'sil' => (int) ($targetUser['sil'] ?? 2),
            'password' => '',
        ];
        $hasChange = false;

        if (array_key_exists('ad_soyad', $body)) {
            $name = $this->normalizeHumanName($body['ad_soyad'] ?? null);
            if ($name === null) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Ad soyad alanı 2-120 karakter olmalı ve HTML içeremez.',
                ];
            }

            $payload['ad_soyad'] = $name;
            $hasChange = true;
        }

        if (array_key_exists('eposta', $body)) {
            $email = $this->normalizeEmail($body['eposta'] ?? null);
            if ($email === null) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Geçerli bir e-posta adresi gönderin.',
                ];
            }

            $payload['eposta'] = $email;
            $hasChange = true;
        }

        if (array_key_exists('password', $body)) {
            $password = $this->normalizePassword($body['password'] ?? null);
            if ($password === null) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Şifre en az 10 karakter olmalı.',
                ];
            }

            $payload['password'] = $password;
            $hasChange = true;
        }

        if ($canManageOthers && !$isSelf && array_key_exists('rutbe', $body)) {
            $role = $this->normalizeRole($body['rutbe'] ?? null);
            if ($role === null) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Geçerli bir rol gönderin.',
                ];
            }

            $payload['rutbe'] = $role;
            $hasChange = true;
        }

        if ($canManageOthers && !$isSelf && array_key_exists('sil', $body)) {
            $status = $this->normalizeStatus($body['sil'] ?? null);
            if ($status === null) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Geçerli bir durum değeri gönderin.',
                ];
            }

            $payload['sil'] = $status;
            $hasChange = true;
        }

        if (!$hasChange) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Güncellenecek en az bir alan gönderin.',
            ];
        }

        $emailChanged = array_key_exists('eposta', $body)
            && strcasecmp((string) $payload['eposta'], (string) ($targetUser['eposta'] ?? '')) !== 0;
        $passwordChanged = array_key_exists('password', $body);

        if ($isSelf && ($emailChanged || $passwordChanged)) {
            $currentPassword = is_string($body['current_password'] ?? null)
                ? trim((string) $body['current_password'])
                : '';

            if ($currentPassword === '') {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Hassas değişiklikler için mevcut şifrenizi gönderin.',
                ];
            }

            return [
                'ok' => true,
                'payload' => $payload,
                'requires_current_password' => true,
                'current_password' => $currentPassword,
            ];
        }

        return [
            'ok' => true,
            'payload' => $payload,
            'requires_current_password' => false,
        ];
    }

    private function presentUser(array $user, bool $canManageOthers, bool $isSelf): array
    {
        return [
            'kullanici_id' => (int) ($user['kullanici_id'] ?? 0),
            'ad_soyad' => (string) ($user['ad_soyad'] ?? ''),
            'eposta' => (string) ($user['eposta'] ?? ''),
            'rutbe' => (int) ($user['rutbe'] ?? self::ROLE_EDITOR),
            'sil' => (int) ($user['sil'] ?? 2),
            'kayit_tarihi' => (string) ($user['kayit_tarihi'] ?? ''),
            'son_giris_tarihi' => (string) ($user['son_giris_tarihi'] ?? ''),
            'izinler' => [
                'kendi_kaydini_duzenleyebilir' => $isSelf,
                'diger_kullanicilari_yonetebilir' => $canManageOthers,
            ],
        ];
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || !preg_match('/^[1-9]\d*$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    private function normalizeHumanName(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || preg_match('/[<>\x00-\x1F\x7F]/u', $value)) {
            return null;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);

        return $length >= 2 && $length <= 120 ? $value : null;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || strlen($value) > 190 || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return strtolower($value);
    }

    private function normalizePassword(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value, '8bit') : strlen($value);

        return $length >= 10 && $length <= 255 ? $value : null;
    }

    private function normalizeRole(mixed $value): ?int
    {
        $value = $this->normalizePositiveInt($value);

        return in_array($value, [self::ROLE_SUPER, self::ROLE_EDITOR], true) ? $value : null;
    }

    private function normalizeStatus(mixed $value): ?int
    {
        $value = $this->normalizePositiveInt($value);

        return in_array($value, [1, 2], true) ? $value : null;
    }

    private function success(int $status, array $body, ?array $sessionUserUpdate = null): AdminUserApiResponse
    {
        return new AdminUserApiResponse($status, array_merge(['ok' => true], $body), $sessionUserUpdate);
    }

    private function error(int $status, string $message): AdminUserApiResponse
    {
        return new AdminUserApiResponse($status, [
            'ok' => false,
            'message' => $message,
        ]);
    }

    private function audit(string $event, array $context): void
    {
        if (function_exists('log_security_event')) {
            log_security_event($event, $context);
        }
    }
}
