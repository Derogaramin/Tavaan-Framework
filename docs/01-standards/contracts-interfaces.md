# DOC-014 — کاتالوگ قراردادها (Contracts / Interfaces Catalog) 🔒

| متادیتا | مقدار |
|---|---|
| DOC-ID | DOC-014 |
| نقش | فهرست مرجع همهٔ Interfaceهای هسته؛ تنها نقطهٔ وابستگی مجاز |
| مسیر فیزیکی | `core/Contracts/` → namespace `Tavaan\Core\Contracts\*` |
| قاعده | 🔒 Interfaceها پس از انتشار `1.0` فقط افزودنی (Additive) تغییر می‌کنند |

---

## ۱. سازماندهی Namespace قراردادها

```
Tavaan\Core\Contracts\
├── Container\        ContainerInterface, Bindable, Resolvable, ContextualBinder
├── Config\           RepositoryInterface, ConfigCache
├── Events\           DispatcherInterface, ShouldBroadcast, Listenable, EventSubscriber
├── Http\             RequestInterface, ResponseInterface, KernelInterface, MiddlewareInterface,
│                     StreamInterface, UploadedFileInterface, CookieInterface
├── Routing\          RouterInterface, RouteInterface, UrlGeneratorInterface, RouteMatcherInterface
├── Database\         ConnectionInterface, QueryBuilderInterface, GrammarInterface,
│                     TransactionManagerInterface, SchemaBuilderInterface, MigrationInterface
├── ORM\              ModelInterface, RepositoryInterface, RelationInterface, CastInterface, ScopeInterface
├── Validation\       ValidatorInterface, RuleInterface, ValidatedDataInterface
├── Cache\            StoreInterface, RepositoryInterface, LockProviderInterface, TaggedCache
├── Queue\            QueueInterface, JobInterface, WorkerInterface, PayloadSerializer, FailedJobProvider
├── Auth\             GuardInterface, UserProviderInterface, Authenticatable, TokenInterface, StatefulGuard
├── Authorization\    GateInterface, PolicyInterface, Accessible
├── Filesystem\       FilesystemInterface, DiskInterface, StreamWrapper
├── Log\              LoggerInterface, HandlerInterface, FormatterInterface, ProcessorInterface
├── Mail\             TransportInterface, Mailable, MailerInterface
├── Notification\     ChannelInterface, Notifiable, NotificationInterface
├── I18n\             TranslatorInterface, Localizable, CalendarInterface
├── Encryption\       EncrypterInterface, HasherInterface, KeyProvider
├── Console\          CommandInterface, InputInterface, OutputInterface, KernelInterface
├── Observability\    MetricsInterface, TracerInterface, SpanInterface, HealthCheckInterface
├── Workflow\         WorkflowDefinitionInterface, StateInterface, TransitionInterface
├── Payment\          GatewayInterface, PaymentRequest, PaymentResult
├── Search\           SearchEngineInterface, IndexerInterface, QueryBuilderInterface
└── Support\          Arrayable, Jsonable, Renderable, MessageProvider, DeferrableProvider
```

---

## ۲. قراردادهای حیاتی (Full Signatures)

### ۲٫۱ Container

```php
namespace Tavaan\Core\Contracts\Container;

interface ContainerInterface
{
    public function bind(string $abstract, \Closure|string|null $concrete = null, bool $shared = false): void;
    public function singleton(string $abstract, \Closure|string|null $concrete = null): void;
    public function instance(string $abstract, object $instance): object;
    public function factory(string $abstract, \Closure $factory): void;
    public function alias(string $abstract, string $alias): void;
    public function tag(array|string $abstracts, string ...$tags): void;

    /** @return iterable<object> */
    public function tagged(string $tag): iterable;

    public function bound(string $abstract): bool;
    public function resolved(string $abstract): bool;

    /**
     * @template T
     * @param class-string<T>|string $abstract
     * @param array<string, mixed>   $parameters
     * @return T|mixed
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /** @param callable $callback @return mixed */
    public function call(callable|array|string $callback, array $parameters = []): mixed;

    public function when(string $consumer): ContextualBindingBuilder;
    public function extend(string $abstract, \Closure $extender): void;
    public function flush(): void;
}

interface ContextualBindingBuilder
{
    public function needs(string $abstract): self;
    public function give(\Closure|string|array $implementation): void;
}
```

### ۲٫۲ Events

```php
namespace Tavaan\Core\Contracts\Events;

interface DispatcherInterface
{
    /**
     * @param class-string|list<class-string>|string $events
     * @param callable|class-string|array{0:object|class-string,1:string} $listener
     */
    public function listen(string|array $events, callable|string|array $listener, int $priority = 0): void;

    public function subscribe(string|object $subscriber): void;
    public function hasListeners(string $event): bool;

    /**
     * @param object|string $event
     * @param mixed         $payload
     * @param bool          $halt توقف در اولین پاسخ غیر-null
     * @return array<int, mixed>|mixed
     */
    public function dispatch(object|string $event, mixed $payload = [], bool $halt = false): mixed;

    public function until(string|object $event, mixed $payload = []): mixed;
    public function forget(string $event): void;
    public function push(string $event, mixed $payload = []): void;   // به صف
}

interface ShouldQueue {}          // marker
interface ShouldBroadcast { public function broadcastOn(): array; public function broadcastAs(): string; }
interface EventSubscriber { /** @return array<string, string|int> */ public function subscribe(DispatcherInterface $e): array; }
```

### ۲٫۳ HTTP

```php
namespace Tavaan\Core\Contracts\Http;

interface RequestInterface
{
    public function method(): string;
    public function uri(): UriInterface;
    public function path(): string;
    public function fullUrl(): string;
    public function isSecure(): bool;
    public function ip(): ?string;
    public function userAgent(): ?string;
    public function header(string $key, ?string $default = null): ?string;
    /** @return array<string, list<string>> */
    public function headers(): array;
    public function query(string $key, mixed $default = null): mixed;
    public function input(string $key, mixed $default = null): mixed;
    /** @return array<string, mixed> */
    public function all(): array;
    public function file(string $key): ?UploadedFileInterface;
    /** @return array<string, UploadedFileInterface> */
    public function files(): array;
    public function cookie(string $key, mixed $default = null): mixed;
    public function json(?string $key = null, mixed $default = null): mixed;
    public function bearerToken(): ?string;
    public function expectsJson(): bool;
    public function is(string ...$patterns): bool;
    public function withAttribute(string $key, mixed $value): static;
    public function attribute(string $key, mixed $default = null): mixed;
    public function withQuery(array $query): static;
    public function withMethod(string $method): static;
}

interface ResponseInterface
{
    public function statusCode(): int;
    public function withStatus(int $code, string $reason = ''): static;
    public function header(string $key, string $value, bool $replace = true): static;
    public function withHeaders(array $headers): static;
    public function body(): string;
    public function withBody(string|StreamInterface $body): static;
    public function withJson(mixed $data, int $status = 200, int $flags = 0): static;
    public function cookie(CookieInterface $cookie): static;
    public function send(): never;
    public function isSuccessful(): bool;
    public function isRedirect(): bool;
}

interface MiddlewareInterface
{
    /**
     * @param \Closure(RequestInterface): ResponseInterface $next
     */
    public function handle(RequestInterface $request, \Closure $next, string ...$params): ResponseInterface;

    /** کارهای پس از ارسال پاسخ (لاگ، آمار، Session Write) */
    public function terminate(RequestInterface $request, ResponseInterface $response): void;
}

interface KernelInterface
{
    public function handle(RequestInterface $request): ResponseInterface;
    public function terminate(RequestInterface $request, ResponseInterface $response): void;
}
```

### ۲٫۴ Database / ORM

```php
namespace Tavaan\Core\Contracts\Database;

interface ConnectionInterface
{
    public function name(): string;
    public function driver(): string;            // mysql|pgsql|sqlite
    /** @param array<int|string, mixed> $bindings @return list<array<string, mixed>> */
    public function select(string $sql, array $bindings = []): array;
    public function selectOne(string $sql, array $bindings = []): ?array;
    /** @return \Generator<array<string, mixed>> */
    public function cursor(string $sql, array $bindings = []): \Generator;
    public function insert(string $sql, array $bindings = []): bool;
    public function update(string $sql, array $bindings = []): int;
    public function delete(string $sql, array $bindings = []): int;
    public function statement(string $sql, array $bindings = []): bool;
    public function affectingStatement(string $sql, array $bindings = []): int;
    public function lastInsertId(?string $name = null): string|int;
    public function transaction(\Closure $callback, int $attempts = 1, ?string $isolation = null): mixed;
    public function beginTransaction(?string $isolation = null): void;
    public function commit(): void;
    public function rollBack(int $toLevel = 0): void;
    public function transactionLevel(): int;
    public function pdo(): \PDO;
    /** @return list<array{sql:string, bindings:array, time:float, connection:string}> */
    public function queryLog(): array;
}

interface QueryBuilderInterface
{
    public function table(string $table, ?string $as = null): static;
    public function select(string ...$columns): static;
    public function selectRaw(string $expression, array $bindings = []): static;
    public function selectSub(string|\Closure $query, string $as): static;
    public function distinct(): static;
    public function from(string $table, ?string $as = null): static;
    public function join(string $table, \Closure|string $first, ?string $op = null, ?string $second = null, string $type = 'inner'): static;
    public function leftJoin(string $table, \Closure|string $first, ?string $op = null, ?string $second = null): static;
    public function rightJoin(/* … */): static;
    public function crossJoin(string $table): static;
    public function where(string|\Closure $column, mixed $op = null, mixed $value = null, string $boolean = 'and'): static;
    public function orWhere(/* … */): static;
    public function whereIn(string $column, array|\Closure $values, string $boolean = 'and', bool $not = false): static;
    public function whereNull(string $column): static;
    public function whereBetween(string $column, array $values): static;
    public function whereExists(\Closure $callback): static;
    public function whereRaw(string $sql, array $bindings = []): static;
    public function groupBy(string ...$columns): static;
    public function having(string $column, mixed $op = null, mixed $value = null): static;
    public function orderBy(string $column, string $direction = 'asc'): static;
    public function orderByRaw(string $sql, array $bindings = []): static;
    public function limit(int $limit): static;
    public function offset(int $offset): static;
    public function forUpdate(string $of = ''): static;
    public function sharedLock(): static;
    public function union(self $query, bool $all = false): static;
    public function withCte(string $name, \Closure $query): static;
    public function toSql(): string;
    /** @return array<int|string, mixed> */
    public function getBindings(): array;
    /** @return list<\stdClass|array<string, mixed>> */
    public function get(array $columns = ['*']): array;
    /** @return \Generator */
    public function cursor(): \Generator;
    public function first(array $columns = ['*']): object|null;
    public function find(int|string $id, array $columns = ['*']): object|null;
    public function value(string $column): mixed;
    /** @return list<mixed> */
    public function pluck(string $column, ?string $key = null): array;
    public function count(string $column = '*'): int;
    public function sum(string $column): int|float|null;
    public function avg(string $column): int|float|null;
    public function min(string $column): int|float|null;
    public function max(string $column): int|float|null;
    public function exists(): bool;
    public function insert(array $values): bool;
    public function insertGetId(array $values, ?string $sequence = null): int|string;
    public function insertOrIgnore(array $values): int;
    public function upsert(array $values, array $uniqueBy, array $update = null): int;
    public function update(array $values): int;
    public function increment(string $column, int|float $amount = 1, array $extra = []): int;
    public function decrement(string $column, int|float $amount = 1, array $extra = []): int;
    public function delete(): int;
    public function truncate(): void;
    public function paginate(int $perPage = 15, int $page = 1, array $columns = ['*']): PaginatorInterface;
    public function chunk(int $size, callable $callback): bool;
    public function lazy(int $chunkSize = 1000): \Generator;
    public function clone(): static;
}
```

### ۲٫۵ Cache

```php
namespace Tavaan\Core\Contracts\Cache;

interface StoreInterface
{
    public function get(string $key): mixed;
    /** @return array<string, mixed> */
    public function many(array $keys): array;
    public function put(string $key, mixed $value, int $ttl = 0): bool;
    public function putMany(array $values, int $ttl = 0): bool;
    public function has(string $key): bool;
    public function increment(string $key, int $value = 1): int|false;
    public function decrement(string $key, int $value = 1): int|false;
    public function forever(string $key, mixed $value): bool;
    public function forget(string $key): bool;
    public function flush(): bool;
    public function getPrefix(): string;
}

interface LockProviderInterface
{
    public function lock(string $name, int $seconds = 0, ?string $owner = null): LockInterface;
    public function restoreLock(string $name, string $owner): LockInterface;
}

interface LockInterface
{
    public function acquire(?int $timeout = null): bool;
    public function block(int $timeout, ?callable $callback = null): mixed;
    public function release(): bool;
    public function forceRelease(): bool;
    public function owner(): string;
}
```

### ۲٫۶ Auth / Authorization

```php
namespace Tavaan\Core\Contracts\Auth;

interface Authenticatable
{
    public function getAuthIdentifier(): int|string;
    public function getAuthIdentifierName(): string;
    public function getAuthPassword(): string;      // hashed
    public function getRememberToken(): ?string;
    public function setRememberToken(string $value): void;
    public function getRememberTokenName(): string;
}

interface GuardInterface
{
    public function check(): bool;
    public function guest(): bool;
    public function user(): ?Authenticatable;
    public function id(): int|string|null;
    public function validate(array $credentials): bool;
    public function setUser(Authenticatable $user): void;
}

interface UserProviderInterface
{
    public function retrieveById(mixed $identifier): ?Authenticatable;
    public function retrieveByToken(mixed $identifier, string $token): ?Authenticatable;
    public function updateRememberToken(Authenticatable $user, string $token): void;
    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;
    public function validateCredentials(Authenticatable $user, array $credentials): bool;
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials): void;
}

namespace Tavaan\Core\Contracts\Authorization;

interface GateInterface
{
    public function define(string $ability, callable|string $callback): static;
    public function policy(string $model, string $policy): static;
    public function before(callable $callback): static;
    public function after(callable $callback): static;
    public function allows(string $ability, mixed ...$arguments): bool;
    public function denies(string $ability, mixed ...$arguments): bool;
    public function check(iterable|string $abilities, mixed ...$arguments): bool;
    public function any(iterable|string $abilities, mixed ...$arguments): bool;
    public function authorize(string $ability, mixed ...$arguments): Response;   // throws
    public function forUser(mixed $user): static;
    public function getPolicyFor(object|string $class): ?object;
    public function abilities(): array;
}
```

### ۲٫۷ Queue

```php
namespace Tavaan\Core\Contracts\Queue;

interface QueueInterface
{
    public function push(string|object $job, mixed $data = '', string $queue = ''): string;
    public function later(int|\DateTimeInterface $delay, string|object $job, mixed $data = '', string $queue = ''): string;
    public function pop(string $queue = '', int $timeout = 3): ?JobInterface;
    public function delete(string $id): void;
    public function release(string $id, int $delay): void;
    public function size(string $queue = ''): int;
    public function connectionName(): string;
}

interface JobInterface
{
    public function id(): string;
    public function queue(): string;
    public function attempts(): int;
    public function payload(): array;
    public function fire(): void;
    public function delete(): void;
    public function release(int $delay): void;
    public function fail(?\Throwable $e = null): void;
    public function hasFailed(): bool;
    public function timeout(): int;
    public function maxTries(): int;
}
```

### ۲٫۸ سایر قراردادها (خلاصهٔ امضا)

| Interface | متدهای کلیدی |
|---|---|
| `ValidatorInterface` | `make(array $data, array $rules): self`, `validate(): array`, `fails(): bool`, `errors(): MessageBag` |
| `RuleInterface` | `passes(string $attribute, mixed $value): bool`, `message(): string`, `implicit(): bool` |
| `TranslatorInterface` | `get(string $key, array $replace = [], ?string $locale = null): string`, `has(): bool`, `choice(string $key, int $n): string` |
| `CalendarInterface` | `toGregorian(int $y,int $m,int $d): array`, `fromGregorian(...): array`, `isLeap(int $y): bool`, `daysInMonth()` |
| `EncrypterInterface` | `encrypt(string $plain): string`, `decrypt(string $cipher): string`, `hashHmac(string $d): string` |
| `HasherInterface` | `make(string $value): string`, `check(string $value, string $hash): bool`, `needsRehash(string $hash): bool`, `info(string $hash): array` |
| `LoggerInterface` | `emergency/alert/critical/error/warning/notice/info/debug(string $msg, array $ctx): void`, `log(Level $lvl, ...)` |
| `FilesystemInterface` | `exists/get/put/append/prepend/delete/copy/move/size/lastModified/mimeType/visibility/url/files/directories/allFiles/makeDirectory/deleteDirectory` |
| `TransportInterface` (Mail) | `send(Message $m): SentMessage`, `ping(): bool` |
| `ChannelInterface` (Notification) | `send(object $notifiable, NotificationInterface $n): void` |
| `CommandInterface` | `signature(): string`, `description(): string`, `handle(): int`, `arguments(): array`, `options(): array` |
| `MetricsInterface` | `counter/gauge/histogram/observe/record/inc/dec/register/collect(): string` |
| `TracerInterface` | `startSpan(string $name, array $attrs = []): SpanInterface`, `currentSpan(): ?SpanInterface` |
| `GatewayInterface` (Payment) | `purchase(PaymentRequest): PurchaseResult`, `verify(VerifyRequest): PaymentResult`, `refund(RefundRequest): RefundResult` |
| `SearchEngineInterface` | `index/delete/search/suggest/facets/createIndex/deleteIndex/flush` |
| `WorkflowDefinitionInterface` | `states(): array`, `transitions(): array`, `can(string $from, string $to): bool`, `apply(): void` |
| `Renderable` | `render(): string\|ResponseInterface` |
| `Arrayable` | `toArray(): array` |
| `Jsonable` | `toJson(int $flags = 0): string` |
| `DeferrableProvider` | `provides(): array` |

---

## ۳. قواعد طراحی قرارداد 🔒

| # | قاعده |
|---|---|
| C1 | Interface باید **کوچک** باشد (ISP)؛ حداکثر ۸ متد اصلی، بقیه در Interface فرعی |
| C2 | هیچ Implementation Detail در امضا نشت نکند (مثل `PDO` در `RepositoryInterface`) |
| C3 | Return Type تا حد امکان دقیق؛ `mixed` فقط با `@return` دقیق |
| C4 | متدهای mutating باید صریح باشند (`withX` = immutable copy، `setX` = mutable) |
| C5 | استثناهای مجاز هر متد در PHPDoc با `@throws` |
| C6 | افزودن متد جدید = Major برای مصرف‌کننده → فقط با ADR و دورهٔ Deprecation |
| C7 | هر Interface باید حداقل یک پیاده‌سازی پیش‌فرض و یک Fake برای تست داشته باشد |
| C8 | `@api 1.0` روی همهٔ قراردادهای عمومی |
| C9 | برای قابلیت‌های اختیاری از Interface کوچک + `instanceof` استفاده شود نه متد boolean |
| C10 | Genericها با `@template` برای PHPStan |

---

## ۴. نگاشت Interface → پیاده‌سازی پیش‌فرض → Fake تست

| Interface | پیش‌فرض | Fake |
|---|---|---|
| `ContainerInterface` | `Tavaan\Engine\Container\Container` | `Tavaan\Testing\Container\ArrayContainer` |
| `DispatcherInterface` | `Tavaan\Engine\Event\EventDispatcher` | `EventRecorder` (ضبط رویدادها) |
| `StoreInterface` (Cache) | `FileStore` | `ArrayStore` |
| `ConnectionInterface` | `MySqlConnection`/`PdoConnection` | `SqliteInMemoryConnection` |
| `GuardInterface` | `SessionGuard` | `FakeGuard` (کاربر ثابت) |
| `GateInterface` | `Gate` | `AlwaysAllowGate` |
| `QueueInterface` | `DatabaseQueue` | `SyncQueue` / `FakeQueue` |
| `TransportInterface` (Mail) | `SmtpTransport` | `ArrayTransport` |
| `FilesystemInterface` | `LocalFilesystem` | `VirtualFilesystem` (در حافظه) |
| `LoggerInterface` | `StreamLogger` | `ArrayLogger` |
| `HasherInterface` | `Argon2idHasher` | `PlainHasher` (فقط تست) |
| `SearchEngineInterface` | `MysqlFulltextSearch` | `ArraySearch` |
| `CalendarInterface` | `JalaliCalendar` | `GregorianCalendar` |

> 🔒 در تست‌ها هرگز پیاده‌سازی واقعی شبکه‌ای استفاده نشود؛ همیشه Fake (اصل CON-11).
