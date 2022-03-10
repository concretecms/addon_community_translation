<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Package\CommunityTranslation\Block\TranslationTeamRequest\Controller $controller
 */

?>
<div class="card">
    <div class="card-header"><h3><?= t('New Translators Team') ?></h3></div>
    <div class="card-body">
        <div class="card-text">
            <form method="POST" action="<?= h($controller->getBlockActionURL('login')) ?>">
                <div class="alert alert-danger">
                    <?= t('You need to sign-in in order to ask the creation of a new Translation Team') ?>
                </div>
                <input type="submit" class="btn btn-primary" value="<?= t('Login') ?>">
            </form>
        </div>
    </div>
</div>
