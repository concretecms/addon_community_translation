<?php

declare(strict_types=1);

use CommunityTranslation\Service\Access;
use Concrete\Core\Page\Page;
use Punic\Misc;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Core\Block\View\BlockView $this
 * @var Concrete\Package\CommunityTranslation\Block\TranslationTeams\Controller $controller
 * @var string $showError (may not be set)
 * @var string $showSuccess (may not be set)
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $urlResolver
 * @var Concrete\Core\Localization\Service\Date $dh
 * @var CommunityTranslation\Service\User $userService
 * @var CommunityTranslation\Entity\Locale $locale
 * @var int $access
 * @var array[] $globalAdmins
 * @var array[] $admins
 * @var array[] $translators
 * @var array[] $aspiring
 */

if (($showError ?? '') !== '') {
    ?>
    <div class="alert alert-danger">
        <?= $showError ?>
    </div>
    <?php
}
if (($showSuccess ?? '') !== '') {
    ?>
    <div class="alert alert-success">
        <?= $showSuccess ?>
    </div>
    <?php
}

?><h2><?= t('%s Translation Team', h($locale->getDisplayName())) ?></h2><?php

if ($globalAdmins !== []) {
    ?>
    <div class="card card-body bg-light">
        <div class="card-text">
            <h4><?= t('Maintainers') ?></h4>
            <?php
            $chunks = [];
            foreach ($globalAdmins as $u) {
                $chunks[] = $userService->format($u['ui']);
            }
            echo Misc::joinAnd($chunks);
            ?>
        </div>
    </div>
    <?php
}

?>
<div class="card">
    <div class="card-header"><h3><?= t('Team Coordinators') ?></h3></div>
        <div class="card-body">
            <div class="card-text">
            <?php
            if ($admins === []) {
                ?><p><?= t('No coordinators so far') ?></p><?php
            } else {
                ?>
                <ul class="list-group">
                    <?php
                    foreach ($admins as $u) {
                        ?>
                        <li class="list-group-item list-group-item-action">
                            <?php
                            if ($access >= Access::GLOBAL_ADMIN && empty($u['actuallyGlobalAdmin'])) {
                                ?>
                                <div class="float-end">
                                    <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('change_access', $locale->getID(), $u['ui']->getUserID(), Access::TRANSLATE)) ?>">
                                        <?php $token->output('comtra_change_access' . $locale->getID() . '#' . $u['ui']->getUserID() . ':' . Access::TRANSLATE) ?>
                                        <input type="submit" class="btn btn-sm btn-warning" value="<?= t('Set as translator') ?>" />
                                    </form>
                                    <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('change_access', $locale->getID(), $u['ui']->getUserID(), Access::NONE)) ?>" onsubmit="return comtraConfirmPost(this)">
                                        <?php $token->output('comtra_change_access' . $locale->getID() . '#' . $u['ui']->getUserID() . ':' . Access::NONE) ?>
                                        <input type="submit" class="btn btn-sm btn-danger" value="<?= t('Expel') ?>" />
                                    </form>
                                </div>
                                <?php
                            }
                            ?>
                            <?= $userService->format($u['ui']) ?>
                            <div class="small text-muted"><?= t2('%1$s translation (currently approved: %2$s)', '%1$s translations (currently approved: %2$s)', $u['totalTranslations'], $u['approvedTranslations']) ?></div>
                            <div class="small text-muted"><?= t('Coordinator since: %s', $dh->formatPrettyDateTime($u['since'], true)) ?></div>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
                <?php
            }
            ?>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-header"><h3><?= t('Translators') ?></h3></div>
    <div class="card-body">
        <div class="card-text">
            <?php
            if ($translators === []) {
                ?><p><?= t('No translators so far') ?></p><?php
            } else {
                ?>
                <ul class="list-group">
                    <?php
                    foreach ($translators as $u) {
                        ?>
                        <li class="list-group-item list-group-item-action">
                            <?php
                            if ($access >= Access::ADMIN) {
                                ?>
                                <div class="float-end">
                                    <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('change_access', $locale->getID(), $u['ui']->getUserID(), Access::ADMIN)) ?>">
                                        <?php $token->output('comtra_change_access' . $locale->getID() . '#' . $u['ui']->getUserID() . ':' . Access::ADMIN) ?>
                                        <input type="submit" class="btn btn-sm btn-success" value="<?= t('Set as coordinator') ?>" />
                                    </form>
                                    <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('change_access', $locale->getID(), $u['ui']->getUserID(), Access::NONE)) ?>" onsubmit="return comtraConfirmPost(this)">
                                        <?php $token->output('comtra_change_access' . $locale->getID() . '#' . $u['ui']->getUserID() . ':' . Access::NONE) ?>
                                        <input type="submit" class="btn btn-sm btn-danger" value="<?= t('Expel') ?>" />
                                    </form>
                                </div>
                                <?php
                            }
                            ?>
                            <?= $userService->format($u['ui']) ?>
                            <div class="small text-muted"><?= t2('%1$s translation (currently approved: %2$s)', '%1$s translations (currently approved: %2$s)', $u['totalTranslations'], $u['approvedTranslations']) ?></div>
                            <div class="small text-muted"><?= t('Translator since: %s', $dh->formatPrettyDateTime($u['since'], true)) ?></div>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
                <?php
            }
            ?>
        </div>
    </div>
</div>
<?php
if ($aspiring !== []) {
    ?>
    <div class="card">
        <div class="card-header"><h3><?= t('Join Requests') ?></h3></div>
        <div class="card-body">
            <div class="card-text">
                <ul class="list-group">
                    <?php
                    foreach ($aspiring as $u) {
                        ?>
                        <li class="list-group-item list-group-item-action">
                            <?php
                            if ($access >= Access::ADMIN) {
                                ?>
                                <div class="float-end">
                                    <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('answer_join_request', $locale->getID(), $u['ui']->getUserID(), 1)) ?>">
                                        <?php $token->output('comtra_answer_join_request' . $locale->getID() . '#' . $u['ui']->getUserID() . ':1') ?>
                                        <input type="submit" class="btn btn-sm btn-success" value="<?= t('Approve') ?>" />
                                    </form>
                                    <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('answer_join_request', $locale->getID(), $u['ui']->getUserID(), 0)) ?>" onsubmit="return comtraConfirmPost(this)">
                                        <?php $token->output('comtra_answer_join_request' . $locale->getID() . '#' . $u['ui']->getUserID() . ':0') ?>
                                        <input type="submit" class="btn btn-sm btn-danger" value="<?= t('Deny') ?>" />
                                    </form>
                                </div>
                                <?php
                            }
                            ?>
                            <?= $userService->format($u['ui']) ?>
                            <div class="text-muted"><?= t('Request date: %s', $dh->formatPrettyDateTime($u['since'], true)) ?></div>
                        </li>
                        <?php
                    }
                    ?>
                </ul>
            </div>
        </div>
    </div>
    <?php
}
?>
<div class="text-end mb-3">
    <?php
    switch ($access) {
        case Access::NOT_LOGGED_IN:
            ?><a href="#" class="btn btn btn-info" onclick="<?= h('window.alert(' . json_encode(t('You must sign-in in order to join this translation group.')) . '); return false') ?>"><?= t('Join this team') ?></a><?php
            break;
        case Access::NONE:
            ?>
            <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('join_translation_group', $locale->getID())) ?>">
                <?php $token->output('comtra_join_translation_group' . $locale->getID()) ?>
                <input type="submit" class="btn btn btn-info" value="<?= t('Join this team') ?>" />
            </form>
            <?php
            break;
        case Access::ASPRIRING:
            ?>
            <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('leave_translation_group', $locale->getID())) ?>">
                <?php $token->output('comtra_leave_translation_group' . $locale->getID()) ?>
                <input type="submit" class="btn btn-danger" value="<?= t('Cancel join request') ?>" />
            </form>
            <?php
            break;
        case Access::TRANSLATE:
        case Access::ADMIN:
            ?>
            <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('leave_translation_group', $locale->getID())) ?>" onsubmit="return comtraConfirmPost(this)">
                <?php $token->output('comtra_leave_translation_group' . $locale->getID()) ?>
                <input type="submit" class="btn btn-danger" value="<?= t('Leave this group') ?>" />
            </form>
            <?php
            break;
    }
    if ($access >= Access::GLOBAL_ADMIN) {
        ?>
        <form class="d-inline" method="POST" action="<?= h($controller->getBlockActionURL('delete_translation_group', $locale->getID())) ?>" data-comtra-warning="<?= h(t("Are you sure you want to PERMANENTLY DELETE this translation group?\n\nWARNING! THIS OPERATION CAN'T BE UNDONE!"))?>" onsubmit="return comtraConfirmPost(this)">
            <?php $token->output('comtra_delete_translation_group' . $locale->getID()) ?>
            <input type="submit" class="btn btn-danger" value="<?= t('Delete') ?>" />
        </form>
        <?php
    }
    ?>
    <a class="btn btn-secondary" href="<?= $urlResolver->resolve([Page::getCurrentPage()]) ?>"><?= t('Back to Team list') ?></a>
</div>

<script>
if (!window.comtraConfirmPost) {
    window.comtraConfirmPost = function(form) {
        var message = $(form).data('comtra-warning') || <?= json_encode(t('Are you sure?')) ?>;
        return window.confirm(message) ? true : false;
    }
}
</script>
