<?php
defined('C5_EXECUTE') or die('Access Denied.');

// Arguments
/* @var Pages[] $pages */

?>
<div class="col-md-4 ccm-dashboard-section-menu">
    <ul class="list-unstyled">
        <?php
        if (!empty($pages)) {
            foreach ($pages as $page) {
                ?>
                <li>
                    <a class="btn btn-primary" style="width: 100%" href="<?= $page[0] ?>"><?= h(t($page[1])) ?></a>
                </li>
                <?php
            }
        }
        ?>
    </ul>
</div>
