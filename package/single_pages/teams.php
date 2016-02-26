<?php
use Concrete\Package\CommunityTranslation\Src\Service\Access;

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var Concrete\Core\Localization\Service\Date $dh */

defined('C5_EXECUTE') or die('Access Denied.');

id(new Area('Opening'))->display($c);

?>
<div class="panel panel-default">
    <div class="panel-heading"><h3><?php echo t('Translators Teams'); ?></h3></div>
    <div class="panel-body">
        <table class="table table-hover">
            <tbody><?php
                foreach ($approved as $l) {
                    ?><tr data-locale-id="<?php echo h($l['id']); ?>">
                        <td><a href="<?php echo URL::to('/teams/details', $l['id']); ?>"><?php echo h($l['name']); ?></a></td>
                        <td><?php
                            if (!isset($me)) {
                                ?><a class="btn btn-sm btn-default pull-right" href="#" onclick="comtraAlert('dlg-join-must-login'); return false"><?php echo t('Join'); ?></a><?php
                            } else {
                                switch ($l['access']) {
                                    case Access::GLOBAL_ADMIN:
                                        break;
                                    case Access::ADMIN:
                                    case Access::TRANSLATE:
                                        ?><form method="POST" action="<?php echo $this->action('leave', $l['id']); ?>" onsubmit="comtraConfirmPost(this); return false">
                                            <?php $token->output('comtra_leave'.$l['id']); ?>
                                            <input type="submit" class="btn btn-sm btn-danger pull-right" value="<?php echo h(t('Leave')); ?>" /> 
                                        </form><?php
                                        break;
                                    case Access::ASPRIRING:
                                        ?><form method="POST" action="<?php echo $this->action('cancel_request', $l['id']); ?>" onsubmit="comtraConfirmPost(this); return false">
                                            <?php $token->output('comtra_cancel_request'.$l['id']); ?>
                                            <input type="submit" class="btn btn-sm btn-warning pull-right" value="<?php echo h(t('Cancel request')); ?>" /> 
                                        </form><?php
                                        break;
                                    case Access::NONE:
                                        ?><form method="POST" action="<?php echo $this->action('join', $l['id']); ?>" onsubmit="comtraConfirmPost(this); return false">
                                            <?php $token->output('comtra_join'.$l['id']); ?>
                                            <input type="submit" class="btn btn-sm btn-success pull-right" value="<?php echo h(t('Join')); ?>" /> 
                                        </form><?php
                                        break;
                                }
                            }
                        ?></td>
                    </tr><?php
                }
            ?></tbody>
        </table>
    </div>
</div>

<?php
if (!empty($requested)) {
    ?>
    <div class="panel panel-default">
        <div class="panel-heading"><h3><?php echo t('Requested Teams'); ?></h3></div>
        <div class="panel-body">
            <table class="table table-hover">
                <tbody><?php
                    foreach ($requested as $l) {
                        ?><tr data-locale-id="<?php echo h($l['id']); ?>">
                            <td>
                                <b><?php echo h($l['name']); ?></b><br />
                                <?php echo tc('Language', 'Requested by: %s', $l['requestedBy'] ? h($l['requestedBy']->getUserName()) : '?'); ?><br />
                                <?php echo tc('Language', 'Requested on: %s', $dh->formatPrettyDateTime($l['requestedOn'], true, true)); ?>
                            </td>
                            <td><?php
                                if ($l['canApprove']) {
                                    ?><form method="POST" action="<?php echo $this->action('approve_locale', $l['id']); ?>" onsubmit="comtraConfirmPost(this); return false">
                                        <?php $token->output('comtra_approve_locale'.$l['id']); ?>
                                        <input type="submit" class="btn btn-sm btn-success pull-right" value="<?php echo h(t('Approve')); ?>" /> 
                                    </form><?php
                                }
                                if ($l['canCancel']) {
                                    ?><form method="POST" action="<?php echo $this->action('cancel_locale', $l['id']); ?>" onsubmit="comtraConfirmPost(this); return false">
                                        <?php $token->output('comtra_cancel_locale'.$l['id']); ?>
                                        <input type="submit" class="btn btn-sm btn-danger pull-right" value="<?php echo h(t('Cancel')); ?>" /> 
                                    </form><?php
                                }
                            ?></td>
                        </tr><?php
                    }
                ?></tbody>
            </table>
        </div>
    </div>
    <?php
}

?>
<div class="panel panel-default">
    <div class="panel-heading"><h3><?php echo t('Would you like a new translation group?'); ?></h3></div>
    <div class="panel-body">
        <p><?php echo t("If you'd like to help us translating concrete5 to a new language, you can ask us to <a href=\"%s\">create a new Translators Team</a>.", URL::to('/teams/create')); ?></p>
    </div>
</div>

<div id="dlg-join-must-login" title="<?php echo h(t('Login required')); ?>" style="display: none">
    <?php echo t('You must sign-in in order to join this translation group.'); ?>
</div>

<script>
function comtraAlert(id) {
    var $dlg = $('#'+id);
    $('#'+id).dialog({
        resizable: false,
        modal: true,
        buttons: [
            {
                text: <?php echo json_encode(t('Close')); ?>,
                click: function() {
                    $dlg.dialog('close');
                }
            }
        ]
    });
}
function comtraConfirmPost(form) {
    var $dlg = $('<div />').append($('<p />').text(<?php echo json_encode(t('Are you sure?')); ?>));
    $(document.body).append($dlg);
    $dlg.dialog({
        close: function() {
            $dlg.remove();
        },
        modal: true,
        resizable: false,
        buttons: [
            {
                text: <?php echo json_encode(t('Yes')); ?>,
                click: function() {
                    form.submit();
                    $dlg.dialog('close');
                }
            },
            {
                text: <?php echo json_encode(t('No')); ?>,
                click: function() {
                    $dlg.dialog('close');
                }
            }          
        ]
    });
}
<?php if (isset($highlightLocale)) { ?> 
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
<?php } ?>

</script>
<?php

id(new Area('Closing'))->display($c);
