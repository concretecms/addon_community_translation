<?php

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Page\Page;
use Concrete\Core\Permission\Checker;

defined('C5_EXECUTE') or die('Access Denied.');

class CommunityTranslation extends DashboardPageController
{
    public function view()
    {
        $pages = [];
        $c = $this->request->getCurrentPage();
        if ($c) {
            $children = $c->getCollectionChildrenArray(true);
            $resolver = $this->app->make('url/manager');
            foreach ($children as $childID) {
                $childPage = Page::getByID($childID, 'ACTIVE');
                $ncp = new Checker($childPage);
                if ($ncp->canRead()) {
                    $pages[] = [$resolver->resolve([$childPage]), $childPage->getCollectionName()];
                }
            }
        }
        $this->set('pages', $pages);
    }
}
