<?php
namespace CommunityTranslation\Controller;

use CommunityTranslation\Service\Access;
use Concrete\Core\Block\BlockController as CoreBlockController;
use Concrete\Core\Block\View\BlockView;
use Concrete\Core\Url\UrlImmutable;
use Exception;
use ZendQueue\Message;

abstract class BlockController extends CoreBlockController
{
    /**
     * @var Access|null
     */
    private $access = null;

    /**
     * @return Access
     */
    protected function getAccess()
    {
        if ($this->access === null) {
            $this->access = $this->app->make(Access::class);
        }

        return $this->access;
    }

    /**
     * Ovrride this method to define tasks that are instance-specific.
     *
     * @return bool
     */
    protected function isControllerTaskInstanceSpecific($method)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @see CoreBlockController::isValidControllerTask()
     */
    public function isValidControllerTask($method, $parameters = [])
    {
        $result = false;
        if (parent::isValidControllerTask($method, $parameters)) {
            if ($this->isControllerTaskInstanceSpecific($method)) {
                $bID = array_pop($parameters);
                if ((is_string($bID) && is_numeric($bID)) || is_int($bID)) {
                    if ($this->bID == $bID) {
                        $result = true;
                    }
                }
            } else {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * Creates a URL that can be posted or navigated to that, when done so, will automatically run the corresponding method inside the block's controller.
     * <code>
     *     <a href="<?= $controller->getActionURL($view, 'get_results') ?>">Get the results</a>
     * </code>.
     *
     * @param string $task
     *
     * @return string $url
     */
    public function getActionURL(BlockView $view, $task)
    {
        $actionArguments = func_get_args();
        array_shift($actionArguments);
        $result = call_user_func_array([$view, 'action'], $actionArguments);
        if ($this->bID && $result instanceof UrlImmutable && !$this->isControllerTaskInstanceSpecific("action_$task")) {
            $pathParts = $result->getPath()->toArray();
            /* @var \Concrete\Core\Url\Components\Path $pathParts */
            if (array_pop($pathParts) == $this->bID) {
                $result = $result->setPath($pathParts);
            }
        }

        return $result;
    }

    /**
     * @param array $args
     *
     * @return \Concrete\Core\Error\Error|\Concrete\Core\Error\ErrorList\ErrorList|array
     */
    abstract protected function normalizeArgs(array $args);

    /**
     * {@inheritdoc}
     *
     * @see CoreBlockController::validate()
     */
    public function validate($args)
    {
        $check = $this->normalizeArgs(is_array($args) ? $args : []);

        return is_array($check) ? true : $check;
    }

    /**
     * {@inheritdoc}
     *
     * @see CoreBlockController::save()
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
     * @param string $message The message
     * @param bool $isError
     * @param mixed $action,... Arguments for the redirection
     */
    protected function redirectWithMessage($message, $isError, $action)
    {
        $session = $this->app->make('session');
        /* @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session->set('block_flash_message', [$message, $isError]);
        if ($action) {
            $args = func_get_args();
            array_shift($args);
            array_shift($args);
            array_unshift($args, new BlockView($this->getBlockObject()));
            $this->redirect(call_user_func_array([$this, 'getActionURL'], $args));
        } else {
            $this->redirect(\Page::getCurrentPage());
        }
    }

    public function on_start()
    {
        parent::on_start();
        $session = $this->app->make('session');
        /* @var \Symfony\Component\HttpFoundation\Session\Session $session */
        if ($session->has('block_flash_message')) {
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
}
