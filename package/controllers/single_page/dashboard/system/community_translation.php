<?php
namespace Concrete\Package\CommunityTranslation\Controller\SinglePage\Dashboard\System;

use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Package\Letsencrypt\Entity\Account;
use Concrete\Package\Letsencrypt\Entity\Domain;

defined('C5_EXECUTE') or die('Access Denied.');

class CommunityTranslation extends DashboardPageController
{
    public function view()
    {
        $this->redirect('/dashboard/system/community_translation/options');
    }
}
