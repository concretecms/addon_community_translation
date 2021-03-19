<?php

/**
 * @project:   ConcreteCMS Translation
 *
 * @copyright  (C) 2021 Portland Labs (https://www.portlandlabs.com)
 * @author     Fabian Bitter (fabian@bitter.de)
 */

defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Core\Page\Page;
use Concrete\Core\Support\Facade\Url;
use Concrete\Core\User\User;
use Concrete\Core\Validation\CSRF\Token;
use Concrete\Core\Support\Facade\Application;

$c = Page::getCurrentPage();
$user = new User();
$app = Application::getFacadeApplication();
/** @var Token $token */
$token = $app->make(Token::class);
?>

<div id="ccm-sub-nav">
    <div class="container">
        <div class="row">
            <div class="col">
                <h3>
                    <?php echo t("Translation"); ?>
                </h3>

                <nav>
                    <ul>
                        <li class="<?php echo strpos($c->getCollectionPath(), "/teams") !== false ? "active" : "";?>">
                            <a href="<?php echo (string)Url::to("/teams");?>">
                                <?php echo t("Teams"); ?>
                            </a>
                        </li>
                        <li class="<?php echo strpos($c->getCollectionPath(), "/translate") !== false ? "active" : "";?>">
                            <a href="<?php echo (string)Url::to("/translate");?>">
                                <?php echo t("Translate"); ?>
                            </a>
                        </li>
                        <li class="<?php echo strpos($c->getCollectionPath(), "/translate-your-packages") !== false ? "active" : "";?>">
                            <a href="<?php echo (string)Url::to("/translate-your-packages");?>">
                                <?php echo t("Translate your packages"); ?>
                            </a>
                        </li>

                        <?php if ($user->isRegistered()) { ?>
                            <li>
                                <a href="<?php echo (string)Url::to('/login', 'do_logout', $token->generate('do_logout')); ?>">
                                    <?php echo t("Sign Out"); ?>
                                </a>
                            </li>
                        <?php } else { ?>
                            <li>
                                <a href="<?php echo (string)Url::to('/login') ?>">
                                    <?php echo t("Sign In"); ?>
                                </a>
                            </li>
                        <?php } ?>

                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>