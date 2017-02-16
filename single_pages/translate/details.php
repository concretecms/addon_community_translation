<?php
defined('C5_EXECUTE') or die('Access Denied.');

use CommunityTranslation\Service\Access;

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */

/* @var CommunityTranslation\Locale\Locale[] $locales */
/* @var CommunityTranslation\Locale\Locale $locale */
/* @var CommunityTranslation\Package\Package $package */
/* @var CommunityTranslation\Package\Package[] $allVersions */
/* @var CommunityTranslation\Stats\Stats[] $stats */
/* @var Concrete\Core\Localization\Service\Date $dh */

?>
<div class="row">
	<div class="col-sm-7">
		<h3><?php echo h(t('Details for %s', $package->getDisplayName())); ?></h3>
	</div>
	<div class="col-sm-5">
		<table class="table table-condensed">
			<tbody>
				<tr>
					<td><?php echo t('Other languages'); ?></td>
					<td><select onchange="if (this.value) window.location.href = this.value">
						<?php foreach ($locales as $o) {
    ?>
							<option<?php echo ($o->getID() === $locale->getID()) ? ' value="" selected="selected"' : (' value="' . h($this->action('', 'pkg_' . $package->getHandle(), $package->getVersion(), $o->getID())) . '"'); ?>><?php echo h($o->getDisplayName()); ?></option>
						<?php 
} ?>
					</select></td>
				</tr>
				<?php if (count($allVersions) > 1) {
    ?>
					<tr>
						<td><?php echo t('Other versions'); ?></td>
						<td><select onchange="if (this.value) window.location.href = this.value">
							<?php foreach ($allVersions as $o) {
        ?>
								<option<?php echo ($o->getVersion() === $package->getVersion()) ? ' value="" selected="selected"' : (' value="' . h($this->action('', 'pkg_' . $o->getHandle(), $o->getVersion(), $locale->getID())) . '"'); ?>><?php echo h($o->getVersionDisplayName()); ?></option>
							<?php 
    } ?>
						</select></td>
					</tr>
				<?php 
} ?>
			</tbody>
		</table>
	</div>
</div>
<div class="panel panel-primary">
	<div class="panel-heading"><?php echo h($locale->getDisplayName()); ?></div>
	<div class="panel-body">
		<div id="comtra-panel-main">
    		<a href="#" class="btn btn-default comtra-translate" id="comtra-translate-online"><?php echo t('Translate online'); ?></a>
    		<a href="#" class="btn btn-default comtra-download" data-format="po"><?php echo t('Download .po file for offline translation'); ?></a>
    		<a href="#" class="btn btn-default comtra-download" data-format="mo"><?php echo t('Download .mo file to use it'); ?></a>
    		<a href="#" class="btn btn-default comtra-translate" id="comtra-translate-upload"><?php echo t('Upload translated file'); ?></a>
		</div>
		<?php if ($translationsAccess >= Access::TRANSLATE) {
    ?>
			<form id="comtra-translate-upload-form" style="display: none" method="POST" enctype="multipart/form-data" action="<?php echo $this->action('upload', $package->getID(), $locale->getID()); ?>">
				<?php $token->output('comtra-upload-' . $package->getID() . '@' . $locale->getID()); ?>
				<div class="row">
					<div class="col-md-8">
						<input type="file" name="translations-file" class="btn btn-default" required="required" />
					</div>
					<div class="col-md-4" style="text-align: right">
						<a id="comtra-translate-upload-cancel" class="btn btn-default" href="#"><?php echo t('Cancel'); ?></a>
						<input type="submit" class="btn btn-primary" value="<?php echo t('Submit'); ?>" />
					</div>
				</div>
			</form>
		<?php 
} ?>
	</div>
</div>
<fieldset>
	<legend><?php echo t('Statistics'); ?></legend>
	<table class="table table-condensed table-striped table-hover">
		<thead>
			<tr>
				<th><?php echo t('Language'); ?></th>
				<th><?php echo t('Progress'); ?></th>
				<th><?php echo t('Translated strings'); ?></th>
				<th><?php echo t('Untranslated strings'); ?></th>
				<th><?php echo t('Last updated'); ?></th>
			</tr>
		</thead>
		<tbody><?php foreach ($stats as $s) {
    ?>
			<?php $current = $s->getLocale() === $locale; ?>
			<tr>
				<td>
					<?php if ($s->getLocale() === $locale) {
        ?>
						<b>
					<?php 
    } else {
        ?>
						<a href="<?php echo $this->action('', 'pkg_' . $package->getHandle(), $package->getVersion(), $s->getLocale()->getID()); ?>">
					<?php 
    } ?>
					<?php echo h($s->getLocale()->getDisplayName()); ?>
					<?php if ($s->getLocale() !== $locale) {
        ?>
						</a>
					<?php 
    } else {
        ?>
						</b>
					<?php 
    } ?>
				</td>
				<td title="<?php echo sprintf('%.02f%%', $s->getPercentage(false)); ?>"><?php View::element('progress', ['perc' => $s->getPercentage(), 'translatedThreshold' => $translatedThreshold], 'community_translation'); ?></td>
				<td><?php echo $s->getTranslated(); ?></td>
				<td><?php echo $s->getUntranslated(); ?></td>
				<td><?php echo $dh->formatPrettyDateTime($s->getLastUpdated(), true, true); ?></td>
			</tr>
		<?php 
} ?></tbody>
	</table>
</fieldset>

<?php if ($cannotDownloadTranslationsBecause === '') {
    ?>
	<form id="comtra-download-form" class="hide" method="POST" action="<?php echo $this->action('download', $package->getID(), $locale->getID()); ?>" target="comtra-download-frame">
		<?php $token->output('comtra-download-' . $package->getID() . '@' . $locale->getID()); ?>
		<input type="hidden" name="format" value=""/> 
	</form>
	<iframe class="hide" id="comtra-download-frame" name="comtra-download-frame"></iframe>
<?php 
} ?>

<script>$(document).ready(function() {

$('.comtra-download').on('click', function(e) {
    <?php if ($cannotDownloadTranslationsBecause !== '') {
    ?>
		window.alert(<?php echo json_encode($cannotDownloadTranslationsBecause); ?>);
    <?php 
} else {
    ?>
    	$('#comtra-download-form')
    		.find('input[name="format"]').val($(this).data('format')).end()
    		.submit()
    	;
    <?php 
} ?>
	e.preventDefault();
	return false;
});

function showPanel(id, oldID, out) {
	var duration = 100;
	$(oldID).hide(
		'slide',
		{direction: out ? 'up' : 'down'},
		duration,
		function() {
			$(id).show(
				'slide',
				{direction: out ? 'down' : 'up'},
				duration
			);
		}
	);
}
<?php if ($translationsAccess >= Access::TRANSLATE) {
    ?>
	$('#comtra-translate-online').attr('href', <?php echo json_encode((string) URL::to('/translate/online', $package->getID(), $locale->getID())); ?>);
	$('#comtra-translate-upload').on('click', function(e) {
		showPanel('#comtra-translate-upload-form', '#comtra-panel-main', true);
		e.preventDefault();
		return false;
	});
	$('#comtra-translate-upload-cancel').on('click', function(e) {
		showPanel('#comtra-panel-main', '#comtra-translate-upload-form', false);
		e.preventDefault();
		return false;
	});
	
<?php 
} else {
    ?>
	$('.comtra-translate').on('click', function(e) {
		window.alert(<?php echo json_encode(
            ($translationsAccess <= Access::NOT_LOGGED_IN) ?
            t('You must log in and you have to be a member of the %s translation team in order to help us translating it', $locale->getDisplayName()) :
            t('You must be a member of the %s translation team in order to help us translating it', $locale->getDisplayName())
        ); ?>);
		e.preventDefault();
		return false;
	});
<?php 
} ?>

});</script>
