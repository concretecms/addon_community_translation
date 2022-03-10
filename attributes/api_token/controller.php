<?php

declare(strict_types=1);

namespace Concrete\Package\CommunityTranslation\Attribute\ApiToken;

use CommunityTranslation\Api\Token;
use Concrete\Core\Attribute\DefaultController;
use Concrete\Core\Attribute\FontAwesomeIconFormatter;
use Concrete\Core\Error\UserMessageException;
use Exception;
use Symfony\Component\HttpFoundation\Session\Session;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends DefaultController
{
    protected const SESSIONKEY_HASH = 'community_translation.apitoken_hash';

    protected $searchIndexFieldDefinition = [
        'type' => 'string',
        'options' => ['length' => 32, 'fixed' => true, 'default' => null, 'notnull' => false],
    ];

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\Controller::getIconFormatter()
     */
    public function getIconFormatter()
    {
        return new FontAwesomeIconFormatter('key');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\Controller::createAttributeValueFromRequest()
     */
    public function createAttributeValueFromRequest()
    {
        $data = $this->post();
        $data = (is_array($data) ? $data : []) + [
            'operation' => '',
            'current-token' => '',
            'current-token-hash' => '',
        ];
        switch ($data['operation']) {
            case 'keep':
                $value = $data['current-token'];
                if (!is_string($value)) {
                    $value = '';
                }
                if ($value !== '') {
                    $hash = $this->getSessionHash(false);
                    if ($hash === '' || sha1($hash . $value) !== $data['current-token-hash']) {
                        $ok = false;
                    } else {
                        $ok = $this->app->make(Token::class)->isGenerated($value);
                    }
                    if (!$ok) {
                        throw new Exception('Hack attempt detected.');
                    }
                }
                break;
            case 'generate':
                $value = $this->app->make(Token::class)->generate();
                break;
            case 'remove':
                $value = '';
                break;
            default:
                throw new UserMessageException(t('Invalid operation'));
        }

        return $this->createAttributeValue($value);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\DefaultController::form()
     */
    public function form()
    {
        $value = '';
        $av = $this->getAttributeValue();
        if ($av) {
            $avo = $av->getValueObject();
            if ($avo) {
                $value = (string) $avo->getValue();
            }
        }
        $hash = $this->getSessionHash(true);
        $this->set('value', $value);
        $this->set('valueHash', sha1($hash . $value));
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\DefaultController::getDisplayValue()
     */
    public function getDisplayValue()
    {
        $s = '';
        $av = $this->getAttributeValue();
        if ($av) {
            $avo = $av->getValueObject();
            if ($avo) {
                $s = (string) $avo->getValue();
            }
        }

        return ($s === '') ? '' : ('<code>' . h($s) . '</code>');
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\DefaultController::search()
     */
    public function search()
    {
        $this->set('form', $this->app->make('helper/form'));
        $this->set('fieldName', $this->field('value'));
        $this->set('fieldValue', $this->request('value'));
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\DefaultController::searchForm()
     */
    public function searchForm($list)
    {
        $list->filterByAttribute($this->attributeKey->getAttributeKeyHandle(), $this->request('value'), '=');

        return $list;
    }

    private function getSessionHash(bool $generateIfNotSet = false): string
    {
        $session = $this->app->make(Session::class);
        if ($session->has(static::SESSIONKEY_HASH)) {
            $hash = $session->get(static::SESSIONKEY_HASH);
            if (is_string($hash) && $hash !== '') {
                return $hash;
            }
        }
        if ($generateIfNotSet) {
            $hash = random_bytes(128);
            $session->set(static::SESSIONKEY_HASH, $hash);

            return $hash;
        }

        return '';
    }
}
