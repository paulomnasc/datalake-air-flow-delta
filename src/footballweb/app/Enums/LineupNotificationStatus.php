<?php

namespace App\Enums;

/**
 * Enum tipado para o ciclo de vida das notificações de confirmação de escalação oficial (Regra de Ouro 21).
 * 
 * 0 = NAO_NOTIFICADO (Sem notificação de confirmação de escalação enviada ainda)
 * 1 = NOTIFICADO     (Notificação de confirmação de escalação oficial já enviada com sucesso)
 */
enum LineupNotificationStatus: int
{
    case NAO_NOTIFICADO = 0;
    case NOTIFICADO = 1;

    public static function fromValue(?int $val): self
    {
        return match ($val) {
            1 => self::NOTIFICADO,
            default => self::NAO_NOTIFICADO,
        };
    }

    public function isNotificado(): bool
    {
        return $this === self::NOTIFICADO;
    }

    public function label(): string
    {
        return match ($this) {
            self::NOTIFICADO => 'Notificado',
            self::NAO_NOTIFICADO => 'Não Notificado',
        };
    }
}

