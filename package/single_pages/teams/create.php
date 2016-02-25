<?php

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */

defined('C5_EXECUTE') or die('Access Denied.');

id(new Area('Opening'))->display($c);

if (!isset($skip) || !$skip) {

if (!isset($preselectLanguage)) {
	$preselectLanguage = '';
}

if (!isset($preselectCountry)) {
	$preselectCountry = '';
}
if (!isset($precheckNoCountry)) {
    $precheckNoCountry = false;
}
if (!isset($precheckApprove)) {
    $precheckApprove = true;
}
?>
<div class="panel panel-default">
    <div class="panel-heading"><h3><?php echo t('New Translators Team'); ?></h3></div>
    <div class="panel-body">
        <form class="form-stacked" method="POST" action="<?php echo $this->action('create_locale'); ?>">
            <?php $token->output('comtra_create_locale'); ?>
            <div class="form-group">
                <label class="control-label" for="language"><?php echo t('Language'); ?></label>
                <select name="language" id="language" class="form-control" required="required">
                    <option value=""<?php echo $preselectLanguage ? '' : ' selected="selected"'; ?>><?php echo t('Please select'); ?></option>
                    <?php
                    foreach ($languages as $id => $name) {
                        ?><option value="<?php echo h($id); ?>"<?php echo ($preselectLanguage === $id) ? ' selected="selected"' : ''; ?>><?php echo h($name); ?></option><?php
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label class="control-label" for="country"><?php echo t('Country'); ?></label>
                <select name="country" id="country" class="form-control" required="required">
                    <option value=""<?php echo $preselectCountry ? '' : ' selected="selected"'; ?>><?php echo t('Please select'); ?></option>
                    <?php
                    foreach ($countries as $id => $name) {
                        ?><option value="<?php echo h($id); ?>"<?php echo ($preselectCountry === $id) ? ' selected="selected"' : ''; ?>><?php echo h($name); ?></option><?php
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label class="control-label"><?php echo t('Options'); ?></label>
                <div class="checkbox">
                    <label><input type="checkbox" id="no-country"<?php echo $precheckNoCountry ? ' checked="checked"': ''; ?>> <?php echo t('This language is not Country-specific'); ?></label>
                </div>
                <?php if ($canApprove) { ?>
                    <div class="checkbox">
                        <label><input type="checkbox" id="approve"<?php echo $precheckApprove ? ' checked="checked"': ''; ?>> <?php echo t('Enable this locale immediately'); ?></label>
                    </div>
                <?php } ?>
            </div>
            <div class="form-actions">
                <input type="submit" class="btn btn-primary" value="<?php echo t('Submit request'); ?>">
            </div>
        </form>
    </div>
</div>

<script>$(document).ready(function() {
var $language = $('#language'), $country = $('#country');
var originalCountries = null, sortCache = {};
$language.on('change', function() {
    var language = this.value;
    if (!language) {
        return;
    }
    if (originalCountries === null) {
        originalCountries = [];
        $country.find('option').each(function() {
            originalCountries.push([this.value, $(this).text()]);
        });
    }
    if (language in sortCache) {
        sortCountries();
    } else {
        $.ajax({
            data: {
                token: <?php echo json_encode($token->generate('comtra_get_language_countries')); ?>,
                language: language
            },
            dataType: 'json',
            method: 'POST',
            url: <?php echo json_encode($this->action('get_language_countries')); ?>
        })
        .done(function(data) {
            if (data) {
                sortCache[language] = data;
                sortCountries();
            }
        })
        ;
    }
});
function sortCountries() {
    var language = $language.val();
    if (!(language && (language in sortCache))) {
        return;
    }
    var preferred = sortCache[language];
    var cur = $country.val();
    $country.empty();
    $country.append($('<option value="" />').text(originalCountries[0][1]));
    $.each(preferred, function(_, id) {
        $.each(originalCountries, function(_, o) {
            if (o[0] === id) {
                $country.append($('<option />').val(o[0]).text(o[1]));
            }
        });
    });
    $.each(originalCountries, function(i, o) {
        if (i > 0 && $.inArray(o[0], preferred) < 0) {
            $country.append($('<option />').val(o[0]).text(o[1]));
        }
    });
    $country.val(cur);
}
$('#no-country').on('change', function() {
    var b = $(this).is(':checked');
    if (b) {
        $country.removeAttr('required');
        $country.attr('disabled', 'disabled');
    } else {
        $country.attr('required', 'required');
        $country.removeAttr('disabled');
    }
}).trigger('change');
});</script>
<?php }

id(new Area('Closing'))->display($c);
