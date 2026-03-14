<?php

declare(strict_types=1);

namespace LPhenom\Migrate\Tests\Stub;

use LPhenom\Db\Contract\ResultInterface;
use PDOStatement;

/**
 * PDO-backed result for unit tests.
 */
final class TestResult implements ResultInterface
{
    /**
     * @var PDOStatement
     */
    private PDOStatement $stmt;

    public function __construct(PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchAll(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $rows;
    }
}
