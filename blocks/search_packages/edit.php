<?php

use Concrete\Core\Support\Facade\Application;

$app = Application::getFacadeApplication();

$form = $app->make('helper/form');
/* @var Concrete\Core\Form\Service\Form $form */

/* @var string $preloadPackageHandle */

?>

<fieldset>

    <legend><?php echo t('Options'); ?></legend>

    <div class="form-group">
        <?php
        echo $form->label('preloadPackageHandle', t('Preload package'));
        echo $form->text('preloadPackageHandle', $preloadPackageHandle);
        ?>
    </div>

</fieldset>
