<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Permission\Checker;
use Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface;

defined('C5_EXECUTE') or die('Access Denied.');

class CommunityTranslation extends DashboardPageController
{
    public function view()
    {
        $pages = [];
        $c = $this->request->getCurrentPage();
        if ($c && !$c->isError()) {
            $resolver = $this->app->make(ResolverManagerInterface::class);
            foreach ($c->getCollectionChildren() as $child) {
                if ($child && !$child->isError()) {
                    $ncp = new Checker($child);
                    if ($ncp->canViewPage()) {
                        $pages[] = [$resolver->resolve([$child]), $child->getCollectionName()];
                    }
                }
            }
        }
        $this->set('pages', $pages);
    }
}
