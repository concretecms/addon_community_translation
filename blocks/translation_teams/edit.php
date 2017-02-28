<?php

use Concrete\Core\Support\Facade\Application;

$app = Application::getFacadeApplication();

$form = $app->make('helper/form');
/* @var Concrete\Core\Form\Service\Form $form */
$pageSelector = $app->make('helper/form/page_selector');
/* @var Concrete\Core\Form\Service\Widget\PageSelector $pageSelector */

/* @var string $askNewTeamLinkType */
/* @var int|null $askNewTeamCID */
/* @var string $askNewTeamLink */

?>

<fieldset>

    <legend><?php echo t('Options'); ?></legend>

    <div class="form-group">
        <?php
        $options = [
            0 => t('No link'),
            1 => t('Another Page'),
            2 => t('External URL'),
        ];

        echo $form->label('askNewTeamLinkType', t('Show link to ask creation of a new translation team'));
        echo $form->select('askNewTeamLinkType', [
            'none' => t('No'),
            'cid' => t('Another Page'),
            'link' => t('External URL'),
        ], $askNewTeamLinkType);
        ?>
    </div>

    <div id="askNewTeamLinkType_cid" class="form-group askNewTeamLinkType"<?= ($askNewTeamLinkType === 'cid') ? '' : ' style="display:none"' ?>>
        <?php
        echo $form->label('askNewTeamCID', t('Choose Page:'));
        echo $pageSelector->selectPage('askNewTeamCID', $askNewTeamCID);
        ?>
    </div>

    <div id="askNewTeamLinkType_link" class="form-group askNewTeamLinkType"<?= ($askNewTeamLinkType === 'link') ? '' : ' style="display:none"' ?>>
        <?php
        echo $form->label('askNewTeamLink', t('URL'));
        echo $form->text('askNewTeamLink', $askNewTeamLink);
        ?>
    </div>

</fieldset>
<script>
$(document).ready(function() {
    $('#askNewTeamLinkType').on('change', function() {
        $('.askNewTeamLinkType').hide('fast');
        $('#askNewTeamLinkType_' + this.value).show('fast');
    });
});
</script>
