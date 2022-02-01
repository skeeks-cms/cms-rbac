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

/**
 * This is the model class for table "tbl_auth_item".
 *
 * @property string      $name
 * @property integer     $type
 * @property string      $description
 * @property string      $ruleName
 * @property string      $data
 *
 * @property CmsAuthItem $authItem
 * @property CmsUser     $user
 */
class CmsAuthAssignment extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%auth_assignment}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['item_name', 'cms_user_id'], 'required'],
            [['cms_user_id', 'created_at'], 'integer'],
            [['item_name'], 'string', 'max' => 64],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'item_name'   => Yii::t('app', 'Item Name'),
            'cms_user_id' => Yii::t('app', 'User ID'),
            'created_at'  => Yii::t('app', 'Created At'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAuthItem()
    {
        return $this->hasOne(CmsAuthItem::className(), ['name' => 'item_name']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(CmsUser::className(), ['id' => 'cms_user_id']);
    }

}