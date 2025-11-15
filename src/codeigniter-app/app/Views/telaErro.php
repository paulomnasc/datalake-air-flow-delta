<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>
<div id="main-content">

    <button class="open-btn" onclick="toggleSidebar()">☰</button>

    <div id="content">

        <div class="container">
            <?php if (session()->getFlashdata('error_message')): ?>
                <div id="error-message" class="alert alert-danger">
                    <?= session()->getFlashdata('error_message'); ?>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>  

<?php
require VIEWPATH . '/footer.php';
?>