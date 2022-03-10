<?php

declare(strict_types=1);

namespace CommunityTranslation\Controller;

use CommunityTranslation\Service\Access as AccessService;
use CommunityTranslation\Service\User as UserService;
use Concrete\Core\Block\BlockController as CoreBlockController;
use Concrete\Core\Session\SessionValidatorInterface;
use Concrete\Core\Url\UrlImmutable;
use Exception;
use Symfony\Component\HttpFoundation\Response;

defined('C5_EXECUTE') or die('Access Denied.');

abstract class BlockController extends CoreBlockController
{
    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::$supportSavingNullValues
     */
    protected $supportSavingNullValues = true;

    private ?AccessService $accessService = null;

    private ?UserService $userService = null;

    public function on_start()
    {
        parent::on_start();
        $session = $this->app->make(SessionValidatorInterface::class)->getActiveSession();
        if ($session !== null && $session->has('block_flash_message')) {
            $data = $session->get('block_flash_message');
            $session->remove('block_flash_message');
            if (is_array($data)) {
                if ($data[1]) {
                    $this->set('showError', $data[0]);
                } else {
                    $this->set('showSuccess', $data[0]);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::validate()
     */
    public function validate($args)
    {
        $check = $this->normalizeArgs(is_array($args) ? $args : []);

        return is_array($check) ? true : $check;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::save()
     */
    public function save($args)
    {
        $normalized = $this->normalizeArgs(is_array($args) ? $args : []);
        if (!is_array($normalized)) {
            throw new Exception(implode("\n", $normalized->getList()));
        }
        parent::save($normalized);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Block\BlockController::isValidControllerTask()
     */
    public function isValidControllerTask($method, $parameters = [])
    {
        if (!parent::isValidControllerTask($method, $parameters)) {
            return false;
        }
        if (!$this->isControllerTaskInstanceSpecific((string) $method)) {
            return true;
        }
        $bID = array_pop($parameters);

        return is_numeric($bID) && (int) $this->bID === (int) $bID;
    }

    /**
     * Creates a URL that can be posted or navigated to that, when done so, will automatically run the corresponding method inside the block's controller.
     * <code>
     *     <a href="<?= h($controller->getBlockActionURL('get_results')) ?>">Get the results</a>
     * </code>.
     */
    public function getBlockActionURL(string $action, ...$actionArguments): string
    {
        $result = $this->getActionURL($action, ...$actionArguments);
        if ($this->bID && $result instanceof UrlImmutable && !$this->isControllerTaskInstanceSpecific("action_{$action}")) {
            $pathParts = $result->getPath()->toArray();
            if (array_pop($pathParts) == $this->bID) {
                $result = $result->setPath($pathParts);
            }
        }

        return (string) $result;
    }

    protected function getAccessService(): AccessService
    {
        if ($this->accessService === null) {
            $this->accessService = $this->app->make(AccessService::class);
        }

        return $this->accessService;
    }

    protected function getUserService(): UserService
    {
        if ($this->userService === null) {
            $this->userService = $this->app->make(UserService::class);
        }

        return $this->userService;
    }

    /**
     * Ovrride this method to define tasks that are instance-specific.
     */
    protected function isControllerTaskInstanceSpecific(string $method): bool
    {
        return false;
    }

    /**
     * @return \Concrete\Core\Error\Error|\Concrete\Core\Error\ErrorList\ErrorList|array
     */
    abstract protected function normalizeArgs(array $args);

    protected function redirectWithMessage(string $message, bool $isError, string $action, ...$actionArguments): Response
    {
        $session = $this->app->make('session');
        $session->set('block_flash_message', [$message, $isError]);
        if ($action === '') {
            return $this->buildRedirect([$this->request->getCurrentPage()]);
        }

        return $this->buildRedirect($this->getBlockActionURL($action, $actionArguments));
    }
}
