<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Tests\Stub;

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
use LPhenom\Db\Contract\TransactionCallbackInterface;
use LPhenom\Db\Param\Param;
use PDO;
use PDOStatement;

/**
 * SQLite in-memory connection for unit tests.
 *
 * Wraps PDO SQLite and implements ConnectionInterface using the same
 * parameter binding approach as PdoMySqlConnection.
 */
final class TestConnection implements ConnectionInterface
{
    /**
     * @var PDO
     */
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, Param> $params
     */
    public function query(string $sql, array $params = []): ResultInterface
    {
        $stmt = $this->pdo->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return new TestResult($stmt);
    }

    /**
     * @param array<string, Param> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->rowCount();
    }

    /**
     * @return mixed
     */
    public function transaction(TransactionCallbackInterface $callback): mixed
    {
        $this->pdo->beginTransaction();

        $exception = null;
        $result    = null;
        try {
            $result = $callback->execute($this);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $exception = $e;
            $this->pdo->rollBack();
        }

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    /**
     * @param array<string, Param> $params
     */
    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $param) {
            $stmt->bindValue($name, $param->isNull ? null : $param->value, $param->type);
        }
    }
}
