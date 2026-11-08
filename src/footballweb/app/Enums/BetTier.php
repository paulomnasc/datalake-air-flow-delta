<?php

namespace App\Enums;

/**
 * Enumeração Canônica de Níveis de Oportunidade e Destaque de Apostas (BetTier).
 * Fonte Única da Verdade (SSOT) compartilhada entre Python e PHP.
 */
enum BetTier: int
{
    case REGULAR = 0;
    case QUASE_PREMIUM = 1;
    case PREMIUM = 2;

    public function isPremium(): bool
    {
        return $this === self::PREMIUM;
    }

    public function isQuasePremium(): bool
    {
        return $this === self::QUASE_PREMIUM;
    }

    public function isRegular(): bool
    {
        return $this === self::REGULAR;
    }

    public function label(): string
    {
        return match ($this) {
            self::PREMIUM => '👑 Oportunidade PREMIUM',
            self::QUASE_PREMIUM => '🛡️ Aposta Quase Premium',
            self::REGULAR => 'Aposta Regular (+EV)',
        };
    }

    public function badgeText(): string
    {
        return match ($this) {
            self::PREMIUM => '👑 ⭐ Aposta PREMIUM (3x Stake)',
            self::QUASE_PREMIUM => '🛡️ Aposta Quase Premium (1x Stake)',
            self::REGULAR => 'Regular (+EV)',
        };
    }

    public function stakeMultiplier(): float
    {
        return match ($this) {
            self::PREMIUM => 3.0,
            self::QUASE_PREMIUM => 1.0,
            self::REGULAR => 1.0,
        };
    }

    public static function fromDestaque(?int $destaque): self
    {
        if ($destaque === null) {
            return self::REGULAR;
        }
        return self::tryFrom($destaque) ?? self::REGULAR;
    }
}

