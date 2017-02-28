<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var Concrete\Core\Block\View\BlockView $view */

/* @var array|null $initialData */
?>
<select id="comtra-search_package" placeholder="<?= t('Search for packages') ?>">
    <?php
    if ($initialData !== null) {
        ?><option value="<?= h($initialData['handle']) ?>" selected="selected"><?= h($initialData['name']) ?></option><?php
    }
    ?>
</select>
<script>
$(document).ready(function() {

$('#comtra-search_package').selectize({
    valueField: 'handle',
    labelField: 'name',
    create: false,
    load: function(query, callback) {
        if (!query.length) return callback();
        $.ajax({
            cache: true,
            data: {ccm_token: <?= json_encode($token->generate('comtra_search-package'))?>, search: query},
            dataType: 'json',
            method: 'POST',
            url: <?= json_encode((string) $view->action('search')) ?>,
            error: function() {
                callback();
            },
            success: function(res) {
                callback(res);
            }
        });
    }
});

});
</script>