<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 25.08.2015
 */
namespace skeeks\cms\rbac\widgets\adminPermissionForRoles;
use skeeks\cms\components\Cms;
use skeeks\cms\helpers\UrlHelper;
use skeeks\cms\modules\admin\components\UrlRule;
use skeeks\cms\rbac\CmsManager;
use yii\base\Widget;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\rbac\Permission;

/**
 * @property Permission $permission
 * @property array $permissionRoles
 *
 * Class AdminPermissionForRolesWidget
 *
 * @package skeeks\cms\rbac\widgets\adminPermissionForRoles
 */
class AdminPermissionForRolesWidget extends Widget
{
    /**
     * @var string Привилегия которую необходимо назначать, и настраивать.
     */
    public $permissionName        = "";
    public $permissionDescription = "";
    public $label                 = "";
    public $items                 = [];
    public $defaultRoles          = [CmsManager::ROLE_ROOT];

    /**
     * @var bool Проверят разрешение и создавать если его нет
     */
    public $createPermission      = true;


    public function init()
    {
        parent::init();

        if (!$this->items)
        {
            $this->items = \yii\helpers\ArrayHelper::map(\Yii::$app->authManager->getRoles(), 'name', 'description');
        }

        $permission = \Yii::$app->authManager->getPermission($this->permissionName);

        if ($this->createPermission && !$permission)
        {
            $permission = \Yii::$app->authManager->createPermission($this->permissionName);
            $permission->description = $this->permissionDescription;

            \Yii::$app->authManager->add($permission);
        }


        if ($this->defaultRoles && $permission)
        {
            foreach ($this->defaultRoles as $roleName)
            {
                if ($role = \Yii::$app->authManager->getRole($roleName))
                {
                    if (!\Yii::$app->authManager->hasChild($role, $permission))
                    {
                        \Yii::$app->authManager->addChild($role, $permission);
                    }
                }
            }
        }

    }

    public function run()
    {
        return $this->render('permission-for-roles', [
            'widget' => $this,
        ]);
    }

    /**
     * @return string
     */
    public function getClientOptionsJson()
    {
        return Json::encode([
            'id'                                => $this->id,
            'permissionName'                    => $this->permissionName,
            'backend'                           => Url::to(['/rbac/admin-permission/permission-for-role', UrlRule::ADMIN_PARAM_NAME => UrlRule::ADMIN_PARAM_VALUE]),
        ]);
    }

    /**
     * @return \yii\rbac\Permission
     */
    public function getPermission()
    {
        return \Yii::$app->authManager->getPermission($this->permissionName);
    }


    /**
     * @return array
     */
    public function getPermissionRoles()
    {
        $result = [];

        if ($roles = \Yii::$app->authManager->getRoles())
        {
            foreach ($roles as $role)
            {
                //Если у роли есть это разрешение
                if (\Yii::$app->authManager->hasChild($role, $this->permission))
                {
                    $result[] = $role->name;
                }
            }
        }

        return $result;
    }
}