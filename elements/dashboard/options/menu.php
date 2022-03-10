<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Package\CommunityTranslation\Controller\Element\Dashboard\Options\Menu $controller
 * @var Concrete\Core\Application\Service\UserInterface $userInterface
 * @var array $tabs
 * @var Concrete\Core\View\FileLocatorView $this
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManager $urlResolver
 * @var Concrete\Core\View\FileLocatorView $view
 */

if (count($tabs) > 1) {
    ?>
    <div>
        <?= $userInterface->tabs($tabs) ?>
    </div>
    <?php
}
