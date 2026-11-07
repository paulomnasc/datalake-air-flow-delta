# -*- coding: utf-8 -*-
"""
Módulo Canônico de Enumeração de Tiers e Destaques de Apostas (BetTier).
Fonte Única da Verdade (SSOT) para classificação de níveis de oportunidade,
cálculo de stake alavancada e governança de risco no ecossistema FootballWeb.
"""

from enum import IntEnum


class BetTier(IntEnum):
    """
    Enumeração Canônica de Níveis de Oportunidade e Destaque:
    0: REGULAR - Aposta padrão aprovada pelo Gatekeeper (+EV), stake 1x.
    1: QUASE_PREMIUM - Aposta validada pelo Prisma Quase Premium (V1, V2, V3), stake 1x.
    2: PREMIUM - Super-Favorito Tier 1 com abismo técnico e Tríade convergente, stake 3x.
    """
    REGULAR = 0
    QUASE_PREMIUM = 1
    PREMIUM = 2

    @property
    def label(self) -> str:
        if self == BetTier.PREMIUM:
            return "👑 Oportunidade PREMIUM"
        elif self == BetTier.QUASE_PREMIUM:
            return "🛡️ Aposta Quase Premium"
        return "Aposta Regular (+EV)"

    @property
    def badge_text(self) -> str:
        if self == BetTier.PREMIUM:
            return "👑 ⭐ Aposta PREMIUM (3x Stake)"
        elif self == BetTier.QUASE_PREMIUM:
            return "🛡️ Aposta Quase Premium (1x Stake)"
        return "Regular (+EV)"

    @property
    def stake_multiplier(self) -> float:
        return 3.0 if self == BetTier.PREMIUM else 1.0

    @property
    def gatekeeper_category(self) -> str:
        if self == BetTier.PREMIUM:
            return "Oportunidade PREMIUM (+EV Alavancado)"
        elif self == BetTier.QUASE_PREMIUM:
            return "Aposta Quase Premium (+EV Selecionado)"
        return "Valor Esperado Positivo (+EV)"

    @classmethod
    def from_destaque(cls, val) -> "BetTier":
        """Converte com segurança qualquer valor numérico ou nulo para BetTier."""
        try:
            return cls(int(val or 0))
        except (ValueError, TypeError):
            return cls.REGULAR

