<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\Element\Dashboard\Options;

use Concrete\Core\Application\Service\UserInterface;
use Concrete\Core\Controller\ElementController;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

final class Menu extends ElementController
{
    private const OPTIONS_PAGE_PATH = '/dashboard/community_translation/options';

    private ResolverManagerInterface $resolverManager;

    private UserInterface $userInterface;

    public function __construct(ResolverManagerInterface $resolverManager, UserInterface $userInterface)
    {
        parent::__construct();
        $this->resolverManager = $resolverManager;
        $this->userInterface = $userInterface;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Controller\ElementController::getElement()
     */
    public function getElement()
    {
        return 'dashboard/options/menu';
    }

    public function view()
    {
        $currentPage = $this->request->getCurrentPage();
        $tabs = [];
        $parent = $this->checkPage(Page::getByPath(self::OPTIONS_PAGE_PATH));
        if ($parent !== null) {
            foreach ($parent->getCollectionChildren() as $child) {
                $child = $this->checkPage($child);
                if ($child !== null) {
                    $tabs[] = [
                        (string) $this->resolverManager->resolve([$child]),
                        h(t($child->getCollectionName())),
                        $currentPage->getCollectionID() === $child->getCollectionID(),
                    ];
                }
            }
        }
        $this->set('userInterface', $this->userInterface);
        $this->set('tabs', $tabs);
    }

    /**
     * @param \Concrete\Core\Page\Page|mixed $page
     */
    private function checkPage($page): ?Page
    {
        if (!$page instanceof Page || $page->isError()) {
            return null;
        }
        $checker = new Checker($page);

        return $checker->canViewPage() ? $page : null;
    }
}
