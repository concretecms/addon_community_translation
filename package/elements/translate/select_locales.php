<?php
defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Package\CommunityTranslation\Controller\SinglePage\Translate;

/* @var Locale[] $officialLocales */
/* @var Locale[] $otherLocales */
/* @var Locale[] $checkedLocales */

?>
<div class="form-group">
	<label for="comtra_locales"><?php echo t2('Show language', 'Show languages', Translate::MAX_LOCALES); ?></label>
	<select<?php echo (Translate::MAX_LOCALES === 1) ? '' : ' multiple="multiple"'; ?> id="comtra_locales" name="locales[]">
		<?php if (!empty($otherLocales)) { ?><optgroup label="<?php echo t('Official Languages')?>"><?php } ?>
			<?php foreach ($officialLocales as $locale) { ?>
				<option value="<?php echo $locale->getID(); ?>"<?php echo in_array($locale, $checkedLocales) ? ' selected="selected"' : ''; ?>><?php echo h($locale->getDisplayName()); ?></option>
		    <?php } ?>
		<?php if (!empty($otherLocales)) { ?></optgroup><?php } ?>
		<?php if (!empty($otherLocales)) { ?>
   			<optgroup label="<?php echo t('Other Languages'); ?>">
   				<?php foreach ($otherLocales as $locale) { ?>
   					<option value="<?php echo $locale->getID(); ?>"<?php echo in_array($locale, $checkedLocales) ? ' selected="selected"' : ''; ?>><?php echo h($locale->getDisplayName()); ?></option>
   			    <?php } ?>
   			</optgroup>
   		<?php } ?>
	</select>
	<?php if (Translate::MAX_LOCALES > 1) { ?><div>
		<a href="#" onclick="$('#comtra_locales option').prop('selected', false); $('#comtra_locales').trigger('change'); return false"><?php echo t('Unselect all'); ?></a>
	</div><?php } ?>
</div>
<script>$(document).ready(function() {

$('select#comtra_locales').css('visibility', '').select2({width: 'resolve'})
	.closest('form').on('submit', function(e) {
		var n = $('select#comtra_locales option:selected').length;
		if (n === 0) {
			alert(<?php echo json_encode((Translate::MAX_LOCALES === 1) ? t('Please select a language') : t('Please select at least one language')); ?>);
			e.preventDefault();
			return false;
		}
		if (n > <?php echo Translate::MAX_LOCALES; ?>) {
			alert(<?php echo json_encode(t2('Please select up to %d language', 'Please select up to %d languages', Translate::MAX_LOCALES)); ?>);
			e.preventDefault();
			return false;
		}
	})
;

});</script>