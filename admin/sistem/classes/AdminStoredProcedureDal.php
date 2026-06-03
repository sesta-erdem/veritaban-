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
