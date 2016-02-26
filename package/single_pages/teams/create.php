<?php

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */

defined('C5_EXECUTE') or die('Access Denied.');

id(new Area('Opening'))->display($c);

if (!isset($skip) || !$skip) {

if (!isset($language)) {
    $language = '';
}

if (!isset($country)) {
    $country = '';
}
if (!isset($approve)) {
    $approve = true;
}
if (!isset($noCountry)) {
    $noCountry = false;
}
if (!isset($whyNoCountry)) {
    $whyNoCountry = '';
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
                    <option value=""<?php echo $language ? '' : ' selected="selected"'; ?>><?php echo t('Please select'); ?></option>
                    <?php
                    foreach ($languages as $id => $name) {
                        ?><option value="<?php echo h($id); ?>"<?php echo ($language === $id) ? ' selected="selected"' : ''; ?>><?php echo h($name); ?></option><?php
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label class="control-label" for="country"><?php echo t('Country'); ?></label>
                <select name="country" id="country" class="form-control" required="required">
                    <option value=""<?php echo $country ? '' : ' selected="selected"'; ?>><?php echo t('Please select'); ?></option>
                    <?php
                    foreach ($countries as $id => $name) {
                        ?><option value="<?php echo h($id); ?>"<?php echo ($country === $id) ? ' selected="selected"' : ''; ?>><?php echo h($name); ?></option><?php
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label class="control-label"><?php echo t('Options'); ?></label>
                <?php if ($canApprove) { ?>
                    <div class="checkbox">
                        <label><input type="checkbox" id="approve" name="approve"<?php echo $approve ? ' checked="checked"': ''; ?>> <?php echo t('Enable this locale immediately'); ?></label>
                    </div>
                <?php } ?>
                <div class="checkbox">
                    <label><input type="checkbox" id="no-country" name="no-country"<?php echo $noCountry ? ' checked="checked"': ''; ?>> <?php echo t('This language is not Country-specific'); ?></label>
                </div>
            </div>
            <div class="form-group" style="display: none">
                <label class="control-label" for="why-no-country"><?php echo t('Please explain why this language should not be associated to a Country'); ?></label>
                <textarea class="form-control" id="why-no-country" name="why-no-country" rows="5"><?php echo h($whyNoCountry); ?></textarea>
            </div>
            <div class="form-actions">
                <input type="submit" class="btn btn-primary" value="<?php echo t('Submit request'); ?>">
            </div>
        </form>
    </div>
</div>

<script>$(document).ready(function() {

var $language = $('#language'), $country = $('#country');
var countries = null, sortCache = {};
$language.on('change', function() {
    var language = this.value;
    if (countries === null) {
        countries = [];
        $country.find('option').each(function() {
            countries.push([this.value, $(this).text()]);
        });
    }
    if (!language || (language in sortCache)) {
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
}).trigger('change');
function sortCountries() {
    var language = $language.val(), preferred;
    if (language && (language in sortCache)) {
        preferred = sortCache[language];
    } else {
        preferred = [];
    }
    var cur = $country.val();
    $country.empty();
    var $suggested = null, $others = $('<optgroup />').attr('label', <?php echo json_encode(t('Other Countries')); ?>);
    $.each(preferred, function(_, preferredCountryID) {
        var found = null;
        $.each(countries, function(_, country) {
            if (country[0] === preferredCountryID) {
                found = country;
                return false;
            }
        });
        if (found !== null) {
            if ($suggested === null) {
                $suggested = $('<optgroup />').attr('label', <?php echo json_encode(t('Suggested Countries')); ?>);
            }
            $suggested.append($('<option />').val(found[0]).text(found[1]));
        }
    })
    $.each(countries, function(i, country) {
        var $parent = null;
        if (i === 0 || $suggested === null) {
            $parent = $country;
        } else if($.inArray(country[0], preferred) < 0) {
            $parent = $others;
        }
        if ($parent !== null) {
            $parent.append($('<option />').val(country[0]).text(country[1]));
        }
    });
    if ($suggested !== null) {
        $country.append($suggested).append($others);
    }
    $country.val(cur);
}
$('#no-country').on('change', function() {
    var b = $(this).is(':checked');
    if (b) {
        $country.removeAttr('required');
        $country.attr('disabled', 'disabled');
        <?php if (!$canApprove) { ?>
        $('#why-no-country')
            .attr('required', 'required')
            .closest('.form-group').show('fast');
        <?php } ?>
    } else {
        $country.attr('required', 'required');
        $country.removeAttr('disabled');
        <?php if (!$canApprove) { ?>
        $('#why-no-country')
            .removeAttr('required')
            .closest('.form-group').hide('fast');
        <?php } ?>
    }
}).trigger('change');

});</script>
<?php }

id(new Area('Closing'))->display($c);
