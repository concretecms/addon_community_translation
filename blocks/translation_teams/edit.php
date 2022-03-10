<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Form\Service\DestinationPicker\DestinationPicker $destinationPicker
 * @var array $askNewTeamConfig
 * @var string $askNewTeamHandle
 * @var mixed $askNewTeamValue
 */

?>

<fieldset>
    <div class="form-group">
        <?= $form->label('askNewTeam', t('Show link to ask creation of a new translation team')) ?>
        <?= $destinationPicker->generate('askNewTeam', $askNewTeamConfig, $askNewTeamHandle, $askNewTeamValue) ?>
    </div>
</fieldset>
