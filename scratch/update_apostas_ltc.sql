UPDATE apostas
SET 
  moeda_codigo = 'LTC',
  cotacao_brl_snapshot = 298.8000,
  valor_aposta = 0.33467202,
  ganhos_potenciais = CASE 
    WHEN status = 'Perdida' THEN 0.00000000
    WHEN status = 'Meio Perdida' THEN ROUND(0.33467202 * 0.5, 8)
    WHEN status = 'ANULADA' THEN 0.33467202
    ELSE ROUND(0.33467202 * odd, 8)
  END,
  updated_at = NOW()
WHERE usuario_id = 558 
  AND DATE(data_hora_jogo) IN ('2026-10-18', '2026-10-19');
