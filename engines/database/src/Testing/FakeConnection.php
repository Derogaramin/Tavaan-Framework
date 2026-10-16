<?php

declare(strict_types=1);

namespace Tavaan\Engine\Database\Testing;

use Tavaan\Core\Contracts\Events\DispatcherInterface;
use Tavaan\Engine\Database\Connection;
use Tavaan\Engine\Database\Grammars\Grammar;
use Tavaan\Engine\Database\Grammars\MySqlGrammar;
use Tavaan\Engine\Database\Grammars\PostgresGrammar;
use Tavaan\Engine\Database\Grammars\SqliteGrammar;
use Tavaan\Engine\Database\Processor;
use Tavaan\Engine\Database\QueryLog;

/**
 * تَوان | Tavaan — Database
 * FakeConnection — اتصال ساختگی برای تست compile (بدون PDO تا وقتی execute نشود).
 *
 * طبق قاعده C7 (DOC-014 §4): هر Interface یک Fake برای تست دارد.
 *
 * @api internal
 * @see docs/03-engines/database.md
 */
class FakeConnection extends Connection
{
    /** @param array<string, mixed> $config */
    public function __construct(
        ?Grammar $grammar = null,
        private readonly string $driverName = 'mysql',
        array $config = [],
        ?DispatcherInterface $events = null,
    ) {
        $grammar ??= match ($driverName) {
            'pgsql' => new PostgresGrammar((string) ($config['prefix'] ?? '')),
            'sqlite' => new SqliteGrammar((string) ($config['prefix'] ?? '')),
            default => new MySqlGrammar((string) ($config['prefix'] ?? '')),
        };

        parent::__construct(
            'fake',
            $config,
            $grammar,
            new Processor(),
            new QueryLog(false),
            $events,
        );
    }

    public function driver(): string
    {
        return $this->driverName;
    }
}
