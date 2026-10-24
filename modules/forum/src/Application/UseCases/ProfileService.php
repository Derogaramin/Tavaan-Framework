<?php

declare(strict_types=1);

/**
 * تَوان | Tavaan — ماژول انجمن
 * ProfileService — پروفایل انجمن: username، karma، ban (DOC-094 §4/§6).
 */

namespace Modules\Forum\Application\UseCases;

use Modules\CoreUser\Contracts\UserReaderInterface;
use Modules\Forum\Domain\Entities\ForumProfile;
use Modules\Forum\Domain\Exceptions\ForumException;
use Modules\Forum\Domain\Repositories\ProfileRepositoryInterface;
use Modules\Forum\Domain\Services\ReputationCalculator;
use Modules\Forum\Domain\ValueObjects\Username;
use Tavaan\Core\Facade\Facades\Security;
use Tavaan\Core\Facade\Facades\Validator;

/**
 * ProfileService — ensure/update پروفایل + karma + ban.
 *
 * @api 1.0
 * @see modules/forum/README.md
 */
final class ProfileService
{
    /**
     * @param array<string, mixed> $config کلید config('forum')
     */
    public function __construct(
        private readonly ProfileRepositoryInterface $profiles,
        private readonly UserReaderInterface $users,
        private readonly ReputationCalculator $reputation,
        private readonly array $config = [],
    ) {
    }

    /** پروفایل کاربر — در صورت نبود، با username پیشنهادی می‌سازد. */
    public function ensureProfile(int $userId): ForumProfile
    {
        $profile = $this->profiles->findByUserId($userId);
        if ($profile !== null) {
            return $profile;
        }

        $displayName = $this->users->displayNameOf($userId, 'کاربر');
        $username = Username::suggest(
            $displayName,
            fn (string $candidate): bool => $this->profiles->usernameExists($candidate),
            $userId,
        );

        $profile = ForumProfile::create($userId, $username);
        $this->profiles->save($profile);

        return $profile;
    }

    public function profileOf(int $userId): ?ForumProfile
    {
        return $this->profiles->findByUserId($userId);
    }

    public function findByUsername(string $username): ?ForumProfile
    {
        return $this->profiles->findByUsername($username);
    }

    /** نام کاربری انجمن با fallback (برای ویوها/اعلان‌ها). */
    public function usernameOf(int $userId): string
    {
        return $this->profiles->findByUserId($userId)?->username() ?? 'user-' . $userId;
    }

    /** @param array<string, mixed> $input */
    public function updateProfile(int $userId, array $input): ForumProfile
    {
        $validated = Validator::validate($input, [
            'username' => ['required', 'string', 'max:40'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'signature' => ['nullable', 'string', 'max:300'],
        ], attributes: ['username' => 'نام کاربری', 'bio' => 'بیوگرافی']);

        $profile = $this->ensureProfile($userId);

        $username = \trim((string) $validated['username']);
        $min = (int) ($this->config['profile']['username_min'] ?? 3);
        $max = (int) ($this->config['profile']['username_max'] ?? 30);
        if (!Username::isValid($username, $min, $max)) {
            throw new \InvalidArgumentException('نام کاربری باید ۳ تا ۳۰ نویسه (حرف/رقم/_/.) و با حرف شروع شود.');
        }
        if ($this->profiles->usernameExists($username, $userId)) {
            throw new \InvalidArgumentException('این نام کاربری پیش‌تر گرفته شده است.');
        }

        $bio = isset($validated['bio']) ? Security::sanitize((string) $validated['bio'], 'plain_text') : null;
        $signature = isset($validated['signature'])
            ? Security::sanitize((string) $validated['signature'], 'plain_text')
            : null;

        $profile->updateProfile($username, $bio !== '' ? $bio : null, $signature !== '' ? $signature : null);
        $this->profiles->save($profile);

        return $profile;
    }

    /** آیا کاربر در انجمن ban است؟ (مسیر نوشتن این را چک می‌کند) */
    public function isBanned(int $userId): bool
    {
        $profile = $this->profiles->findByUserId($userId);
        if ($profile === null) {
            return false;
        }

        if ($profile->isCurrentlyBanned()) {
            return true;
        }

        // banned_until گذشته → لغو خودکار ban
        if ($profile->isBanned()) {
            $profile->unban();
            $this->profiles->save($profile);
        }

        return false;
    }

    public function assertNotBanned(int $userId): void
    {
        if ($this->isBanned($userId)) {
            $profile = $this->profiles->findByUserId($userId);
            throw ForumException::banned($profile?->banReason());
        }
    }

    public function ban(int $userId, ?\DateTimeImmutable $until = null, string $reason = ''): ForumProfile
    {
        $profile = $this->ensureProfile($userId);
        $profile->ban($until, $reason);
        $this->profiles->save($profile);

        return $profile;
    }

    public function unban(int $userId): ForumProfile
    {
        $profile = $this->ensureProfile($userId);
        $profile->unban();
        $this->profiles->save($profile);

        return $profile;
    }

    /** افزودن karma (مسیر سریع بدون load کامل). */
    public function addKarma(int $userId, int $delta): void
    {
        if (!$this->reputation->enabled() || $delta === 0) {
            return;
        }

        $this->ensureProfile($userId);
        $this->profiles->incrementKarma($userId, $delta);
    }

    /** ثبت فعالیت در شمارنده‌های پروفایل (موضوع/پست جدید). */
    public function registerActivity(int $userId, bool $thread): void
    {
        $profile = $this->ensureProfile($userId);
        if ($thread) {
            $profile->registerThread();
        }
        $profile->registerPost();
        $this->profiles->save($profile);
    }

    /** ثبت/لغو پاسخ برگزیده در شمارندهٔ پروفایل. */
    public function registerSolution(int $userId, bool $add): void
    {
        $profile = $this->ensureProfile($userId);
        $profile->registerSolution($add);
        $this->profiles->save($profile);
    }

    /** اعضای برتر (karma) — برای sidebar انجمن. */
    public function topMembers(int $limit = 5): array
    {
        return $this->profiles->topByKarma($limit);
    }

    public function countMembers(): int
    {
        return $this->profiles->countAll();
    }

    /**
     * نام‌های نمایشی دسته‌ای برای یک فهرست author_id (بدون N+1).
     *
     * @param list<int>             $userIds
     *
     * @return array{names: array<string, string>, usernames: array<string, string>}
     */
    public function displayNames(array $userIds): array
    {
        $userIds = \array_values(\array_unique(\array_map('intval', $userIds)));
        $profiles = $this->profiles->findByUserIds($userIds);

        $names = [];
        $usernames = [];
        foreach ($userIds as $id) {
            $names[(string) $id] = $this->users->displayNameOf($id, 'کاربر');
            $usernames[(string) $id] = $profiles[(string) $id]?->username() ?? 'user-' . $id;
        }

        return ['names' => $names, 'usernames' => $usernames];
    }
}
