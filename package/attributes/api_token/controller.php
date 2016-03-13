<?php
namespace Concrete\Package\CommunityTranslation\Attribute\ApiToken;

class Controller extends \Concrete\Core\Attribute\Controller
{
    const DICTIONARY = 'abcefghijklmnopqrstuvwxyz1234567890_-.';

    protected $searchIndexFieldDefinition = array(
        'type' => 'string',
        'options' => array('length' => 32, 'fixed' => true, 'default' => null, 'notnull' => false),
    );

    public function deleteKey()
    {
        $cn = $this->app->make('database')->connection();
        $attributeValueIDs = $this->attributeKey->getAttributeValueIDList();
        foreach ($attributeValueIDs as $avID) {
            $cn->executeQuery('delete from atApiToken where avID = ? limit 1', array($avID));
        }
    }

    public function getValue()
    {
        $cn = $this->app->make('database')->connection();
        $value = $cn->fetchColumn('select token from atApiToken where avID = ? limit 1', array($this->getAttributeValueID()));
        if (!is_string($value)) {
            $value = '';
        }

        return $value;
    }

    public function deleteValue()
    {
        $cn = $this->app->make('database')->connection();
        /* @var \Concrete\Core\Database\Connection\Connection $cn */
        $cn->executeQuery('delete from atApiToken where avID = ? limit 1', array($this->getAttributeValueID()));
    }

    public function saveValue($value)
    {
        $this->deleteValue();
        if (is_string($value) && $value !== '') {
            $cn = $this->app->make('database')->connection();
            $cn->executeQuery(
                'insert into atApiToken (avID, token) values (?, ?)',
                array($this->getAttributeValueID(), $value)
            );
        }
    }

    public function form()
    {
        $this->set('value', $this->getValue());
    }

    public function saveForm($data)
    {
        $value = null;
        if (is_array($data) && isset($data['operation'])) {
            switch ($data['operation']) {
                case 'generate':
                    $cn = $this->app->make('database')->connection();
                    /* @var \Concrete\Core\Database\Connection\Connection $cn */
                    for (;;) {
                        $str = str_repeat(self::DICTIONARY, 10);
                        $value = substr(str_shuffle($str), 0, 32);
                        if ($cn->fetchColumn('select token from atApiToken where token = ? limit 1', array($value)) !== $value) {
                            break;
                        }
                    }
                    break;
                case 'remove':
                    $value = '';
                    break;
            }
        }
        if ($value !== null) {
            $this->saveValue($value);
        }
    }
}
