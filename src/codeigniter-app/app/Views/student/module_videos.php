<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>

<style>
.module-header-banner {
    background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
    color: white;
    padding: 40px;
    margin-bottom: 40px;
    border-radius: 12px;
}

.breadcrumb {
    color: rgba(255,255,255,0.8);
    margin-bottom: 20px;
}

.breadcrumb a {
    color: white;
    text-decoration: none;
}

.videos-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.video-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    margin-bottom: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
    display: flex;
    gap: 20px;
}

.video-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.video-thumbnail {
    width: 280px;
    height: 157px;
    object-fit: cover;
    flex-shrink: 0;
}

.video-content {
    padding: 20px;
    flex: 1;
}

.video-title {
    font-size: 20px;
    font-weight: bold;
    color: #333;
    margin: 0 0 10px 0;
}

.video-description {
    color: #666;
    line-height: 1.6;
    margin-bottom: 15px;
}

.video-meta {
    display: flex;
    gap: 20px;
    font-size: 14px;
    color: #666;
    border-top: 1px solid #eee;
    padding-top: 15px;
}

.progress-bar {
    height: 6px;
    background: #e0e7ff;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 10px;
}

.progress-fill {
    height: 100%;
    background: #4f46e5;
    transition: width 0.3s;
}

.video-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #10b981;
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}
</style>

<div id="content">
    <div class="videos-container">
        <div class="module-header-banner">
            <div class="breadcrumb">
                <a href="<?php echo site_url('cursos'); ?>">Cursos</a> / 
                <a href="<?php echo site_url('curso/' . $course['id']); ?>"><?php echo esc($course['name']); ?></a>
            </div>
            <h1 style="margin: 0; font-size: 36px;">
                Módulo <?php echo esc($module['module_number']); ?>: <?php echo esc($module['name']); ?>
            </h1>
            <?php if($module['description']): ?>
                <p style="margin-top: 15px; font-size: 18px; opacity: 0.9;">
                    <?php echo esc($module['description']); ?>
                </p>
            <?php endif; ?>
        </div>

        <h2 style="color: #333; margin-bottom: 30px;">🎬 Vídeos do Módulo</h2>

        <?php if(empty($videos)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #999;">
                <div style="font-size: 64px; margin-bottom: 20px;">🎬</div>
                <h3 style="color: #666;">Nenhum vídeo disponível ainda</h3>
                <p>Os vídeos deste módulo serão adicionados em breve!</p>
            </div>
        <?php else: ?>
            <?php foreach($videos as $index => $video): 
                $thumbnailUrl = $video['thumbnail_url'] ?? 'https://img.youtube.com/vi/' . $video['youtube_id'] . '/maxresdefault.jpg';
                $hasProgress = isset($video['percent']) && $video['percent'] > 0;
                $isCompleted = isset($video['completed']) && $video['completed'];
            ?>
                <div class="video-card" onclick="window.location.href='<?php echo site_url('video/' . $video['id']); ?>';" style="position: relative;">
                    <img src="<?php echo esc($thumbnailUrl); ?>" 
                         alt="Thumbnail" 
                         class="video-thumbnail">
                    
                    <?php if($isCompleted): ?>
                        <div class="video-badge">✓ Concluído</div>
                    <?php endif; ?>

                    <div class="video-content">
                        <h3 class="video-title">
                            <?php echo ($index + 1); ?>. <?php echo esc($video['title']); ?>
                        </h3>

                        <?php if($video['description']): ?>
                            <p class="video-description">
                                <?php echo esc(substr($video['description'], 0, 150)); ?><?php echo strlen($video['description']) > 150 ? '...' : ''; ?>
                            </p>
                        <?php endif; ?>

                        <div class="video-meta">
                            <?php if($video['duration_seconds']): 
                                $minutes = floor($video['duration_seconds'] / 60);
                                $seconds = $video['duration_seconds'] % 60;
                            ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span>⏱️</span>
                                    <span><?php echo sprintf('%d:%02d', $minutes, $seconds); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span>🎬</span>
                                <span><?php echo esc($video['video_id']); ?></span>
                            </div>

                            <?php if(isset($video['uc_count']) && $video['uc_count'] > 0): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span>✅</span>
                                    <span><?php echo $video['uc_count']; ?> tarefas</span>
                                </div>
                            <?php endif; ?>

                            <?php if(isset($video['total_xp']) && $video['total_xp'] > 0): ?>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span>⭐</span>
                                    <span><?php echo $video['total_xp']; ?> XP</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if($hasProgress): ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $video['percent']; ?>%;"></div>
                            </div>
                            <small style="color: #666; margin-top: 5px; display: block;">
                                <?php echo round($video['percent']); ?>% concluído
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require VIEWPATH.'/footer.php'; ?>
