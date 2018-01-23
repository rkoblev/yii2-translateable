<?php
/**
 * @link https://github.com/creocoder/yii2-translateable
 * @copyright Copyright (c) 2015 Alexander Kochetov
 * @license http://opensource.org/licenses/BSD-3-Clause
 */

namespace creocoder\translateable;

use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\db\ActiveRecord;

/**
 * TranslateableBehavior
 *
 * @property ActiveRecord $owner
 *
 * @author Alexander Kochetov <creocoder@gmail.com>
 */
class TranslateableBehavior extends Behavior
{
    /**
     * @var string the translations relation name
     */
    public $translationRelation = 'translations';
    /**
     * @var string the translations model language attribute name
     */
    public $translationLanguageAttribute = 'language';
    /**
     * @var string[] the list of attributes to be translated
     */
    public $translationAttributes;

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_VALIDATE => 'afterValidate',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
        ];
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->translationAttributes === null) {
            throw new InvalidConfigException('The "translationAttributes" property must be set.');
        }
    }

    /**
     * Returns the translation model for the specified language.
     * @param string|null $language
     * @return ActiveRecord
     */
    public function translate($language = null)
    {
        return $this->getTranslation($language);
    }

    /**
     * Returns the translation model for the specified language.
     * @param string|null $language
     * @return ActiveRecord
     */
    public function getTranslation($language = null)
    {
        if ($language === null) {
            $language = Yii::$app->language;
        }

        /* @var ActiveRecord[] $translations */
        $translations = $this->owner->{$this->translationRelation};

        foreach ($translations as $translation) {
            if ($translation->getAttribute($this->translationLanguageAttribute) === $language) {
                return $translation;
            }
        }

        /* @var ActiveRecord $class */
        $class = $this->owner->getRelation($this->translationRelation)->modelClass;
        /* @var ActiveRecord $translation */
        $translation = new $class();
        $translation->setAttribute($this->translationLanguageAttribute, $language);
        $translations[] = $translation;
        $this->owner->populateRelation($this->translationRelation, $translations);

        return $translation;
    }

    /**
     * Returns a value indicating whether the translation model for the specified language exists.
     * @param string|null $language
     * @return boolean
     */
    public function hasTranslation($language = null)
    {
        if ($language === null) {
            $language = Yii::$app->language;
        }

        /* @var ActiveRecord $translation */
        foreach ($this->owner->{$this->translationRelation} as $translation) {
            if ($translation->getAttribute($this->translationLanguageAttribute) === $language) {
                return true;
            }
        }

        return false;
    }

    /**
     * Auto-populates translation fields and validate
     * @return void
     * @throws \ReflectionException
     */
    public function afterValidate()
    {
        // populate from POST
        /* @var ActiveRecord $class */
        $class = $this->owner->getRelation($this->translationRelation)->modelClass;

        /* If method create or update - populate attributes */
        $className = (new \ReflectionClass($class))->getShortName();
        foreach (Yii::$app->request->post($className, []) as $language => $data) {
            foreach ($data as $attribute => $translation) {
                $this->owner->translate($language)->$attribute = $translation;
            }
        }

        /** @var ActiveRecord[] $translations */
        $translations = $this->owner->{$this->translationRelation};
        $attributeNames = null;
        if (!empty($translations)) { // get 1st, all models are same
            $attributeNames = array_keys($translations[0]->getAttributes());
            $attributeNames = array_combine($attributeNames, $attributeNames); // key = value
            if ($this->owner->getIsNewRecord()) {
                foreach ($this->owner->getRelation($this->translationRelation)->link as $a => $b) {
                    //skip FK checks, our record will be saved later and we trust Yii2 it will have correct ID
                    unset($attributeNames[$a]);
                }
            }
        }

        if (!Model::validateMultiple($translations, $attributeNames)) {
            $errors = [];
            foreach ($translations as $model) {
                if ($model->hasErrors()){
                    $errors = $model->getErrors();
                }
            }

            $this->owner->addError($this->translationRelation, $errors);
        }
    }

    /**
     * @return void
     */
    public function afterSave()
    {
        /* @var ActiveRecord $translation */
        foreach ($this->owner->{$this->translationRelation} as $translation) {
            $this->owner->link($this->translationRelation, $translation);
        }
    }

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return in_array($name, $this->translationAttributes) ?: parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return in_array($name, $this->translationAttributes) ?: parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        return $this->getTranslation()->getAttribute($name);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        $translation = $this->getTranslation();
        $translation->setAttribute($name, $value);
    }
}
