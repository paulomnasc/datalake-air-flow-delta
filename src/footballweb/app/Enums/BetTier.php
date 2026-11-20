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

    public static function fromDestaque(?int $destaque, ?string $gatekeeperCategory = null, ?string $resultadoDetalhado = null): self
    {
        if ($destaque !== null && $destaque > 0) {
            $found = self::tryFrom($destaque);
            if ($found !== null) {
                return $found;
            }
        }

        $cat = $gatekeeperCategory ?? '';
        if (stripos($cat, 'PREMIUM') !== false && stripos($cat, 'QUASE') === false) {
            return self::PREMIUM;
        }
        if (stripos($cat, 'Quase Premium') !== false || stripos($cat, 'QUASE') !== false) {
            return self::QUASE_PREMIUM;
        }

        $det = $resultadoDetalhado ?? '';
        if (stripos($det, 'CATEGORIA: Premium') !== false || (stripos($det, 'PREMIUM') !== false && stripos($det, 'Quase Premium') === false && stripos($det, 'QUASE') === false)) {
            return self::PREMIUM;
        }
        if (stripos($det, 'Quase Premium') !== false) {
            return self::QUASE_PREMIUM;
        }

        return self::REGULAR;
    }
}

