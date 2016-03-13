<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var string $value */

if ($value === '') {
    echo t('No current API token');
    ?><label><input type="checkbox" name="operation" value="generate" /> <?php echo t('Generate'); ?><?php
} else {
    ?><code><?php echo h($value); ?></code>
    <label><input type="radio" name="operation" value="" checked="checked" /> Keep</label>
    <label><input type="radio" name="operation" value="generate" /> Generate new</label>
    <label><input type="radio" name="operation" value="remove" /> Remove</label>
    <?php
}
