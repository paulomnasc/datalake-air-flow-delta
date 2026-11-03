<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\SistemaModel;
use App\Models\OrdemServicoModel;
use App\Models\DemandaModel;

class StatusReportController extends BaseController
{
    protected $sistemaModel;
    protected $ordemServicoModel;
    protected $demandaModel;

    public function __construct()
    {
        $this->sistemaModel = new SistemaModel();
        $this->ordemServicoModel = new OrdemServicoModel();
        $this->demandaModel = new DemandaModel();
    }

    /**
     * Retorna a lista de sistemas para preenchimento do seletor
     */
    public function getSistemas(): ResponseInterface
    {
        $sistemas = $this->sistemaModel->select('id, nome, sigla')->orderBy('nome', 'ASC')->findAll();
        return $this->response->setJSON([
            'status' => 'success',
            'data' => $sistemas
        ]);
    }

    /**
     * Gera o Status Report consolidado utilizando a IA Gemini
     */
    public function gerar(): ResponseInterface
    {
        try {
            $idSistema = $this->request->getPost('id_sistema') ?? $this->request->getGet('id_sistema') ?? 'all';

            // 1. Obter sistemas selecionados
            if ($idSistema !== 'all' && is_numeric($idSistema)) {
                $sistemas = $this->sistemaModel->where('id', (int)$idSistema)->findAll();
            } else {
                $sistemas = $this->sistemaModel->orderBy('nome', 'ASC')->findAll();
            }

            if (empty($sistemas)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'mensagem' => 'Nenhum sistema de informação encontrado para geração do relatório.'
                ]);
            }

            // 2. Coletar Ordens de Serviço e Demandas relacionadas
            $db = \Config\Database::connect();
            $dossieSistemas = [];

            foreach ($sistemas as $sis) {
                $sisId = $sis->id;

                // Buscar Ordens de Serviço do Sistema (excluindo Canceladas)
                $osList = $this->ordemServicoModel
                    ->where('id_sistema', $sisId)
                    ->whereNotIn('LOWER(status)', ['cancelada', 'cancelado', 'canceladas', 'cancelados'])
                    ->orderBy('id', 'ASC')
                    ->findAll();

                // Buscar Demandas do Sistema (excluindo Canceladas)
                $demandasList = $this->demandaModel
                    ->where('id_sistema', $sisId)
                    ->whereNotIn('LOWER(status)', ['cancelada', 'cancelado', 'canceladas', 'cancelados'])
                    ->orderBy('id', 'ASC')
                    ->findAll();

                // Mapear demandas por OS
                $osFormatadas = [];
                foreach ($osList as $os) {
                    $demandasDaOs = [];
                    foreach ($demandasList as $dem) {
                        if ((int)$dem->id_ordem_servico === (int)$os->id) {
                            $demandasDaOs[] = [
                                'id' => $dem->id,
                                'titulo_completo' => $dem->titulo,
                                'descricao_completa' => $dem->descricao,
                                'status' => $dem->status,
                                'sistema_critico' => (bool)$dem->sistema_critico,
                                'atualizado_em' => $dem->atualizado_em ?? $dem->criado_em
                            ];
                        }
                    }

                    $osFormatadas[] = [
                        'id' => $os->id,
                        'nup_sei' => $os->nup_sei,
                        'status' => $os->status,
                        'horas_alocadas' => $os->Horas_Alocadas,
                        'descricao' => $os->descricao,
                        'data_emissao' => $os->Data_Emissao,
                        'data_aceite' => $os->Data_Aceite,
                        'nota_empenho' => $os->nota_empenho,
                        'demandas_relacionadas' => $demandasDaOs
                    ];
                }

                // Demandas que por ventura não tenham OS vinculada diretamente
                $demandasSemOs = [];
                foreach ($demandasList as $dem) {
                    if (empty($dem->id_ordem_servico)) {
                        $demandasSemOs[] = [
                            'id' => $dem->id,
                            'titulo_completo' => $dem->titulo,
                            'descricao_completa' => $dem->descricao,
                            'status' => $dem->status,
                            'sistema_critico' => (bool)$dem->sistema_critico,
                            'atualizado_em' => $dem->atualizado_em ?? $dem->criado_em
                        ];
                    }
                }

                $dossieSistemas[] = [
                    'sistema_id' => $sis->id,
                    'nome' => $sis->nome,
                    'sigla' => $sis->sigla,
                    'descricao' => $sis->descricao,
                    'total_os' => count($osList),
                    'total_demandas' => count($demandasList),
                    'ordens_servico' => $osFormatadas,
                    'demandas_gerais_sem_os' => $demandasSemOs
                ];
            }

            // 3. Obter chave da API Gemini
            $apiKey = $this->getGeminiApiKey();

            // 4. Executar chamada à IA Gemini ou Fallback Estruturado
            $dataHora = date('d/m/Y H:i');
            if (!empty($apiKey)) {
                $resultadoIa = $this->consultarGemini($dossieSistemas, $apiKey, $dataHora);
            } else {
                $resultadoIa = $this->gerarRelatorioFallback($dossieSistemas, $dataHora, 'Chave de API Gemini não configurada no ambiente.');
            }

            return $this->response->setJSON([
                'status' => 'success',
                'gerado_em' => $dataHora,
                'total_sistemas' => count($dossieSistemas),
                'report' => $resultadoIa,
                'nome_sistema' => ($idSistema !== 'all' && !empty($sistemas)) ? $sistemas[0]->nome : 'Todos os Sistemas'
            ]);

        } catch (\Throwable $e) {
            log_message('error', '[StatusReportController] Erro ao gerar relatório: ' . $e->getMessage());
            return $this->response->setJSON([
                'status' => 'error',
                'mensagem' => 'Erro interno ao processar o Status Report: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Endpoint para download direto em formato Word (.docx)
     */
    public function exportarDocx(): ResponseInterface
    {
        $conteudo = $this->request->getPost('conteudo') ?? '';
        $nomeSistema = $this->request->getPost('nome_sistema') ?? 'Sistemas';

        if (empty(trim($conteudo))) {
            $conteudo = "# STATUS REPORT DE FISCALIZAÇÃO\nNenhum conteúdo informado para exportação.\nGerado em: " . date('d/m/Y H:i:s');
        }

        $siglaLimpa = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nomeSistema);
        $siglaLimpa = substr($siglaLimpa, 0, 30);
        $nomeArquivo = 'Status_Report_' . $siglaLimpa . '_' . date('Ymd_His') . '.docx';

        $tempInput = tempnam(sys_get_temp_dir(), 'rep_in_') . '.txt';
        $tempOutput = tempnam(sys_get_temp_dir(), 'rep_out_') . '.docx';

        file_put_contents($tempInput, $conteudo);

        $scriptPath = realpath(APPPATH . '../scripts/docx_exporter.py');
        if (!$scriptPath || !file_exists($scriptPath)) {
            $scriptPath = '/var/www/html/scripts/docx_exporter.py';
        }

        $cmd = "python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($tempInput) . " " . escapeshellarg($tempOutput) . " 2>&1";
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tempOutput)) {
            log_message('error', '[StatusReportController] Falha ao gerar DOCX: ' . implode("\n", $output));
            @unlink($tempInput);
            @unlink($tempOutput);
            return $this->response->setStatusCode(500, 'Falha ao compilar documento DOCX');
        }

        $docxBytes = file_get_contents($tempOutput);
        @unlink($tempInput);
        @unlink($tempOutput);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nomeArquivo . '"')
            ->setBody($docxBytes);
    }

    /**
     * Endpoint para download direto em formato .txt
     */
    public function exportarTxt(): ResponseInterface
    {
        $conteudo = $this->request->getPost('conteudo') ?? '';
        $nomeArquivo = 'Status_Report_Fiscalizacao_' . date('Ymd_His') . '.txt';

        if (empty($conteudo)) {
            $conteudo = "Nenhum conteúdo informado para exportação.\nGerado em: " . date('d/m/Y H:i:s');
        }

        return $this->response
            ->setHeader('Content-Type', 'text/plain; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $nomeArquivo . '"')
            ->setBody($conteudo);
    }

    /**
     * Realiza a consulta HTTP à API Gemini REST
     */
    protected function consultarGemini(array $dossie, string $apiKey, string $dataHora): string
    {
        $model = 'gemini-flash-lite-latest';
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $resumoJson = json_encode($dossie, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = <<<EOT
Você é o Especialista em Fiscalização Técnica de Contratos do FiscalWeb.
Sua missão é gerar um STATUS REPORT SIMPLIFICADO E EXCLUSIVO contendo APENAS E TÃO SOMENTE as informações solicitadas abaixo, eliminando qualquer introdução, sumário global, matriz de riscos, horas alocadas, datas e recomendações.

Data e Hora de Referência: {$dataHora}

DADOS DA BASE DO FISCALWEB:
{$resumoJson}

REGRAS E DIRETRIZES MANDATÓRIAS (OBRIGATÓRIO):
1. O relatório deve conter EXCLUSIVAMENTE os seguintes 7 campos:
   - Sistema de Informação
   - NUP SEI
   - Nota de Empenho
   - Status da OS
   - Demandas Relacionadas na OS
   - Título Completo (demanda)
   - Status Atual Demanda
   ELIMINE qualquer outra informação ou seção (não incluir sumário global, não incluir matriz de risco, não incluir horas, não incluir recomendações, não incluir introduções).

2. PRESERVAÇÃO INTEGRAL DE NOMENCLATURAS:
   - NUNCA abrevie, resuma, corte ou trunque à direita títulos de demandas nem NUPs SEI. Reproduza o título completo exatamente como fornecido em 'titulo_completo'.

3. FILTRAGEM:
   - Itens com status cancelado já foram excluídos e NÃO devem aparecer no relatório.

4. ESTRUTURA HIERÁRQUICA E INDENTADA POR BULLETS:
   Estruture exatamente no seguinte formato estrito:

# 📌 STATUS REPORT DE ORDENS DE SERVIÇO E DEMANDAS

* **Sistema de Informação: [Nome do Sistema] ([Sigla])**
  * **NUP SEI:** [NUP SEI]
    * **Nota de Empenho:** [Nota de Empenho]
    * **Status da OS:** [Status da OS]
    * **Demandas Relacionadas na OS:**
      * **Título Completo:** [Título Completo da Demanda]
        * **Status Atual:** [Status Atual da Demanda]

(Se uma OS não possuir demandas vinculadas, escreva exatamente:
    * **Demandas Relacionadas na OS:** Nenhuma demanda vinculada.)
EOT;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 3500
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 40);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            log_message('error', "[StatusReportController] Falha na chamada Gemini: HTTP {$httpCode} - {$curlError} - Resp: {$response}");
            return $this->gerarRelatorioFallback($dossie, $dataHora, "API Gemini retornou código HTTP {$httpCode}. Relatório compilado via contingência local.");
        }

        $resDecoded = json_decode($response, true);
        $textoRetorno = $resDecoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty(trim($textoRetorno))) {
            return $this->gerarRelatorioFallback($dossie, $dataHora, "A IA não retornou conteúdo. Relatório compilado via contingência local.");
        }

        return $textoRetorno;
    }

    /**
     * Gera relatório estruturado de contingência local quando a IA estiver inacessível
     */
    protected function gerarRelatorioFallback(array $dossie, string $dataHora, string $motivoFallback = ''): string
    {
        $out = "# 📌 STATUS REPORT DE ORDENS DE SERVIÇO E DEMANDAS\n\n";

        foreach ($dossie as $sis) {
            $siglaTxt = !empty($sis['sigla']) ? " (" . $sis['sigla'] . ")" : "";
            $out .= "* **Sistema de Informação: " . $sis['nome'] . $siglaTxt . "**\n";

            if (empty($sis['ordens_servico'])) {
                $out .= "  * Nenhuma Ordem de Serviço vinculada.\n";
            } else {
                foreach ($sis['ordens_servico'] as $os) {
                    $out .= "  * **NUP SEI:** " . $os['nup_sei'] . "\n";
                    $out .= "    * **Nota de Empenho:** " . ($os['nota_empenho'] ?: '-') . "\n";
                    $out .= "    * **Status da OS:** " . ($os['status'] ?: '-') . "\n";

                    if (!empty($os['demandas_relacionadas'])) {
                        $out .= "    * **Demandas Relacionadas na OS:**\n";
                        foreach ($os['demandas_relacionadas'] as $dem) {
                            $titulo = $dem['titulo_completo'] ?? $dem['titulo'] ?? '';
                            $out .= "      * **Título Completo:** " . $titulo . "\n";
                            $out .= "        * **Status Atual:** " . ($dem['status'] ?: '-') . "\n";
                        }
                    } else {
                        $out .= "    * **Demandas Relacionadas na OS:** Nenhuma demanda vinculada.\n";
                    }
                }
            }
            $out .= "\n";
        }

        return rtrim($out) . "\n";
    }

    /**
     * Busca a GEMINI_API_KEY do ambiente ou dos arquivos .env
     */
    protected function getGeminiApiKey(): string
    {
        $key = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');
        if (!empty($key)) {
            return trim($key);
        }

        $paths = [
            ROOTPATH . '.env',
            dirname(ROOTPATH) . '/footballweb/.env',
            '/root/datalake-air-flow-delta/src/footballweb/.env',
            '/root/datalake-air-flow-delta/src/fiscalweb/.env'
        ];

        foreach ($paths as $p) {
            if (file_exists($p)) {
                $lines = file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, 'GEMINI_API_KEY=') === 0) {
                        $parts = explode('=', $line, 2);
                        return trim(trim($parts[1]), "'\"");
                    }
                }
            }
        }

        return '';
    }
}

