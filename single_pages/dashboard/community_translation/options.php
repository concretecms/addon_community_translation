<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var Concrete\Core\Form\Service\Form $form */

?>

<form method="post" class="form-horizontal" action="<?= $view->action('submit') ?>" onsubmit="if (this.already) return false; this.already = true">

	<?php $token->output('ct-options-save') ?>

	<fieldset>
		<legend><?= t('Translations') ?></legend>
        <div class="row">
            <div class="form-group">
                <div class="col-sm-3 control-label">
                    <label for="sourceLocale">
                        <?= t('Source locale') ?>
                    </label>
                </div>
                <div class="col-sm-2">
                    <?= $form->text('sourceLocale', $sourceLocale, ['required' => 'required', 'pattern' => '[a-z]{2,3}(_([A-Z]{2}|[0-9]{3}))?']) ?>
                </div>
            </div>
        </div>
		<div class="row">
			<div class="form-group">
				<div class="col-sm-3 control-label">
					<label for="translatedThreshold" class="launch-tooltip" data-html="true" title="<?= t('Translations below this value as considered as <i>not translated</i>') ?>">
						<?= t('Translation threshold') ?>
					</label>
				</div>
				<div class="col-sm-2">
					<div class="input-group">
						<?= $form->number('translatedThreshold', $translatedThreshold, ['min' => 0, 'max' => 100, 'required' => 'required']) ?>
						<span class="input-group-addon">%</span>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<label for="downloadAccess" class="control-label col-sm-3"><?= t('Download access via web') ?></label>
			<div class="col-sm-7">
				<?= $form->select(
                    'downloadAccess',
                    [
                        'anyone' => t('Anyone can download translations'),
                        'members' => t('Only registered users can download translations'),
                        'translators' => t('Only translators of a language can download the associated translations'),
                    ],
                    $downloadAccess
                ) ?>
			</div>
		</div>
	</fieldset>

	<fieldset>
		<legend><?= t('API Access') ?></legend>
		<div id="pick-api-fields"></div>
		<script type="text/template" data-template="pick-api-group">
			<div class="row">
				<div class="form-group">
					<div class="col-sm-3 control-label">
						<label for="<%=field.id%>"><%=field.description%></label>
					</div>
					<div class="col-sm-7">
							<input type="hidden" name="<%=field.id%>" value="<%=value.id%>" />
							<a class="form-control"
								id="<%=field.id%>"
								class="input-group-addon btn btn-default btn-xs"
								data-button="pick-api-group"
								dialog-width="640"
								dialog-height="480"
								dialog-modal="true"
								href="<?= h(URL::to('/ccm/dialogs/group/search')) ?>"
								dialog-title="<?= h(t('Select group')) ?>"
								dialog-modal="true"
								data-field="<%=field.id%>"
							><%=value.description%></a>
						</div>
					</div>
				</div>
			</div>
		</script>
	</fieldset>

	<fieldset>
		<legend><?= t('Paths') ?></legend>
        <div class="row">
            <div class="form-group">
                <label for="apiEntryPoint" class="control-label col-sm-3"><?= t('API Entry Point') ?></label>
                <div class="col-sm-7">
                    <?= $form->text('apiEntryPoint', $apiEntryPoint, ['required' => 'required']) ?>
                </div>
            </div>
        </div>
		<div class="row">
			<div class="form-group">
				<label for="tempDir" class="control-label col-sm-3"><?= t('Temporary directory') ?></label>
				<div class="col-sm-7">
					<?= $form->text('tempDir', $tempDir, ['placeholder' => t('Default temporary directory: %s', h(Core::make('helper/file')->getTemporaryDirectory()))]) ?>
				</div>
			</div>
		</div>
	</fieldset>


    <fieldset>
        <legend><?= t('System') ?></legend>
        <div class="row">
            <div class="form-group">
                <label for="tempDir" class="control-label col-sm-3"><?= t('Strings Parser') ?></label>
                <div class="col-sm-7">
                    <?= $form->select('parser', $parsers, $defaultParser, ['required' => 'required']) ?>
                </div>
            </div>
        </div>
    </fieldset>

	<fieldset>
		<legend><?= t('Notifications') ?></legend>
		<div class="row">
			<div class="form-group">
				<label for="notificationsSenderAddress" class="control-label col-sm-3"><?= t('Sender email address') ?></label>
				<div class="col-sm-7">
					<?= $form->email('notificationsSenderAddress', $notificationsSenderAddress, ['placeholder' => t('Default email address: %s', h(Config::get('concrete.email.default.address')))]) ?>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="form-group">
				<label for="notificationsSenderName" class="control-label col-sm-3"><?= t('Sender name') ?></label>
				<div class="col-sm-7">
					<?= $form->text('notificationsSenderName', $notificationsSenderName, ['placeholder' => t('Default sender name: %s', h(Config::get('concrete.email.default.name')))]) ?>
				</div>
			</div>
		</div>
	</fieldset>

	<div class="ccm-dashboard-form-actions-wrapper">
		<div class="ccm-dashboard-form-actions">
			<a href="<?= URL::to('/dashboard/community_translation') ?>" class="btn btn-default pull-left"><?= t('Cancel') ?></a>
			<input type="submit" class="btn btn-primary pull-right btn ccm-input-submit" value="<?= t('Save') ?>">
		</div>
	</div>

</form>

<script>$(document).ready(function() {

var currentApiGroupID;
var _pickApiGroup = _.template($('script[data-template=pick-api-group]').html());
$('#pick-api-fields')
	.append(_pickApiGroup(<?= json_encode([
        'field' => [
           'id' => 'apiAccess_stats',
           'description' => t('Retrieve statistical data'),
        ],
        'value' => [
           'id' => $apiAccess_stats['gID'],
           'description' => $apiAccess_stats['gName'],
        ],
    ]) ?>))
	.append(_pickApiGroup(<?= json_encode([
        'field' => [
           'id' => 'apiAccess_download',
           'description' => t('Download translations'),
        ],
        'value' => [
           'id' => $apiAccess_download['gID'],
           'description' => $apiAccess_download['gName'],
        ],
    ]) ?>))
	.append(_pickApiGroup(<?= json_encode([
        'field' => [
           'id' => 'apiAccess_import_packages',
           'description' => t('Import translations from packages'),
        ],
        'value' => [
           'id' => $apiAccess_import_packages['gID'],
           'description' => $apiAccess_import_packages['gName'],
        ],
    ]) ?>))
	.append(_pickApiGroup(<?= json_encode([
        'field' => [
           'id' => 'apiAccess_updatePackageTranslations',
           'description' => t('Update package translations'),
        ],
        'value' => [
           'id' => $apiAccess_updatePackageTranslations['gID'],
           'description' => $apiAccess_updatePackageTranslations['gName'],
        ],
    ]) ?>))
;
ConcreteEvent.subscribe('SelectGroup', function (e, data) {
	if (data && data.gID) {
		$('input[name="' + currentApiGroupID + '"]').val(data.gID);
		$('#' + currentApiGroupID).text(data.gName);
		jQuery.fn.dialog.closeTop();
	}
});
$('a[data-button=pick-api-group]').dialog().on('click', function() {
	currentApiGroupID = $(this).data('field');
});

});</script>
