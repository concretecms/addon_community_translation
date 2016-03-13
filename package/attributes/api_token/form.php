<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Attribute\View $view */
/* @var string $value */

if ($value === '') {
    ?><div class="ccm-attribute checkbox">
    	<label>
    		<input type="checkbox" name="<?php echo $view->field('operation'); ?>" value="generate">
			<span><?php echo t('Generate API Token'); ?></span>
		</label>
	</div><?php
} else {
    ?>
    <div class="ccm-attribute">
    	<code><?php echo h($value); ?></code>
    	<p class="help-block">
	    	<label><input type="radio" name="<?php echo $view->field('operation'); ?>" value="" checked="checked" /> <?php echo t('Keep this API Token'); ?></label>
    		<label><input type="radio" name="<?php echo $view->field('operation'); ?>" value="generate" /> <?php echo t('Generate new API Token'); ?></label>
    		<label><input type="radio" name="<?php echo $view->field('operation'); ?>" value="remove" /> <?php echo t('Remove API Token'); ?></label>
    	</p>
    </div>
    <?php
}
