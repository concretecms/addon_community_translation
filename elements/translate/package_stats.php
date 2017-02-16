<?php
defined('C5_EXECUTE') or die('Access Denied.');

use CommunityTranslation\Locale\Locale;
use CommunityTranslation\Stats\Stats;
use CommunityTranslation\Package\Package;

/* @var Locale[] $locales */
/* @var Package[] $packages */
/* @var Stats[] $stats */
/* @var int $translatedThreshold */

?>
<table class="comtra_list">
	<thead>
		<tr>
			<th><?php echo t('Version'); ?></th>
			<?php foreach ($locales as $locale) {
    ?>
				<th style="width: <?php echo(100 - 10) / count($locales); ?>%"><?php echo h($locale->getDisplayName()); ?></th>
			<?php 
} ?>
		</tr>
	</thead>
	<tbody><?php foreach ($packages as $package) {
    ?>
		<tr>
			<th><?php echo h($package->getVersionDisplayName()); ?></th>
			<?php
            foreach ($locales as $locale) {
                ?><td class="comtra_link" data-link="<?php echo URL::to('/translate/details', 'pkg_' . $package->getHandle(), $package->getVersion(), $locale->getID()); ?>"><?php
                    foreach ($stats as $s) {
                        if ($s->getPackage() === $package && $s->getLocale() === $locale) {
                            $tooltip = sprintf('%s - %s', $package->getVersionDisplayName(), $locale->getDisplayName());
                            $tooltip .= "\n" . t('Total strings: %d', $s->getTotal());
                            $tooltip .= "\n" . t('Translated strings: %d', $s->getTranslated());
                            $tooltip .= "\n" . t('Untranslated strings: %d', $s->getUntranslated());
                            $tooltip .= "\n" . t('Progress: %.2f%%', $s->getPercentage(false)); ?><div title="<?php echo h($tooltip); ?>">
				            	<?php View::element('progress', ['perc' => $s->getPercentage(), 'translatedThreshold' => $translatedThreshold], 'community_translation'); ?>
				            </div><?php
                            break;
                        }
                    } ?></td><?php

            } ?>
			</tr>
		<?php 
} ?>
	</tbody>
</table>
<script>$(document).ready(function() {

$('td.comtra_link').on('click', function() {
	debugger;
	window.location.href = $(this).data('link');
});

});</script>
