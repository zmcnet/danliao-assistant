<?php
/**
 * 单聊辅助 - 数据库连接 (PDO单例)
 * 兼容 MySQL 5.7+
 */

declare(strict_types=1);

namespace DanChat;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $cfg = config('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['database'],
            $cfg['charset']
        );
        try {
            $this->pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $cfg['options']);
        } catch (PDOException $e) {
            throw new RuntimeException('数据库连接失败: ' . $e->getMessage());
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public static function init(): void
    {
        self::getInstance();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * 预编译 + 执行 + 返回 PDOStatement
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchValue(string $sql, array $params = []): mixed
    {
        $v = $this->query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /**
     * 插入数据，返回新行的自增ID
     */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $placeholders = [];
        $params = [];
        $i = 0;
        foreach ($cols as $c) {
            $ph = ':p' . $i++;
            $placeholders[] = $ph;
            $params[$ph] = $data[$c];
        }
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`,`', $cols),
            implode(',', $placeholders)
        );
        $this->query($sql, $params);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * 更新数据，$where 中使用 ? 占位符对应 $whereParams
     * @return int 受影响行数
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setClause = [];
        $params = [];
        $i = 0;
        foreach ($data as $k => $v) {
            $ph = ':s' . $i++;
            $setClause[] = "`$k` = $ph";
            $params[$ph] = $v;
        }
        // where 子句使用 ? 占位符，重写为 :w0, :w1, ...
        $idx = 0;
        $whereRewritten = preg_replace_callback('/\?/', function () use (&$idx) {
            return ':w' . $idx++;
        }, $where);
        foreach ($whereParams as $v) {
            $params[':w' . $idx++] = $v;
        }
        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(',', $setClause), $whereRewritten);
        return $this->query($sql, $params)->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        return $this->query($sql, $params)->rowCount();
    }

    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function lastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }
}
