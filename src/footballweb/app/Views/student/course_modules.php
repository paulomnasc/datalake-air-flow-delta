<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>

<style>
.course-header-banner {
    background: linear-gradient(135deg, <?php echo esc($course['color'] ?? '#4f46e5'); ?> 0%, <?php echo esc($course['color'] ?? '#4f46e5'); ?>dd 100%);
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

.modules-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.module-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 25px;
    margin-bottom: 20px;
    border-left: 4px solid #4f46e5;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}

.module-card:hover {
    transform: translateX(8px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.module-number {
    background: #4f46e5;
    color: white;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: bold;
}

.module-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 15px;
}

.module-title {
    font-size: 22px;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.module-description {
    color: #666;
    line-height: 1.6;
    margin: 10px 0;
}

.module-meta {
    display: flex;
    gap: 20px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
    font-size: 14px;
    color: #666;
}
</style>

<div id="content">
    <div class="modules-container">
        <div class="course-header-banner">
            <div class="breadcrumb">
                <a href="<?php echo site_url('cursos'); ?>">← Voltar para Cursos</a>
            </div>
            <h1 style="margin: 0; font-size: 36px;"><?php echo esc($course['name']); ?></h1>
            <?php if($course['description']): ?>
                <p style="margin-top: 15px; font-size: 18px; opacity: 0.9;">
                    <?php echo esc($course['description']); ?>
                </p>
            <?php endif; ?>
        </div>

        <h2 style="color: #333; margin-bottom: 30px;">📖 Módulos do Curso</h2>

        <?php if(empty($modules)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #999;">
                <div style="font-size: 64px; margin-bottom: 20px;">📖</div>
                <h3 style="color: #666;">Nenhum módulo disponível ainda</h3>
                <p>Os módulos deste curso serão adicionados em breve!</p>
            </div>
        <?php else: ?>
            <?php foreach($modules as $module): ?>
                <div class="module-card" onclick="window.location.href='<?php echo site_url('modulo/' . $module['id']); ?>';">
                    <div class="module-header">
                        <div class="module-number">
                            <?php echo esc($module['module_number']); ?>
                        </div>
                        <div style="flex: 1;">
                            <h3 class="module-title"><?php echo esc($module['name']); ?></h3>
                            <span style="background: #e0e7ff; color: #4f46e5; padding: 4px 12px; border-radius: 4px; font-size: 12px;">
                                <?php echo esc($module['module_id']); ?>
                            </span>
                        </div>
                    </div>

                    <?php if($module['description']): ?>
                        <p class="module-description">
                            <?php echo esc($module['description']); ?>
                        </p>
                    <?php endif; ?>

                    <div class="module-meta">
                        <?php if($module['estimated_hours']): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span>⏱️</span>
                                <span><?php echo esc($module['estimated_hours']); ?> horas estimadas</span>
                            </div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span>🎬</span>
                            <span><?php echo $module['video_count'] ?? 0; ?> vídeos</span>
                        </div>
                        <?php if(isset($module['total_xp']) && $module['total_xp'] > 0): ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span>⭐</span>
                                <span><?php echo $module['total_xp']; ?> XP</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require VIEWPATH.'/footer.php'; ?>
