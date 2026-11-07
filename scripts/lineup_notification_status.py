"""
lineup_notification_status.py
-----------------------------
Módulo canônico com Enum tipado para governança do ciclo de vida
das notificações de confirmação de escalação oficial (Regra de Ouro 21).

Escala de Prioridade:
0 = NAO_NOTIFICADO (Sem notificação de confirmação de escalação enviada ainda)
1 = NOTIFICADO     (Notificação de confirmação de escalação oficial enviada com sucesso)
"""

from enum import IntEnum


class LineupNotificationStatus(IntEnum):
    NAO_NOTIFICADO = 0
    NOTIFICADO = 1

    @classmethod
    def from_value(cls, val):
        """Converte valor seguro para o Enum, tratando None e tipos inconsistentes."""
        if val is None:
            return cls.NAO_NOTIFICADO
        try:
            return cls(int(val))
        except (ValueError, TypeError):
            return cls.NAO_NOTIFICADO

    @property
    def label(self) -> str:
        return "Notificado" if self == 1 else "Não Notificado"

    @property
    def is_notificado(self) -> bool:
        return self == LineupNotificationStatus.NOTIFICADO

