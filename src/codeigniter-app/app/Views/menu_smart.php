<?php

if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR.'Views');
}
require VIEWPATH.'/header.php';
?>

        <div id="content" class="container-fluid">
            <?php
                require VIEWPATH.'/home_body.php';
            ?>

        </div>
    </div>
<?php
require VIEWPATH.'/footer.php';
?>
