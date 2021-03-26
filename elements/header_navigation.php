<?php

/**
 * @project:   ConcreteCMS Theme
 *
 * @copyright  (C) 2021 Portland Labs (https://www.portlandlabs.com)
 * @author     Fabian Bitter (fabian@bitter.de)
 */

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Support\Facade\Url;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Page\Page;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\User\User;
use Concrete\Core\Validation\CSRF\Token;

$app = Application::getFacadeApplication();
/** @var Token $token */
$token = $app->make(Token::class);
$site = $app->make('site')->getActiveSiteForEditing();
/** @var Repository $siteConfig */
$siteConfig = $site->getConfigRepository();
$c = Page::getCurrentPage();
$user = new User();
?>

<ul class="nav navbar-nav navbar-right">
    <li class="nav-item index-1">
        <a href="<?php echo (string)Url::to("/about"); ?>" target="_self" class="nav-link">
            <?php echo t("About"); ?>
        </a>
    </li>

    <li class="nav-item index-2">
        <a href="<?php echo (string)Url::to("/get-started"); ?>" target="_self" class="nav-link">
            <?php echo t("Get Started"); ?>
        </a>
    </li>

    <li class="nav-item index-3">
        <a href="<?php echo (string)Url::to("/extensions"); ?>" target="_self" class="nav-link">
            <?php echo t("Extensions"); ?>
        </a>
    </li>

    <li class="nav-item index-3">
        <a href="<?php echo (string)Url::to("/support"); ?>" target="_self" class="nav-link">
            <?php echo t("Support"); ?>
        </a>
    </li>

    <li class="nav-item index-5">
        <a href="<?php echo (string)Url::to("/community"); ?>" target="_self" class="nav-link">
            <?php echo t("Community"); ?>
        </a>
    </li>

    <?php

    // add search icon
    $searchPageId = (int)$siteConfig->get("concrete_cms_theme.search_page_id");
    $searchPage = Page::getByID($searchPageId);

    if ($searchPage instanceof Page && !$searchPage->isError()) {
        echo '<li class="d-none d-lg-block nav-item">';
        echo '<a href="' . (string)Url::to($searchPage) . '" title="' . h(t("Search")) . '" class="nav-link"><i class="fas fa-search"></i></a>';
        echo '</li>';
    }

    // add user icon

    $user = new User();


    if ($user->isRegistered()) {
        echo '<li class="d-none d-lg-block nav-item">';
        echo '<a href="' . (string)Url::to('/login', 'do_logout', $token->generate('do_logout')) . '" title="' . h(t("Sign Out")) . '" class="nav-link"><i class="fas fa-sign-out-alt"></i></a>';
        echo '</li>';
        echo '<li class="d-block d-lg-none nav-item">';
        echo '<a href="' . (string)Url::to('/login', 'do_logout', $token->generate('do_logout')) . '" title="' . h(t("Sign Out")) . '" class="nav-link">' . t("Sign Out") . '</a>';
        echo '</li>';
    } else {
        echo '<li class="d-none d-lg-block nav-item">';
        echo '<a href="' . (string)Url::to('/login') . '" title="' . h(t("Sign In")) . '" class="nav-link"><i class="fas fa-sign-in-alt"></i></a>';
        echo '</li>';
        echo '<li class="d-block d-lg-none nav-item">';
        echo '<a href="' . (string)Url::to('/login') . '" title="' . h(t("Sign In")) . '" class="nav-link">' . t("Sign In") . '</a>';
        echo '</li>';
    }
    ?>
</ul>