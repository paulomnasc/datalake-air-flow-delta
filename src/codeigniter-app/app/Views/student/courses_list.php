<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>

<style>
.courses-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
}

.course-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 30px;
    margin-bottom: 30px;
    border-left: 6px solid #4f46e5;
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}

.course-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.course-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 15px;
}

.course-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    color: white;
}

.course-title {
    font-size: 24px;
    font-weight: bold;
    color: #333;
    margin: 0;
}

.course-description {
    color: #666;
    line-height: 1.6;
    margin: 15px 0;
}

.course-meta {
    display: flex;
    gap: 20px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.course-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
    font-size: 14px;
}

.course-cta {
    background: #4f46e5;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background 0.2s;
    margin-top: 20px;
}

.course-cta:hover {
    background: #3730a3;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #999;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 20px;
}
</style>

<div id="content">
    <div class="courses-container">
        <h1 style="text-align: center; color: #333; margin-bottom: 40px;">
            📚 Meus Cursos
            <?php if (!empty($courses)): ?>
                <span style="font-size: 18px; color: #666; font-weight: normal; display: block; margin-top: 10px;">
                    (<?php echo $courses[0]['module_count'] ?? 0; ?> módulos, <?php echo $courses[0]['video_count'] ?? 0; ?> vídeos)
                </span>
            <?php endif; ?>
        </h1>

        <?php if(empty($courses)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📚</div>
                <h2 style="color: #666;">Nenhum curso disponível no momento</h2>
                <p>Novos cursos serão adicionados em breve!</p>
            </div>
        <?php else: ?>
            <?php foreach($courses as $course): ?>
                <div class="course-card" onclick="window.location.href='<?php echo site_url('curso/' . $course['id']); ?>'">
                    <div class="course-header">
                        <?php if($course['icon_url']): ?>
                            <img src="<?php echo esc($course['icon_url']); ?>" 
                                 alt="Ícone" 
                                 style="width: 60px; height: 60px; border-radius: 12px; object-fit: cover;">
                        <?php else: ?>
                            <div class="course-icon" style="background: <?php echo esc($course['color'] ?? '#4f46e5'); ?>;">
                                📖
                            </div>
                        <?php endif; ?>
                        
                        <div style="flex: 1;">
                            <h2 class="course-title"><?php echo esc($course['name']); ?></h2>
                            <span style="background: <?php echo esc($course['color'] ?? '#4f46e5'); ?>; color: white; padding: 4px 12px; border-radius: 4px; font-size: 12px;">
                                <?php echo esc($course['course_id']); ?>
                            </span>
                        </div>
                    </div>

                    <?php if($course['description']): ?>
                        <p class="course-description">
                            <?php echo esc($course['description']); ?>
                        </p>
                    <?php endif; ?>

                    <div class="course-meta">
                        <div class="course-meta-item">
                            <span>📖</span>
                            <span><?php echo $course['module_count'] ?? 0; ?> módulos</span>
                        </div>
                        <div class="course-meta-item">
                            <span>🎬</span>
                            <span><?php echo $course['video_count'] ?? 0; ?> vídeos</span>
                        </div>
                        <?php if(isset($course['total_xp']) && $course['total_xp'] > 0): ?>
                            <div class="course-meta-item">
                                <span>⭐</span>
                                <span><?php echo $course['total_xp']; ?> XP total</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button class="course-cta" onclick="event.stopPropagation(); window.location.href='<?php echo site_url('curso/' . $course['id']); ?>';">
                        Executar Curso →
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require VIEWPATH.'/footer.php'; ?>
