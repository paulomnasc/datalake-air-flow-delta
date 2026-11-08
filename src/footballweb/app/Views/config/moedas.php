<div class="container-fluid py-4 px-lg-5" style="background-color: #0d1117; min-height: 85vh; color: #f0f6fc;">
  
  <!-- Header da Página -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3 border-bottom border-secondary pb-3">
    <div>
      <h2 class="h3 fw-bold mb-1 d-flex align-items-center gap-2" style="font-family: 'Outfit', sans-serif;">
        <i class="bi bi-coin text-warning"></i>
        <span>Configuração de Criptomoedas & Taxas Operacionais</span>
      </h2>
      <p class="text-white-50 mb-0 small">
        Defina a moeda padrão do sistema para criação de apostas e configure taxas de movimentação (saque, rede e conversão spot) para cálculo líquido em Reais (BRL).
      </p>
    </div>
    
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-outline-info btn-sm px-3 d-flex align-items-center gap-2" onclick="openModalNovaMoeda()">
        <i class="bi bi-plus-circle"></i> Cadastrar Moeda
      </button>
      <a href="<?= base_url('apostas/relatorio-eficiencia') ?>" class="btn btn-outline-secondary btn-sm px-3 d-flex align-items-center gap-2">
        <i class="bi bi-graph-up-arrow"></i> Relatório de Eficiência
      </a>
    </div>
  </div>

  <!-- Alerta de Moeda Padrão Ativa -->
  <div class="alert alert-dark border border-success d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 shadow-sm" style="background: rgba(16, 185, 129, 0.08);">
    <div class="d-flex align-items-center gap-3">
      <div class="rounded-circle bg-success text-dark d-flex align-items-center justify-content-center" style="width: 44px; height: 44px; font-size: 1.4rem; font-weight: 800;">
        <?= esc($moedaDefault->simbolo ?? 'R$') ?>
      </div>
      <div>
        <div class="text-success fw-bold text-uppercase small" style="letter-spacing: 0.5px;">Moeda Padrão Ativa (Default do Sistema)</div>
        <div class="fs-5 fw-bold text-white">
          <?= esc($moedaDefault->nome ?? 'Real Brasileiro') ?> (<?= esc($moedaDefault->codigo ?? 'BRL') ?>)
          <span class="badge bg-success text-dark ms-2">ATIVA P/ CRIAR APOSTAS</span>
        </div>
        <div class="small text-white-50 mt-1">
          Cotação: <strong class="text-white">1 <?= esc($moedaDefault->codigo) ?> = R$ <?= number_format((float)$moedaDefault->cotacao_brl, 2, ',', '.') ?></strong>
          <?php if ($moedaDefault->tipo === 'CRYPTO'): ?>
            • Taxa Saque Stake: <strong class="text-warning"><?= number_format((float)$moedaDefault->taxa_saque_fixa, 8, ',', '.') ?> <?= esc($moedaDefault->codigo) ?></strong>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="text-end">
      <div class="text-white-50 small">Todas as novas apostas são emitidas em:</div>
      <div class="fw-bold fs-6 text-info"><?= esc($moedaDefault->nome) ?> (<?= esc($moedaDefault->simbolo) ?>)</div>
    </div>
  </div>

  <!-- Tabela de Moedas e Criptomoedas Cadastradas -->
  <div class="card bg-dark border-secondary shadow-sm mb-4">
    <div class="card-header bg-transparent border-secondary py-3 d-flex align-items-center justify-content-between">
      <h5 class="card-title mb-0 fw-bold text-white d-flex align-items-center gap-2">
        <i class="bi bi-wallet2 text-primary"></i> Moedas Cadastradas no Sistema
      </h5>
      <span class="badge bg-secondary text-white"><?= count($moedas) ?> moedas</span>
    </div>
    <div class="card-body p-0 table-responsive">
      <table class="table table-dark table-hover align-middle mb-0" style="font-size: 0.92rem;">
        <thead class="border-secondary text-white-50" style="background-color: rgba(255, 255, 255, 0.03);">
          <tr>
            <th class="ps-3">Moeda</th>
            <th>Tipo</th>
            <th>Cotação em Reais (BRL)</th>
            <th>Saque Mínimo</th>
            <th>Taxa de Saque (Stake)</th>
            <th>Taxa Rede Estimada</th>
            <th>Taxa Spot (%)</th>
            <th class="text-center">Moeda Padrão</th>
            <th class="pe-3 text-end">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($moedas as $m): ?>
            <?php 
              $isDef = (bool)$m->is_default;
              $isCrypto = ($m->tipo === 'CRYPTO');
            ?>
            <tr class="<?= $isDef ? 'border-start border-4 border-success bg-opacity-10 bg-success' : '' ?>">
              <td class="ps-3">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge bg-primary text-white fw-bold px-2 py-1 fs-6" style="min-width: 45px; text-align: center;">
                    <?= esc($m->simbolo) ?>
                  </span>
                  <div>
                    <div class="fw-bold text-white"><?= esc($m->nome) ?></div>
                    <div class="small text-white-50"><?= esc($m->codigo) ?> • <?= (int)$m->casas_decimais ?> decimais</div>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge <?= $isCrypto ? 'bg-warning text-dark' : 'bg-info text-dark' ?> fw-bold">
                  <?= $isCrypto ? '🪙 CRIPTO' : '💵 FIDUCIÁRIA' ?>
                </span>
              </td>
              <td>
                <div class="fw-bold text-white">R$ <?= number_format((float)$m->cotacao_brl, 2, ',', '.') ?></div>
                <div class="small text-white-50">1 <?= esc($m->codigo) ?></div>
              </td>
              <td>
                <?php if ($isCrypto && (float)$m->saque_minimo > 0): ?>
                  <span class="text-white"><?= number_format((float)$m->saque_minimo, (int)$m->casas_decimais, ',', '.') ?> <?= esc($m->codigo) ?></span>
                <?php else: ?>
                  <span class="text-white-50">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isCrypto && (float)$m->taxa_saque_fixa > 0): ?>
                  <span class="text-warning fw-bold"><?= number_format((float)$m->taxa_saque_fixa, (int)$m->casas_decimais, ',', '.') ?> <?= esc($m->codigo) ?></span>
                  <div class="small text-white-50">~ R$ <?= number_format((float)$m->taxa_saque_fixa * (float)$m->cotacao_brl, 2, ',', '.') ?></div>
                <?php else: ?>
                  <span class="text-white-50">Isento</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($isCrypto && (float)$m->taxa_rede_estimada > 0): ?>
                  <span class="text-white"><?= number_format((float)$m->taxa_rede_estimada, (int)$m->casas_decimais, ',', '.') ?> <?= esc($m->codigo) ?></span>
                  <div class="small text-white-50">~ R$ <?= number_format((float)$m->taxa_rede_estimada * (float)$m->cotacao_brl, 2, ',', '.') ?></div>
                <?php else: ?>
                  <span class="text-white-50">0,00</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge bg-secondary text-white"><?= number_format((float)$m->taxa_spot_percentual, 2, ',', '.') ?>%</span>
              </td>
              <td class="text-center">
                <?php if ($isDef): ?>
                  <span class="badge bg-success text-dark px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i> PADRÃO ATUAL</span>
                <?php else: ?>
                  <button class="btn btn-sm btn-outline-success py-1 px-2" onclick="setMoedaPadrao('<?= esc($m->codigo) ?>')">
                    <i class="bi bi-arrow-repeat me-1"></i> Tornar Padrão
                  </button>
                <?php endif; ?>
              </td>
              <td class="pe-3 text-end">
                <button class="btn btn-sm btn-outline-secondary py-1 px-2" onclick='openModalEditarMoeda(<?= json_encode($m) ?>)'>
                  <i class="bi bi-pencil-square"></i> Editar
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Rota Segura de Compliance & Liquidação Financeira -->
  <div class="card bg-dark border-secondary shadow-sm">
    <div class="card-header bg-transparent border-secondary py-3">
      <h5 class="card-title mb-0 fw-bold text-white d-flex align-items-center gap-2">
        <i class="bi bi-shield-check text-info"></i> Rota Segura de Compliance (VM Hetzner ➔ Carteira Não-Custodial ➔ Binance ➔ PIX)
      </h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <div class="p-3 rounded border border-secondary h-100" style="background: rgba(255, 255, 255, 0.02);">
            <div class="fw-bold text-warning mb-1"><i class="bi bi-1-circle me-1"></i> Stake.com (Alemanha)</div>
            <div class="small text-white-50">Operação automatizada de apostas esportivas (+EV). Saques efetuados preferencialmente em <strong>Litecoin (LTC)</strong> com taxa de apenas 0.000138 LTC (~ R$ 0,06).</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="p-3 rounded border border-secondary h-100" style="background: rgba(255, 255, 255, 0.02);">
            <div class="fw-bold text-info mb-1"><i class="bi bi-2-circle me-1"></i> Carteira Pessoal (Exodus/Trust)</div>
            <div class="small text-white-50">Camada de isolamento e autocustódia. Garante que os depósitos na corretora provenham de um endereço particular de pessoa física, blindando sua conta bancária.</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="p-3 rounded border border-secondary h-100" style="background: rgba(255, 255, 255, 0.02);">
            <div class="fw-bold text-primary mb-1"><i class="bi bi-3-circle me-1"></i> Binance Brasil (Spot)</div>
            <div class="small text-white-50">Conversão de LTC para Reais (BRL) via ordem spot com taxa mínima de 0,10%. Suporte a alta liquidez cambial imediata.</div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="p-3 rounded border border-secondary h-100" style="background: rgba(255, 255, 255, 0.02);">
            <div class="fw-bold text-success mb-1"><i class="bi bi-4-circle me-1"></i> Saque PIX (Conta Bancária)</div>
            <div class="small text-white-50">Crédito imediato em segundos na sua conta corrente brasileira (Nubank, Itaú, Inter, etc.) sem impostos retidos na fonte.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Modal Cadastrar / Editar Moeda -->
<div class="modal fade" id="modalMoeda" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content bg-dark border-secondary text-white">
      <div class="modal-header border-secondary">
        <h5 class="modal-title fw-bold" id="modalMoedaTitulo">Cadastrar / Editar Moeda</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="formMoeda" onsubmit="salvarMoeda(event)">
        <input type="hidden" name="id" id="moedaId" value="">
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small text-white-50">Código ISO (ex: LTC, BTC, BRL)*</label>
              <input type="text" class="form-control bg-secondary text-white border-0" name="codigo" id="moedaCodigo" required maxlength="10">
            </div>
            <div class="col-md-5">
              <label class="form-label small text-white-50">Nome Completo (ex: Litecoin)*</label>
              <input type="text" class="form-control bg-secondary text-white border-0" name="nome" id="moedaNome" required maxlength="50">
            </div>
            <div class="col-md-3">
              <label class="form-label small text-white-50">Símbolo (ex: Ł, ₿, R$)*</label>
              <input type="text" class="form-control bg-secondary text-white border-0" name="simbolo" id="moedaSimbolo" required maxlength="10">
            </div>

            <div class="col-md-4">
              <label class="form-label small text-white-50">Tipo de Moeda*</label>
              <select class="form-select bg-secondary text-white border-0" name="tipo" id="moedaTipo">
                <option value="CRYPTO">🪙 Criptomoeda</option>
                <option value="FIAT">💵 Fiduciária (Fiat)</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small text-white-50">Casas Decimais (ex: 8 p/ Cripto, 2 p/ Fiat)*</label>
              <input type="number" class="form-control bg-secondary text-white border-0" name="casas_decimais" id="moedaCasasDecimais" min="0" max="8" value="8">
            </div>
            <div class="col-md-4">
              <label class="form-label small text-white-50">Cotação em Reais (1 Unidade = R$)*</label>
              <input type="number" step="0.0001" class="form-control bg-secondary text-white border-0" name="cotacao_brl" id="moedaCotacaoBrl" required>
            </div>

            <div class="col-md-4">
              <label class="form-label small text-white-50">Saque Mínimo da Stake</label>
              <input type="number" step="0.00000001" class="form-control bg-secondary text-white border-0" name="saque_minimo" id="moedaSaqueMinimo" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label small text-white-50">Taxa Saque Fixa da Stake (Withdrawal Fee)</label>
              <input type="number" step="0.00000001" class="form-control bg-secondary text-white border-0" name="taxa_saque_fixa" id="moedaTaxaSaqueFixa" value="0">
            </div>
            <div class="col-md-4">
              <label class="form-label small text-white-50">Taxa Rede Estimada (Carteira ➔ Binance)</label>
              <input type="number" step="0.00000001" class="form-control bg-secondary text-white border-0" name="taxa_rede_estimada" id="moedaTaxaRedeEstimada" value="0">
            </div>

            <div class="col-md-6">
              <label class="form-label small text-white-50">Taxa Spot na Binance (%)</label>
              <input type="number" step="0.01" class="form-control bg-secondary text-white border-0" name="taxa_spot_percentual" id="moedaTaxaSpot" value="0.10">
            </div>
            <div class="col-md-6">
              <label class="form-label small text-white-50">Opções</label>
              <div class="d-flex align-items-center gap-3 mt-2">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_default" id="moedaIsDefault" value="1">
                  <label class="form-check-label small" for="moedaIsDefault">Definir como Padrão</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="ativo" id="moedaAtivo" value="1" checked>
                  <label class="form-check-label small" for="moedaAtivo">Habilitada</label>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer border-secondary">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success" id="btnSalvarMoeda">Salvar Configuração</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let modalMoedaInstance = null;

function openModalNovaMoeda() {
  document.getElementById('formMoeda').reset();
  document.getElementById('moedaId').value = '';
  document.getElementById('modalMoedaTitulo').textContent = 'Cadastrar Nova Moeda / Cripto';
  document.getElementById('moedaIsDefault').checked = false;
  document.getElementById('moedaAtivo').checked = true;
  document.getElementById('moedaCasasDecimais').value = 8;
  document.getElementById('moedaTaxaSpot').value = 0.10;

  if (!modalMoedaInstance) {
    modalMoedaInstance = new bootstrap.Modal(document.getElementById('modalMoeda'));
  }
  modalMoedaInstance.show();
}

function openModalEditarMoeda(m) {
  document.getElementById('moedaId').value = m.id;
  document.getElementById('moedaCodigo').value = m.codigo;
  document.getElementById('moedaNome').value = m.nome;
  document.getElementById('moedaSimbolo').value = m.simbolo;
  document.getElementById('moedaTipo').value = m.tipo;
  document.getElementById('moedaCasasDecimais').value = m.casas_decimais;
  document.getElementById('moedaCotacaoBrl').value = parseFloat(m.cotacao_brl);
  document.getElementById('moedaSaqueMinimo').value = parseFloat(m.saque_minimo);
  document.getElementById('moedaTaxaSaqueFixa').value = parseFloat(m.taxa_saque_fixa);
  document.getElementById('moedaTaxaRedeEstimada').value = parseFloat(m.taxa_rede_estimada);
  document.getElementById('moedaTaxaSpot').value = parseFloat(m.taxa_spot_percentual);
  document.getElementById('moedaIsDefault').checked = (parseInt(m.is_default) === 1);
  document.getElementById('moedaAtivo').checked = (parseInt(m.ativo) === 1);

  document.getElementById('modalMoedaTitulo').textContent = `Editar Moeda: ${m.nome} (${m.codigo})`;

  if (!modalMoedaInstance) {
    modalMoedaInstance = new bootstrap.Modal(document.getElementById('modalMoeda'));
  }
  modalMoedaInstance.show();
}

function salvarMoeda(e) {
  e.preventDefault();
  const form = document.getElementById('formMoeda');
  const formData = new FormData(form);

  const btn = document.getElementById('btnSalvarMoeda');
  btn.disabled = true;
  btn.textContent = 'Salvando...';

  fetch('<?= base_url('moedas/salvar') ?>', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.textContent = 'Salvar Configuração';
    if (data.success) {
      if (modalMoedaInstance) modalMoedaInstance.hide();
      location.reload();
    } else {
      alert(data.message || 'Erro ao salvar moeda.');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.textContent = 'Salvar Configuração';
    alert('Erro de comunicação com o servidor.');
  });
}

function setMoedaPadrao(codigo) {
  if (!confirm(`Deseja definir ${codigo} como a Moeda Padrão para criação de apostas?`)) {
    return;
  }

  const formData = new FormData();
  formData.append('codigo', codigo);

  fetch('<?= base_url('moedas/set-default') ?>', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      location.reload();
    } else {
      alert(data.message || 'Erro ao alterar moeda padrão.');
    }
  })
  .catch(() => alert('Erro de comunicação ao definir moeda padrão.'));
}
</script>
