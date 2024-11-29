<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Form\Service\Form $form
 * @var Concrete\Core\Form\Service\DestinationPicker\DestinationPicker $destinationPicker
 * @var array $askNewTeamConfig
 * @var string $askNewTeamHandle
 * @var mixed $askNewTeamValue
 * @var bool|int|string|null $displayLastOnline
 */

?>

<fieldset>
    <div class="mb-3">
        <?= $form->label('askNewTeam', t('Show link to ask creation of a new translation team')) ?>
        <?= $destinationPicker->generate('askNewTeam', $askNewTeamConfig, $askNewTeamHandle, $askNewTeamValue) ?>
    </div>
    <div>
        <?= $form->checkbox('displayLastOnline', 1, !empty($displayLastOnline)) ?>
        <label class="form-check-label" for="displayLastOnline">
            <?= t('Show when users last logged in') ?>
        </label>
    </div>
</fieldset>
