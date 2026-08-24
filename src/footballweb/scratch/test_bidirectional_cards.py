import os
import sys

# Adiciona o diretório dos scripts ao sys.path
sys.path.insert(0, '/root/datalake-air-flow-delta/scripts')

from criar_apostas_cartoes_diario import extract_cards_under_suggestion, calculate_poisson_under_cdf

def test_bidirectional_card_rules():
    print("🧪 Executando testes unitários do Módulo Bidirecional de Cartões...\n")

    # Cenário 1: Jogo Baixo xC (Under 4.5)
    pred_under_low = "🛡️ Estratégia Under (Expectativa: 3.10 cartões). Sugestões de valor: 1ª Opção: Under 4.5 (78.5% | Odd Justa: 1.27) | 2ª Opção: Under 5.5."
    line, palpite, status, odd_j, prob, _, xc = extract_cards_under_suggestion(pred_under_low)
    print(f"Cenário 1 (xC=3.10): Palpite='{palpite}', Status={status}, Prob={prob}%, OddJusta={odd_j}")
    assert palpite == "Menos de 4.5 Cartões", f"Esperado Menos de 4.5 Cartões, obtido {palpite}"
    assert status == "APROVADO", f"Esperado APROVADO, obtido {status}"

    # Cenário 2: Jogo Alto xC (Over 4.5)
    pred_over_high = "⚡ Estratégia Over (Expectativa: 5.90 cartões | Árbitro: 5.40 cartões/jogo). Sugestões de valor: 1ª Opção: Over 4.5 (69.2% | Odd Justa: 1.45) | 2ª Opção: Over 3.5."
    line, palpite, status, odd_j, prob, _, xc = extract_cards_under_suggestion(pred_over_high)
    print(f"Cenário 2 (xC=5.90): Palpite='{palpite}', Status={status}, Prob={prob}%, OddJusta={odd_j}")
    assert palpite == "Mais de 4.5 Cartões", f"Esperado Mais de 4.5 Cartões, obtido {palpite}"
    assert status == "APROVADO", f"Esperado APROVADO, obtido {status}"
    assert prob >= 60.0, f"Esperado prob >= 60.0%, obtido {prob}%"

    # Cenário 3: Jogo Muito Alto xC (Over 5.5)
    pred_over_very_high = "⚡ Estratégia Over (Expectativa: 6.60 cartões | Árbitro: 5.80 cartões/jogo). Sugestões de valor: 1ª Opção: Over 5.5 (64.8% | Odd Justa: 1.54) | 2ª Opção: Over 4.5."
    line, palpite, status, odd_j, prob, _, xc = extract_cards_under_suggestion(pred_over_very_high)
    print(f"Cenário 3 (xC=6.60): Palpite='{palpite}', Status={status}, Prob={prob}%, OddJusta={odd_j}")
    assert palpite == "Mais de 5.5 Cartões", f"Esperado Mais de 5.5 Cartões, obtido {palpite}"
    assert status == "APROVADO", f"Esperado APROVADO, obtido {status}"
    assert prob >= 60.0, f"Esperado prob >= 60.0%, obtido {prob}%"

    # Cenário 4: Jogo Neutro / Probabilidade Over Insuficiente (< 60%)
    # xC = 5.0 -> P(Over 4.5) = 100 - P(Under 4.5) = 100 - 44.0 = 56.0% (< 60.0%)
    pred_neutral = "⚡ Estratégia Over (Expectativa: 5.00 cartões | Árbitro: 4.80 cartões/jogo). Sugestões de valor: 1ª Opção: Over 4.5 (56.0% | Odd Justa: 1.78)."
    line, palpite, status, odd_j, prob, _, xc = extract_cards_under_suggestion(pred_neutral)
    print(f"Cenário 4 (xC=5.00, P=56%): Palpite='{palpite}', Status={status}, Prob={prob}% (Reprovado por limiar de 60%)")
    assert status == "NO_BET", f"Esperado NO_BET para prob < 60.0%, obtido {status}"

    print("\n✅ TODOS OS TESTES PASSARAM COM SUCESSO!")

if __name__ == "__main__":
    test_bidirectional_card_rules()
