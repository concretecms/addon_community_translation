<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var Concrete\Core\Form\Service\Form $form */

?>

<form method="post" class="form-horizontal" action="<?php echo $view->action('submit'); ?>" onsubmit="if (this.already) return false; this.already = true">

	<?php echo $token->output('ct-options-save'); ?>

	<fieldset>
		<legend><?php echo t('Translations'); ?></legend>
		<div class="row">
			<div class="form-group">
				<div class="col-sm-3 control-label">
					<label for="translatedThreshold" class="launch-tooltip" data-html="true" title="<?php echo t('Translations below this value as considered as <i>not translated</i>'); ?>">
						<?php echo t("Translation threshold"); ?>
					</label>
				</div>
				<div class="col-sm-2">
					<div class="input-group">
						<?php echo $form->number('translatedThreshold', $translatedThreshold, array('min' => 0, 'max' => 100)); ?>
						<span class="input-group-addon">%</span>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<label for="downloadAccess" class="control-label col-sm-3"><?php echo t('Download access via web'); ?></label>
			<div class="col-sm-7">
				<?php echo $form->select(
				    'downloadAccess',
				    array(
				        'anyone' => t('Anyone can download translations'),
				        'members' => t('Only registered users can download translations'),
				        'translators' => t('Only translators of a language can download the associated translations'),
				    ),
				    $downloadAccess
				); ?>
			</div>
		</div>
	</fieldset>

	<fieldset>
		<legend><?php echo t('API Access'); ?></legend>
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
								href="<?php echo URL::to('/ccm/system/dialogs/group/search'); ?>"
								dialog-title="<?php echo h(t('Select group')); ?>"
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
		<legend><?php echo t('Paths'); ?></legend>
		<div class="row">
			<div class="form-group">
				<label for="tempDir" class="control-label col-sm-3"><?php echo t("Temporary directory"); ?></label>
				<div class="col-sm-7">
					<?php echo $form->text('tempDir', $tempDir, array('placeholder' => t('Default temporary directory: %s', h(Core::make('helper/file')->getTemporaryDirectory())))); ?>
				</div>
			</div>
		</div>
	</fieldset>

	<fieldset>
		<legend><?php echo t('Notifications'); ?></legend>
		<div class="row">
			<div class="form-group">
				<label for="notificationsSenderAddress" class="control-label col-sm-3"><?php echo t("Sender email address"); ?></label>
				<div class="col-sm-7">
					<?php echo $form->email('notificationsSenderAddress', $notificationsSenderAddress, array('placeholder' => t('Default email address: %s', h(Config::get('concrete.email.default.address'))))); ?>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="form-group">
				<label for="notificationsSenderName" class="control-label col-sm-3"><?php echo t("Sender name"); ?></label>
				<div class="col-sm-7">
					<?php echo $form->text('notificationsSenderName', $notificationsSenderName, array('placeholder' => t('Default sender name: %s', h(Config::get('concrete.email.default.name'))))); ?>
				</div>
			</div>
		</div>
	</fieldset>

	<div class="ccm-dashboard-form-actions-wrapper">
		<div class="ccm-dashboard-form-actions">
			<a href="<?php echo URL::to('/dashboard/system/https'); ?>" class="btn btn-default pull-left"><?php echo t('Cancel'); ?></a>
			<input type="submit" class="btn btn-primary pull-right btn ccm-input-submit" value="<?php echo t('Save'); ?>">
		</div>
	</div>

</form>

<script>$(document).ready(function() {

var currentApiGroupID;
var _pickApiGroup = _.template($('script[data-template=pick-api-group]').html());
$('#pick-api-fields')
	.append(_pickApiGroup(<?php echo json_encode(array(
	    'field' => array(
	       'id' => 'apiAccess_stats',
	       'description' => t('Retrieve statistical data'),
	    ),
	    'value' => array(
	       'id' => $apiAccess_stats['gID'],
	       'description' => $apiAccess_stats['gName'],
	    )
	)); ?>))
	.append(_pickApiGroup(<?php echo json_encode(array(
	    'field' => array(
	       'id' => 'apiAccess_download',
	       'description' => t('Download translations'),
	    ),
	    'value' => array(
	       'id' => $apiAccess_download['gID'],
	       'description' => $apiAccess_download['gName'],
	    )
	)); ?>))
	.append(_pickApiGroup(<?php echo json_encode(array(
	    'field' => array(
	       'id' => 'apiAccess_import_packages',
	       'description' => t('Import translations from packages'),
	    ),
	    'value' => array(
	       'id' => $apiAccess_import_packages['gID'],
	       'description' => $apiAccess_import_packages['gName'],
	    )
	)); ?>))
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
