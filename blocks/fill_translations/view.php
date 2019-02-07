<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Block\View\BlockView $view */
/* @var Concrete\Package\CommunityTranslation\Block\FillTranslations\Controller $controller */
/* @var int $bID */

/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var CommunityTranslation\Entity\Locale[] $translatedLocales */
/* @var CommunityTranslation\Entity\Locale[] $untranslatedLocales */
/* @var array $displayLimits */

$id = 'comtra-fill-translations-' . uniqid();
?>
<fieldset class="comtra-fill-translations" id="<?= $id ?>">

    <legend><?= t('Fill-in already translated strings')?></legend>

    <p><?= t('Here you can upload a ZIP file containing a package, or a dictionary file.') ?></p>
    <p><?= t("You'll get back a ZIP file containing all the translatable strings found (.pot file) and the translated strings we already know for the languages that you specify (as source .po files or as compiled .mo files).") ?></p>

    <form method="POST" action="<?= $controller->getBlockActionURL($view, 'fill_in') ?>" enctype="multipart/form-data" target="_blank">

        <?php $token->output('comtra-fill-translations') ?>

        <?php
        if (!empty($displayLimits)) {
            ?>
            <div class="form-group">
                <label class="control-label"><?= t('Limits') ?></label>
                <?php
                foreach ($displayLimits as $n => $v) {
                    ?>
                    <br />
                    <label style="font-weight: normal"><?= $n ?>: <code><?= $v ?></code></label>
                    <?php
                }
                ?>
            </div>
            <?php
        }
        ?>

        <div class="form-group">
            <label class="control-label" for="<?= $id ?>_file"><?= t('File to be processed') ?></label>
            <input class="form-control" type="file" name="file" id="<?= $id ?>_file" required="required" />
        </div>

        <div class="form-group">
            <label class="control-label"><?= t('File to be generated') ?></label>
            <br />
            <label style="font-weight: normal"><input type="checkbox" name="include-pot" value="1" /> <?= t('Include list of found translatable strings (.pot file)') ?></label>
            <br />
            <label style="font-weight: normal"><input type="checkbox" name="include-po" value="1" checked="checked" /> <?= t('Include source translations (.po files)') ?></label>
            <br />
            <label style="font-weight: normal"><input type="checkbox" name="include-mo" value="1" checked="checked" /> <?= t('Include compiled translations (.mo files)') ?></label>
        </div>

        <div class="form-group">
            <div class="control-label">
                <label for="<?= $id ?>_translatedLocales"><?= t('Main languages') ?></label>
                <div class="pull-right">
                    <a href="#" onclick="$('#<?= $id ?>_translatedLocales option').prop('selected', true); return false"><?= tc('Languages', 'Select all') ?></a>
                    |
                    <a href="#" onclick="$('#<?= $id ?>_translatedLocales option').prop('selected', false); return false"><?= tc('Languages', 'Select none') ?></a>
                </div>
            </div>
            <select class="form-control" multiple="multiple" name="translatedLocales[]" id="<?= $id ?>_translatedLocales">
                <?php
                foreach ($translatedLocales as $locale) {
                    ?><option value="<?= h($locale->getID()) ?>" selected="selected"><?= h($locale->getDisplayName()) ?></option><?php
                }
                ?>
            </select>
        </div>
        <?php
        if (!empty($untranslatedLocales)) {
            ?>
            <div class="form-group">
                <div class="control-label">
                    <label for="<?= $id ?>_untranslatedLocales"><?= t('Other languages') ?></label>
                    <div class="pull-right">
                        <a href="#" onclick="$('#<?= $id ?>_untranslatedLocales option').prop('selected', true); return false"><?= tc('Languages', 'Select all') ?></a>
                        |
                        <a href="#" onclick="$('#<?= $id ?>_untranslatedLocales option').prop('selected', false); return false"><?= tc('Languages', 'Select none') ?></a>
                    </div>
                    <select class="form-control" multiple="multiple" name="untranslatedLocales[]" id="<?= $id ?>_untranslatedLocales">
                        <?php
                        foreach ($untranslatedLocales as $locale) {
                            ?><option value="<?= h($locale->getID()) ?>"><?= h($locale->getDisplayName()) ?></option><?php
                        }
                        ?>
                    </select>
                </div>
            </div>
            <?php
            }
        ?>
        <input class="btn btn-primary" type="submit" value="<?= t('Submit') ?>" />
    </form>
</fieldset>
<script>
$(document).ready(function() {
    var $lay = $('#<?= $id ?>'), $localeChecks = $lay.find('input[name=include-po],input[name=include-mo]');
    $localeChecks.on('change', function() {
        var askLocales = $localeChecks.filter(':checked').length > 0,
            $lists = $('#<?= $id ?>_translatedLocales,#<?= $id ?>_untranslatedLocales'),
            $labels = $('label[for=<?= $id ?>_translatedLocales],label[for=<?= $id ?>_untranslatedLocales]');
        if (askLocales) {
            $lists.removeAttr('disabled');
            $labels.removeClass('text-muted');
        } else {
            $lists.attr('disabled', 'disabled');
            $labels.addClass('text-muted');
        }
    }).trigger('change');
});
</script>
