<?php
defined('C5_EXECUTE') or die('Access Denied.');

use CommunityTranslation\Service\Access;

/* @var Concrete\Core\Block\View\BlockView $view */
/* @var string $step */

if (isset($showError) && $showError !== '') {
    ?>
    <div class="alert alert-danger" role="alert">
        <?= $showError ?>
    </div>
    <?php
}
if (isset($showSuccess) && $showSuccess !== '') {
    ?>
    <div class="alert alert-success" role="alert">
        <?= $showSuccess ?>
    </div>
    <?php
}

switch ($step) {
    case 'teamList':
        /* @var string $askNewTeamURL */
        /* @var Concrete\Core\Validation\CSRF\Token $token */
        /* @var Concrete\Core\Localization\Service\Date $dh */
        /* @var CommunityTranslation\Service\User $userService */
        /* @var User|null $me */
        /* @var array[] $approved */
        /* @var array[] $requested */
        ?>
        <div class="panel panel-default">
            <div class="panel-heading"><h3><?= t('Translators Teams') ?></h3></div>
            <div class="panel-body">
                <?php
                if (empty($approved)) {
                    ?>
                    <div class="alert alert-warning" role="alert">
                        <?= t('No translation teams so far') ?>
                    </div>
                    <?php
                } else {
                    ?>
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><?= t('Team') ?></th>
                                <th><?= t('Translators') ?></th>
                                <th><?= t('Join Requests') ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            foreach ($approved as $l) {
                                ?>
                                <tr data-locale-id="<?= h($l['id']) ?>">
                                    <td><a href="<?= $view->action('details', $l['id']) ?>"><?= h($l['name']) ?></a></td>
                                    <td><?= $l['translators'] ? ('<span class="label label-success">' . $l['translators'] . '</span>') : '<span class="label label-default">0</span>' ?></td>
                                    <td><?= $l['aspiring'] ? ('<span class="label label-success">' . $l['aspiring'] . '</span>') : '<span class="label label-default">0</span>' ?></td>
                                    <td><?php
                                        switch ($l['access']) {
                                            case Access::GLOBAL_ADMIN:
                                                break;
                                            case Access::ADMIN:
                                            case Access::TRANSLATE:
                                                ?><form method="POST" action="<?= $view->action('leave_translation_group', $l['id']) ?>" data-comtra-warning="<?= h(t('Are you sure you want to leave this translation group?'))?>" onsubmit="comtraConfirmPost(this); return false">
                                                    <?php $token->output('comtra_leave_translation_group' . $l['id']); ?>
                                                    <input type="submit" class="btn btn-sm btn-danger pull-right" value="<?= h(t('Leave')) ?>" />
                                                </form><?php
                                                break;
                                            case Access::ASPRIRING:
                                                ?><form method="POST" action="<?= $view->action('leave_translation_group', $l['id']) ?>" data-comtra-warning="<?= h(t('Are you sure you want to cancel your join request?'))?>" onsubmit="comtraConfirmPost(this); return false">
                                                    <?php $token->output('comtra_leave_translation_group' . $l['id']); ?>
                                                    <input type="submit" class="btn btn-sm btn-warning pull-right" value="<?= h(t('Cancel request')) ?>" />
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
        <?php
        if (!empty($requested)) {
            ?>
            <div class="panel panel-default">
                <div class="panel-heading"><h3><?= t('Requested Teams') ?></h3></div>
                <div class="panel-body">
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
                                            ?><form method="POST" action="<?= $view->action('approve_new_locale_request', $l['id']) ?>" data-comtra-warning="<?= h(t('Are you sure you want to approve this new translation group?'))?>" onsubmit="comtraConfirmPost(this); return false">
                                                <?php $token->output('comtra_approve_new_locale_request' . $l['id']); ?>
                                                <input type="submit" class="btn btn-sm btn-success pull-right" value="<?= h(t('Approve')) ?>" />
                                            </form><?php
                                        }
                                        if ($l['canCancel']) {
                                            ?><form method="POST" action="<?= $view->action('cancel_new_locale_request', $l['id']) ?>" data-comtra-warning="<?= h(t('Are you sure you want to cancel this request?'))?>" onsubmit="comtraConfirmPost(this); return false">
                                                <?php $token->output('comtra_cancel_locale_request' . $l['id']); ?>
                                                <input type="submit" class="btn btn-sm btn-danger pull-right" value="<?= h($l['canApprove'] ? t('Reject') : t('Cancel')) ?>" />
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
            <div class="panel panel-default">
                <div class="panel-heading"><h3><?= t('Would you like a new translation group?') ?></h3></div>
                <div class="panel-body">
                    <p><?=
                        t("If you'd like to help us translating to a new language, you can ask us to <a href=\"%s\"%s>create a new Translators Team</a>.",
                        ($me === null) ? '#' : h($askNewTeamURL),
                        ($me === null) ? (' onclick="window.alert(' . h(json_encode('You must sign-in in order to ask the creation of a new translation group.')) . '); return false"') : ''
                    ) ?></p>
                </div>
            </div>
            <?php
        }
        ?>
        <div id="dlg-join-must-login" title="<?= h(t('Login required')) ?>" style="display: none">
            <?= t('You must sign-in in order to join this translation group.') ?>
        </div>
        <?php
        if (isset($highlightLocale)) {
            ?>
            <script>
            $(document).ready(function() {
                var $row = $(<?php echo json_encode("tr[data-locale-id=\"$highlightLocale\"]") ?>);
                if ($row.length === 1) {
                    var offset = $row.offset().top - 20;
                    if (offset > 0) {
                        $(window).scrollTo(offset, 750);
                    }
                    var oldBG = $row.css('background-color');
                    $row
                        .animate({backgroundColor: '#b5efad'}, 1000)
                        .animate({backgroundColor: oldBG}, 1000)
                    ;
                }
            });
            </script>
            <?php
        }
        break;

    case 'teamDetails':
        /* @var CommunityTranslation\Entity\Locale $locale */
        /* @var Concrete\Core\Validation\CSRF\Token $token */
        /* @var Concrete\Core\Localization\Service\Date $dh */
        /* @var CommunityTranslation\Service\User $userService */
        /* @var int $access */
        /* @var array[] $globalAdmins */
        /* @var array[] $admins */
        /* @var array[] $translators */
        /* @var array[] $aspiring */

        ?><h1><?= t('%s Translation Team', $locale->getDisplayName()) ?></h1><?php

        if (!empty($globalAdmins)) {
            ?>
            <div class="well">
                <h4><?= t('Maintainers') ?></h4>
                <?php
                foreach ($globalAdmins as $i => $u) {
                    if ($i > 0) {
                        echo ', ';
                    }
                    echo $userService->format($u['ui']);
                } ?>
            </div>
            <?php
        }

        ?>
        <div class="panel panel-default">
            <div class="panel-heading"><h3><?= t('Team Coordinators') ?></h3></div>
                <div class="panel-body">
                <?php
                if (empty($admins)) {
                    ?><p><?= t('No coordinators so far') ?></p><?php
                } else {
                    ?>
                    <ul class="list-group">
                        <?php
                        foreach ($admins as $u) {
                            ?>
                            <li class="list-group-item">
                                <?php
                                if ($access >= Access::GLOBAL_ADMIN) {
                                    ?>
                                    <div class="pull-right">
                                        <form style="display: inline" method="POST" action="<?= $view->action('change_access', $locale->getID(), $u['ui']->getUserID(), Access::TRANSLATE) ?>">
                                            <?php $token->output('comtra_change_access' . $locale->getID() . '#' . $u['ui']->getUserID() . ':' . Access::TRANSLATE); ?>
                                            <input type="submit" class="btn btn-info" value="<?= t('Set as translator') ?>" />
                                        </form>
                                        <form style="display: inline" method="POST" action="<?= $view->action('change_access', $locale->getID(), $u['ui']->getUserID(), Access::NONE) ?>">
                                            <?php $token->output('comtra_change_access' . $locale->getID() . '#' . $u['ui']->getUserID() . ':' . Access::NONE); ?>
                                            <input type="submit" class="btn btn-info" value="<?= t('Expel') ?>" />
                                        </form>
                                    </div>
                                    <?php
                                }
                                ?>
                                <?= $userService->format($u['ui']) ?>
                                <div class="text-muted"><?= t('Coordinator since: %s', $dh->formatPrettyDateTime($u['since'], true)) ?></div>
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
        <div class="panel panel-default">
        <div class="panel-heading"><h3><?= t('Translators') ?></h3></div>
            <div class="panel-body">
                <?php
                if (empty($translators)) {
                    ?><p><?= t('No translators so far') ?></p><?php
                } else {
                    ?>
                    <ul class="list-group">
                        <?php
                        foreach ($translators as $u) {
                            ?>
                            <li class="list-group-item">
                                <?php
                                if ($access >= Access::ADMIN) {
                                    ?>
                                    <div class="pull-right">
                                        <form style="display: inline" method="POST" action="<?= $view->action('change_access', $locale->getID(), $u['ui']->getUserID(), Access::ADMIN) ?>">
                                            <?php $token->output('comtra_change_access' . $locale->getID() . '#' . $u['ui']->getUserID() . ':' . Access::ADMIN); ?>
                                            <input type="submit" class="btn btn-info" value="<?= t('Set as coordinator') ?>" />
                                        </form>
                                        <form style="display: inline" method="POST" action="<?= $view->action('change_access', $locale->getID(), $u['ui']->getUserID(), Access::NONE) ?>">
                                            <?php $token->output('comtra_change_access' . $locale->getID() . '#' . $u['ui']->getUserID() . ':' . Access::NONE); ?>
                                            <input type="submit" class="btn btn-info" value="<?= t('Expel') ?>" />
                                        </form>
                                    </div>
                                    <?php
                                }
                                ?>
                                <?= $userService->format($u['ui']) ?>
                                <div class="text-muted"><?= t('Translator since: %s', $dh->formatPrettyDateTime($u['since'], true)) ?></div>
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
        <?php
        if (!empty($aspiring)) {
            ?>
            <div class="panel panel-default">
                <div class="panel-heading"><h3><?= t('Join Requests') ?></h3></div>
                <div class="panel-body">
                    <ul class="list-group">
                        <?php
                        foreach ($aspiring as $u) {
                            ?>
                            <li class="list-group-item">
                                <?php
                                if ($access >= Access::ADMIN) {
                                    ?>
                                    <div class="pull-right">
                                        <form style="display: inline" method="POST" action="<?= $view->action('answer_join_request', $locale->getID(), $u['ui']->getUserID(), 1) ?>">
                                            <?php $token->output('comtra_answer_join_request' . $locale->getID() . '#' . $u['ui']->getUserID() . ':1'); ?>
                                            <input type="submit" class="btn btn-info" value="<?= t('Approve') ?>" />
                                        </form>
                                        <form style="display: inline" method="POST" action="<?= $view->action('answer_join_request', $locale->getID(), $u['ui']->getUserID(), 0) ?>">
                                            <?php $token->output('comtra_answer_join_request' . $locale->getID() . '#' . $u['ui']->getUserID() . ':0'); ?>
                                            <input type="submit" class="btn btn-danger" value="<?= t('Deny') ?>" />
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
            <?php
        }
        ?>
        <div class="pull-right">
            <?php
            switch ($access) {
                case Access::NOT_LOGGED_IN:
                    ?><a href="#" class="btn btn-default" onclick="<?= h('window.alert(' . json_encode(t('You must sign-in in order to join this translation group.')) . '); return false') ?>"><?= t('Join this team') ?></a><?php
                    break;
                case Access::NONE:
                    ?>
                    <form style="display: inline" method="POST" action="<?= $view->action('join_translation_group', $locale->getID()) ?>">
                        <?php $token->output('comtra_join_translation_group' . $locale->getID()); ?>
                        <input type="submit" class="btn btn-info" value="<?= t('Join this team') ?>" />
                    </form>
                    <?php
                    break;
                case Access::ASPRIRING:
                    ?>
                    <form style="display: inline" method="POST" action="<?= $view->action('leave_translation_group', $locale->getID()) ?>">
                        <?php $token->output('comtra_leave_translation_group' . $locale->getID()); ?>
                        <input type="submit" class="btn btn-danger" value="<?= t('Cancel join request') ?>" />
                    </form>
                    <?php
                    break;
                case Access::TRANSLATE:
                case Access::ADMIN:
                    ?>
                    <form style="display: inline" method="POST" action="<?= $view->action('leave_translation_group', $locale->getID()) ?>">
                        <?php $token->output('comtra_leave_translation_group' . $locale->getID()); ?>
                        <input type="submit" class="btn btn-danger" value="<?= t('Leave this group') ?>" />
                    </form>
                    <?php
                    break;
            }
            if ($access >= Access::GLOBAL_ADMIN) {
                ?>
                <form style="display: inline" method="POST" action="<?= $view->action('delete_translation_group', $locale->getID()) ?>" data-comtra-warning="<?= h(t('Are you sure you want to PERMANENTLY DELETE this translation group?<br /><br />WARNING! THIS OPERATION CAN\'T BE UNDONE!'))?>" onsubmit="comtraConfirmPost(this); return false">
                    <?php $token->output('comtra_delete_translation_group' . $locale->getID()); ?>
                    <input type="submit" class="btn btn-danger" value="<?= t('Delete') ?>" />
                </form>
                <?php
            }
            ?>
            <a class="btn btn-default" href="<?= URL::to(Page::getCurrentPage()) ?>"><?= t('Back to Team list') ?></a>
        </div>
        <?php
        break;
}
?>
<script>
function comtraConfirmPost(form) {
    var $form = $(form),
        html = $(form).data('comtra-warning') || <?= json_encode(t('Are you sure?')) ?>,
        $dlg = $('<div />').append($('<p />').html(html));
    $(document.body).append($dlg);
    $dlg.dialog({
        close: function() {
            $dlg.remove();
        },
        modal: true,
        resizable: false,
        buttons: [
            {
                text: <?= json_encode(t('Yes')) ?>,
                click: function() {
                    form.submit();
                    $dlg.dialog('close');
                }
            },
            {
                text: <?= json_encode(t('No')) ?>,
                click: function() {
                    $dlg.dialog('close');
                }
            }
        ]
    });
}
</script>