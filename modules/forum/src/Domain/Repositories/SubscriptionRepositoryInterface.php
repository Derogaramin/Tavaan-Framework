<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * SubscriptionRepositoryInterface — پورت اشتراک موضوع (DOC-094 §2).
 */

namespace Modules\Forum\Domain\Repositories;

/**
 * @api 1.0
 */
interface SubscriptionRepositoryInterface
{
    public function subscribe(int $userId, string $threadId): void;

    public function unsubscribe(int $userId, string $threadId): void;

    public function isSubscribed(int $userId, string $threadId): bool;

    /**
     * دنبال‌کنندگان یک موضوع.
     *
     * @return list<int> شناسهٔ کاربران
     */
    public function subscribersOf(string $threadId): array;

    /**
     * موضوعات دنبال‌شدهٔ یک کاربر (برای پروفایل).
     *
     * @return list<string> شناسهٔ موضوعات
     */
    public function threadsOf(int $userId, int $limit = 20): array;

    public function deleteForThread(string $threadId): void;
}
