<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Package\CommunityTranslation\Attribute\SubscribedPackages\Controller $controller
 * @var Concrete\Core\Attribute\View $view
 * @var int $akID
 * @var string|null $error if set, next values are not set
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Entity\User\User $user
 * @var CommunityTranslation\Entity\PackageSubscription[] $packageSubscriptions
 * @var CommunityTranslation\Entity\PackageVersionSubscription[] $packageVersionSubscriptions
 */

if (!empty($error)) {
    ?>
    <div class="alert alert-danger">
        <?= nl2br(h($error)) ?>
    </div>
    <?php
    return;
}

$id = preg_replace('/\W+/', '_', $view->field('id'));

$serialized = [];
foreach ($packageSubscriptions as $ps) {
    $packageID = $ps->getPackage()->getID();
    if (!isset($serialized[$packageID])) {
        $serialized[$packageID] = [
            'id' => $packageID,
            'name' => $ps->getPackage()->getName(),
        ];
    }
}
foreach ($packageVersionSubscriptions as $pvs) {
    $packageID = $pvs->getPackageVersion()->getPackage()->getID();
    if (!isset($serialized[$packageID])) {
        $serialized[$packageID] = [
            'id' => $packageID,
            'name' => $pvs->getPackageVersion()->getPackage()->getName(),
        ];
    }
}
$serialized = array_values($serialized);
?>
<div id="<?= h($id) ?>" v-cloak>
    <i v-if="packages.length === 0"><?= t('No package notification is set.') ?></i>
    <div v-else>
        <select class="form-select" size="5">
            <option v-for="package in packages" v-bind:key="package.id" v-bind:value="package">{{ package.name }}</option>
        </select>
        <div class="small text-muted text-end"><?= t2('%d Package', '%d Packages', count($serialized)) ?></div>
        <button class="btn btn-sm" v-bind:class="unsubscribeAll ? 'btn-danger' : 'btn-light'" v-on:click.prevent="unsubscribeAll = !unsubscribeAll"><?= t('Unsubscribe all') ?></button>
        <input type="hidden" name="<?= h($view->field('user-id')) ?>" value="<?= $user->getUserID() ?>" />
        <input type="hidden" name="<?= h($view->field('user-id-token')) ?>" value="<?= h($token->generate("u{$user->getUserID()}")) ?>" />
        <input type="hidden" name="<?= h($view->field('unsubscribe-all')) ?>" value="1" v-if="unsubscribeAll" />
    </div>
</div>
<script>
window.addEventListener('DOMContentLoaded', function() {
    new Vue({
        el: <?= json_encode("#{$id}") ?>,
        data() {
            const packages = <?= json_encode($serialized) ?>;
            packages.sort((a, b) => {
                const aName = a.name.toLowerCase();
                const bName = b.name.toLowerCase();
                return aName < bName ? -1 : aName > bName ? 1 : 0;
            });
            return {
                packages,
                selectedPackage: null,
                unsubscribeAll: false,
            };
        },
    });
});
</script>
