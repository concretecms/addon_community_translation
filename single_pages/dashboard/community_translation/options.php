<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Page\View\PageView $view */
/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var Concrete\Core\Form\Service\Form $form */

/* @var CommunityTranslation\Service\RateLimit $rateLimitHelper */

/* @var string $sourceLocale */
/* @var int $translatedThreshold */
/* @var int|null $apiRateLimitMaxRequests */
/* @var int $apiRateLimitTimeWindow */
/* @var string $apiAccessControlAllowOrigin */
/* @var array $apiAccessChecks */
/* @var string $onlineTranslationPath */
/* @var string $apiEntryPoint */
/* @var string $tempDir */
/* @var array $parsers */
/* @var string $defaultParser */
/* @var string $notificationsSenderAddress */
/* @var string $notificationsSenderName */
?>

<form method="post" class="form-horizontal" action="<?= $view->action('submit') ?>" onsubmit="if (this.already) return false; this.already = true">

    <?php $token->output('ct-options-save') ?>

    <fieldset>
        <legend><?= t('Translations') ?></legend>
        <div class="row">
            <div class="form-group">
                <div class="col-sm-3 control-label">
                    <label for="sourceLocale">
                        <?= t('Source locale') ?>
                    </label>
                </div>
                <div class="col-sm-2">
                    <?= $form->text('sourceLocale', $sourceLocale, ['required' => 'required', 'pattern' => '[a-z]{2,3}(_([A-Z]{2}|[0-9]{3}))?']) ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="form-group">
                <div class="col-sm-3 control-label">
                    <label for="translatedThreshold" class="launch-tooltip" data-html="true" title="<?= t('Translations below this value as considered as <i>not translated</i>') ?>">
                        <?= t('Translation threshold') ?>
                    </label>
                </div>
                <div class="col-sm-2">
                    <div class="input-group">
                        <?= $form->number('translatedThreshold', $translatedThreshold, ['min' => 0, 'max' => 100, 'required' => 'required']) ?>
                        <span class="input-group-addon">%</span>
                    </div>
                </div>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><?= t('API Access') ?></legend>

        <div class="row">
            <div class="form-group">
                <div class="col-sm-3 control-label">
                    <label for="apiRateLimitMaxRequests" class="launch-tooltip" title="<?= h(t('Leave empty for no limits')) ?>">
                        <?= t('Max requests per IP address') ?>
                    </label>
                </div>
                <div class="col-sm-7">
                    <?= $rateLimitHelper->getWidgetHtml('apiRateLimit', $apiRateLimitMaxRequests, $apiRateLimitTimeWindow) ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <div class="col-sm-3 control-label">
                    <label for="apiAccessControlAllowOrigin" class="launch-tooltip" data-html="true" title="<?= h(t(/*i18n: %s is a header name of an HTTP response*/'Set the content of the %s header added to the API request responses', '<br /><code>Access-Control-Allow-Origin</code><br />')) ?>">
                        <?= tc(/*i18n: %s is a header name of an HTTP response*/'ResponseHeader', '%s header', 'Access-Control-Allow-Origin') ?>
                    </label>
                </div>
                <div class="col-sm-7">
                    <?= $form->text('apiAccessControlAllowOrigin', $apiAccessControlAllowOrigin, ['required' => 'required']) ?>
                </div>
            </div>
        </div>

        <?php
        foreach ($apiAccessChecks as $aacKey => $aacInfo) {
            ?>
            <div class="row">
                <div class="form-group">
                    <div class="col-sm-3 control-label">
                        <label for="apiAccess-<?= $aacKey ?>">
                            <?= h($aacInfo['label']) ?>
                        </label>
                    </div>
                    <div class="col-sm-7">
                        <?= $form->select('apiAccess-' . $aacKey, $aacInfo['values'], $aacInfo['value'], ['required' => 'required']) ?>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>
    </fieldset>

    <fieldset>
        <legend><?= t('Paths') ?></legend>
        <div class="row">
            <div class="form-group">
                <label for="onlineTranslationPath" class="control-label col-sm-3"><?= t('Online Translation URI') ?></label>
                <div class="col-sm-7">
                    <?= $form->text('onlineTranslationPath', $onlineTranslationPath, ['required' => 'required']) ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="form-group">
                <label for="apiEntryPoint" class="control-label col-sm-3"><?= t('API Entry Point') ?></label>
                <div class="col-sm-7">
                    <?= $form->text('apiEntryPoint', $apiEntryPoint, ['required' => 'required']) ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="form-group">
                <label for="tempDir" class="control-label col-sm-3"><?= t('Temporary directory') ?></label>
                <div class="col-sm-7">
                    <?= $form->text('tempDir', $tempDir, ['placeholder' => t('Default temporary directory: %s', h(Core::make('helper/file')->getTemporaryDirectory()))]) ?>
                </div>
            </div>
        </div>
    </fieldset>


    <fieldset>
        <legend><?= t('System') ?></legend>
        <div class="row">
            <div class="form-group">
                <label for="tempDir" class="control-label col-sm-3"><?= t('Strings Parser') ?></label>
                <div class="col-sm-7">
                    <?= $form->select('parser', $parsers, $defaultParser, ['required' => 'required']) ?>
                </div>
            </div>
        </div>
    </fieldset>

    <fieldset>
        <legend><?= t('Notifications') ?></legend>
        <div class="row">
            <div class="form-group">
                <label for="notificationsSenderAddress" class="control-label col-sm-3"><?= t('Sender email address') ?></label>
                <div class="col-sm-7">
                    <?= $form->email('notificationsSenderAddress', $notificationsSenderAddress, ['placeholder' => t('Default email address: %s', h(Config::get('concrete.email.default.address')))]) ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="form-group">
                <label for="notificationsSenderName" class="control-label col-sm-3"><?= t('Sender name') ?></label>
                <div class="col-sm-7">
                    <?= $form->text('notificationsSenderName', $notificationsSenderName, ['placeholder' => t('Default sender name: %s', h(Config::get('concrete.email.default.name')))]) ?>
                </div>
            </div>
        </div>
    </fieldset>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= URL::to('/dashboard/community_translation') ?>" class="btn btn-default pull-left"><?= t('Cancel') ?></a>
            <input type="submit" class="btn btn-primary pull-right btn ccm-input-submit" value="<?= t('Save') ?>">
        </div>
    </div>

</form>
