<?php

use Concrete\Core\Support\Facade\Application;

$app = Application::getFacadeApplication();

$form = $app->make('helper/form');
/* @var Concrete\Core\Form\Service\Form $form */

/* @var int $territoryRequestLevel */
/* @var array $territoryRequestLevels */
?>

<fieldset>

    <legend><?php echo t('Options'); ?></legend>

    <div class="form-group">
        <?= $form->label('territoryRequestLevel', t('Territory specification')) ?>
        <?= $form->select('territoryRequestLevel', $territoryRequestLevels, $territoryRequestLevel) ?>
    </div>

</fieldset>
