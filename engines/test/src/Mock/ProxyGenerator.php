<?php

declare(strict_types=1);

namespace Tavaan\Testing\Mock;

/**
 * تَوان | Tavaan — Testing
 * ProxyGenerator — تولید فایل کلاس Proxy برای Interface/کلاس غیرنهایی.
 *
 * 🔒 بدون `eval` — کد در `sys_get_temp_dir()/tvn-mocks` تولید و require می‌شود
 * (همان الگوی LazyProxy؛ DOC-084E §4.1). امضای متدها با Reflection بازتولید
 * می‌شود (Union/Intersection/DNF، variadic، by-ref، پیش‌فرض‌ها شامل Enum/ثابت).
 * نام فایل شامل hash اثر انگشت امضاهاست → با تغییر Interface، cache باطل می‌شود.
 *
 * محدودیت‌ها (مستند): کلاس/متد `final` قابل Mock نیست (گذر به کد واقعی یا رد)؛
 * پارامترهای by-reference فقط با معنای مقدار (value) forward می‌شوند.
 *
 * @api 1.0
 * @see docs/03-engines/test.md
 */
final class ProxyGenerator
{
    /** @var array<string, class-string> target → نام کلاس Proxy */
    private static array $generated = [];

    /**
     * ساخت (یا بازیابی از cache) کلاس Proxy برای هدف داده‌شده.
     *
     * @param class-string $target
     *
     * @return class-string<MockProxy>
     *
     * @throws \InvalidArgumentException اگر هدف وجود نداشته باشد یا final/enum باشد
     */
    public static function generate(string $target): string
    {
        if (isset(self::$generated[$target])) {
            return self::$generated[$target];
        }

        if (!\interface_exists($target) && !\class_exists($target)) {
            throw new \InvalidArgumentException(\sprintf('Mock target [%s] does not exist.', $target));
        }

        $reflection = new \ReflectionClass($target);
        if ($reflection->isFinal()) {
            throw new \InvalidArgumentException(\sprintf(
                'Cannot mock final class [%s] — mock one of its interfaces instead.',
                $target,
            ));
        }
        if ($reflection->isEnum()) {
            throw new \InvalidArgumentException(\sprintf('Cannot mock enum [%s].', $target));
        }

        $methods = self::mockableMethods($reflection);
        $proxyClass = 'TavaanMock_' . self::safeShortName($reflection) . '_' . self::fingerprint($target, $reflection, $methods);

        if (!\class_exists($proxyClass, false)) {
            $file = self::write($proxyClass, self::source($proxyClass, $reflection, $methods));
            require_once $file;
        }

        /** @var class-string<MockProxy> $proxyClass */
        return self::$generated[$target] = $proxyClass;
    }

    /**
     * متدهای قابل override: عمومی/محافظ non-final (به‌جز constructor/destructor).
     *
     * @return list<\ReflectionMethod>
     */
    private static function mockableMethods(\ReflectionClass $reflection): array
    {
        $methods = [];
        foreach ($reflection->getMethods() as $method) {
            if ($method->isPrivate() || $method->isFinal()) {
                continue;
            }
            if (\in_array($method->getName(), ['__construct', '__destruct'], true)) {
                continue;
            }
            $methods[] = $method;
        }

        return $methods;
    }

    /**
     * @param list<\ReflectionMethod> $methods
     */
    private static function fingerprint(string $target, \ReflectionClass $reflection, array $methods): string
    {
        $signature = $target . '|' . (string) $reflection->isInterface();
        foreach ($methods as $method) {
            $signature .= '|' . $method->getName()
                . (string) $method->isStatic()
                . (string) $method->getReturnType()
                . '|' . (string) $method;
        }

        return \substr(\md5($signature), 0, 12);
    }

    private static function safeShortName(\ReflectionClass $reflection): string
    {
        $short = \preg_replace('/[^A-Za-z0-9_]/', '_', $reflection->getShortName()) ?? 'Target';

        return \substr($short, 0, 40);
    }

    /**
     * @param list<\ReflectionMethod> $methods
     */
    private static function write(string $proxyClass, string $source): string
    {
        $dir = \sys_get_temp_dir() . '/tvn-mocks';
        if (!\is_dir($dir) && !\mkdir($dir, 0775, true) && !\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Cannot create mock cache directory [%s].', $dir));
        }

        $file = $dir . '/' . $proxyClass . '.php';
        if (\is_file($file) && \file_get_contents($file) === $source) {
            return $file;
        }

        // 🔒 نوشتن اتمیک: temp یکتا + rename (سازگار با اجرای موازی تست‌ها)
        $tmp = $file . '.' . \getmypid() . '.' . \bin2hex(\random_bytes(4)) . '.tmp';
        if (\file_put_contents($tmp, $source, LOCK_EX) === false) {
            throw new \RuntimeException(\sprintf('Cannot write mock proxy file [%s].', $tmp));
        }
        if (!@\rename($tmp, $file)) {
            if (!\is_file($file)) {
                throw new \RuntimeException(\sprintf('Cannot install mock proxy file [%s].', $file));
            }
            @\unlink($tmp);
        }

        return $file;
    }

    /** @param list<\ReflectionMethod> $methods */
    private static function source(string $proxyClass, \ReflectionClass $reflection, array $methods): string
    {
        $target = '\\' . $reflection->getName();
        $relation = $reflection->isInterface()
            ? 'implements ' . $target . ', \\Tavaan\\Testing\\Mock\\MockProxy'
            : 'extends ' . $target . ' implements \\Tavaan\\Testing\\Mock\\MockProxy';

        $body = '';
        foreach ($methods as $method) {
            $body .= self::methodSource($method);
        }

        return <<<PHP
        <?php

        declare(strict_types=1);

        /**
         * Proxy تولیدشده توسط Tavaan Mock — زمان اجرا (دست‌نویس نیست؛ ویرایش نکنید).
         * هدف: {$target}
         */
        final class {$proxyClass} {$relation}
        {
            public function __construct(private readonly \\Tavaan\\Testing\\Mock\\MockHandler \$tvnHandler)
            {
                \\Tavaan\\Testing\\Mock\\MockHandler::registerStatic(self::class, \$this->tvnHandler);
            }

            public function tvnMockHandler(): \\Tavaan\\Testing\\Mock\\MockHandler
            {
                return \$this->tvnHandler;
            }
        {$body}
        }
        PHP;
    }

    private static function methodSource(\ReflectionMethod $method): string
    {
        $name = $method->getName();
        $visibility = $method->isProtected() ? 'protected' : 'public';
        $static = $method->isStatic() ? 'static ' : '';
        $params = self::parametersSource($method);
        $argsList = self::argumentsSource($method);

        $returnType = $method->getReturnType();
        $returnStr = $returnType === null ? '' : ': ' . self::typeToString($returnType);
        $isVoid = $returnType instanceof \ReflectionNamedType && $returnType->getName() === 'void';
        $isNever = $returnType instanceof \ReflectionNamedType && $returnType->getName() === 'never';

        // فهرست صریح آرگومان‌ها → پیش‌فرض‌های اعمال‌شده نیز به Handler می‌رسند
        $dispatch = $method->isStatic()
            ? '\\Tavaan\\Testing\\Mock\\MockHandler::staticCall(self::class, ' . \var_export($name, true) . ', [' . $argsList . '])'
            : '$this->tvnHandler->call(' . \var_export($name, true) . ', [' . $argsList . '])';

        // در never اگر dispatch عادی برگردد، خودِ PHP خطای TypeError می‌دهد (semantic درست)
        $body = $isVoid || $isNever
            ? '        ' . $dispatch . ';'
            : '        return ' . $dispatch . ';';

        return "\n    {$visibility} {$static}function {$name}({$params}){$returnStr}\n    {\n{$body}\n    }\n";
    }

    /**
     * فهرست آرگومان‌های مؤثر برای dispatch (`$a, $b, ...$rest`).
     */
    private static function argumentsSource(\ReflectionMethod $method): string
    {
        $parts = [];
        foreach ($method->getParameters() as $param) {
            $parts[] = ($param->isVariadic() ? '...' : '') . '$' . $param->getName();
        }

        return \implode(', ', $parts);
    }

    private static function parametersSource(\ReflectionMethod $method): string
    {
        $parts = [];
        foreach ($method->getParameters() as $param) {
            $part = '';

            $type = $param->getType();
            if ($type !== null) {
                $part .= self::typeToString($type) . ' ';
            }
            if ($param->isPassedByReference()) {
                $part .= '&';
            }
            if ($param->isVariadic()) {
                $part .= '...';
            }
            $part .= '$' . $param->getName();

            if (!$param->isVariadic()) {
                $default = self::defaultSource($param, $method);
                if ($default !== null) {
                    $part .= ' = ' . $default;
                }
            }

            $parts[] = $part;
        }

        return \implode(', ', $parts);
    }

    /**
     * منبع مقدار پیش‌فرض پارامتر — یا null اگر پیش‌فرضی ندارد.
     *
     * 🔒 nullable ضمنی (F-15 در DOC-010) به `= null` صریح تبدیل می‌شود.
     */
    private static function defaultSource(\ReflectionParameter $param, \ReflectionMethod $method): ?string
    {
        if ($param->isDefaultValueAvailable()) {
            $constant = $param->getDefaultValueConstantName();
            if ($constant !== null) {
                return self::constantSource($constant, $method);
            }

            return self::exportValue($param->getDefaultValue());
        }

        if ($param->allowsNull() && $param->getType() !== null) {
            $type = $param->getType();
            // nullable صریح بدون پیش‌فرض → در proxy صریح کنیم (سازگاری 8.4)
            $isStandaloneNullableNamed = $type instanceof \ReflectionNamedType
                && !\in_array($type->getName(), ['mixed', 'null'], true);
            if ($isStandaloneNullableNamed) {
                return 'null';
            }
        }

        return null;
    }

    private static function constantSource(string $constant, \ReflectionMethod $method): string
    {
        if (\in_array($constant, ['null', 'true', 'false'], true)) {
            return $constant;
        }

        if (\str_contains($constant, '::')) {
            [$class, $name] = \explode('::', $constant, 2);
            $class = match (\strtolower($class)) {
                'self', 'static' => $method->getDeclaringClass()->getName(),
                'parent' => (string) $method->getDeclaringClass()->getParentClass()?->getName(),
                default => $class,
            };
            if ($class === '') {
                return '\\' . $name;
            }

            return '\\' . \ltrim($class, '\\') . '::' . $name;
        }

        // ⚠️ Reflection نام فضای‌نام را به ثابت‌های unqualified می‌چسباند
        // (`App\PHP_INT_MAX`) — بازتولید وضوح واقعی PHP: نخست نام فضای‌نام‌دار،
        // سپس fallback سراسری.
        if (\defined($constant)) {
            return '\\' . \ltrim($constant, '\\');
        }
        $position = \strrpos($constant, '\\');
        if ($position !== false) {
            $short = \substr($constant, $position + 1);
            if (\defined($short)) {
                return '\\' . $short;
            }
        }

        return '\\' . \ltrim($constant, '\\');   // شاید بعداً تعریف شود — best effort
    }

    private static function exportValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_int($value)) {
            return (string) $value;
        }
        if (\is_float($value)) {
            if (\is_nan($value)) {
                return 'NAN';
            }
            if (\is_infinite($value)) {
                return $value > 0 ? 'INF' : '-INF';
            }

            return \var_export($value, true);
        }
        if (\is_string($value)) {
            return \var_export($value, true);
        }
        if (\is_array($value)) {
            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = self::exportValue($key) . ' => ' . self::exportValue($item);
            }

            return '[' . \implode(', ', $parts) . ']';
        }
        if ($value instanceof \UnitEnum) {
            return '\\' . $value::class . '::' . $value->name;
        }

        throw new \InvalidArgumentException(\sprintf(
            'Unsupported default value type [%s] in mocked signature.',
            \get_debug_type($value),
        ));
    }

    private static function typeToString(\ReflectionType $type): string
    {
        if ($type instanceof \ReflectionNamedType) {
            $name = $type->getName();
            // انواع نسبی (isBuiltin=false گزارش می‌شوند!) — در Proxy معنای درست دارند
            if (\in_array($name, ['self', 'static', 'parent'], true)) {
                return $name;
            }
            $rendered = $type->isBuiltin() ? $name : '\\' . $name;
            if ($type->allowsNull() && !\in_array($name, ['mixed', 'null'], true)) {
                return '?' . $rendered;
            }

            return $rendered;
        }

        if ($type instanceof \ReflectionIntersectionType) {
            $parts = [];
            foreach ($type->getTypes() as $sub) {
                $parts[] = self::typeToString($sub);
            }

            return \implode('&', $parts);
        }

        if ($type instanceof \ReflectionUnionType) {
            $parts = [];
            foreach ($type->getTypes() as $sub) {
                $rendered = self::typeToString($sub);
                if ($sub instanceof \ReflectionIntersectionType) {
                    $rendered = '(' . $rendered . ')';   // DNF
                } elseif (\str_starts_with($rendered, '?')) {
                    $rendered = \substr($rendered, 1);   // در Union: `Foo|null` نه `?Foo`
                }
                $parts[] = $rendered;
            }

            return \implode('|', $parts);
        }

        return (string) $type;
    }
}
