<?php

declare(strict_types=1);

namespace Tavaan\Engine\Validation\Testing;

use Tavaan\Engine\Validation\Attributes\Between;
use Tavaan\Engine\Validation\Attributes\Email;
use Tavaan\Engine\Validation\Attributes\Integer;
use Tavaan\Engine\Validation\Attributes\IranMobile;
use Tavaan\Engine\Validation\Attributes\MaxLength;
use Tavaan\Engine\Validation\Attributes\Required;

/**
 * تَوان | Tavaan — Validation
 * CreateUserDto — Fixture تست Attribute-based validation (DOC-051 §6).
 *
 * @api internal
 * @see docs/03-engines/validation.md
 */
final class CreateUserDto
{
    public function __construct(
        #[Required]
        #[Email]
        #[MaxLength(255)]
        public readonly string $email,

        #[Required]
        #[Integer]
        #[Between(18, 120)]
        public readonly int $age,

        #[Required]
        #[IranMobile]
        public readonly string $mobile,
    ) {
    }
}
