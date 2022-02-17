<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var array[] $pages
 */

?>
<div class="col-md-4 ccm-dashboard-section-menu">
    <ul class="list-unstyled">
        <?php
        if ($pages !== []) {
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
