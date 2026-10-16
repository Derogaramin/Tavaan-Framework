<?php

declare(strict_types=1);

namespace Tavaan\Core\Contracts\Console;

/**
 * قرارداد Console Kernel (نقطهٔ ورود bin/tvn).
 *
 * @api 1.0
 * @see docs/03-engines/console.md
 */
interface KernelInterface
{
    /** اجرا و بازگرداندن کد خروج. */
    public function handle(InputInterface $input, OutputInterface $output): int;

    /** فراخوانی درون‌برنامه‌ای یک دستور. @param array<string, mixed> $arguments */
    public function call(string $command, array $arguments = []): int;

    public function register(CommandInterface|string $command): void;

    /** نگاشت نام دستور → class (برای list). @return array<string, string> */
    public function commands(): array;

    /** نمونهٔ Command یک دستور ثبت‌شده (throw اگر ثبت نشده باشد). */
    public function resolve(string $name): CommandInterface;
}
