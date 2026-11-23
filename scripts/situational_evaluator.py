"""
Módulo Canônico de Avaliação Situacional do Jogo em Tempo Real (Python Mirror)
Padronizado com Design Patterns: Abstract Factory + Builder + Strategy

Espelhamento rigoroso 1:1 com App\\Services\\Situational (PHP / CodeIgniter 4)
Garante a Single Source of Truth para o mapeamento situacional entre o Frontend e o Sentinela.

Regras de Ouro:
- Regra 8: Inicialização prévia mandatória de variáveis numéricas locais.
- Regra 11: Sincronização obrigatória com PHP (ApostaController.php).
- Regra 12: Zero dados sintéticos ou arbitrários.
"""

import re
from typing import Dict, Any, Optional
from dataclasses import dataclass, asdict


@dataclass(frozen=True)
class SituationalResult:
    gauge_pct: int
    faixa_cor: str
    is_cashout_moment: bool
    alerta_titulo: str
    alerta_msg: str
    distancia_linha: float = 0.0
    saldo: float = 0.0
    estado_resultado: str = "NEUTRO"
    detalhe_tatico: str = ""

    def to_dict(self) -> Dict[str, Any]:
        return asdict(self)


class SituationalEvaluatorInterface:
    def evaluate(self, bet: Dict[str, Any], match_context: Dict[str, Any]) -> SituationalResult:
        raise NotImplementedError("Método evaluate deve ser implementado.")


class AsianHandicapSituationalEvaluator(SituationalEvaluatorInterface):
    """
    Avalia o estado situacional para mercados de Handicap Asiático (AH) e Draw No Bet (DNB)
    baseado na distância matemática e direção do resultado real em relação à linha apostada (R = ΔG + L).
    """
    def evaluate(self, bet: Dict[str, Any], match_context: Dict[str, Any]) -> SituationalResult:
        gh = 0
        ga = 0
        elapsed = 0
        rc_home = 0
        rc_away = 0
        diff_gols = 0
        my_red_cards = 0
        opp_red_cards = 0
        line_val = 0.0
        resultado_linha = 0.0
        gauge_pct = 20

        palpite = str(bet.get("palpite") or "")
        home_team = str(bet.get("time_casa") or match_context.get("home_team") or "Mandante")
        away_team = str(bet.get("time_fora") or match_context.get("away_team") or "Visitante")

        gh = int(match_context.get("goals_home") or 0)
        ga = int(match_context.get("goals_away") or 0)
        elapsed = int(match_context.get("elapsed") or 0)
        status = str(match_context.get("fixture_status") or "NS").strip().upper()
        is_live = bool(match_context.get("is_live") or (status in ("1H", "2H", "HT", "LIVE", "IN_PLAY", "PAUSED")))

        rc_home = int(match_context.get("red_cards_home") or 0)
        rc_away = int(match_context.get("red_cards_away") or 0)

        is_home_fav = (home_team.lower() in palpite.lower())
        diff_gols = (gh - ga) if is_home_fav else (ga - gh)
        my_red_cards = rc_home if is_home_fav else rc_away
        opp_red_cards = rc_away if is_home_fav else rc_home

        m = re.search(r'([+-]?\d+(?:\.\d+)?)', palpite)
        if m:
            line_val = float(m.group(1))

        is_dnb_line = (abs(line_val) < 0.01 or "0.0" in palpite or "dnb" in palpite.lower() or "empate anula" in palpite.lower())

        # Se pré-jogo
        if not is_live and status in ("NS", "TBD", "POSTP"):
            return SituationalResult(
                gauge_pct=10,
                faixa_cor="verde",
                is_cashout_moment=False,
                alerta_titulo="⏳ Aguardando Início",
                alerta_msg="Partida ainda não iniciada. Velocímetro em standby de pré-jogo.",
                distancia_linha=line_val,
                saldo=0.0,
                estado_resultado="PRE_JOGO",
                detalhe_tatico="Aposta confirmada aguardando o apito inicial."
            )

        saldo_str = f"+{diff_gols}" if diff_gols > 0 else str(diff_gols)
        detalhe_tatico = f"Placar: {gh}x{ga} ({elapsed}') • Saldo: {saldo_str}"

        # Resultado instantâneo em relação à linha: R = ΔG + L
        resultado_linha = float(diff_gols + line_val)

        faixa_cor = "verde"
        is_cashout_moment = False
        alerta_titulo = "🟢 Em Vantagem"
        alerta_msg = f"Cenário favorável no handicap aos {elapsed}'."
        estado_resultado = "GREEN_TOTAL"

        # 1. Cenário de PUSH (Reembolso 100% / Capital Protegido)
        if abs(resultado_linha) < 0.001:
            estado_resultado = "PUSH_REEMBOLSO"
            is_cashout_moment = False

            if elapsed >= 80:
                gauge_pct = 15
                faixa_cor = "verde"
                alerta_titulo = "🟢 Reembolso Garantido (Push Protegido)"
                alerta_msg = f"Empate aos {elapsed}'. Na linha 0.0 AH, sua aposta está 100% protegida com reembolso total se o placar mantiver!"
            else:
                gauge_pct = 20
                faixa_cor = "verde"
                alerta_titulo = "🟢 Empate (Capital 100% Protegido / Devolução)"
                alerta_msg = f"Partida empatada aos {elapsed}'. Linha 0.0 AH em zona de devolução/reembolso integral sem risco de perda financeira."

            if my_red_cards > 0 and elapsed >= 46:
                gauge_pct = 40
                faixa_cor = "amarelo"
                alerta_titulo = "🟡 Atenção: Expulsão com Placar Empatado"
                alerta_msg = f"Empate aos {elapsed}', mas o time apostado tem cartão vermelho. Monitorando risco de pressão adversária."

        # 2. Cenário de GREEN TOTAL (R >= +0.5)
        elif resultado_linha >= 0.5:
            estado_resultado = "GREEN_TOTAL"
            is_cashout_moment = False
            faixa_cor = "verde"

            if diff_gols >= 2:
                gauge_pct = 10
                alerta_titulo = "🟢 Vantagem Confortável"
                alerta_msg = f"Equipe liderando com folga no placar ({gh}x{ga} aos {elapsed}')."
            else:
                if elapsed >= 80:
                    gauge_pct = 15
                    alerta_titulo = "🟢 Lucro Iminente (Reta Final)"
                    alerta_msg = f"Vencendo por 1 gol aos {elapsed}'. Segure até o fim para consolidar o Green total!"
                else:
                    gauge_pct = 20
                    alerta_titulo = "🟢 Em Vantagem"
                    alerta_msg = f"Liderando por 1 gol aos {elapsed}'. Cenário positivo."

        # 3. Cenário de MEIO GREEN (R == +0.25)
        elif abs(resultado_linha - 0.25) < 0.001:
            estado_resultado = "MEIO_GREEN"
            is_cashout_moment = False
            faixa_cor = "verde"
            gauge_pct = 20 if elapsed >= 80 else 25
            alerta_titulo = "🟢 Meio Green Assegurado"
            alerta_msg = f"Posição favorável no handicap (+0.25 acima da linha). Meio lucro assegurado no momento ({elapsed}')."

        # 4. Cenário de MEIO RED (R == -0.25)
        elif abs(resultado_linha - (-0.25)) < 0.001:
            estado_resultado = "MEIO_RED"

            if elapsed <= 45:
                gauge_pct = 42
                faixa_cor = "amarelo"
                is_cashout_moment = False
                alerta_titulo = "🟡 Meio Red Provisório (1º Tempo)"
                alerta_msg = f"Resultado momentâneo a meio gol da meta ({elapsed}'). Primeiro tempo aberto com amplo tempo para buscar a vitória."
            elif elapsed <= 74:
                gauge_pct = 52
                faixa_cor = "amarelo"
                is_cashout_moment = False
                alerta_titulo = "🟡 Atenção ao Ritmo (Buscando Gol)"
                alerta_msg = f"Empate aos {elapsed}'. Linha -0.25 AH necessita de 1 gol para Green total. Equipe buscando o gol."
            else:
                gauge_pct = 65
                faixa_cor = "cashout"
                is_cashout_moment = True
                alerta_titulo = "⚡ MOMENTO DO CASHOUT (Meio Red Reta Final)"
                alerta_msg = f"Reta final aos {elapsed}'. Linha -0.25 em meio-red. Avalie fechamento ou hedge para salvar 50% da stake!"

        # 5. Cenário de RED TOTAL (R <= -0.5)
        else:
            estado_resultado = "RED_TOTAL"

            if diff_gols <= -2:
                gauge_pct = 90
                faixa_cor = "vermelho"
                is_cashout_moment = (elapsed >= 30)
                alerta_titulo = "🔴 Em Desvantagem Crítica (2+ Gols)"
                alerta_msg = f"Equipe em desvantagem de 2+ gols no placar ({gh}x{ga} aos {elapsed}'). Posição sob alto risco de perda total."
            elif diff_gols == -1:
                if elapsed <= 45:
                    gauge_pct = 55
                    faixa_cor = "amarelo"
                    is_cashout_moment = False
                    alerta_titulo = "🟡 Desvantagem Precoce (Tempo de Reação)"
                    alerta_msg = f"Desvantagem mínima ({gh}x{ga}) aos {elapsed}'. Primeiro tempo em andamento: ampla margem e tempo no relógio para buscar empate ou virada."
                elif elapsed <= 75:
                    gauge_pct = 70
                    faixa_cor = "amarelo"
                    is_cashout_moment = False
                    alerta_titulo = "🟡 Buscando Reação"
                    alerta_msg = f"Desvantagem de 1 gol ({gh}x{ga}) aos {elapsed}'. Equipe pressionando pelo resultado."
                else:
                    gauge_pct = 88
                    faixa_cor = "vermelho"
                    is_cashout_moment = True
                    alerta_titulo = "⚡ MOMENTO DO CASHOUT (Reta Final em Desvantagem)"
                    alerta_msg = f"Desvantagem de 1 gol aos {elapsed}'. Pouco tempo restante no relógio para reversão. Acionamento de Hedge recomendado."
            else:
                if elapsed <= 60:
                    gauge_pct = 48
                    faixa_cor = "amarelo"
                    is_cashout_moment = False
                    alerta_titulo = "🟡 Jogo Empatado (Buscando Vantagem)"
                    alerta_msg = f"Partida empatada ({gh}x{ga}) aos {elapsed}'. Linha {line_val} AH necessita de gol para Green."
                elif elapsed <= 75:
                    gauge_pct = 60
                    faixa_cor = "cashout"
                    is_cashout_moment = False
                    alerta_titulo = "🟡 Empate no 2º Tempo"
                    alerta_msg = f"Empate aos {elapsed}'. Tempo escasseando para cobrir a linha {line_val} AH."
                else:
                    gauge_pct = 75
                    faixa_cor = "cashout"
                    is_cashout_moment = True
                    alerta_titulo = "⚡ MOMENTO DO CASHOUT (Empate Reta Final)"
                    alerta_msg = f"Empate aos {elapsed}'. Linha de vitória {line_val} AH não atingida na reta final. Avalie fechar e proteger a banca!"

            if my_red_cards > 0 and elapsed >= 46:
                gauge_pct = min(98, gauge_pct + 15)
                is_cashout_moment = True
                alerta_titulo = "⚡ MOMENTO DO CASHOUT (Expulsão em Desvantagem)"

        return SituationalResult(
            gauge_pct=int(gauge_pct),
            faixa_cor=faixa_cor,
            is_cashout_moment=is_cashout_moment,
            alerta_titulo=alerta_titulo,
            alerta_msg=alerta_msg,
            distancia_linha=resultado_linha,
            saldo=float(diff_gols),
            estado_resultado=estado_resultado,
            detalhe_tatico=detalhe_tatico
        )


class CardsSituationalEvaluator(SituationalEvaluatorInterface):
    """
    Avalia o estado situacional para mercados de Cartões (Total e Individual, Under e Over).
    """
    def evaluate(self, bet: Dict[str, Any], match_context: Dict[str, Any]) -> SituationalResult:
        elapsed = 0
        yh = 0
        ya = 0
        rh = 0
        ra = 0
        yellows = 0
        reds = 0
        total_cards = 0
        line_val = 4.5
        distancia_linha = 0.0
        gauge_pct = 15

        palpite = str(bet.get("palpite") or "")
        mercado = str(bet.get("mercado") or "")
        home_team = str(bet.get("time_casa") or match_context.get("home_team") or "Mandante")
        away_team = str(bet.get("time_fora") or match_context.get("away_team") or "Visitante")

        elapsed = int(match_context.get("elapsed") or 0)
        status = str(match_context.get("fixture_status") or "NS").strip().upper()
        is_live = bool(match_context.get("is_live") or (status in ("1H", "2H", "HT", "LIVE", "IN_PLAY", "PAUSED")))

        yh = int(match_context.get("yellow_cards_home") or 0)
        ya = int(match_context.get("yellow_cards_away") or 0)
        rh = int(match_context.get("red_cards_home") or 0)
        ra = int(match_context.get("red_cards_away") or 0)

        m = re.search(r'(\d+(?:\.\d+)?)', palpite)
        if m:
            line_val = float(m.group(1))

        is_under = ("menos" in palpite.lower() or "under" in palpite.lower())
        is_individual = ("individual" in mercado.lower() or "individual" in palpite.lower())

        has_home = bool(home_team and home_team.lower() in palpite.lower())
        has_away = bool(away_team and away_team.lower() in palpite.lower())

        # Se for mercado individual mas nenhum dos times foi explicitamente citado no palpite
        # OU se a linha de cartões for >= 5.0 (patamar clássico de jogo completo, nunca individual):
        # avalia obrigatoriamente como Total do Jogo!
        if is_individual and (has_home or has_away) and line_val < 5.0:
            is_home = has_home
            yellows = yh if is_home else ya
            reds = rh if is_home else ra
            team_name = home_team if is_home else away_team
        else:
            yellows = yh + ya
            reds = rh + ra
            team_name = "Total do Jogo"

        total_cards = yellows + reds
        if team_name == "Total do Jogo":
            detalhe_tatico = f"Total no Jogo: {total_cards} cartões ({yh}+{ya} Am, {rh}+{ra} Vm - {elapsed}')"
        else:
            detalhe_tatico = f"{team_name}: {yellows} Amarelos, {reds} Vermelhos ({elapsed}')"

        if not is_live and status in ("NS", "TBD", "POSTP"):
            return SituationalResult(
                gauge_pct=10,
                faixa_cor="verde",
                is_cashout_moment=False,
                alerta_titulo="⏳ Aguardando Início",
                alerta_msg="Partida ainda não iniciada. Velocímetro em standby de pré-jogo.",
                distancia_linha=line_val,
                saldo=float(total_cards),
                estado_resultado="PRE_JOGO",
                detalhe_tatico="Aposta confirmada aguardando o apito inicial."
            )

        distancia_linha = line_val - total_cards

        if is_under:
            sobra_cards = line_val - total_cards
            if total_cards > line_val:
                return SituationalResult(
                    gauge_pct=95,
                    faixa_cor="vermelho",
                    is_cashout_moment=False,
                    alerta_titulo="🔴 Limite de Cartões Estourado",
                    alerta_msg=f"{total_cards} cartões aos {elapsed}'. Limite de {line_val} cartões ultrapassado.",
                    distancia_linha=distancia_linha,
                    saldo=float(total_cards),
                    estado_resultado="RED_TOTAL",
                    detalhe_tatico=detalhe_tatico
                )

            if sobra_cards <= 0.5:
                if elapsed >= 82:
                    gauge_pct = 35
                    faixa_cor = "verde"
                    is_cashout_moment = False
                    alerta_titulo = "🟢 Segure até o Fim (Reta Final)"
                    alerta_msg = f"{total_cards} cartões aos {elapsed}'. Jogo na reta final: deixe o relógio correr para assegurar o Green!"
                else:
                    gauge_pct = 60
                    faixa_cor = "cashout"
                    is_cashout_moment = True
                    alerta_titulo = "⚡ MOMENTO DO CASHOUT"
                    alerta_msg = f"{total_cards} cartões aplicados aos {elapsed}'. A 1 cartão do red! Avalie cashout preventivo ou hedge!"
            elif elapsed <= 45 and total_cards >= (line_val * 0.60):
                gauge_pct = 62
                faixa_cor = "cashout"
                is_cashout_moment = True
                alerta_titulo = "⚡ MOMENTO DO CASHOUT (1T Violento)"
                alerta_msg = f"{total_cards} cartões já no 1º tempo aos {elapsed}' (mais de 60% do limite de {line_val}). Clima tenso. Avalie cashout!"
            else:
                gauge_pct = max(10, round(35 - (elapsed / 90.0) * 25))
                faixa_cor = "verde"
                is_cashout_moment = False
                alerta_titulo = "🟢 Sob Controle"
                alerta_msg = f"{total_cards} cartões aos {elapsed}'. Restam {sobra_cards:.1f} cartões de folga."

            return SituationalResult(
                gauge_pct=int(gauge_pct),
                faixa_cor=faixa_cor,
                is_cashout_moment=is_cashout_moment,
                alerta_titulo=alerta_titulo,
                alerta_msg=alerta_msg,
                distancia_linha=distancia_linha,
                saldo=float(total_cards),
                estado_resultado="UNDER_CONTROLE",
                detalhe_tatico=detalhe_tatico
            )
        else:
            if total_cards >= int(line_val + 0.5):
                return SituationalResult(
                    gauge_pct=10,
                    faixa_cor="verde",
                    is_cashout_moment=False,
                    alerta_titulo="🟢 Meta de Cartões Batida (Green)",
                    alerta_msg=f"Já saíram {total_cards} cartões aos {elapsed}'. Aposta liquidada com vitória!",
                    distancia_linha=distancia_linha,
                    saldo=float(total_cards),
                    estado_resultado="GREEN_TOTAL",
                    detalhe_tatico=detalhe_tatico
                )

            faltam_cards = int(line_val + 0.5) - total_cards
            if elapsed >= 80:
                gauge_pct = 80
                faixa_cor = "vermelho"
                is_cashout_moment = True
                alerta_titulo = "⚡ MOMENTO DO CASHOUT (Falta de Cartões)"
                alerta_msg = f"Reta final aos {elapsed}'. Faltam {faltam_cards} cartões para o green. Pouco tempo disponível."
            else:
                gauge_pct = max(20, min(65, round(60 - (total_cards / line_val) * 40)))
                faixa_cor = "amarelo" if gauge_pct >= 50 else "verde"
                is_cashout_moment = False
                alerta_titulo = "🟡 Buscando Cartões" if gauge_pct >= 50 else "🟢 Ritmo Disciplinar Ativo"
                alerta_msg = f"{total_cards} cartões aplicados aos {elapsed}'. Faltam {faltam_cards} para a meta."

            return SituationalResult(
                gauge_pct=int(gauge_pct),
                faixa_cor=faixa_cor,
                is_cashout_moment=is_cashout_moment,
                alerta_titulo=alerta_titulo,
                alerta_msg=alerta_msg,
                distancia_linha=distancia_linha,
                saldo=float(total_cards),
                estado_resultado="OVER_BUSCANDO",
                detalhe_tatico=detalhe_tatico
            )


class GoalsSituationalEvaluator(SituationalEvaluatorInterface):
    """
    Avalia o estado situacional para mercados de Total de Gols (Over e Under).
    """
    def evaluate(self, bet: Dict[str, Any], match_context: Dict[str, Any]) -> SituationalResult:
        gh = 0
        ga = 0
        elapsed = 0
        total_gols = 0
        line_val = 2.5
        distancia_linha = 0.0
        gauge_pct = 20

        palpite = str(bet.get("palpite") or "")
        gh = int(match_context.get("goals_home") or 0)
        ga = int(match_context.get("goals_away") or 0)
        elapsed = int(match_context.get("elapsed") or 0)
        status = str(match_context.get("fixture_status") or "NS").strip().upper()
        is_live = bool(match_context.get("is_live") or (status in ("1H", "2H", "HT", "LIVE", "IN_PLAY", "PAUSED")))

        total_gols = gh + ga

        m = re.search(r'(\d+(?:\.\d+)?)', palpite)
        if m:
            line_val = float(m.group(1))

        is_line_integer = (int(line_val) == line_val)
        is_under = ("menos" in palpite.lower() or "under" in palpite.lower())
        detalhe_tatico = f"Placar: {gh}x{ga} ({elapsed}') • Total: {total_gols} gols"

        if not is_live and status in ("NS", "TBD", "POSTP"):
            return SituationalResult(
                gauge_pct=10,
                faixa_cor="verde",
                is_cashout_moment=False,
                alerta_titulo="⏳ Aguardando Início",
                alerta_msg="Partida ainda não iniciada. Velocímetro em standby de pré-jogo.",
                distancia_linha=line_val,
                saldo=float(total_gols),
                estado_resultado="PRE_JOGO",
                detalhe_tatico="Aposta confirmada aguardando o apito inicial."
            )

        distancia_linha = line_val - total_gols

        if is_under:
            sobra_gols = line_val - total_gols
            if total_gols > line_val:
                return SituationalResult(
                    gauge_pct=95,
                    faixa_cor="vermelho",
                    is_cashout_moment=False,
                    alerta_titulo="🔴 Limite de Gols Estourado",
                    alerta_msg=f"{total_gols} gols marcados aos {elapsed}'. Limite de {line_val} gols ultrapassado.",
                    distancia_linha=distancia_linha,
                    saldo=float(total_gols),
                    estado_resultado="RED_TOTAL",
                    detalhe_tatico=detalhe_tatico
                )

            if is_line_integer and total_gols == int(line_val):
                gauge_pct = 15 if elapsed >= 80 else 25
                return SituationalResult(
                    gauge_pct=gauge_pct,
                    faixa_cor="verde",
                    is_cashout_moment=False,
                    alerta_titulo="🟢 Reembolso Garantido (Push Protegido)",
                    alerta_msg=f"Total de {total_gols} gols aos {elapsed}'. Linha inteira empatada: stake 100% protegida se não saírem mais gols!",
                    distancia_linha=distancia_linha,
                    saldo=float(total_gols),
                    estado_resultado="PUSH_REEMBOLSO",
                    detalhe_tatico=detalhe_tatico
                )

            progresso_under = (total_gols / line_val) if line_val > 0 else 0.0
            if sobra_gols <= 0.5 or progresso_under >= 0.80:
                if elapsed >= 80:
                    gauge_pct = 35
                    faixa_cor = "verde"
                    is_cashout_moment = False
                    alerta_titulo = "🟢 Segure até o Fim (Reta Final)"
                    alerta_msg = f"{total_gols} gols aos {elapsed}'. Reta final: deixe o relógio correr para consolidar o Green!"
                elif 46 <= elapsed <= 79:
                    # Janela Nobre de Cash Out (46' a 79' - Meio de Jogo)
                    gauge_pct = 60
                    faixa_cor = "cashout"
                    is_cashout_moment = True
                    alerta_titulo = "⚡ JANELA NOBRE DE CASHOUT (A 1 Gol do Limite)"
                    alerta_msg = f"{total_gols} gols marcados aos {elapsed}' (próximo a 80% do limite de {line_val} gols). Momento ideal de agir na Cloudbet para resgate de capital!"
                else:
                    gauge_pct = 65
                    faixa_cor = "cashout"
                    is_cashout_moment = (total_gols >= 2)
                    alerta_titulo = "⚡ MOMENTO DO CASHOUT"
                    alerta_msg = f"{total_gols} gols marcados aos {elapsed}'. Apenas 1 gol de margem de segurança. Avalie cashout preventivo!"
            else:
                gauge_pct = max(10, round(35 - (elapsed / 90.0) * 25))
                faixa_cor = "verde"
                is_cashout_moment = False
                alerta_titulo = "🟢 Sob Controle"
                alerta_msg = f"{total_gols} gol(s) marcado(s) aos {elapsed}'. Restam {sobra_gols:.1f} gols de proteção até o apito final."

            return SituationalResult(
                gauge_pct=int(gauge_pct),
                faixa_cor=faixa_cor,
                is_cashout_moment=is_cashout_moment,
                alerta_titulo=alerta_titulo,
                alerta_msg=alerta_msg,
                distancia_linha=distancia_linha,
                saldo=float(total_gols),
                estado_resultado="UNDER_CONTROLE",
                detalhe_tatico=detalhe_tatico
            )
        else:
            progresso_over = (total_gols / line_val) if line_val > 0 else 0.0

            if is_line_integer and total_gols == int(line_val):
                return SituationalResult(
                    gauge_pct=15,
                    faixa_cor="verde",
                    is_cashout_moment=False,
                    alerta_titulo="🟢 Reembolso Garantido (Push Protegido)",
                    alerta_msg=f"Total de {total_gols} gols aos {elapsed}'. Linha inteira empatada: stake 100% protegida com reembolso se não saírem mais gols!",
                    distancia_linha=distancia_linha,
                    saldo=float(total_gols),
                    estado_resultado="PUSH_REEMBOLSO",
                    detalhe_tatico=detalhe_tatico
                )

            if (is_line_integer and total_gols > int(line_val)) or (not is_line_integer and total_gols >= int(line_val + 0.5)):
                return SituationalResult(
                    gauge_pct=5,
                    faixa_cor="verde",
                    is_cashout_moment=False,
                    alerta_titulo="🟢 Meta Batida (Green Total)",
                    alerta_msg=f"Já saíram {total_gols} gols aos {elapsed}'! Aposta liquidada com vitória!",
                    distancia_linha=distancia_linha,
                    saldo=float(total_gols),
                    estado_resultado="GREEN_TOTAL",
                    detalhe_tatico=detalhe_tatico
                )

            if total_gols == (int(line_val - 1) if is_line_integer else int(line_val)):
                if elapsed <= 45:
                    gauge_pct = 20
                    faixa_cor = "verde"
                    is_cashout_moment = False
                    alerta_titulo = "🟢 Ritmo Ofensivo (Gol Precoce)"
                    alerta_msg = f"Já saiu {total_gols} gol aos {elapsed}'! Ritmo acelerado com amplo tempo restante para buscar a meta."
                elif elapsed <= 75:
                    gauge_pct = 35
                    faixa_cor = "verde"
                    is_cashout_moment = False
                    alerta_titulo = "🟢 A 1 Gol da Meta"
                    alerta_msg = f"Partida com {total_gols} gols aos {elapsed}'. Falta apenas 1 gol para o green!"
                else:
                    gauge_pct = 60
                    faixa_cor = "cashout"
                    is_cashout_moment = True
                    alerta_titulo = "⚡ MOMENTO DO CASHOUT"
                    alerta_msg = f"Reta final de jogo ({elapsed}'). Falta 1 gol para a meta. Avalie cashout para travar retorno parcial!"
            else:
                if elapsed >= 70:
                    gauge_pct = 85
                if 46 <= elapsed <= 79 and progresso_over < 0.75:
                    # Janela Nobre de Cash Out (46' a 79' - Meio de Jogo)
                    gauge_pct = 85 if elapsed >= 65 else 70
                    faixa_cor = "vermelho" if elapsed >= 65 else "cashout"
                    is_cashout_moment = True
                    alerta_titulo = "⚡ JANELA NOBRE DE CASHOUT (Ritmo Lento)"
                    alerta_msg = f"Apenas {total_gols} gol(s) aos {elapsed}' (abaixo de 75% do Over {line_val}). Momento ideal de agir na Cloudbet para resgate de capital com boas cotações!"
                elif elapsed >= 80:
                    gauge_pct = 90
                    faixa_cor = "vermelho"
                    is_cashout_moment = True
                    alerta_titulo = "⚡ MOMENTO DO CASHOUT (Ritmo Lento)"
                    alerta_titulo = "⚡ MOMENTO DO CASHOUT (Reta Final)"
                    alerta_msg = f"Apenas {total_gols} gols aos {elapsed}'. Tempo escasseando para cobrir a linha Over {line_val}."
                else:
                    gauge_pct = max(25, min(70, round(65 - (total_gols / line_val) * 40)))
                    gauge_pct = max(25, min(70, round(65 - progresso_over * 40)))
                    faixa_cor = "amarelo" if gauge_pct >= 50 else "verde"
                    is_cashout_moment = False
                    alerta_titulo = "🟡 Buscando Gols" if gauge_pct >= 50 else "🟢 Jogo Aberto"
                    alerta_msg = f"Partida com {total_gols} gols aos {elapsed}'. Buscando meta Over {line_val}."

            return SituationalResult(
                gauge_pct=int(gauge_pct),
                faixa_cor=faixa_cor,
                is_cashout_moment=is_cashout_moment,
                alerta_titulo=alerta_titulo,
                alerta_msg=alerta_msg,
                distancia_linha=distancia_linha,
                saldo=float(total_gols),
                estado_resultado="OVER_BUSCANDO",
                detalhe_tatico=detalhe_tatico
            )


class SituationalEvaluatorFactory:
    """
    Abstract Factory que instancia o avaliador adequado pelo mercado.
    """
    _evaluators = {}

    @classmethod
    def create(cls, mercado: str = "", palpite: str = "") -> SituationalEvaluatorInterface:
        m_lower = str(mercado or "").lower()
        p_lower = str(palpite or "").lower()

        if "cart" in m_lower or "cart" in p_lower:
            if "cards" not in cls._evaluators:
                cls._evaluators["cards"] = CardsSituationalEvaluator()
            return cls._evaluators["cards"]

        if "gol" in m_lower or "total" in m_lower or "mais de" in p_lower or "menos de" in p_lower:
            if "goals" not in cls._evaluators:
                cls._evaluators["goals"] = GoalsSituationalEvaluator()
            return cls._evaluators["goals"]

        if "asian_handicap" not in cls._evaluators:
            cls._evaluators["asian_handicap"] = AsianHandicapSituationalEvaluator()
        return cls._evaluators["asian_handicap"]


class LiveSituationalBuilder:
    """
    Builder que orquestra os dados da aposta e contexto de partida para o Sentinela e APIs.
    """
    def __init__(self):
        self.bet_row: Dict[str, Any] = {}
        self.custom_context: Dict[str, Any] = {}

    @classmethod
    def create(cls) -> "LiveSituationalBuilder":
        return cls()

    def with_bet_row(self, row: Dict[str, Any]) -> "LiveSituationalBuilder":
        self.bet_row = row
        return self

    def with_context(self, context: Dict[str, Any]) -> "LiveSituationalBuilder":
        self.custom_context.update(context)
        return self

    def build(self) -> Dict[str, Any]:
        row = self.bet_row
        gh = int(row.get("goals_home") or 0)
        ga = int(row.get("goals_away") or 0)
        elapsed = int(row.get("elapsed") or 0)
        status = str(row.get("fixture_status") or "NS").strip().upper()
        is_live = bool(status in ("1H", "2H", "HT", "LIVE", "IN_PLAY", "PAUSED"))

        match_context = {
            "goals_home": gh,
            "goals_away": ga,
            "elapsed": elapsed,
            "fixture_status": status,
            "is_live": is_live,
            "yellow_cards_home": int(row.get("yellow_cards_home") or 0),
            "yellow_cards_away": int(row.get("yellow_cards_away") or 0),
            "red_cards_home": int(row.get("red_cards_home") or 0),
            "red_cards_away": int(row.get("red_cards_away") or 0),
            "corners_home": int(row.get("corners_home") or 0),
            "corners_away": int(row.get("corners_away") or 0),
            "shots_home": int(row.get("shots_home") or 0),
            "shots_away": int(row.get("shots_away") or 0),
            "home_team": str(row.get("time_casa") or row.get("home_team") or "Mandante"),
            "away_team": str(row.get("time_fora") or row.get("away_team") or "Visitante"),
        }
        match_context.update(self.custom_context)

        mercado = str(row.get("mercado") or "Handicap Asiático")
        palpite = str(row.get("palpite") or "")

        evaluator = SituationalEvaluatorFactory.create(mercado, palpite)
        result = evaluator.evaluate(row, match_context)

        return {
            "aposta_id": int(row.get("id") or 0),
            "fixture_id": int(row.get("fixture_id") or 0),
            "mercado": mercado,
            "palpite": palpite,
            "gauge_pct": result.gauge_pct,
            "faixa_cor": result.faixa_cor,
            "is_cashout_moment": result.is_cashout_moment,
            "alerta_titulo": result.alerta_titulo,
            "alerta_msg": result.alerta_msg,
            "detalhe_tatico": result.detalhe_tatico,
            "estado_resultado": result.estado_resultado,
            "distancia_linha": result.distancia_linha,
            "saldo": result.saldo
        }
