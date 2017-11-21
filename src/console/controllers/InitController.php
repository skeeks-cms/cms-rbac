<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 20.04.2016
 */

namespace skeeks\cms\rbac\console\controllers;

use skeeks\cms\admin\AdminComponent;
use skeeks\cms\backend\IBackendComponent;
use skeeks\cms\IHasPermissions;
use skeeks\cms\models\User;
use skeeks\cms\modules\admin\components\UrlRule;
use skeeks\cms\modules\admin\controllers\AdminController;
use skeeks\cms\rbac\CmsManager;
use Yii;
use yii\base\Exception;
use yii\console\Controller;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\helpers\FileHelper;
use yii\helpers\Json;
use yii\rbac\Rule;

/**
 * Setting permissions
 *
 * Class RbacController
 * @package skeeks\cms\controllers
 */
class InitController extends Controller
{
    /**
     * @var string the default command action.
     */
    public $defaultAction = 'init';

    /**
     * Загрузка конфига и применение правил
     */
    public function actionInit()
    {
        $this->initRbacModules();
        $this->initBackendData();
        $this->initRootAssigning();
        $this->initRootUser();
    }

    /**
     * Получение rules, permissions and data по всем расширениям и модулям
     */
    public function initRbacModules()
    {
        $this->stdout("Init rules, permissions adn data from all modules and extensions\n\n", Console::BOLD);
        $this->stdout("\t1) Loading config\n", Console::FG_YELLOW);
        if (!$config = $this->loadConfig()) {
            $this->stdout("Start script: not found data for rbac migrations", Console::FG_RED);
            die;
        }
        $this->stdout("\t2) Start migrations\n", Console::FG_YELLOW);
        $this->applyConfig($config);
        $this->stdout("\t3) Assigning roles, privileges, rules\n", Console::FG_YELLOW);
        $this->applyAssigningConfig($config);
    }

    /**
     * Сканирование всех расширений и модулей и получение правил для rbac миграций
     * @return array
     */
    public function loadConfig()
    {
        $config = \Yii::$app->authManager->config;

        $this->stdout("\tAll config is ready: ", Console::FG_GREEN);
        $this->stdout(" (rules: " . count(ArrayHelper::getValue($config, 'rules', [])) . ';');
        $this->stdout(" roles: " . count(ArrayHelper::getValue($config, 'roles', [])) . ';');
        $this->stdout(" permissions: " . count(ArrayHelper::getValue($config, 'permissions', [])) . ';)');
        $this->stdout("\n");
        return $config;
    }

    /**
     * @param array $config
     */
    public function applyConfig($config = [])
    {
        if ($rules = ArrayHelper::getValue($config, 'rules')) {
            $this->stdout("\tInit rules: " . count($rules) . "\n");
            foreach ($rules as $data) {
                if ($rule = $this->_applyRule($data)) {
                    //$this->stdout("\t\t- success: " . $rule->name . "\n");
                } else {
                    //$this->stdout("\t\t- error config rule: " . Json::encode($data) . "\n");
                }
            }
        }
        if ($roles = ArrayHelper::getValue($config, 'roles')) {
            $this->stdout("\tInit roles: " . count($roles) . "\n");
            foreach ($roles as $data) {
                if ($role = $this->_applyRole($data)) {
                    //$this->stdout("\t\t- success: " . $role->name . "\n");
                } else {
                    //$this->stdout("\t\t- error config role: " . Json::encode($data) . "\n");
                }
            }
        }
        if ($permissions = ArrayHelper::getValue($config, 'permissions')) {
            $this->stdout("\tInit permissions: " . count($permissions) . "\n");
            foreach ($permissions as $data) {
                if ($permission = $this->_applyPermission($data)) {
                    //$this->stdout("\t\t- success: " . $permission->name . "\n");
                } else {
                    //$this->stdout("\t\t- error config role: " . Json::encode($data) . "\n");
                }
            }
        }
    }

    /**
     * Применение одного правила по данным из конфига
     * @param $config
     * @return bool
     */
    protected function _applyRule($config)
    {
        if (!is_array($config)) {
            return false;
        }
        if (!$calssName = ArrayHelper::getValue($config, 'class')) {
            return false;
        }
        if (!class_exists($calssName)) {
            return false;
        }
        $rule = new $calssName;
        if (!$rule instanceof Rule) {
            return false;
        }

        if ($ruleExist = \Yii::$app->authManager->getRule($rule->name)) {
            return $ruleExist;
        }

        if (\Yii::$app->authManager->add($rule)) {
            return $rule;
        }

        return false;
    }

    /**
     * Применение одного правила по данным из конфига
     * @param $config
     * @return bool
     */
    protected function _applyRole($config)
    {
        if (!is_array($config)) {
            return false;
        }
        if (!$name = ArrayHelper::getValue($config, 'name')) {
            return false;
        }
        $description = ArrayHelper::getValue($config, 'description');
        if ($role = \Yii::$app->authManager->getRole($name)) {
            return $role;
        }
        //Менеджер который может управлять только своими данными
        $role = \Yii::$app->authManager->createRole($name);
        $role->description = $description;
        if (\Yii::$app->authManager->add($role)) {
            return $role;
        }
        return false;
    }

    /**
     * Применение одного правила по данным из конфига
     * @param $config
     * @return bool
     */
    protected function _applyPermission($config)
    {
        if (!is_array($config)) {
            return false;
        }
        if (!$name = ArrayHelper::getValue($config, 'name')) {
            return false;
        }
        $description = ArrayHelper::getValue($config, 'description');
        $ruleName = ArrayHelper::getValue($config, 'ruleName', '');
        if ($role = \Yii::$app->authManager->getPermission($name)) {
            return $role;
        }
        //Менеджер который может управлять только своими данными
        $role = \Yii::$app->authManager->createPermission($name);
        if ($description) {
            $role->description = $description;
        }
        if ($ruleName) {
            $role->ruleName = $ruleName;
        }
        if (\Yii::$app->authManager->add($role)) {
            return $role;
        }
        return false;
    }

    public function applyAssigningConfig($config)
    {
        if ($roles = ArrayHelper::getValue($config, 'roles')) {
            $this->stdout("\tAssining roles: " . count($roles) . "\n");
            foreach ($roles as $data) {
                if ($role = $this->_assignRole($data)) {
                    //$this->stdout("\t- success assigned: " . $role->name . "\n");
                }
            }
        }
        if ($permissions = ArrayHelper::getValue($config, 'permissions')) {
            $this->stdout("\tAssining permissions: " . count($roles) . "\n");
            foreach ($permissions as $data) {
                if ($permission = $this->_assignPermission($data)) {
                    //$this->stdout("\t- success assigned: " . $permission->name . "\n");
                }
            }
        }
    }

    /**
     * Применение одного правила по данным из конфига
     * @param $config
     * @return bool
     */
    protected function _assignRole($config)
    {
        if (!is_array($config)) {
            return false;
        }
        if (!$name = ArrayHelper::getValue($config, 'name')) {
            return false;
        }
        if (!$child = ArrayHelper::getValue($config, 'child')) {
            return false;
        }
        if (!$role = \Yii::$app->authManager->getRole($name)) {
            return false;
        }
        if ($childRoles = ArrayHelper::getValue($child, 'roles')) {
            if ($childRoles) {
                foreach ($childRoles as $name) {
                    if ($roleChild = \Yii::$app->authManager->getRole($name)) {
                        try {
                            \Yii::$app->authManager->addChild($role, $roleChild);
                            $this->stdout("\t\tassign child role: " . $name . ' - success' . "\n", Console::FG_GREEN);
                        } catch (\Exception $e) {
                            //$this->stdout(' - already exist'. "\n");
                        }
                    }
                }
            }
        }
        if ($childPermissions = ArrayHelper::getValue($child, 'permissions')) {
            if ($childPermissions) {
                foreach ($childPermissions as $name) {
                    if ($permissionChild = \Yii::$app->authManager->getPermission($name)) {
                        try {
                            \Yii::$app->authManager->addChild($role, $permissionChild);
                            $this->stdout("\t\tassign child permission: " . $name . " - success" . "\n",
                                Console::FG_GREEN);
                        } catch (\Exception $e) {
                            //$this->stdout(' - already exist'. "\n");
                        }
                    }
                }
            }
        }
        return $role;
    }

    /**
     * Применение одного правила по данным из конфига
     * @param $config
     * @return bool
     */
    protected function _assignPermission($config)
    {
        if (!is_array($config)) {
            return false;
        }
        if (!$name = ArrayHelper::getValue($config, 'name')) {
            return false;
        }
        if (!$child = ArrayHelper::getValue($config, 'child')) {
            return false;
        }
        if (!$permission = \Yii::$app->authManager->getPermission($name)) {
            return false;
        }
        if ($childRoles = ArrayHelper::getValue($child, 'roles')) {
            if ($childRoles) {
                foreach ($childRoles as $name) {
                    if ($roleChild = \Yii::$app->authManager->getRole($name)) {
                        try {
                            \Yii::$app->authManager->addChild($permission, $roleChild);
                            $this->stdout("\t\tassign child role: " . $name . ' - success' . "\n", Console::FG_GREEN);
                        } catch (\Exception $e) {
                            //$this->stdout(' - already exist' . "\n");
                        }
                    }
                }
            }
        }
        if ($childPermissions = ArrayHelper::getValue($child, 'permissions')) {
            if ($childPermissions) {
                foreach ($childPermissions as $name) {
                    if ($permissionChild = \Yii::$app->authManager->getPermission($name)) {
                        try {
                            \Yii::$app->authManager->addChild($permission, $permissionChild);
                            $this->stdout("\t\tassign child permission: " . $name . ' - success' . "\n",
                                Console::FG_GREEN);
                        } catch (\Exception $e) {
                            //$this->stdout(' - already exist' . "\n");
                        }
                    }
                }
            }
        }
        return $permission;
    }

    public function initBackendData()
    {
        $this->stdout("Init backend data\n", Console::BOLD);

        $auth = Yii::$app->authManager;
        //print_r(\Yii::getAlias('@vendor/skeeks/cms/app-web-create.php'));die;

        /*$config = \yii\helpers\ArrayHelper::merge(
            (array) require( \Yii::getAlias('@vendor/skeeks/cms/tmp-config-extensions.php') ),
            (array) require( \Yii::getAlias('@vendor/skeeks/cms/app-config.php') )
        );
        \Yii::endProfile('Load config app');

        return new yii\web\Application($config);

        $webApplication = include_once \Yii::getAlias('@vendor/skeeks/cms/app-web-create.php');
        var_dump($webApplication);die;*/

        foreach (Yii::$app->getComponents(true) as $id => $component) {
            $component = \Yii::$app->get($id);

            if ($component instanceof IBackendComponent) {
                $this->stdout("\tInit backend {$id}\n");
                foreach ($component->getMenu()->data as $itemData) {
                    $this->_initMenuItem($itemData);
                }

            }
        }

        return $this;
    }

    protected function _initMenuItem($itemData = null)
    {
        if (!is_array($itemData)) {
            return false;
        }

        if ($url = ArrayHelper::getValue($itemData, 'url')) {

            if (is_array($url)) {

                $url = $url[0];
                if (!$url || !is_string($url)) {
                    return false;
                }

                try {
                    /**
                     * @var $controller \yii\web\Controller|IHasPermissions
                     */
                    if ($result = \Yii::$app->createController($url)) {
                        list($controller, $route) = $result;

                        $this->stdout("\t\tcreated: {$url}\n", Console::FG_GREEN);

                        if ($controller) {
                            if ($controller instanceof IHasPermissions) {
                                $controller->isAllow;
                            }
                        }
                    } else {
                        $this->stdout("\t\tnot create: {$url}\n", Console::FG_RED);
                    }
                } catch (\Exception $e) {
                    $this->stdout("\t\t{$e->getMessage()}\n", Console::FG_RED);
                }

            }
        }

        if ($items = ArrayHelper::getValue($itemData, 'items')) {
            if (is_array($items)) {
                foreach ($items as $item) {
                    $this->_initMenuItem($item);
                }
            }
        }

        return $this;
    }

    public function initRootAssigning()
    {
        $this->stdout("Init root assigning \n", Console::BOLD);
        $roleRoot = \Yii::$app->authManager->getRole(CmsManager::ROLE_ROOT);
        foreach (\Yii::$app->authManager->getPermissions() as $permission) {
            //$this->stdout("\t\tassign root permisssion: " . $permission->name);
            try {
                \Yii::$app->authManager->addChild($roleRoot, $permission);
                //$this->stdout(' - success' . "\n");
            } catch (\Exception $e) {
                //$this->stdout(' - already exist' . "\n");
            }
        };

        foreach (\Yii::$app->authManager->getRoles() as $role) {
            //$this->stdout("\t\tassign root role: " . $role->name);
            try {
                \Yii::$app->authManager->addChild($roleRoot, $role);
                //$this->stdout(' - success' . "\n");
            } catch (\Exception $e) {
                //$this->stdout(' - already exist' . "\n");
            }
        };
    }

    /**
     * Автоматическая генерация
     * @return $this
     */
    protected function initRootUser()
    {
        $this->stdout("Init root user \n", Console::BOLD);
        $root = User::findByUsername('root');
        $aManager = \Yii::$app->authManager;
        if ($root && $aManager->getRole(CmsManager::ROLE_ROOT)) {
            if (!$aManager->getAssignment(CmsManager::ROLE_ROOT, $root->primaryKey)) {
                $aManager->assign($aManager->getRole(CmsManager::ROLE_ROOT), $root->primaryKey);
            }
        }
        return $this;
    }

    /**
     * Загрузить и посмотреть данные конфига
     */
    public function actionViewConfig()
    {
        $this->loadConfig();
    }
}