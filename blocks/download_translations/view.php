<?php
defined('C5_EXECUTE') or die('Access Denied.');

if (isset($stopForError) && $stopForError !== '') {
    ?>
    <div class="comtra-downloadtranslations alert alert-danger" role="alert">
        <?= $stopForError ?>
    </div>
    <?php
    return;
}

/* @var array $allowedFormats */
/* @var \CommunityTranslation\Entity\Locale[] $allowedLocales */
/* @var \CommunityTranslation\Entity\Package|null $package */
/* @var \CommunityTranslation\Entity\Package\Version|null $packageVersion */
/* @var bool $fixedPackage */
/* @var bool $fixedPackageVersion */
/* @var array $availableVersions */

?>
<div class="comtra-downloadtranslations">
    <form method="POST">
        <?php
        if ($fixedPackageVersion) {
            ?><p><?= t('You are going to download the translations for %s', h($packageVersion->getDisplayName())) ?></p><?php
        } elseif ($fixedPackage) {
            ?>
            <p><?= t('You are going to download the translations for %s', h($package->getDisplayName())) ?></p>
            <p><?= t('Please choose the version:') ?></p>
            <ul>
                <?php
                foreach ($availableVersions as $versionID => $versionName) {
                    $checked = count($availableVersions) === 1 || ($packageVersion !== null && $packageVersion->getVersion() === $versionID);
                    ?><li><label><input type="radio" name="version" value="<?= h($versionID) ?>"<?= $checked ? ' checked="checked"' : '' ?>> <?= h($versionName) ?></label></li><?php
                }
                ?>
            </ul>
            <?php
        }
        ?>
    </form>
</div>
