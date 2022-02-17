<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Block\View\BlockView $this
 * @var Concrete\Core\Block\View\BlockView $view
 * @var Concrete\Package\CommunityTranslation\Block\TranslationTeamRequest\Controller $controller
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Form\Service\Form $form
 * @var string $showError (may not be set)
 * @var string $step
 */

$id = 'comtra-translation-team-request-' . uniqid();
?>
<div class="card">
    <div class="card-header"><h3><?= t('New Translators Team') ?></h3></div>
    <div class="card-body">
        <div class="card-text">
            <?php
            if (($showError ?? '') !== '') {
                ?>
                <div class="alert alert-danger">
                    <?= $showError ?>
                </div>
                <?php
            }
            switch ($step) {
                case 'language':
                    /**
                     * @var array $languages
                     * @var string $language
                     */
                    ?>
                    <form method="POST" action="<?= h($controller->getBlockActionURL('language_set')) ?>">
                        <?php $token->output('comtra-ttr-language_set') ?>
                        <p><?= t('Please specify the new language you would like to translate') ?>
                        <div class="form-group">
                            <?= $form->label('language', t('Language'), ['classes' => 'form-label']) ?>
                            <?= $form->select('language', $languages, $language, ['required' => 'required', 'classes' => 'form-control']) ?>
                        </div>
                        <div>
                            <input type="submit" class="btn btn-primary" value="<?= t('Proceed') ?>" />
                        </div>
                    </form>
                    <?php
                    break;
                case 'territory':
                    /**
                     * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $urlResolver
                     * @var string $languageName
                     * @var array $existingLocales
                     * @var array $suggestedCountries
                     * @var array $otherCountries
                     * @var bool $allowNoTerrory
                     */
                    if ($existingLocales !== []) {
                        ?>
                        <div id="<?= $id ?>_warning">
                            <p><?= t('We already have the following language teams:') ?></p>
                            <ul>
                                <?php
                                foreach ($existingLocales as $name) {
                                    ?><li><strong><?= h($name) ?></strong></li><?php
                                }
                                ?>
                            </ul>
                            <p><?= t('Are you sure you want to create another team for %s?', h($languageName)) ?></p>
                            <div>
                                <a href="<?= h($urlResolver->resolve(['/'])) ?>" class="btn btn-secondary"><?= t("Don't create a new team") ?></a>
                                <a href="#" class="btn btn-secondary" onclick="$('#<?= $id ?>_warning').remove();$('#<?= $id ?>_create').removeClass('d-none'); return false"><?= t('Create a new team anyway') ?></a>
                            </div>
                        </div>
                        <?php
                    }
                    ?>
                    <form method="POST" action="<?= h($controller->getBlockActionURL('territory_set')) ?>" id="<?= $id ?>_create"<?= $existingLocales === [] ? '' : ' class="d-none"' ?>>
                        <?php $token->output('comtra-ttr-territory_set') ?>
                        <?= $form->hidden('language', $language) ?>
                        <div class="form-group">
                            <?= $form->label("{$id}_territory", t('For which Country would you like to translate %s?', h($languageName)), ['classes' => 'form-label']) ?>
                            <select id="<?= "{$id}_territory" ?>" name="territory" required="required" class="form-control">
                                <option value="" selected="selected"><?= t('Please Select') ?></option>
                                <?php
                                $lists = [];
                                if ($suggestedCountries !== []) {
                                    $lists[] = $suggestedCountries;
                                }
                                if ($otherCountries !== []) {
                                    $lists[] = $otherCountries;
                                }
                                $labels = count($lists) === 2 ? [t('Recommended Countries'), t('Other Countries')] : null;
                                foreach ($lists as $i => $countries) {
                                    if ($labels !== null) {
                                        ?><optgroup label="<?= $labels[$i] ?>"><?php
                                    }
                                    foreach ($countries as $countryID => $countryName) {
                                        ?><option value="<?= h($countryID) ?>"><?= h($countryName) ?></option><?php
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
                                <div class="form-check">
                                    <?= $form->checkbox('no-territory', '1', true, ['classes' => 'form-check-input', 'id' => "{$id}_noTerritory"]) ?>
                                    <?= $form->label("{$id}_noTerritory", t('%s is not Country-specific', h($languageName)), ['classes' => 'form-check-label']) ?>
                                </div>
                            </div>
                            <script>$(document).ready(function() {
                            $(<?= json_encode("#{$id}_noTerritory")?>)
                                .on('change', function() {
                                    var $territory = $(<?= json_encode("#{$id}_territory") ?>);
                                    if (this.checked) {
                                        $territory.attr('disabled', 'disabled').removeAttr('required');
                                    } else {
                                        $territory.attr('required', 'required').removeAttr('disabled');
                                    }
                                })
                                .trigger('change');
                            });
                            </script>
                            <?php
                        }
                        ?>
                        <div>
                            <input type="submit" class="btn btn-primary" value="<?= t('Proceed') ?>" />
                        </div>
                    </form>
                    <?php
                    break;
                case 'preview':
                    /**
                     * @var string $language
                     * @var string $territory
                     * @var string $localeID
                     * @var string $localeName
                     * @var bool $askApprove
                     * @var bool $askWhyNoCountry
                     */
                    ?>
                    <form method="POST" action="<?= h($controller->getBlockActionURL('submit')) ?>">
                        <?php $token->output('comtra-ttr-submit') ?>
                        <?= $form->hidden('language', $language) ?>
                        <?= $form->hidden('territory', $territory) ?>
                        <?php
                        if ($askApprove) {
                            ?>
                            <div class="form-group">
                                <div class="form-check">
                                    <?= $form->checkbox('approve', '1', true, ['classes' => 'form-check-input']) ?>
                                    <?= $form->label('approve', t("Approve immediately the locale '%s' (%s)", h($localeName), $localeID), ['classes' => 'form-check-label']) ?>
                                </div>
                            </div>
                            <?php
                        } else {
                            ?>
                            <p><?= t("You are going to submit the request to create the '%s' (%s) translators team.", h($localeName), $localeID) ?></p>
                            <?php
                            if ($askWhyNoCountry) {
                                ?>
                                <div class="form-group">
                                    <p><?= t("Since we prefer to have Country-specific locales (eg 'en_US' instead of 'en' alone), please tell us why you need to create a language without an associated Country") ?></p>
                                    <?= $form->textarea('notes', '', ['required' => 'required', 'class' => 'form-control', 'size' => 5]) ?>
                                </div>
                                <?php
                            }
                        }
                        ?>
                        <div>
                            <input type="submit" class="btn btn-primary" value="<?= t('Submit request') ?>" />
                        </div>
                    </form>
                    <?php
                    break;
                case 'submitted':
                    /**
                     * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $urlResolver
                     * @var string $localeID
                     * @var string $localeName
                     * @var bool $approved
                     */
                    ?>
                    <div>
                        <?php
                        if ($approved) {
                            ?>
                            <p><?= t("The locale '%s' (%s) has been created and approved.", h($localeName), '<code>' . h($localeID) . '</code>') ?></p>
                            <?php
                        } else {
                            ?>
                            <p><?= t("Your request to create the language team for '%s' (%s) has been submitted.", h($localeName), h($localeID)) ?></p>
                            <p><?= t("You'll be notified when the administrators approve it.") ?></p>
                            <?php
                        }
                        ?>
                        <div>
                            <a href="<?= h($urlResolver->resolve(['/'])) ?>" class="btn btn-secondary"><?= t('Return to the homepage') ?></a>
                        </div>
                    </div>
                    <?php
                    break;
            }
            ?>
        </div>
    </div>
</div>
