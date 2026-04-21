<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\CourseModel;
use App\Models\ModuleModel;
use App\Models\VideoModel;
use App\Models\UcDefinitionModel;
use App\Models\VideoProgressModel;
use App\Models\UcProgressModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class CursoController extends BaseController
{

    private const COURSE_ASSETS_PREFIX = 'assets/curso/';
    private const DOWNLOADS_BASE_DIR = 'downloads/';
    private const TEMP_ZIP_DIR = 'cache/course-zips/';
    private const TEMP_ZIP_TTL_SECONDS = 3600;

    

    public function modulo1()
    {
        return view('cursoModulo1');
    }

    public function progressMonitor()
    {
        return view('uc_progress_monitor');
    }

    // ========== STUDENT INTERFACE ==========

    /**
     * Redireciona /cursos diretamente para o vídeo principal
     */
    public function index()
    {
        $userId = $_SESSION['id_usuario_logado'] ?? null;
        if ($userId) {
            $usuarioModel = new \App\Models\UsuarioModel();
            $usuario = $usuarioModel->find($userId);
            if (!empty($usuario) && isset($usuario->pagamento_inicial) && $usuario->pagamento_inicial == 1) {
                return redirect()->to('/curso/1');
            }
        }
        return redirect()->to(route_to('video.player', 12));
    }

    /**
     * Exibe os módulos de um curso específico
     */
    public function course($courseId)
    {
        $courseModel = new CourseModel();
        $moduleModel = new ModuleModel();
        $videoModel = new VideoModel();

        $data['course'] = $courseModel->find($courseId);

        if (!$data['course'] || !$data['course']['is_active']) {
            return redirect()->to('/cursos')->with('error', 'Curso não encontrado ou não disponível.');
        }

        $modules = $moduleModel->getModulesByCourse($courseId);
        // Adiciona o campo video_count em cada módulo
        foreach ($modules as &$module) {
            $module['video_count'] = $videoModel->countByModule($module['id']);
        }
        $data['modules'] = $modules;

        return view('student/course_modules', $data);
    }

    /**
     * Exibe os vídeos de um módulo específico
     */
    public function module($moduleId)
    {
        $moduleModel = new ModuleModel();
        $videoModel = new VideoModel();
        $courseModel = new CourseModel();
        $ucModel = new UcDefinitionModel();
        $userId = $_SESSION['id_usuario_logado'] ?? null;

        $data['module'] = $moduleModel->find($moduleId);

        if (!$data['module'] || !$data['module']['is_active']) {
            return redirect()->to('/cursos')->with('error', 'Módulo não encontrado ou não disponível.');
        }
        $data['texto_periodicidade'] = ' taxa única';
        // Validação extra: bloqueia acesso ao módulo 2+ caso pagamento_inicial não esteja autorizado
        if ($userId && intval($data['module']['order'] ?? 1) > 1) {
            $usuarioModel = new \App\Models\UsuarioModel();
            $usuario = $usuarioModel->find($userId);
            if (empty($usuario->pagamento_inicial) || $usuario->pagamento_inicial != 1) {
                return redirect()->to('/subscription/initial-payment');
            }
        }

        $data['course'] = $courseModel->find($data['module']['course_id']);
        $data['videos'] = $videoModel->getVideosByModule($moduleId);

        // Buscar progresso do usuário se estiver logado
        if ($userId) {
            $progressModel = new VideoProgressModel();
            $ucProgressModel = new UcProgressModel();

            foreach ($data['videos'] as &$video) {
                // Progresso de vídeo
                $progress = $progressModel->getUserVideoProgress($userId, $video['id']);
                $video['completed'] = $progress ? $progress['completed'] : 0;
                $video['percent'] = $progress ? $progress['percent'] : 0;

                // Informações de tarefas do vídeo
                $ucs = $ucModel->getUCsByVideo($video['id']);
                $video['uc_count'] = count($ucs);

                // Total de XP disponível
                $video['total_xp'] = 0;
                foreach ($ucs as $uc) {
                    $video['total_xp'] += $uc['xp_points'];
                }

                // Tarefas concluídas e XP ganho
                $video['uc_completed'] = 0;
                $video['xp_earned'] = 0;

                foreach ($ucs as $uc) {
                    $ucProgress = $ucProgressModel->where([
                        'user_id' => $userId,
                        'uc_definition_id' => $uc['id']
                    ])->first();

                    if ($ucProgress && $ucProgress['completed']) {
                        $video['uc_completed']++;
                        $video['xp_earned'] += $uc['xp_points'];
                    }
                }
            }
        }

        return view('student/module_videos', $data);
    }

    /**
     * Player de vídeo com as tarefas/UCs
     */
    public function video($videoId)
    {
        $videoModel = new VideoModel();
        $moduleModel = new ModuleModel();
        $courseModel = new CourseModel();
        $ucModel = new UcDefinitionModel();
        
        $data['video'] = $videoModel->find($videoId);
        
        if (!$data['video'] || !$data['video']['is_active']) {
            return redirect()->to('/cursos')->with('error', 'Vídeo não encontrado ou não disponível.');
        }
        
        $data['module'] = $moduleModel->find($data['video']['module_id']);
        $data['course'] = $courseModel->find($data['module']['course_id']);
        $data['ucs'] = $ucModel->getUCsByVideo($videoId);
        
        $userId = $_SESSION['id_usuario_logado'] ?? null;

        // --- Cálculo da Navegação Sequencial (Próxima Aula) ---
        $nextVideo = null;
        
        // 1. Tentar buscar o próximo vídeo dentro do mesmo módulo
        $moduleVideos = $videoModel->getVideosByModule($data['module']['id']);
        $currentIndex = null;
        foreach ($moduleVideos as $idx => $v) {
            if ($v['id'] == $videoId) {
                $currentIndex = $idx;
                break;
            }
        }
        
        if ($currentIndex !== null && isset($moduleVideos[$currentIndex + 1])) {
            // Existe próximo vídeo no mesmo módulo
            $nextVideo = $moduleVideos[$currentIndex + 1];
        } else {
            // É o último vídeo do módulo, tentar o primeiro vídeo do próximo módulo
            $modules = $moduleModel->getModulesByCourse($data['course']['id']);
            $currentModuleIndex = null;
            foreach ($modules as $idx => $m) {
                if ($m['id'] == $data['module']['id']) {
                    $currentModuleIndex = $idx;
                    break;
                }
            }
            
            if ($currentModuleIndex !== null && isset($modules[$currentModuleIndex + 1])) {
                $nextModule = $modules[$currentModuleIndex + 1];
                $nextModuleVideos = $videoModel->getVideosByModule($nextModule['id']);
                if (!empty($nextModuleVideos)) {
                    $nextVideo = $nextModuleVideos[0];
                }
            }
        }
        $data['next_video'] = $nextVideo;
        // ------------------------------------------------------
        
        // --- Validação de Pagamento (Segurança) ---
        // Apenas o vídeo de ID 12 pode ser assistido sem pagamento
        // Qualquer outro vídeo exige pagamento inicial
        if (intval($videoId) != 12) {
            if (!$userId) {
                // Usuário não logado, redirecionar para pagamento
                return redirect()->to('/subscription/initial-payment');
            }
            
            // Verificar se usuário tem pagamento autorizado
            $usuarioModel = new \App\Models\UsuarioModel();
            $usuario = $usuarioModel->find($userId);
            if (empty($usuario->pagamento_inicial) || $usuario->pagamento_inicial != 1) {
                return redirect()->to('/subscription/initial-payment');
            }
        }
        // ------------------------------------------
        
        // Buscar progresso do usuário se estiver logado
        if (isset($_SESSION['id_usuario_logado'])) {
            $userId = $_SESSION['id_usuario_logado'];
            
            // Progresso do vídeo
            $videoProgressModel = new VideoProgressModel();
            $data['video_progress'] = $videoProgressModel->getUserVideoProgress($userId, $videoId);
            
            // Progresso das UCs
            $ucProgressModel = new UcProgressModel();
            foreach ($data['ucs'] as &$uc) {
                $ucProgress = $ucProgressModel->where([
                    'user_id' => $userId,
                    'uc_definition_id' => $uc['id']
                ])->first();
                
                $uc['completed'] = $ucProgress ? $ucProgress['completed'] : 0;
            }
        }

        foreach ($data['ucs'] as &$uc) {
            $uc['external_url'] = $this->mapExternalUrlToDownloadRoute($uc['external_url'] ?? null);
        }
        unset($uc);
        
        return view('student/video_player', $data);
    }

    public function downloadMaterial(string $relativePath = '')
    {
        if (!isset($_SESSION['id_usuario_logado']) || empty($_SESSION['id_usuario_logado'])) {
            return redirect()->to('/loginUsuario');
        }

        $normalizedPath = $this->resolveDownloadRelativePath($relativePath);

        if ($normalizedPath === '' || str_contains($normalizedPath, '..')) {
            throw PageNotFoundException::forPageNotFound('Material não encontrado.');
        }

        $downloadRoot = WRITEPATH . self::DOWNLOADS_BASE_DIR;
        if (!is_dir($downloadRoot) && !mkdir($downloadRoot, 0755, true) && !is_dir($downloadRoot)) {
            throw PageNotFoundException::forPageNotFound('Diretório de materiais indisponível.');
        }

        $realDownloadRoot = realpath($downloadRoot);
        $candidatePath = $downloadRoot . $normalizedPath;
        $realPath = realpath($candidatePath);

        if (
            !$realDownloadRoot
            || !$realPath
            || !str_starts_with($realPath, $realDownloadRoot)
        ) {
            throw PageNotFoundException::forPageNotFound('Material não encontrado.');
        }

        if (!is_dir($realPath)) {
            throw PageNotFoundException::forPageNotFound('Apenas pastas podem ser baixadas nesta rota.');
        }

        $zipFilePath = $this->createZipFromDirectory($realPath, $normalizedPath, $realDownloadRoot);
        $zipFileName = basename($realPath) . '.zip';

        return $this->response
            ->download($zipFilePath, null)
            ->setFileName($zipFileName);
    }

    private function mapExternalUrlToDownloadRoute(?string $externalUrl): ?string
    {
        if ($externalUrl === null) {
            return null;
        }

        $trimmedUrl = trim($externalUrl);
        if ($trimmedUrl === '') {
            return $trimmedUrl;
        }

        if (preg_match('#^https?://#i', $trimmedUrl)) {
            return $trimmedUrl;
        }

        $pathOnly = preg_split('/[?#]/', $trimmedUrl)[0] ?? $trimmedUrl;
        $normalizedPath = ltrim($pathOnly, '/');

        if (!str_starts_with($normalizedPath, self::COURSE_ASSETS_PREFIX)) {
            return $trimmedUrl;
        }

        $courseRelativePath = 'curso/' . ltrim(substr($normalizedPath, strlen(self::COURSE_ASSETS_PREFIX)), '/');
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $courseRelativePath)));

        return site_url('curso/download/' . $encodedPath);
    }

    private function resolveDownloadRelativePath(string $relativePath): string
    {
        $normalizedPath = trim(rawurldecode($relativePath), "/ \t\n\r\0\x0B");
        $normalizedPath = str_replace('\\', '/', $normalizedPath);

        if (str_contains($normalizedPath, '/')) {
            return $normalizedPath;
        }

        $uriPath = trim((string) $this->request->getUri()->getPath(), '/');
        $prefix = 'curso/download/';

        if (str_starts_with($uriPath, $prefix)) {
            $fromUri = trim(substr($uriPath, strlen($prefix)), "/ \t\n\r\0\x0B");
            $fromUri = str_replace('\\', '/', rawurldecode($fromUri));
            if ($fromUri !== '') {
                return $fromUri;
            }
        }

        return $normalizedPath;
    }

    private function createZipFromDirectory(string $directoryPath, string $normalizedPath, string $realDownloadRoot): string
    {
        $zipDir = WRITEPATH . self::TEMP_ZIP_DIR;
        if (!is_dir($zipDir) && !mkdir($zipDir, 0755, true) && !is_dir($zipDir)) {
            throw PageNotFoundException::forPageNotFound('Não foi possível preparar o download.');
        }

        $this->cleanupOldZipFiles($zipDir);

        $directoryMtime = (string) (filemtime($directoryPath) ?: time());
        $zipFilePath = $zipDir . sha1($normalizedPath . '|' . $directoryMtime) . '.zip';

        if (is_file($zipFilePath)) {
            return $zipFilePath;
        }

        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw PageNotFoundException::forPageNotFound('Não foi possível gerar o arquivo compactado.');
            }

            $folderName = basename($directoryPath);
            $hasFile = false;

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directoryPath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }

                $realItemPath = $item->getRealPath();
                if ($realItemPath === false || !str_starts_with($realItemPath, $realDownloadRoot)) {
                    continue;
                }

                $hasFile = true;
                $relativeInFolder = ltrim(str_replace($directoryPath, '', $realItemPath), DIRECTORY_SEPARATOR);
                $relativeInFolder = str_replace('\\', '/', $relativeInFolder);
                $zip->addFile($realItemPath, $folderName . '/' . $relativeInFolder);
            }

            if (!$hasFile) {
                $zip->addEmptyDir($folderName);
            }

            $zip->close();
            return $zipFilePath;
        }

        $this->createZipWithSystemBinary($directoryPath, $zipFilePath, $realDownloadRoot);

        return $zipFilePath;
    }

    private function createZipWithSystemBinary(string $directoryPath, string $zipFilePath, string $realDownloadRoot): void
    {
        if (!function_exists('shell_exec')) {
            $this->createZipPurePhp($directoryPath, $zipFilePath, $realDownloadRoot);
            return;
        }

        $zipBin = trim((string) shell_exec('command -v zip 2>/dev/null'));
        if ($zipBin === '') {
            $this->createZipPurePhp($directoryPath, $zipFilePath, $realDownloadRoot);
            return;
        }

        $parentDir = dirname($directoryPath);
        $folderName = basename($directoryPath);
        $cmd = sprintf(
            'cd %s && %s -r -q %s %s 2>/dev/null',
            escapeshellarg($parentDir),
            escapeshellarg($zipBin),
            escapeshellarg($zipFilePath),
            escapeshellarg($folderName)
        );

        shell_exec($cmd);

        if (!is_file($zipFilePath) || filesize($zipFilePath) === 0) {
            $this->createZipPurePhp($directoryPath, $zipFilePath, $realDownloadRoot);
        }
    }

    private function createZipPurePhp(string $directoryPath, string $zipFilePath, string $realDownloadRoot): void
    {
        $zipHandle = fopen($zipFilePath, 'wb');
        if ($zipHandle === false) {
            throw PageNotFoundException::forPageNotFound('Não foi possível gerar o arquivo compactado.');
        }

        $folderName = basename($directoryPath);
        $centralDirectory = '';
        $offset = 0;
        $entryCount = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directoryPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $realItemPath = $item->getRealPath();
            if ($realItemPath === false || !str_starts_with($realItemPath, $realDownloadRoot)) {
                continue;
            }

            $data = file_get_contents($realItemPath);
            if ($data === false) {
                continue;
            }

            $relativeInFolder = ltrim(str_replace($directoryPath, '', $realItemPath), DIRECTORY_SEPARATOR);
            $relativeInFolder = str_replace('\\', '/', $relativeInFolder);
            $entryName = $folderName . '/' . $relativeInFolder;

            $dosDateTime = $this->toDosDateTime((int) $item->getMTime());
            $dosTime = $dosDateTime['time'];
            $dosDate = $dosDateTime['date'];

            $uncompressedSize = strlen($data);
            $crc = (int) sprintf('%u', crc32($data));

            $localHeader = "PK\x03\x04"
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', $dosTime)
                . pack('v', $dosDate)
                . pack('V', $crc)
                . pack('V', $uncompressedSize)
                . pack('V', $uncompressedSize)
                . pack('v', strlen($entryName))
                . pack('v', 0)
                . $entryName;

            fwrite($zipHandle, $localHeader);
            fwrite($zipHandle, $data);

            $centralDirectory .= "PK\x01\x02"
                . pack('v', 20)
                . pack('v', 20)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', $dosTime)
                . pack('v', $dosDate)
                . pack('V', $crc)
                . pack('V', $uncompressedSize)
                . pack('V', $uncompressedSize)
                . pack('v', strlen($entryName))
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', 0)
                . pack('V', 0)
                . pack('V', $offset)
                . $entryName;

            $offset += strlen($localHeader) + $uncompressedSize;
            $entryCount++;
        }

        $centralDirectorySize = strlen($centralDirectory);
        fwrite($zipHandle, $centralDirectory);
        fwrite(
            $zipHandle,
            "PK\x05\x06"
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', $entryCount)
            . pack('v', $entryCount)
            . pack('V', $centralDirectorySize)
            . pack('V', $offset)
            . pack('v', 0)
        );

        fclose($zipHandle);

        if (!is_file($zipFilePath) || filesize($zipFilePath) === 0) {
            throw PageNotFoundException::forPageNotFound('Não foi possível gerar o arquivo compactado.');
        }
    }

    private function toDosDateTime(int $timestamp): array
    {
        $date = getdate($timestamp);

        $year = max(1980, (int) $date['year']);
        $month = (int) $date['mon'];
        $day = (int) $date['mday'];
        $hour = (int) $date['hours'];
        $minute = (int) $date['minutes'];
        $second = (int) floor($date['seconds'] / 2);

        $dosTime = ($hour << 11) | ($minute << 5) | $second;
        $dosDate = (($year - 1980) << 9) | ($month << 5) | $day;

        return [
            'time' => $dosTime,
            'date' => $dosDate,
        ];
    }

    private function cleanupOldZipFiles(string $zipDir): void
    {
        $zipFiles = glob(rtrim($zipDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.zip');
        if ($zipFiles === false) {
            return;
        }

        $threshold = time() - self::TEMP_ZIP_TTL_SECONDS;
        foreach ($zipFiles as $zipFile) {
            $mtime = filemtime($zipFile);
            if ($mtime !== false && $mtime < $threshold) {
                @unlink($zipFile);
            }
        }
    }
}
