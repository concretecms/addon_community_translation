<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */

id(new Area('Opening'))->display($c);

?>
<fieldset>
	<legend><?php echo t('Fill-in already translated strings')?></legend>
	<p><?php echo t("Here you can upload a ZIP file containing a package, or a .pot/.po/.mo file."); ?></p>
	<p><?php echo t("You'll get back a ZIP file containing all the translatable strings found (.pot file) and the translated strings we already know for the languages that you specify (as source .po files or as compiled .mo files)."); ?></p>
	<form method="POST" action="<?php echo $this->action('fill_in'); ?>" enctype="multipart/form-data" target="comtra_process">
		<?php $token->output('comtra_fill_in'); ?>
		<div class="form-group">
			<label class="control-label" for="comtra_file"><?php echo t('File to be processed'); ?></label>
			<input class="form-control" type="file" name="file" id="comtra_file" required="required" />
		</div>
		<div class="form-group">
			<label class="control-label"><?php echo t('File to be generated'); ?></label>
			<br />
			<label style="font-weight: normal"><input type="checkbox" name="include-pot" value="1" checked="checked" /> <?php echo t('Include list of found translatable strings (.pot file)'); ?></label>
			<br />
			<label style="font-weight: normal"><input type="checkbox" name="include-po" value="1" checked="checked" /> <?php echo t('Include source translations (.po files)'); ?></label>
			<br />
			<label style="font-weight: normal"><input type="checkbox" name="include-mo" value="1" checked="checked" /> <?php echo t('Include compiled translations (.mo files)'); ?></label>
		</div>
		<div class="form-group">
			<div class="control-label">
				<label for="comtra_translatedLocales"><?php echo t('Standard lanuages'); ?></label>
				<div class="pull-right">
					<a href="#" onclick="$('#comtra_translatedLocales option').prop('selected', true); return false"><?php echo tc('Languages', 'Select all'); ?></a>
					|
					<a href="#" onclick="$('#comtra_translatedLocales option').prop('selected', false); return false"><?php echo tc('Languages', 'Select none'); ?></a>
				</div>
			</div>
			<select class="form-control" multiple="multiple" name="translatedLocales[]" id="comtra_translatedLocales">
				<?php foreach ($translatedLocales as $locale) { ?>
					<option value="<?php echo h($locale->getID()); ?>" selected="selected"><?php echo h($locale->getDisplayName()); ?></option>
				<?php } ?>
			</select>
		</div>
		<?php if (!empty($untranslatedLocales)) { ?>
			<div class="form-group">
				<div class="control-label">
					<label for="comtra_untranslatedLocales"><?php echo t('Additional lanuages'); ?></label>
					<div class="pull-right">
						<a href="#" onclick="$('#comtra_untranslatedLocales option').prop('selected', true); return false"><?php echo tc('Languages', 'Select all'); ?></a>
						|
						<a href="#" onclick="$('#comtra_untranslatedLocales option').prop('selected', false); return false"><?php echo tc('Languages', 'Select none'); ?></a>
					</div>
					<select class="form-control" multiple="multiple" name="untranslatedLocales[]" id="comtra_untranslatedLocales">
						<?php foreach ($untranslatedLocales as $locale) { ?>
							<option value="<?php echo h($locale->getID()); ?>"><?php echo h($locale->getDisplayName()); ?></option>
						<?php } ?>
					</select>
				</div>
			</div>
		<?php } ?>
		<input class="btn btn-primary" type="submit" value="<?php echo t('Submit'); ?>" />
	</form>
	<iframe name="comtra_process" id="comtra_process" style="display: none"></iframe>
</fieldset>
<script>$(document).ready(function() {

var $localeChecks = $('input[name=include-po],input[name=include-mo]');
$localeChecks.on('change', function() {
	var askLocales = $localeChecks.filter(':checked').length > 0;
	var $lists = $('#comtra_translatedLocales,#comtra_untranslatedLocales');
	var $labels = $('label[for=comtra_translatedLocales],label[for=comtra_untranslatedLocales]');
	if (askLocales) {
		$lists.removeAttr('disabled');
		$labels.removeClass('text-muted');
	} else {
		$lists.attr('disabled', 'disabled');
		$labels.addClass('text-muted');		
	}
}).trigger('change');

});</script>
<?php

id(new Area('Closing'))->display($c);
