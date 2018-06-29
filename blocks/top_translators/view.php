<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Block\View\BlockView $view */
/* @var Concrete\Package\CommunityTranslation\Block\FillTranslations\Controller $controller */

/* @var array $counters */
/* @var CommunityTranslation\Service\User $userService */

if (empty($counters)) {
    ?>
    <div class="alert alert-info">
        <?= t('No translators found.') ?>
    </div>
    <?php
} else {
    ?>
    <ol>
        <?php
        foreach ($counters as $numTranslations => $translatorIDs) {
            ?>
            <li>
                <span class="label label-info"><?= t2('%d translation', '%d translations', $numTranslations) ?></span>
                <?php
                $users = [];
                foreach ($translatorIDs as $translatorID) {
                    $users[] = $userService->format($translatorID);
                }
                echo Punic\Misc::joinAnd($users); ?>
            </li>
            <?php
        }
        ?>
    </ol>
    <?php
}
