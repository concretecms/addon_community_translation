<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Block\View\BlockView $this
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Package\CommunityTranslation\Block\FillTranslations\Controller $controller
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var CommunityTranslation\Entity\Locale[] $translatedLocales
 * @var CommunityTranslation\Entity\Locale[] $untranslatedLocales
 * @var array[] $displayLimits
 */

$id = 'comtra-fill-translations-' . uniqid();

?>
<fieldset class="comtra-fill-translations" id="<?= $id ?>">

    <legend><?= t('Fill-in already translated strings')?></legend>

    <p><?= t('Here you can upload a ZIP file containing a package, or a dictionary file.') ?></p>
    <p><?= t("You'll get back a ZIP file containing all the translatable strings found (.pot file) and the translated strings we already know for the languages that you specify (as source .po files or as compiled .mo files).") ?></p>

    <form method="POST" action="<?= h($controller->getBlockActionURL('fill_in')) ?>" enctype="multipart/form-data" target="<?= $id ?>_iframe">

        <?php $token->output('comtra-fill-translations') ?>

        <?php
        if ($displayLimits !== []) {
            ?>
            <h4><?= t('Limits') ?></h4>
            <dl>
                <?php
                foreach ($displayLimits as $n => $v) {
                    ?>
                    <dt><?= $n ?></dt>
                    <dd><?= $v ?></dd>
                    <?php
                }
                ?>
            </dl>
            <?php
        }
        ?>

        <div class="form-group">
            <label class="control-label" for="<?= $id ?>_file"><b><?= t('File to be processed') ?></b></label>
            <input class="form-control" type="file" name="file" id="<?= $id ?>_file" required="required" />
        </div>

        <h4><?= t('Options') ?></h4>

        <div class="form-group">
            <label class="control-label"><?= t('File to be generated') ?></label>

            <div class="form-check">
                <input type="checkbox" name="include-pot" value="1" id="<?= $id ?>_includePot" class="form-check-input"/>
                <label for="<?= $id ?>_includePot" class="form-check-label"><?= t('Include list of found translatable strings (.pot file)') ?></label>
            </div>

            <div class="form-check">
                <input type="checkbox" name="include-po" value="1" id="<?= $id ?>_includePo" class="form-check-input"/>
                <label for="<?= $id ?>_includePo" class="form-check-label"><?= t('Include source translations (.po files)') ?></label>
            </div>

            <div class="form-check">
                <input type="checkbox" name="include-mo" value="1" id="<?= $id ?>_includeMo" class="form-check-input"/>
                <label for="<?= $id ?>_includeMo" class="form-check-label"><?= t('Include compiled translations (.mo files)') ?></label>
            </div>
        </div>

        <div class="form-group">
            <div class="control-label">
                <label for="<?= $id ?>_translatedLocales"><?= t('Main languages') ?></label>
            </div>
            <select class="form-control" multiple="multiple" name="translatedLocales[]" id="<?= $id ?>_translatedLocales" size="7">
                <?php
                foreach ($translatedLocales as $locale) {
                    ?><option value="<?= h($locale->getID()) ?>" selected="selected"><?= h($locale->getDisplayName()) ?></option><?php
                }
                ?>
            </select>
            <div class="text-right comtra-fill-translations-selectlocales">
                <a href="#" onclick="$('#<?= $id ?>_translatedLocales option').prop('selected', true); return false"><?= tc('Languages', 'Select all') ?></a>
                |
                <a href="#" onclick="$('#<?= $id ?>_translatedLocales option').prop('selected', false); return false"><?= tc('Languages', 'Select none') ?></a>
            </div>
        </div>
        <?php
        if ($untranslatedLocales !== []) {
            ?>
            <div class="form-group">
                <div class="control-label">
                    <label for="<?= $id ?>_untranslatedLocales"><?= t('Other languages') ?></label>
                </div>
                <select class="form-control" multiple="multiple" name="untranslatedLocales[]" id="<?= $id ?>_untranslatedLocales" size="7">
                    <?php
                    foreach ($untranslatedLocales as $locale) {
                        ?><option value="<?= h($locale->getID()) ?>"><?= h($locale->getDisplayName()) ?></option><?php
                    }
                    ?>
                </select>
                <div class="text-right comtra-fill-translations-selectlocales">
                    <a href="#" onclick="$('#<?= $id ?>_untranslatedLocales option').prop('selected', true); return false"><?= tc('Languages', 'Select all') ?></a>
                    |
                    <a href="#" onclick="$('#<?= $id ?>_untranslatedLocales option').prop('selected', false); return false"><?= tc('Languages', 'Select none') ?></a>
                </div>
            </div>
            <?php
            }
        ?>
        <input class="btn btn-primary" type="submit" value="<?= t('Submit') ?>" />
    </form>
</fieldset>
<iframe class="d-none" name="<?= $id ?>_iframe"></iframe>
<script>$(document).ready(function() {

var $lay = $('#<?= $id ?>'),
    $localeChecks = $lay.find('input[name=include-po],input[name=include-mo]');

$localeChecks.on('change', function() {
    var askLocales = $localeChecks.filter(':checked').length > 0,
        $lists = $('#<?= $id ?>_translatedLocales,#<?= $id ?>_untranslatedLocales'),
        $labels = $('label[for=<?= $id ?>_translatedLocales],label[for=<?= $id ?>_untranslatedLocales]'),
        $links = $lay.find('.comtra-fill-translations-selectlocales a');
    $labels.add($links).toggleClass('text-muted', !askLocales);
    if (askLocales) {
        $lists.add($links).removeAttr('disabled');
    } else {
        $lists.add($links).attr('disabled', 'disabled');
    }
}).trigger('change');

});</script>
