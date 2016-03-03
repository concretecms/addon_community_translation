<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */

/* @var Concrete\Package\CommunityTranslation\Src\Locale\Locale[] $locales */
/* @var Concrete\Package\CommunityTranslation\Src\Locale\Locale $locale */
/* @var Concrete\Package\CommunityTranslation\Src\Package\Package $package */
/* @var Concrete\Package\CommunityTranslation\Src\Package\Package[] $allVersions */
/* @var Concrete\Package\CommunityTranslation\Src\Stats\Stats[] $stats */
/* @var Concrete\Core\Localization\Service\Date $dh */

?>
<div class="row">
	<div class="col-sm-7">
		<h3><?php echo t('Details for %s', (($package->getHandle() === '') ? t('concrete5') : $package->getHandle()).' '.$package->getVersionDisplayName()) ?></h3>
	</div>
	<div class="col-sm-5">
		<table class="table table-condensed">
			<tbody>
				<tr>
					<td><?php echo t('Other languages'); ?></td>
					<td><select onchange="if (this.value) window.location.href = this.value">
						<?php foreach ($locales as $o) { ?>
							<option<?php echo ($o->getID() === $locale->getID()) ? ' value="" selected="selected"' : (' value="'.h($this->action('', 'pkg_'.$package->getHandle(), $package->getVersion(), $o->getID())).'"'); ?>><?php echo h($o->getDisplayName()); ?></option>
						<?php } ?>
					</select></td>
				</tr>
				<?php if (count($allVersions) > 1) { ?>
					<tr>
						<td><?php echo t('Other versions'); ?></td>
						<td><select onchange="if (this.value) window.location.href = this.value">
							<?php foreach ($allVersions as $o) { ?>
								<option<?php echo ($o->getVersion() === $package->getVersion()) ? ' value="" selected="selected"' : (' value="'.h($this->action('', 'pkg_'.$o->getHandle(), $o->getVersion(), $locale->getID())).'"'); ?>><?php echo h($o->getVersionDisplayName()); ?></option>
							<?php } ?>
						</select></td>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>
<div class="panel panel-primary">
	<div class="panel-heading"><?php echo h($locale->getDisplayName()); ?></div>
	<div class="panel-body">
		<a href="#" onclick="alert('@todo');return false" class="btn btn-default"><?php echo t('Translate online'); ?></a>
		<a href="#" onclick="alert('@todo');return false" class="btn btn-default"><?php echo t('Download .po file for offline translation'); ?></a>
		<a href="#" onclick="alert('@todo');return false" class="btn btn-default"><?php echo t('Download .mo file to use it'); ?></a>
		<a href="#" onclick="alert('@todo');return false" class="btn btn-default"><?php echo t('Upload translated file'); ?></a>
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
		<tbody><?php foreach ($stats as $s) { ?>
			<?php $current = $s->getLocale() === $locale; ?>
			<tr>
				<td>
					<?php if ($s->getLocale() === $locale) { ?>
						<b>
					<?php } else { ?>
						<a href="<?php echo $this->action('', 'pkg_'.$package->getHandle(), $package->getVersion(), $s->getLocale()->getID()); ?>">
					<?php } ?>
					<?php echo h($s->getLocale()->getDisplayName()); ?>
					<?php if ($s->getLocale() !== $locale) { ?>
						</a>
					<?php } else { ?>
						</b>
					<?php } ?>
				</td>
				<td title="<?php echo sprintf('%.02f%%', $s->getPercentage(false)); ?>"><?php View::element('progress', array('perc' => $s->getPercentage(), 'translatedThreshold' => $translatedThreshold), 'community_translation'); ?></td>
				<td><?php echo $s->getTranslated(); ?></td>
				<td><?php echo $s->getUntranslated(); ?></td>
				<td><?php echo $dh->formatPrettyDateTime($s->getLastUpdated(), true, true); ?></td>
			</tr>
		<?php } ?></tbody>
	</table>
</fieldset>
