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
				<label for="translatedThreshold" class="control-label col-sm-3"><?php echo t("Translation threshold"); ?></label>
				<div class="col-sm-2">
					<div class="input-group">
						<?php echo $form->number('translatedThreshold', $translatedThreshold, array('min' => 0, 'max' => 100)); ?>
						<span class="input-group-addon">%</span>
					</div>
				</div>
				<div class="col-sm-5 text-muted">
					<?php echo t('Translations below this value as considered as <i>not translated</i>'); ?>
				</div>
			</div>
		</div>
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
