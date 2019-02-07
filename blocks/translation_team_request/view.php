<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Block\View\BlockView $view */
/* @var Concrete\Package\CommunityTranslation\Block\TranslationTeamRequest\Controller $controller */
/* @var int $bID */

/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var Concrete\Core\Block\View\BlockView $view */
/* @var Concrete\Core\Form\Service\Form $form */

/* @var string $step */
/* @var string|null $showError */

$id = 'comtra-translation-team-request-' . uniqid();

?>
<div class="panel panel-default comtra-translation-team-request">
    <div class="panel-heading"><h3><?= t('New Translators Team') ?></h3></div>
    <div class="panel-body">
        <?php

if (isset($showError) && $showError !== '') {
    ?>
    <div class="alert alert-danger" role="alert">
        <?= $showError ?>
    </div>
    <?php
}

switch ($step) {
    case 'notLoggedIn':
        ?>
        <?php
        break;

    case 'language':
        /* @var string $language */
        /* @var array $languages */
        ?>
        <form class="form-stacked" method="POST" action="<?= $controller->getBlockActionURL($view, 'language_set') ?>">
            <?php $token->output('comtra-ttr-language_set') ?>
            <p><?= t('Please specify the new language you would like to translate') ?>
            <div class="form-group">
                <?= $form->label('language', t('Language')) ?>
                <?= $form->select('language', $languages, $language, ['required' => 'required']) ?>
            </div>
            <div class="form-actions">
                <input type="submit" class="btn btn-primary" value="<?= t('Proceed') ?>">
            </div>
        </form>
        <?php
        break;

    case 'territory':
        /* @var string $languageName */
        /* @var array $existingLocales */
        /* @var array $suggestedCountries */
        /* @var array $otherCountries */
        /* @var bool $allowNoTerrory */
        if (!empty($existingLocales)) {
            ?>
            <form class="form-stacked" id="<?= $id ?>_warning" onsubmit="return false">
                <p><?= t('We already have the following language teams:') ?></p>
                <ul>
                    <?php
                    foreach ($existingLocales as $name) {
                        ?><li><strong><?= h($name) ?></strong></li><?php
                    }
                    ?>
                </ul>
                <p><?= t('Are you sure you want to create another team for %s?', h($languageName)) ?></p>
                <div class="form-actions">
                    <a href="<?= URL::to('/') ?>" class="btn btn-default"><?= t("Don't create a new team") ?></a>
                    <a href="#" class="btn btn-default" onclick="$('#<?= $id ?>_warning').hide();$('#<?= $id ?>_create').show(); return false"><?= t('Create a new team anyway') ?></a>
                </div>
            </form>
            <?php
        }
        ?>
        <form class="form-stacked" method="POST" action="<?= $controller->getBlockActionURL($view, 'territory_set') ?>" id="<?= $id ?>_create"<?= empty($existingLocales) ? '' : ' style="display: none"' ?>>
            <?php $token->output('comtra-ttr-territory_set') ?>
            <?= $form->hidden('language', $language) ?>
            <div class="form-group">
                <?= $form->label('territory', t('For which Country would you like to translate %s?', $languageName)) ?>
                <select id="territory" name="territory" required="required" class="form-control">
                    <option value="" selected="selected"><?= t('Please Select') ?></option>
                    <?php
                    $list = [];
                    if (!empty($suggestedCountries)) {
                        $list[] = $suggestedCountries;
                    }
                    if (!empty($otherCountries)) {
                        $list[] = $otherCountries;
                    }
                    $labels = count($list) === 2 ? [t('Recommended Countries'), t('Other Countries')] : null;
                    foreach ($list as $i => $countries) {
                        if ($labels !== null) {
                            ?><optgroup label="<?= $labels[$i] ?>"><?php
                        }
                        foreach ($countries as $id => $name) {
                            ?><option value="<?= h($id) ?>"><?= h($name) ?></option><?php
                        }
                        if ($labels !== null) {
                            ?></optgroup><?php
                        }
                    }
                    ?>
                </select>
            </div>
            <?php
            if ($allowNoTerrory) {
                ?>
                <div class="form-group">
                    <?= $form->checkbox('noTerritory', '1', true) ?>
                    <?= $form->label('noTerritory', t('%s is not Country-specific', $languageName)) ?>
                </div>
                <script>
                $(document).ready(function() {
                $('#noTerritory')
                    .on('change', function() {
                        if (this.checked) {
                            $('#territory').attr('disabled', 'disabled').removeAttr('required');
                        } else {
                            $('#territory').attr('required', 'required').removeAttr('disabled');
                        }
                    })
                    .trigger('change');
                });
                </script>
                <?php
            }
            ?>
            <div class="form-actions">
                <input type="submit" class="btn btn-primary" value="<?= t('Proceed') ?>">
            </div>
        </form>
        <?php
        break;

    case 'preview':
        /* @var string $language */
        /* @var string $territory */
        /* @var string $localeID */
        /* @var string $localeName */
        /* @var bool $askApprove */
        /* @var bool $askWhyNoCountry */
        ?>
        <form class="form-stacked" method="POST" action="<?= $controller->getBlockActionURL($view, 'submit') ?>">
            <?php $token->output('comtra-ttr-submit') ?>
            <?= $form->hidden('language', $language) ?>
            <?= $form->hidden('territory', $territory) ?>
            <?php
            if ($askApprove) {
                ?>
                <div class="form-group">
                    <?= $form->checkbox('approve', '1', true) ?>
                    <?= $form->label('approve', t("Approve immediately the locale '%s' (%s)", h($localeName), $localeID)) ?>
                </div>
                <?php
            } else {
                ?>
                <p><?= t("You are going to submit the request to create the '%s' (%s) translators team.", h($localeName), $localeID) ?></p>
                <?php
                if ($askWhyNoCountry) {
                    ?>
                    <p><?= t("Since we prefer to have Country-specific locales (eg 'en_US' instead of 'en' alone), please tell us why you need to create a language without an associated Country") ?></p>
                    <?= $form->textarea('notes', '', ['required' => 'required']) ?>
                    <?php
                }
            }
            ?>
            <div class="form-actions">
                <input type="submit" class="btn btn-primary" value="<?= t('Submit request') ?>">
            </div>
        </form>
        <?php
        break;

    case 'submitted':
        /* @var string $localeID */
        /* @var string $localeName */
        /* @var bool $approved */
        ?>
        <form class="form-stacked" onsubmit="return false">
            <?php
            if ($approved) {
                ?>
                <p><?= t("The locale '%s' (%s) has been created and approved.", h($localeName), h($localeID)) ?></p>
                <?php
            } else {
                ?>
                <p><?= t("Your request to create the language team for '%s' (%s) has been submitted.", h($localeName), h($localeID)) ?></p>
                <p><?= t("You'll be notified when the administrators approve it.") ?></p>
                <?php
            }
            ?>
            <div class="form-actions">
                <a href="<?= URL::to('/') ?>" class="btn btn-default"><?= t('Return to the homepage') ?></a>
            </div>
        </form>
        <?php
        break;
}

        ?>
    </div>
</div>
