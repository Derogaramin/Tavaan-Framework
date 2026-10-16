<?php

declare(strict_types=1);

namespace Tavaan\Engine\FeatureFlag;

/**
 * تَوان | Tavaan — FeatureFlag
 * ScopedFeature — ارزیابی featureها در حوزهٔ یک کاربر (Feature::for($user)).
 *
 * @api 1.0
 * @see docs/03-engines/feature-flag.md
 */
final class ScopedFeature
{
    public function __construct(
        private readonly FeatureManager $manager,
        private readonly ?object $user,
    ) {
    }

    public function enabled(string $feature): bool
    {
        return $this->manager->enabled($feature, $this->user);
    }

    public function value(string $feature, mixed $default = null): mixed
    {
        return $this->manager->value($feature, $default);
    }

    /** @param list<string> $variants */
    public function experiment(string $experiment, array $variants): ?string
    {
        return $this->manager->experiment($experiment, $variants, $this->user);
    }
}
