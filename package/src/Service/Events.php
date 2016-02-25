<?php
namespace Concrete\Package\CommunityTranslation\Src\Service;

use Concrete\Core\Application\Application;

class Events implements \Symfony\Component\EventDispatcher\EventSubscriberInterface,  \Concrete\Core\Application\ApplicationAwareInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            'on_user_enter_group' => 'userEnterGroup',
            'community_translation.on_locale_approved' => 'localeApproved',
        );
    }

    /**
     * The application object.
     *
     * @var Application
     */
    protected $app;

    /**
     * Set the application object.
     *
     * @param Application $application
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;
    }

    public function userEnterGroup(\Concrete\Core\User\Event\UserGroup $evt)
    {
        $mail = null;
        try {
            $user = new \User();
            if ($user->isLoggedIn() && $user->getUserID() == $evt->getUserObject()->getUserID()) {
                $groupManager = $this->app->make('community_translation/groups');
                /* @var \Concrete\Package\CommunityTranslation\Src\Service\Groups $groupManager */
                $locale = $groupManager->decodeAspiringTranslatorsGroup($evt->getGroupObject());
                if ($locale !== null) {
                    $mail = $this->app->make('mail');
                    /* @var \Concrete\Core\Mail\Service $mail */
                    $config = \Package::getByHandle('community_translation')->getFileConfig();
                    $senderAddress = $config->get('options.notificationsSenderAddress');
                    if ($senderAddress) {
                        $senderName = $config->get('options.notificationsSenderName');
                        $mail->from($senderAddress, $senderName ?: null);
                    }
                    $params = array(
                        'siteName' => $this->app->make('config')->get('concrete.site'),
                        'localeName' => $locale->getName(),
                        'aspirantName' => h($user->getUserName()),
                        'teamUrl' => h($this->app->make('url/manager')->resolve(array('/team/details/', $locale->getID()))),
                    );
                    $subscribed = array();
                    foreach ($groupManager->getGlobalAdministrators()->getGroupMembers() as $ui) {
                        if (!isset($subscribed[$ui->getUserID()])) {
                            $subscribed[$ui->getUserID()] = $ui;
                        }
                    }
                    foreach ($groupManager->getAdministrators($locale)->getGroupMembers() as $ui) {
                        if (!isset($subscribed[$ui->getUserID()])) {
                            $subscribed[$ui->getUserID()] = $ui;
                        }
                    }
                    foreach ($subscribed as $ui) {
                        try {
                            /* @var \Concrete\Core\User\UserInfo $ui */
                            $mail->reset();
                            foreach ($params as $key => $val) {
                                $mail->addParameter($key, $val);
                            }
                            $mail->addParameter('recipientName', h($ui->getUserName()));
                            $mail->load('new_aspirant_translator', 'community_translation');
                            $mail->to($ui->getUserEmail(), $ui->getUserName());
                            $mail->sendMail(false);
                        } catch (\Exception $foo) {
                        }
                    }
                }
            }
        } catch (\Exception $x) {
        }
        if ($mail !== null) {
            $mail->reset();
        }
    }

    public function localeApproved(\Concrete\Package\CommunityTranslation\Src\Service\Event\LocaleApproved $evt)
    {
        $mail = null;
        try {
            $locale = $evt->getLocale();
            $groupManager = $this->app->make('community_translation/groups');
            /* @var \Concrete\Package\CommunityTranslation\Src\Service\Groups $groupManager */
            $recipients = array();
            foreach ($groupManager->getGlobalAdministrators()->getGroupMembers() as $ui) {
                if (!isset($recipients[$ui->getUserID()])) {
                    $recipients[$ui->getUserID()] = $ui;
                }
            }
            $requestedBy = \UserInfo::getByID($locale->getRequestedBy());
            if ($requestedBy && !isset($recipients[$requestedBy->getUserID()])) {
                $recipients[$requestedBy->getUserID()] = $requestedBy;
            }
            $approver = null;
            if ($evt->getApprover()) {
                $approver = \UserInfo::getByID($evt->getApprover()->getUserID());
                if (!isset($recipients[$approver->getUserID()])) {
                    $recipients[$approver->getUserID()] = $approver;
                }
            }
            $mail = $this->app->make('mail');
            /* @var \Concrete\Core\Mail\Service $mail */
            $config = \Package::getByHandle('community_translation')->getFileConfig();
            $senderAddress = $config->get('options.notificationsSenderAddress');
            if ($senderAddress) {
                $senderName = $config->get('options.notificationsSenderName');
                $mail->from($senderAddress, $senderName ?: null);
            }
            $lang = \Localization::activeLocale();
            if ($lang !== 'en_US') {
                \Localization::changeLocale('en_US');
            }
            $params = array(
                'siteName' => $this->app->make('config')->get('concrete.site'),
                'localeName' => $locale->getName(),
                'approverName' => $approver ? $approver->getUserName() : '?',
                'requestedBy' => $requestedBy ? $requestedBy->getUserName() : '?',
                'requestedOn' => $this->app->make('helper/date')->formatDateTime($locale->getRequestedOn(), true, false),
                'teamUrl', h($this->app->make('url/manager')->resolve(array('/team/details/', $locale->getID()))),
            );
            if ($lang !== 'en_US') {
                \Localization::changeLocale($lang);
            }
            foreach ($recipients as $ui) {
                try {
                    /* @var \Concrete\Core\User\UserInfo $ui */
                    $mail->reset();
                    foreach ($params as $key => $val) {
                        $mail->addParameter($key, $val);
                    }
                    $mail->addParameter('recipientName', h($ui->getUserName()));
                    $mail->load('new_locale_approved', 'community_translation');
                    $mail->to($ui->getUserEmail(), $ui->getUserName());
                    $mail->sendMail(false);
                } catch (\Exception $foo) {
                }
            }
            
        } catch (\Exception $x) {
        }
        if ($mail !== null) {
            $mail->reset();
        }
    }
}
