<?php
namespace Concrete\Package\CommunityTranslation\Attribute\ApiToken;

use CommunityTranslation\Api\Token;
use Concrete\Core\Attribute\DefaultController;
use Concrete\Core\Attribute\FontAwesomeIconFormatter;
use Exception;
use Symfony\Component\HttpFoundation\Session\Session;

class Controller extends DefaultController
{
    const DICTIONARY = 'abcefghijklmnopqrstuvwxyz1234567890_-.';
    const SESSIONKEY_HASH = 'community_translation.apitoken_hash';

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

    private function getSessionHash($generateInNotSet = false)
    {
        $session = $this->app->make(Session::class);
        /* @var Session $session */
        $hash = null;
        if ($session->has(static::SESSIONKEY_HASH)) {
            $hash = (string) $session->get(static::SESSIONKEY_HASH);
            if ($hash === '') {
                $hash = null;
            }
        }
        if ($hash === null && $generateInNotSet) {
            $hash = random_bytes(128);
            $session->set(static::SESSIONKEY_HASH, $hash);
        }

        return $hash;
    }

    /**
     * {@inheritdoc}
     *
     * @see \Concrete\Core\Attribute\Controller::createAttributeValueFromRequest()
     */
    public function createAttributeValueFromRequest()
    {
        $data = $this->post();
        if (!is_array($data)) {
            $data = [];
        }
        $data += [
            'operation' => null,
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
                    if (sha1($this->getSessionHash(false) . $value) !== $data['current-token-hash']) {
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
                throw new Exception('Invalid operation');
        }

        return $this->createAttributeValue($value);
    }

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
     * @param \Concrete\Core\Search\ItemList\Database\AttributedItemList $list
     *
     * @return \Concrete\Core\Search\ItemList\Database\AttributedItemList
     */
    public function searchForm($list)
    {
        $list->filterByAttribute($this->attributeKey->getAttributeKeyHandle(), $this->request('value'), '=');

        return $list;
    }
}
