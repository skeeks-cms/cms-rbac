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
use skeeks\cms\models\CmsContent;
use skeeks\cms\models\User;
use skeeks\cms\modules\admin\controllers\AdminController;
use skeeks\cms\rbac\CmsManager;
use skeeks\cms\rbac\models\CmsAuthItem;
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
 * Работа с правами доступа и ролями
 */
class InitController extends Controller
{
    /**
     * @var bool Перезагрузить все привелегии
     *      0 - нет
     *      1 - да
     */
    public $reload_perrmions = 0;

    /**
     * @var string the default command action.
     */
    public $defaultAction = 'init';

    public function options($actionID)
    {
        // $actionId might be used in subclasses to provide options specific to action id
        return ArrayHelper::merge(parent::options($actionID), [
            'reload_perrmions',
        ]);
    }

    /**
     * Применить все привилегии и роли
     */
    public function actionInit()
    {
        if ($this->reload_perrmions == 1) {
            $this->actionClearPermissions();
        }

        $this->actionInitBackendData();

        $this->actionInitRbacModules();

        $this->actionInitRootAssigning();

        $this->actionInitRootUser();
    }

    /**
     * Удаление всех привилегий
     * @return void
     */
    public function actionClearPermissions()
    {
        $this->stdout("Удаление привилегий\n", Console::FG_YELLOW);
        $deleted = CmsAuthItem::deleteAll(['type' => 2]);
        $this->stdout("\tУдалено: {$deleted}\n");
    }

    /**
     * Получение rules, permissions and data по всем расширениям и модулям
     */
    public function actionInitRbacModules()
    {
        $this->stdout("Init rules, permissions adn data from all modules and extensions\n", Console::BOLD);
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

        /*$q = CmsContent::find()->andWhere(['base_role' != CmsContent::ROLE_PRODUCTS]);
        foreach ($q->each(10) as $cmsContent)
        {
            $permissionName = $cmsContent->adminPermissionName;
            $permissions = [
                [
                    [
                        'name'        => $permissionName . "/index",
                        'description' => $cmsContent->name . " | Список",
                        'child' => [
                            'permissions' => [
                                \skeeks\crm\components\CrmComponent::CRM_PROJECT_VIEW_PERMISSION,
                            ],
                        ],
                        'ruleName' => \skeeks\crm\rbac\rules\CrmViewProjectRule::class
                    ],
                    [
                        'name'        => $permissionName . "/create",
                        'description' => $cmsContent->name . " | Список",
                        'child' => [
                            'permissions' => [
                                \skeeks\crm\components\CrmComponent::CRM_PROJECT_VIEW_PERMISSION,
                            ],
                        ],
                        'ruleName' => \skeeks\crm\rbac\rules\CrmViewProjectRule::class
                    ],
                ]
            ];
        }*/

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
            $this->stdout("\t\tInit rules: " . count($rules) . "\n");
            foreach ($rules as $data) {
                if ($rule = $this->_applyRule($data)) {
                    //$this->stdout("\t\t- success: " . $rule->name . "\n");
                } else {
                    //$this->stdout("\t\t- error config rule: " . Json::encode($data) . "\n");
                }
            }
        }
        if ($roles = ArrayHelper::getValue($config, 'roles')) {
            $this->stdout("\t\tInit roles: " . count($roles) . "\n");
            foreach ($roles as $data) {
                if ($role = $this->_applyRole($data)) {
                    //$this->stdout("\t\t- success: " . $role->name . "\n");
                } else {
                    //$this->stdout("\t\t- error config role: " . Json::encode($data) . "\n");
                }
            }
        }
        if ($permissions = ArrayHelper::getValue($config, 'permissions')) {
            $this->stdout("\t\tInit permissions: " . count($permissions) . "\n");
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
        $description = is_array($description) ? \Yii::t($description[0], $description[1]): $description;
        
        if ($role = \Yii::$app->authManager->getRole($name)) {
            if ($role->description != $description) {
                $role->description = $description;
                \Yii::$app->authManager->updateRole($name, $role);
            }
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
        $description = is_array($description) ? \Yii::t($description[0], $description[1]): $description;
        
        $ruleName = ArrayHelper::getValue($config, 'ruleName', '');
        if ($role = \Yii::$app->authManager->getPermission($name)) {
            
            if ($role->description != $description) {
                $role->description = $description;
                \Yii::$app->authManager->updatePermission($name, $role);
            }
            
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
            $this->stdout("\t\tAssining roles: " . count($roles) . "\n");
            foreach ($roles as $data) {
                if ($role = $this->_assignRole($data)) {
                    //$this->stdout("\t- success assigned: " . $role->name . "\n");
                }
            }
        }
        if ($permissions = ArrayHelper::getValue($config, 'permissions')) {
            $this->stdout("\t\tAssining permissions: " . count($roles) . "\n");
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

    /**
     * Сбор и настройка нужных привелегий из контроллеров
     * @return void
     * @throws \yii\base\InvalidConfigException
     */
    public function actionInitBackendData()
    {
        $this->stdout("Сбор и настройка нужных привелегий из контроллеров\n", Console::FG_YELLOW);

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

        $webApplication = $this->_getWebApplication();

        foreach ($webApplication->getComponents(true) as $id => $component) {
            $component = $webApplication->get($id);

            if ($component instanceof IBackendComponent) {
                $this->stdout("Init backend {$id}\n");

                foreach ($component->getMenu()->data as $itemData) {
                    $this->_initMenuItem($itemData);
                }

            }
        }

    }

    protected $_web_application = null;
    
    protected function _getWebApplication() {
        if ($this->_web_application === null) {
            $config = new \Yiisoft\Config\Config(
                new \Yiisoft\Config\ConfigPaths(ROOT_DIR, "config"),
                null,
                [
                    \Yiisoft\Config\Modifier\RecursiveMerge::groups('web', 'web-prod', 'params', "params-web-prod"),
                ],
                "params-web-prod"
            );
    
            if ($config->has('web-prod')) {
                $configData = $config->get('web-prod');
            } else {
                $configData = $config->get('web');
            }
    
            ArrayHelper::remove($configData, "components.log.targets");
            ArrayHelper::remove($configData, "bootstrap");
            $this->_web_application = new \yii\web\Application($configData);
        }
        
        return $this->_web_application;
    }
    
    protected function _initMenuItem($itemData = null)
    {
        if (!is_array($itemData)) {
            return false;
        }

        
        $applicationWeb = $this->_getWebApplication();
        

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

                    /*if (in_array($url, ['shop/admin-cms-content-element', 'cms/admin-cms-content-element'])) {
                        return false;
                    }*/
                    

                    if ($result = $applicationWeb->createController($url)) {
                        [$controller, $route] = $result;

                        

                        if ($controller) {
                            
                            
                            
                            if ($controller instanceof IHasPermissions) {
                                
                                $controller->isAllow;

                                if ($controller->generateAccessActions) {


                                    $this->stdout("\t Controller {$url}\n");
                                    
                                    $actions = $controller->actions();
                                    $totalActions = count($actions);
                                    $this->stdout("\t\t Has genereted actions\n");
                                    $this->stdout("\t\t actions: {$totalActions}\n");
                                    foreach ($actions as $key => $action)
                                    {
                                        $action = $controller->createAction($key);
                                        $action->getIsAllow();
                                    }
                                    /*
                                    if (method_exists($controller, 'getModelActions')) {
                                        $this->stdout("\t\t Has getModelActions\n");
                                        if ($controller->modelActions) {
                                            foreach ($controller->modelActions as $key => $action)
                                            {
                                                $rf = new \ReflectionClass($action);
                                                $this->stdout("\t\t\tAction: {$key}\n", Console::FG_GREEN);
                                                $this->stdout("\t\t\tAction: {$rf->getName()}\n", Console::FG_GREEN);
                                                $action->getIsAllow();
                                            }
                                        }
                                        
                                    }*/
                                        
                                }
                            }
                        }
                    } else {
                        //$this->stdout("\t\tnot create: {$url}\n", Console::FG_RED);
                    }
                } catch (\Exception $e) {
                    $this->stdout("\t\t{$e->getMessage()}\n", Console::FG_RED);
                    //die;
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

    /**
     * Настройка роли суперадминистратор
     * @return void
     */
    public function actionInitRootAssigning()
    {
        $this->stdout("Настройка роли суперадминистратор \n", Console::FG_YELLOW);
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
     * Насктройка пользователя - суперадминистратор
     * @return $this
     */
    protected function actionInitRootUser()
    {
        $this->stdout("Насктройка пользователя - суперадминистратор \n", Console::FG_YELLOW);
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
        $config = \Yii::$app->authManager->config;
        print_r($config);
    }
}