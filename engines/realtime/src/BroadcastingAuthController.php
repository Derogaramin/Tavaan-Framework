<?php

declare(strict_types=1);

namespace Tavaan\Engine\Realtime;

use Tavaan\Core\Contracts\Container\ContainerInterface;
use Tavaan\Engine\Http\Request;
use Tavaan\Engine\Http\Response;

/**
 * تَوان | Tavaan — Realtime
 * BroadcastingAuthController — احراز کانال خصوصی (DOC-045 §8).
 *
 * `POST /broadcasting/auth` با `channel_name` (+ `token` کاربر احراز‌شده):
 * - کاربر ناشناس → 403
 * - callback کانال رد کند → 403
 * - قبول → `{"auth": "<token امضاشده>"}` (+ عضویت presence اگر callback آرایه برگرداند)
 *
 * @api 1.0
 * @see docs/03-engines/realtime.md
 */
final class BroadcastingAuthController
{
    public function __construct(
        private readonly RealtimeManager $manager,
        private readonly PresenceManager $presence,
        private readonly ContainerInterface $container,
    ) {
    }

    public function auth(Request $request): Response
    {
        $channel = \trim((string) ($request->input('channel_name') ?? ''));
        if ($channel === '' || \mb_strlen($channel) > 200) {
            return Response::json(['error' => 'invalid_channel'], 400);
        }

        $user = $this->resolveUser($request);
        if ($user === null) {
            return Response::json(['error' => 'unauthenticated'], 403);
        }

        $result = $this->manager->authorize($user, $channel);
        if ($result === false) {
            return Response::json(['error' => 'forbidden'], 403);
        }

        // کانال presence → عضویت + دادهٔ عضو در payload
        $presenceData = null;
        if (\is_array($result)) {
            $presenceData = $result;
        }
        if ($this->manager->isPresence($channel)) {
            $member = \is_array($presenceData) ? $presenceData : ['id' => $this->userId($user)];
            $member['id'] ??= $this->userId($user);
            $this->presence->join($channel, $member);
        }

        return Response::json([
            'auth' => $this->manager->sign($channel, $user, $presenceData),
            'channel' => $channel,
            'presence' => $presenceData,
        ]);
    }

    private function resolveUser(Request $request): ?object
    {
        // ۱) attribute ست‌شده توسط middleware احراز
        $user = $request->attribute('user');
        if (\is_object($user)) {
            return $user;
        }

        // ۲) موتور auth
        try {
            if ($this->container->bound('auth')) {
                $auth = $this->container->make('auth');
                if (\method_exists($auth, 'user')) {
                    $resolved = $auth->user();
                    if (\is_object($resolved)) {
                        return $resolved;
                    }
                }
            }
        } catch (\Throwable) {
            // fall through
        }

        return null;
    }

    private function userId(object $user): string
    {
        if (\method_exists($user, 'getAuthIdentifier')) {
            return (string) $user->getAuthIdentifier();
        }
        if (isset($user->id)) {
            return (string) $user->id;
        }

        return \spl_object_hash($user);
    }
}
