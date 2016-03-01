<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Concrete\Core\Application\Application;
use Concrete\Core\User\User;
use Concrete\Package\CommunityTranslation\Src\Locale\Locale;

class Notify implements \Concrete\Core\Application\ApplicationAwareInterface
{
    /**
     * @var \Concrete\Core\Mail\Service
     */
    protected $mail;

    /**
     * @var string|null
     */
    protected $senderAddress = null;

    /**
     * @var string|null
     */
    protected $senderName = null;

    /**
     * @var Application
     */
    protected $app;

    public function __construct(\Concrete\Core\Mail\Service $mail)
    {
        $this->mail = $mail;
        $config = \Package::getByHandle('community_translation')->getFileConfig();
        $senderAddress = $config->get('options.notificationsSenderAddress');
        if ($senderAddress) {
            $this->senderAddress = $senderAddress;
            $senderName = $config->get('options.notificationsSenderName');
            if ($senderName) {
                $this->senderName = $senderName;
            }
        }
    }

    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    /**
     * return \Concrete\Core\User\UserInfo[].
     */
    protected function getGlobalAdministrators()
    {
        $result = array();
        foreach ($this->app->make('community_translation/groups')->getGlobalAdministrators()->getGroupMembers() as $ui) {
            $result[] = $ui;
        }

        return $result;
    }

    /**
     * return \Concrete\Core\User\UserInfo[].
     */
    protected function getLocaleAdministrators(Locale $locale)
    {
        $result = array();
        foreach ($this->app->make('community_translation/groups')->getAdministrators($locale)->getGroupMembers() as $ui) {
            $result[] = $ui;
        }

        return $result;
    }

    /**
     * @param string $template
     * @param \Concrete\Core\User\UserInfo[] $recipients
     * @param array $params
     */
    protected function send($template, array $recipients, array $params = array())
    {
        if (!isset($params['siteName'])) {
            $params['siteName'] = $this->app->make('config')->get('concrete.site');
        }
        $doneRecipentAddress = array();
        foreach ($recipients as $recipient) {
            try {
                $recipentAddress = $recipient->getUserEmail();
                if (isset($doneRecipentAddress[$recipentAddress])) {
                    continue;
                }
                $doneRecipentAddress[$recipentAddress] = true;
                $recipientName = $recipient->getUserName();
                $this->mail->reset();
                foreach ($params as $key => $val) {
                    $this->mail->addParameter($key, $val);
                }
                $this->mail->addParameter('recipientName', h($recipientName));
                $this->mail->load($template, 'community_translation');
                if ($this->senderAddress) {
                    $this->mail->from($this->senderAddress, $this->senderName);
                }
                $this->mail->to($recipentAddress, $recipientName);
                $this->mail->sendMail(true);
            } catch (\Exception $x) {
            }
        }
        $this->mail->reset();
    }

    public function newAspirantTranslator(User $aspirant, Locale $locale)
    {
        $this->send(
            'new_aspirant_translator',
            array_merge($this->getGlobalAdministrators(), $this->getLocaleAdministrators($locale)),
            array(
                'localeName' => $locale->getName(),
                'aspirantName' => h($aspirant->getUserName()),
                'teamUrl' => h($this->app->make('url/manager')->resolve(array('/teams/details/', $locale->getID()))),
            )
        );
    }

    public function newLocaleApproved(Locale $locale, User $approver)
    {
        $recipients = $this->getGlobalAdministrators();
        $requestedBy = \UserInfo::getByID($locale->getRequestedBy()) ?: null;
        if ($requestedBy !== null) {
            $recipients[] = $requestedBy;
        }
        $lang = \Localization::activeLocale();
        if ($lang !== 'en_US') {
            \Localization::changeLocale('en_US');
        }
        $params = array(
            'localeName' => $locale->getName(),
            'approverName' => $approver->getUserName(),
            'requestedBy' => $requestedBy ? $requestedBy->getUserName() : '?',
            'requestedOn' => $this->app->make('helper/date')->formatDateTime($locale->getRequestedOn(), true, false),
            'teamUrl' => h($this->app->make('url/manager')->resolve(array('/teams/details/', $locale->getID()))),
        );
        if ($lang !== 'en_US') {
            \Localization::changeLocale($lang);
        }
        $this->send(
            'new_locale_approved',
            $recipients,
            $params
        );
    }

    public function newLocaleRequested(Locale $locale, $notes = '')
    {
        $requestedBy = \UserInfo::getByID($locale->getRequestedBy()) ?: null;
        $this->send(
            'new_locale_requested',
            $this->getGlobalAdministrators(),
            array(
                'localeName' => $locale->getName(),
                'requestedBy' => $requestedBy ? $requestedBy->getUserName() : '?',
                'teamsUrl' => h($this->app->make('url/manager')->resolve(array('/teams'))),
                'notes' => nl2br(h(trim((string) $notes))),
            )
        );
    }

    public function userApproved(Locale $locale, User $user)
    {
        $operator = new \User();
        if (!$operator->isRegistered()) {
            $operator = null;
        }
        $this->send(
            'new_translator_approved',
            array_merge($this->getGlobalAdministrators(), $this->getLocaleAdministrators($locale), array(\UserInfo::getByID($user->getUserID()))),
            array(
                'localeName' => $locale->getName(),
                'applicant' => $user->getUserName(),
                'operator' => $operator ? $operator->getUserName() : '?',
                'teamUrl' => h($this->app->make('url/manager')->resolve(array('/teams/details/', $locale->getID()))),
            )
        );
    }
    public function userDenied(Locale $locale, User $user)
    {
        $operator = new \User();
        if (!$operator->isRegistered()) {
            $operator = null;
        }
        $this->send(
            'new_translator_denied',
            array_merge($this->getGlobalAdministrators(), $this->getLocaleAdministrators($locale), array(\UserInfo::getByID($user->getUserID()))),
            array(
                'localeName' => $locale->getName(),
                'applicant' => $user->getUserName(),
                'operator' => $operator ? $operator->getUserName() : '?',
                'teamUrl' => h($this->app->make('url/manager')->resolve(array('/teams/details/', $locale->getID()))),
            )
        );
    }
}
