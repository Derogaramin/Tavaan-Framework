<?php

declare(strict_types=1);

namespace Tavaan\Engine\Orm\Tests\Feature;

use Tavaan\Core\Application\Application;
use Tavaan\Core\Contracts\Database\ConnectionInterface;
use Tavaan\Engine\Database\ORM\Testing\DatabaseOrderRepositoryFixture;
use Tavaan\Engine\Database\ORM\Testing\OrderFixture;
use Tavaan\Engine\Database\Schema\Blueprint;
use Tavaan\Support\Ulid;
use Tavaan\Testing\TestCase;

/**
 * تست الگوی Repository (حالت تمیز — بدون extends Model) — DOC-050 §7/T-11.
 */
final class RepositoryPatternTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = \tavaan_test_app();
        $this->app->make('config')->set('database.default', 'sqlite_memory');
        $this->app->make('config')->set('database.connections.sqlite_memory', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);

        $this->app->make('schema')->create('shop_orders', function (Blueprint $t): void {
            $t->ulid('id')->primary();
            $t->string('customer_id');
            $t->integer('total')->default(0);
            $t->string('status')->default('pending');
            $t->timestamps(3);
        });
    }

    public function testRepositoryPatternRoundtrip(): void
    {
        /** @var ConnectionInterface $db */
        $db = $this->app->make(ConnectionInterface::class);

        $repository = new DatabaseOrderRepositoryFixture($db);

        // Domain Entity خالص — بدون extends Model
        $order = OrderFixture::place(Ulid::generate()->toString(), 'cust-1', 25000);
        $this->assertSame('pending', $order->status());

        $repository->save($order);

        $found = $repository->findById($order->id());
        $this->assertNotNull($found);
        $this->assertSame(25000, $found->total());
        $this->assertSame('cust-1', $found->customerId());

        $found->markPaid();
        $repository->save($found);   // updateOrInsert
        $reloaded = $repository->findById($order->id());
        $this->assertNotNull($reloaded);
        $this->assertSame('paid', $reloaded->status());

        $byCustomer = $repository->findBy(['customer_id' => 'cust-1']);
        $this->assertCount(1, $byCustomer);

        $repository->delete($order->id());
        $this->assertNull($repository->findById($order->id()));
    }

    public function testDomainEntityHasNoDbDependency(): void
    {
        // 🔒 Entity دامنه خالص است (بدون extends Model)
        $order = OrderFixture::place('id-1', 'c-1', 100);
        $this->assertFalse($order instanceof \Tavaan\Engine\Database\ORM\Model);
        $this->assertSame(['id' => 'id-1', 'customer_id' => 'c-1', 'total' => 100, 'status' => 'pending'], $order->toRow());
    }
}
