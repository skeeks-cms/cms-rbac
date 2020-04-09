<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 20.04.2016
 */

namespace skeeks\cms\rbac\models;

use skeeks\cms\models\CmsUser;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\rbac\Item;

/**
 * This is the model class for table "tbl_auth_item".
 *
 * @property string $name
 * @property integer $type
 * @property string $description
 * @property string $rule_name
 * @property string $data
 *
 * @property CmsAuthAssignment[] $authAssignments
 * @property CmsUser[] $users
 */
class CmsAuthItem extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%auth_item}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [
                ['rule_name'],
                'in',
                'range' => array_keys(Yii::$app->authManager->getRules()),
                'message' => 'Rule not exists'
            ],
            [['name'], 'unique'],
            [['name', 'type'], 'required'],
            [['type'], 'integer'],
            [['description', 'data', 'rule_name'], 'default'],
            [['name'], 'string', 'max' => 64]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => Yii::t('skeeks/rbac', 'Название'),
            'type' => Yii::t('skeeks/rbac', 'Тип'),
            'description' => Yii::t('skeeks/rbac', 'Описание'),
            'rule_name' => Yii::t('skeeks/rbac', 'Правило'),
            'data' => Yii::t('skeeks/rbac', 'Данные'),
        ];
    }


    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAuthAssignments()
    {
        return $this->hasMany(CmsAuthAssignment::className(), ['item_name' => 'name']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUsers()
    {
        return $this->hasMany(CmsUser::className(), ['id' => 'user_id'])->viaTable('auth_assignment',
            ['item_name' => 'name']);
    }
}