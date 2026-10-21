<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class TerminalStakeController extends BaseController
{
    /**
     * Valida acesso estritamente exclusivo para o administrador Paulo (paulomnasc@gmail.com)
     */
    protected function checkExclusiveAccess(): ?\CodeIgniter\HTTP\ResponseInterface
    {
        $session = session();
        $userEmail = trim((string) ($session->get('email_usuario_logado') ?? ($_SESSION['email_usuario_logado'] ?? '')));
        $usuarioLogado = $session->get('usuario_logado') ?? ($_SESSION['usuario_logado'] ?? 0);
        $userId = $session->get('id_usuario_logado') ?? ($_SESSION['id_usuario_logado'] ?? null);

        // Fallback de resolução do e-mail do usuário se ausente na sessão
        if (empty($userEmail) && !empty($userId)) {
            $usuarioModel = new \App\Models\UsuarioModel();
            $u = $usuarioModel->find($userId);
            if ($u && !empty($u->email)) {
                $userEmail = trim((string) $u->email);
                $_SESSION['email_usuario_logado'] = $userEmail;
                $session->set('email_usuario_logado', $userEmail);
            }
        }

        $isPaulo = (strcasecmp($userEmail, 'paulomnasc@gmail.com') === 0) || ((int) $userId === 558);

        if ((empty($usuarioLogado) && empty($userId)) || !$isPaulo) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => 'Acesso negado. A rota /terminal/stake é de acesso exclusivo de paulomnasc@gmail.com.'
                ]);
            }
            $session->setFlashdata('error', 'Acesso negado. A rota /terminal/stake é de acesso exclusivo de paulomnasc@gmail.com.');
            return redirect()->to('/');
        }
        return null;
    }

    /**
     * Interface do Terminal Remoto Isolado (Stake.com / Casas de Apostas)
     */
    public function index()
    {
        if ($deny = $this->checkExclusiveAccess()) {
            return $deny;
        }

        $data = [
            'title'      => 'Terminal Remoto Seguro (1xBet / Sportsbet.io)',
            'browserUrl' => '/remote-browser/',
        ];

        return $this->loadView('terminal/stake', $data);
    }

    /**
     * Retorna o status de execução do navegador Chromium (AJAX)
     */
    public function status()
    {
        if ($deny = $this->checkExclusiveAccess()) {
            return $deny;
        }

        $output = [];
        $returnVar = 0;
        @exec('docker exec remote-browser pgrep chromium 2>&1', $output, $returnVar);

        $isRunning = ($returnVar === 0 && !empty($output));

        return $this->response->setJSON([
            'success'   => true,
            'isRunning' => $isRunning,
            'status'    => $isRunning ? 'active' : 'stopped',
        ]);
    }

    /**
     * Inicia o navegador Chromium caso esteja parado (AJAX)
     */
    public function start()
    {
        if ($deny = $this->checkExclusiveAccess()) {
            return $deny;
        }

        $url = trim($this->request->getPost('url') ?? '');
        if (empty($url)) {
            $safeUrl = 'https://sportsbet.io/pt/sports';
            $url = 'https://sportsbet.io/pt/sports';
        } else {
            if (! preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            $safeUrl = escapeshellarg($url);
        }

        // Limpeza preventiva de travas de perfil e reset de estado de encerramento
        @exec('docker exec -u abc remote-browser bash -c "rm -f /config/.config/chromium/Singleton* && sed -i \'s/\"exit_type\":\"Crashed\"/\"exit_type\":\"Normal\"/g\' /config/.config/chromium/Default/Preferences 2>/dev/null || true" 2>&1');

        // Disparo assíncrono do Chromium com flags para suprimir balão de crash/restauração, liberar popups e conter processos excessivos
        $cmd = "docker exec -d -u abc -e XDG_RUNTIME_DIR=/config/.XDG -e WAYLAND_DISPLAY=wayland-1 remote-browser /usr/bin/wrapped-chromium --disable-session-crashed-bubble --hide-crash-restore-bubble --disable-popup-blocking --renderer-process-limit=3 --disable-features=SitePerProcess --disable-breakpad --disable-crash-reporter {$safeUrl} 2>&1";
        @exec($cmd);

        usleep(600000); // 600ms para estabilização

        return $this->response->setJSON([
            'success' => true,
            'url'     => $url,
            'message' => 'Navegador iniciado com sucesso.',
        ]);
    }

    /**
     * Encerra o processo do Chromium por motivo de segurança (AJAX)
     */
    public function stop()
    {
        if ($deny = $this->checkExclusiveAccess()) {
            return $deny;
        }

        @exec('docker exec remote-browser pkill -15 -f chromium 2>&1');
        usleep(250000);
        @exec('docker exec remote-browser pkill -9 -f chromium 2>&1');
        @exec('docker exec -u abc remote-browser bash -c "rm -f /config/.config/chromium/Singleton* && sed -i \'s/\"exit_type\":\"Crashed\"/\"exit_type\":\"Normal\"/g\' /config/.config/chromium/Default/Preferences 2>/dev/null || true" 2>&1');

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Navegador finalizado com sucesso por segurança.',
        ]);
    }

    /**
     * Reinicia o processo do Chromium (AJAX)
     */
    public function restart()
    {
        if ($deny = $this->checkExclusiveAccess()) {
            return $deny;
        }

        $url = trim($this->request->getPost('url') ?? '');
        if (empty($url)) {
            $safeUrl = 'https://sportsbet.io/pt/sports';
            $url = 'https://sportsbet.io/pt/sports';
        } else {
            if (! preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            $safeUrl = escapeshellarg($url);
        }

        @exec('docker exec remote-browser pkill -15 -f chromium 2>&1');
        usleep(250000);
        @exec('docker exec remote-browser pkill -9 -f chromium 2>&1');
        @exec('docker exec -u abc remote-browser bash -c "rm -f /config/.config/chromium/Singleton* && sed -i \'s/\"exit_type\":\"Crashed\"/\"exit_type\":\"Normal\"/g\' /config/.config/chromium/Default/Preferences 2>/dev/null || true" 2>&1');
        usleep(400000);

        $cmd = "docker exec -d -u abc -e XDG_RUNTIME_DIR=/config/.XDG -e WAYLAND_DISPLAY=wayland-1 remote-browser /usr/bin/wrapped-chromium --disable-session-crashed-bubble --hide-crash-restore-bubble --disable-popup-blocking --renderer-process-limit=3 --disable-features=SitePerProcess --disable-breakpad --disable-crash-reporter {$safeUrl} 2>&1";
        @exec($cmd);

        return $this->response->setJSON([
            'success' => true,
            'url'     => $url,
            'message' => 'Navegador reiniciado com sucesso.',
        ]);
    }

    /**
     * Direciona o navegador Chromium para uma URL informada (AJAX)
     */
    public function navigate()
    {
        if ($deny = $this->checkExclusiveAccess()) {
            return $deny;
        }

        $url = trim($this->request->getPost('url') ?? '');
        if (empty($url)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Por favor, informe uma URL válida.',
            ]);
        }

        // Se não possuir esquema, adiciona https://
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        // Sanitização segura para o shell
        $safeUrl = escapeshellarg($url);

        // Verifica se o Chromium já está em execução
        $output = [];
        $returnVar = 0;
        @exec('docker exec remote-browser pgrep chromium 2>&1', $output, $returnVar);
        $isRunning = ($returnVar === 0 && ! empty($output));

        if (! $isRunning) {
            @exec('docker exec -u abc remote-browser bash -c "rm -f /config/.config/chromium/Singleton* && sed -i \'s/\"exit_type\":\"Crashed\"/\"exit_type\":\"Normal\"/g\' /config/.config/chromium/Default/Preferences 2>/dev/null || true" 2>&1');
        }

        $cmd = "docker exec -d -u abc -e XDG_RUNTIME_DIR=/config/.XDG -e WAYLAND_DISPLAY=wayland-1 remote-browser /usr/bin/wrapped-chromium --disable-session-crashed-bubble --hide-crash-restore-bubble --disable-popup-blocking --renderer-process-limit=3 --disable-features=SitePerProcess --disable-breakpad --disable-crash-reporter {$safeUrl} 2>&1";
        @exec($cmd);

        return $this->response->setJSON([
            'success' => true,
            'url'     => $url,
            'message' => "Navegando para {$url}...",
        ]);
    }
}


