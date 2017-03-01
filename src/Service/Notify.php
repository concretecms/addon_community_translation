<?php
namespace CommunityTranslation\Service;

use CommunityTranslation\Locale\Locale;
use CommunityTranslation\Package\Package;
use Concrete\Core\Application\Application;
use Concrete\Core\User\User as ConcreteUser;

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
        $config = $this->app->make('community_translation/config');
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
        $result = [];
        foreach ($this->app->make(Groups::class)->getGlobalAdministrators()->getGroupMembers() as $ui) {
            $result[] = $ui;
        }

        return $result;
    }

    /**
     * return \Concrete\Core\User\UserInfo[].
     */
    protected function getLocaleAdministrators(Locale $locale)
    {
        $result = [];
        foreach ($this->app->make(Groups::class)->getAdministrators($locale)->getGroupMembers() as $ui) {
            $result[] = $ui;
        }

        return $result;
    }

    /**
     * @param string $template
     * @param \Concrete\Core\User\UserInfo[] $recipients
     * @param array $params
     */
    protected function send($template, array $recipients, array $params = [])
    {
        if (!isset($params['siteName'])) {
            $params['siteName'] = $this->app->make('config')->get('concrete.site');
        }
        $doneRecipentAddress = [];
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

    public function newAspirantTranslator(ConcreteUser $aspirant, Locale $locale)
    {
        $this->send(
            'new_aspirant_translator',
            array_merge($this->getGlobalAdministrators(), $this->getLocaleAdministrators($locale)),
            [
                'localeName' => $locale->getName(),
                'aspirantName' => h($aspirant->getUserName()),
                'teamUrl' => h($this->app->make('url/manager')->resolve(['/teams/details/', $locale->getID()])),
            ]
        );
    }

    public function newLocaleApproved(Locale $locale, ConcreteUser $approver)
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
        $params = [
            'localeName' => $locale->getName(),
            'approverName' => $approver->getUserName(),
            'requestedBy' => $requestedBy ? $requestedBy->getUserName() : '?',
            'requestedOn' => $this->app->make('helper/date')->formatDateTime($locale->getRequestedOn(), true, false),
            'teamUrl' => h($this->app->make('url/manager')->resolve(['/teams/details/', $locale->getID()])),
        ];
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
            [
                'localeName' => $locale->getName(),
                'requestedBy' => $requestedBy ? $requestedBy->getUserName() : '?',
                'teamsUrl' => h($this->app->make('url/manager')->resolve(['/teams'])),
                'notes' => nl2br(h(trim((string) $notes))),
            ]
        );
    }

    public function userApproved(Locale $locale, ConcreteUser $user)
    {
        $operator = new \User();
        if (!$operator->isRegistered()) {
            $operator = null;
        }
        $this->send(
            'new_translator_approved',
            array_merge($this->getGlobalAdministrators(), $this->getLocaleAdministrators($locale), [\UserInfo::getByID($user->getUserID())]),
            [
                'localeName' => $locale->getName(),
                'applicant' => $user->getUserName(),
                'operator' => $operator ? $operator->getUserName() : '?',
                'teamUrl' => h($this->app->make('url/manager')->resolve(['/teams/details/', $locale->getID()])),
            ]
        );
    }

    public function userDenied(Locale $locale, ConcreteUser $user)
    {
        $operator = new \User();
        if (!$operator->isRegistered()) {
            $operator = null;
        }
        $this->send(
            'new_translator_denied',
            array_merge($this->getGlobalAdministrators(), $this->getLocaleAdministrators($locale), [\UserInfo::getByID($user->getUserID())]),
            [
                'localeName' => $locale->getName(),
                'applicant' => $user->getUserName(),
                'operator' => $operator ? $operator->getUserName() : '?',
                'teamUrl' => h($this->app->make('url/manager')->resolve(['/teams/details/', $locale->getID()])),
            ]
        );
    }

    public function translationsNeedReview(Locale $locale, $numTranslations, Package $package = null)
    {
        $translator = new \User();
        if (!$translator->isRegistered()) {
            $translator = null;
        }
        $params = [
            'localeName' => $locale->getName(),
            'translatorName' => ($translator === null) ? '?' : $translator->getUserName(),
            'numTranslations' => $numTranslations,
            'allUnreviewedUrl' => h($this->app->make('url/manager')->resolve(['/translate/online', 'unreviewed', $locale->getID()])),
        ];
        if ($package !== null) {
            $params['packageUrl'] = h($this->app->make('url/manager')->resolve(['/translate/online', $package->getID(), $locale->getID()]));
            $params['packageName'] = h($package->getDisplayName());
        }
        $this->send(
            'translations_need_review',
            array_merge($this->getGlobalAdministrators(), $this->getLocaleAdministrators($locale)),
            $params
        );
    }

    public function errorFetchingGitRepository(\CommunityTranslation\Git\Repository $gitRepository, \Exception $error)
    {
        $this->send(
            'error_fetching_gitrepository',
            array_merge($this->getGlobalAdministrators()),
            [
                'repositoryName' => $gitRepository->getName(),
                'errorMessage' => nl2br(h($error->getMessage())),
                'stackTrace' => nl2br(h($error->getTraceAsString())),
            ]
        );
    }
}
