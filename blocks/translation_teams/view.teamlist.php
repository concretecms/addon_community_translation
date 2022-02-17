<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

use CommunityTranslation\Service\Access;

/**
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Core\Block\View\BlockView $this
 * @var Concrete\Package\CommunityTranslation\Block\TranslationTeams\Controller $controller
 * @var string $showError (may not be set)
 * @var string $showSuccess (may not be set)
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Localization\Service\Date $dh
 * @var CommunityTranslation\Service\User $userService
 * @var array[] $approved
 * @var array[] $requested
 * @var string $askNewTeamURL
 */

$id = 'comtra-translation-teams-' . uniqid();

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
?>
<div class="card">
    <div class="card-header"><h3><?= t('Translators Teams') ?></h3></div>
    <div class="card-body">
        <div class="card-text">
            <?php
            if ($approved === []) {
                ?>
                <div class="alert alert-warning">
                    <?= t('No translation teams so far') ?>
                </div>
                <?php
            } else {
                if (count($approved) > 15) {
                    ?>
                    <div class="row justify-content-end">
                        <div class="col col-md-6 col-lg-4 col-xl-3">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                                </div>
                                <input id="<?= $id ?>_search" type="search" class="form-control" placeholder="<?= t('Search language') ?>" />
                            </div>
                        </div>
                    </div>
                    <script>$(document).ready(function() {
                    var $search = $('#<?= $id ?>_search'),
                        lastSearch = '',
                        $rows = $('#<?= $id ?>_table>tbody>tr');
                    $search.on('input blur', function() {
                        var search = $.trim(this.value.replace(/\s+/g, ' ')).toLowerCase();
                        if (lastSearch === search) {
                            return;
                        }
                        lastSearch = search;
                        if (search === '') {
                            $rows.show();
                            return;
                        }
                        $rows.each(function() {
                            var $row = $(this);
                            $row.toggle($row.data('locale-name').indexOf(search) >= 0);
                        });
                    });
                    });</script>
                    <?php
                }
                ?>
                <table class="table table-hover" id="<?= $id ?>_table">
                    <thead>
                        <tr>
                            <th><?= t('Team') ?></th>
                            <th class="d-md-none text-center"><?= t('Contributors') ?></th>
                            <th class="d-none d-md-table-cell text-center"><?= t('Coordinators') ?></th>
                            <th class="d-none d-md-table-cell text-center"><?= t('Translators') ?></th>
                            <th class="text-center"><?= t('Join Requests') ?></th>
                            <th class="d-none d-md-table-cell text-right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($approved as $l) {
                            ?>
                            <tr data-locale-id="<?= h($l['id']) ?>" data-locale-name="<?= h(strtolower($l['name'])) ?>">
                                <td><a href="<?= h($controller->getBlockActionURL('details', $l['id'])) ?>"><?= h($l['name']) ?></a></td>
                                <td class="d-md-none text-center">
                                    <?= $l['admins'] ? ('<span class="badge badge-success" title="' . t('Coordinators') . '">' . $l['admins'] . '</span>') : '<span class="badge badge-light" title="' . t('Coordinators') . '">0</span>' ?>
                                    +
                                    <?= $l['translators'] ? ('<span class="badge badge-success" title="' . t('Translators') . '">' . $l['translators'] . '</span>') : '<span class="badge badge-light" title="' . t('Translators') . '">0</span>' ?>
                                <td class="d-none d-md-table-cell text-center"><?= $l['admins'] ? ('<span class="badge badge-success">' . $l['admins'] . '</span>') : '<span class="badge badge-light">0</span>' ?></td>
                                <td class="d-none d-md-table-cell text-center"><?= $l['translators'] ? ('<span class="badge badge-success">' . $l['translators'] . '</span>') : '<span class="badge badge-light">0</span>' ?></td>
                                <td class="text-center"><?= $l['aspiring'] ? ('<span class="badge badge-success">' . $l['aspiring'] . '</span>') : '<span class="badge badge-light">0</span>' ?></td>
                                <td class="d-none d-md-table-cell text-right"><?php
                                    switch ($l['access']) {
                                        case Access::GLOBAL_ADMIN:
                                            break;
                                        case Access::ADMIN:
                                        case Access::TRANSLATE:
                                            ?><form method="POST" action="<?= h($controller->getBlockActionURL('leave_translation_group', $l['id'])) ?>" data-comtra-warning="<?= h(t('Are you sure you want to leave this translation group?'))?>" onsubmit="return comtraConfirmPost(this)">
                                                <?php $token->output('comtra_leave_translation_group' . $l['id']) ?>
                                                <input type="submit" class="btn btn-sm btn-danger float-right" value="<?= h(t('Leave')) ?>" />
                                            </form><?php
                                            break;
                                        case Access::ASPRIRING:
                                            ?><form method="POST" action="<?= h($controller->getBlockActionURL('leave_translation_group', $l['id'])) ?>" data-comtra-warning="<?= h(t('Are you sure you want to cancel your join request?'))?>" onsubmit="return comtraConfirmPost(this)">
                                                <?php $token->output('comtra_leave_translation_group' . $l['id']) ?>
                                                <input type="submit" class="btn btn-sm btn-warning float-right" value="<?= h(t('Cancel request')) ?>" />
                                            </form><?php
                                            break;
                                    }
                                ?></td>
                            </tr>
                            <?php
                        }
                    ?>
                    </tbody>
                </table>
                <?php
            }
            ?>
        </div>
    </div>
</div>
<?php
if ($requested !== []) {
    ?>
    <div class="card">
        <div class="card-header"><h3><?= t('Requested Teams') ?></h3></div>
        <div class="card-body">
            <table class="table table-hover">
                <tbody>
                    <?php
                    foreach ($requested as $l) {
                        ?>
                        <tr data-locale-id="<?= h($l['id']) ?>">
                            <td>
                                <b><?= h($l['name']) ?></b><br />
                                <?= tc('Language', 'Requested by:') ?> <?= $userService->format($l['requestedBy']) ?><br />
                                <?= tc('Language', 'Requested on:') ?> <?= $dh->formatPrettyDateTime($l['requestedOn'], true, true) ?>
                            </td>
                            <td><?php
                                if ($l['canApprove']) {
                                    ?><form method="POST" action="<?= h($controller->getBlockActionURL('approve_new_locale_request', $l['id'])) ?>" data-comtra-warning="<?= h(t('Are you sure you want to approve this new translation group?'))?>" onsubmit="return comtraConfirmPost(this)">
                                        <?php $token->output('comtra_approve_new_locale_request' . $l['id']) ?>
                                        <input type="submit" class="btn btn-sm btn-success float-right" value="<?= h(t('Approve')) ?>" />
                                    </form><?php
                                }
                                if ($l['canCancel']) {
                                    ?><form method="POST" action="<?= h($controller->getBlockActionURL('cancel_new_locale_request', $l['id'])) ?>" data-comtra-warning="<?= h(t('Are you sure you want to cancel this request?'))?>" onsubmit="return comtraConfirmPost(this)">
                                        <?php $token->output('comtra_cancel_locale_request' . $l['id']) ?>
                                        <input type="submit" class="btn btn-sm btn-danger float-right" value="<?= h($l['canApprove'] ? t('Reject') : t('Cancel')) ?>" />
                                    </form><?php
                                }
                            ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

if ($askNewTeamURL !== '') {
    ?>
    <div class="card">
        <div class="card-header"><h3><?= t('Would you like a new translation group?') ?></h3></div>
        <div class="card-body">
            <p class="card-text"><?= t("If you'd like to help us translating to a new language, you can ask us to <a href=\"%s\">create a new Translators Team</a>.", h($askNewTeamURL)) ?></p>
        </div>
    </div>
    <?php
}
?>

<script>
if (!window.comtraConfirmPost) {
    window.comtraConfirmPost = function(form) {
        var message = $(form).data('comtra-warning') || <?= json_encode(t('Are you sure?')) ?>;
        return window.confirm(message) ? true : false;
    }
}
</script>
