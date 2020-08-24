<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 20.03.2015
 */

namespace skeeks\cms\rbac;

use yii\base\InvalidConfigException;
use yii\helpers\ArrayHelper;

/**
 * Class CmsManager
 * @package skeeks\cms\rbac
 */
class CmsManager extends \yii\rbac\DbManager
{
    /**
     * @var array
     */
    public $config = [];

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        if ($this->config && !is_array($this->config)) {
            throw new InvalidConfigException('Config must be array');
        }
        parent::init();
    }

    /**
     * Доступ суперадминистратора
     */
    const PERMISSION_ROOT_ACCESS = 'cms.root';
    /**
     * Доступ к админке
     */
    const PERMISSION_ADMIN_ACCESS = 'cms.admin-access';
    /**
     * Понель управления сайтом из сайтовой части
     */
    const PERMISSION_CONTROLL_PANEL = 'cms.controll-panel-access';


    const PERMISSION_ALLOW_EDIT = 'cms.allow-edit';

    const PERMISSION_ADMIN_DASHBOARDS_EDIT = 'cms.admin-dashboards-edit';
    const PERMISSION_USER_FULL_EDIT = 'cms.user-full-edit';

    const PERMISSION_ELFINDER_USER_FILES = 'cms.elfinder-user-files';
    const PERMISSION_ELFINDER_COMMON_PUBLIC_FILES = 'cms.elfinder-common-public-files';
    const PERMISSION_ELFINDER_ADDITIONAL_FILES = 'cms.elfinder-additional-files';

    const PERMISSION_EDIT_VIEW_FILES = 'cms.edit-view-files';


    const ROLE_GUEST = 'guest';
    const ROLE_ROOT = 'root';
    const ROLE_ADMIN = 'admin';
    const ROLE_MANGER = 'manager';
    const ROLE_EDITOR = 'editor';
    const ROLE_USER = 'user';

    static public function protectedRoles()
    {
        return [
            static::ROLE_ROOT,
            static::ROLE_ADMIN,
            static::ROLE_MANGER,
            static::ROLE_EDITOR,
            static::ROLE_USER,
            static::ROLE_GUEST,
        ];
    }

    static public function protectedPermissions()
    {
        return [
            static::PERMISSION_ADMIN_ACCESS,
            static::PERMISSION_CONTROLL_PANEL,
        ];
    }

    /**
     * @return array|\yii\rbac\Item[]|\yii\rbac\Role[]
     */
    public function getAvailableRoles()
    {
        if (!\Yii::$app->user->identity || !\Yii::$app->user->identity) {
            return $this->getRoles();
        }

        $roles = $this->getRoles();

        if (!$roles) {
            return [];
        }

        $result = [];
        //ArrayHelper::remove($roles, self::ROLE_GUEST);

        if (\Yii::$app->user->can(self::PERMISSION_ROOT_ACCESS)) {
            return $roles;
        }

        ArrayHelper::remove($roles, self::ROLE_ROOT);

        return $roles;
    }

}