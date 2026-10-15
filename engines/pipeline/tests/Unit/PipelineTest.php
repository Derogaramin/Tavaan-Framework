<?php

declare(strict_types=1);

namespace Tavaan\Engine\Pipeline\Tests\Unit;

use Tavaan\Engine\Container\Container;
use Tavaan\Engine\Pipeline\Exceptions\PipelineMaxDepthException;
use Tavaan\Engine\Pipeline\Exceptions\PipelinePipeNotFoundException;
use Tavaan\Engine\Pipeline\Pipeline;
use Tavaan\Testing\TestCase;
use Tests\Fixtures\Pipeline\FailingPipe;
use Tests\Fixtures\Pipeline\ParamPipe;
use Tests\Fixtures\Pipeline\PipeA;
use Tests\Fixtures\Pipeline\PipeB;
use Tests\Fixtures\Pipeline\PipeC;
use Tests\Fixtures\Pipeline\PipelineTrace;
use Tests\Fixtures\Pipeline\ShortCircuitPipe;

/**
 * تست موتور Pipeline — سناریوهای DOC-034 §8 (AC-PIP-01).
 */
final class PipelineTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        PipelineTrace::reset();
        $this->c = new Container();
    }

    /** T-01: ترتیب onion */
    public function testOnionOrder(): void
    {
        $result = (new Pipeline($this->c))
            ->send('payload')
            ->through([PipeA::class, PipeB::class, PipeC::class])
            ->then(static function (mixed $p) {
                PipelineTrace::record('core:' . $p);
                return 'done';
            });

        $this->assertSame('done', $result);
        $this->assertSame(['a:in', 'b:in', 'c:in', 'core:payload', 'c:out', 'b:out', 'a:out'], PipelineTrace::$log);
    }

    /** T-02: پارامتر `throttle:60,1` */
    public function testPipeParameters(): void
    {
        (new Pipeline($this->c))
            ->send(1)
            ->through([ParamPipe::class . ':60,1'])
            ->thenReturn();

        $this->assertSame(['params:60|1'], PipelineTrace::$log);
    }

    /** T-03: Closure به‌عنوان pipe */
    public function testClosurePipe(): void
    {
        $out = (new Pipeline($this->c))
            ->send(2)
            ->through([static fn (mixed $p, \Closure $next): mixed => $next($p * 10)])
            ->then(static fn (mixed $p): mixed => $p + 1);

        $this->assertSame(21, $out);
    }

    /** T-04: short-circuit */
    public function testShortCircuit(): void
    {
        $out = (new Pipeline($this->c))
            ->send('x')
            ->through([PipeA::class, ShortCircuitPipe::class, PipeB::class])
            ->thenReturn();

        $this->assertSame('halted', $out);
        $this->assertSame(['a:in', 'short-circuit', 'a:out'], PipelineTrace::$log);
    }

    /** T-05 + AC-PIP-04: exception → propagate + terminate اجرا می‌شود */
    public function testExceptionPropagatesAndTerminateRuns(): void
    {
        $pipeline = (new Pipeline($this->c))
            ->send('x')
            ->through([PipeA::class, FailingPipe::class]);

        $thrown = false;
        try {
            $pipeline->thenReturn();
        } catch (\RuntimeException $e) {
            $thrown = $e->getMessage() === 'pipe boom';
        }

        $this->assertTrue($thrown);
        $pipeline->terminate('x', null);
        $this->assertContains('fail:terminate', PipelineTrace::$log);
        $this->assertContains('a:terminate', PipelineTrace::$log);
    }

    /** T-06: thenReturn = identity */
    public function testThenReturnIdentity(): void
    {
        $out = (new Pipeline($this->c))->send('v')->through([])->thenReturn();
        $this->assertSame('v', $out);
    }

    /** T-07: Passable دلخواه */
    public function testArbitraryPassable(): void
    {
        $dto = new \stdClass();
        $dto->n = 1;
        $out = (new Pipeline($this->c))
            ->send($dto)
            ->through([static function (object $p, \Closure $next): object { $p->n++; return $next($p); }])
            ->thenReturn();
        $this->assertSame(2, $out->n);
    }

    /** T-08: max_depth → خطا */
    public function testMaxDepthExceeded(): void
    {
        $pipeline = new Pipeline($this->c, ['max_depth' => 3]);
        $this->expectException(PipelineMaxDepthException::class);
        $pipeline->through([PipeA::class, PipeB::class, PipeC::class, PipeA::class]);
    }

    /** T-09: alias نامعتبر → PipelinePipeNotFoundException */
    public function testUnknownAliasThrows(): void
    {
        $this->expectException(PipelinePipeNotFoundException::class);
        (new Pipeline($this->c))->send(1)->through(['nonexistent-alias'])->thenReturn();
    }

    /** alias معتبر از config */
    public function testAliasResolution(): void
    {
        $out = (new Pipeline($this->c, ['aliases' => ['trace' => PipeA::class]]))
            ->send('v')
            ->through(['trace'])
            ->thenReturn();

        $this->assertSame('v', $out);
        $this->assertContains('a:in', PipelineTrace::$log);
    }

    /** after() stage */
    public function testAfterStages(): void
    {
        $pipeline = (new Pipeline($this->c))
            ->send('v')
            ->through([PipeA::class])
            ->after(static fn (mixed $p, mixed $r): mixed => PipelineTrace::record('after:' . $p . ':' . $r));

        $pipeline->thenReturn();
        $pipeline->terminate('v', 'res');
        $this->assertContains('after:v:res', PipelineTrace::$log);
    }

    /** via() — نام متد سفارشی */
    public function testViaCustomMethod(): void
    {
        $pipe = new class {
            public function process(mixed $passable, \Closure $next): mixed
            {
                PipelineTrace::record('via:process');
                return $next($passable);
            }
        };

        (new Pipeline($this->c))->send(1)->through([$pipe])->via('process')->thenReturn();
        $this->assertContains('via:process', PipelineTrace::$log);
    }
}
