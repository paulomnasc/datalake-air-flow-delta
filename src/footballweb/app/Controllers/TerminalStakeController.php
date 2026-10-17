<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class TerminalStakeController extends BaseController
{
    /**
     * Interface do Terminal Remoto Isolado (Stake.com / Casas de Apostas)
     */
    public function index()
    {
        $data = [
            'title'      => 'Terminal Remoto Seguro | Stake.com',
            'browserUrl' => '/remote-browser/',
        ];

        return $this->loadView('terminal/stake', $data);
    }

    /**
     * Retorna o status de execução do navegador Chromium (AJAX)
     */
    public function status()
    {
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
     * Inicia o navegador Chromium na página da Stake caso esteja parado (AJAX)
     */
    public function start()
    {
        // Limpeza preventiva de travas de perfil
        @exec('docker exec -u abc remote-browser rm -f /config/.config/chromium/Singleton* 2>&1');

        // Disparo assíncrono do Chromium na sessão gráfica Wayland
        $cmd = 'docker exec -d -u abc -e XDG_RUNTIME_DIR=/config/.XDG -e WAYLAND_DISPLAY=wayland-1 remote-browser /usr/bin/wrapped-chromium https://stake.com/ 2>&1';
        @exec($cmd);

        usleep(600000); // 600ms para estabilização

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Navegador iniciado com sucesso.',
        ]);
    }

    /**
     * Encerra o processo do Chromium por motivo de segurança (AJAX)
     */
    public function stop()
    {
        @exec('docker exec remote-browser pkill -9 -f chromium 2>&1');
        @exec('docker exec -u abc remote-browser rm -f /config/.config/chromium/Singleton* 2>&1');

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
        @exec('docker exec remote-browser pkill -9 -f chromium 2>&1');
        @exec('docker exec -u abc remote-browser rm -f /config/.config/chromium/Singleton* 2>&1');
        usleep(400000);

        $cmd = 'docker exec -d -u abc -e XDG_RUNTIME_DIR=/config/.XDG -e WAYLAND_DISPLAY=wayland-1 remote-browser /usr/bin/wrapped-chromium https://stake.com/ 2>&1';
        @exec($cmd);

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Navegador reiniciado com sucesso.',
        ]);
    }
}

