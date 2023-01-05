<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var int $territoryRequestLevel
 * @var array $territoryRequestLevels
 */

?>
<fieldset>

    <legend><?= t('Options') ?></legend>

    <div class="mb-3">
        <?= $form->label('territoryRequestLevel', t('Territory specification')) ?>
        <?= $form->select('territoryRequestLevel', $territoryRequestLevels, $territoryRequestLevel) ?>
    </div>

</fieldset>
