<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\CommunityTranslation;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Permission\Checker;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

class Options extends DashboardPageController
{
    public function view(): ?Response
    {
        $c = $this->request->getCurrentPage();
        if ($c && !$c->isError()) {
            foreach ($c->getCollectionChildren() as $child) {
                if ($child && !$child->isError()) {
                    $ncp = new Checker($child);
                    if ($ncp->canViewPage()) {
                        return $this->buildRedirect([$child]);
                    }
                }
            }
        }

        return null;
    }
}
