<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */

?>

<ul class="nav nav-tabs" role="tablist">
	<li role="presentation"<?php echo ($section === 'core') ? ' class="active"' : '' ?>><a href="<?php echo $this->action('core'); ?>" role="tab"><?php echo t('concrete5 core')?></a></li>
	<li role="presentation"<?php echo ($section === 'packages') ? ' class="active"' : '' ?>><a href="<?php echo $this->action('packages'); ?>" role="tab"><?php echo t('Packages')?></a></li>
</ul>

<div class="tab-content"><div class="tab-pane active"><?php
switch ($section) {


    case 'core':
        ?>
<form id="comtra_search" method="POST" action="<?php echo $this->action('core'); ?>">
	<?php $token->output('comtra_core'); ?>
	<?php View::element('translate/select_locales', array('officialLocales' => $officialLocales, 'otherLocales' => $otherLocales, 'checkedLocales' => $checkedLocales), 'community_translation'); ?>
	<button type="submit" class="btn btn-primary"><?php echo t('Search');?></button>
</form>
<?php
if (isset($packages)) {
    ?><h3><?php echo h('Core versions'); ?></h3><?php
    View::element('translate/package_stats', array('locales' => $checkedLocales, 'packages' => $packages, 'stats' => $stats, 'translatedThreshold' => $translatedThreshold), 'community_translation');
}
    break;

    case 'packages':
?>
<form id="comtra_search" method="POST" action="<?php echo $this->action('packages'); ?>">
	<?php $token->output('comtra_packages'); ?>
	<?php View::element('translate/select_locales', array('officialLocales' => $officialLocales, 'otherLocales' => $otherLocales, 'checkedLocales' => $checkedLocales), 'community_translation'); ?>
	<div class="form-group">
		<label for="comtra_package"><?php echo t('Search package'); ?></label>
		<input type="text" class="form-control" id="comtra_package" name="package" placeholder="<?php echo t('Package handle'); ?>" required="required" value="<?php echo isset($searchHandle) ? h($searchHandle) : ''; ?>" />
	</div>
	<input type="hidden" name="comtra_pickedpackage" id="comtra_pickedpackage" />
	<button type="submit" class="btn btn-primary"><?php echo t('Search');?></button>
</form>
<?php
if (isset($foundPackages)) {
    if (empty($foundPackages)) {
        ?><div class="alert alert-warning" role="alert">
        	<?php echo t('No packages found'); ?>
        </div><?php
    } else {
        ?>
        <h3><?php echo t('Found packages'); ?></h3>
        <ul class="list-group">
        	<?php foreach ($foundPackages as $handle) { ?>
  				<li class="list-group-item">
  					<form id="comtra_search" method="POST" action="<?php echo $this->action('packages'); ?>" style="display: inline">
  						<?php $token->output('comtra_packages'); ?>
  						<?php foreach ($checkedLocales as $locale) { ?>
  							<input type="hidden" name="locales[]" value="<?php echo h($locale->getID()); ?>">
  						<?php } ?>
  						<input type="hidden" name="package" value="<?php echo h($searchHandle); ?>" />
  						<input type="hidden" name="pickedpackage" value="<?php echo h($handle); ?>" />
  						<a href="#" class="comtra_pickpackage"><?php echo h($handle); ?></a>
  					</form>
  					
  				</li>
  			<?php } ?>
		</ul>
		<script>$(document).ready(function() {
			$('a.comtra_pickpackage').on('click', function(e) {
				e.preventDefault();
				$(this).closest('form').submit();
			});
		});</script>
		<?php
    }
} elseif (isset($packages)) {
    ?><h3><?php echo h(t('Versions of %s', $packages[0]->getHandle())); ?></h3><?php
    View::element('translate/package_stats', array('locales' => $checkedLocales, 'packages' => $packages, 'stats' => $stats, 'translatedThreshold' => $translatedThreshold), 'community_translation');
}
        break;
}

?></div></div>

