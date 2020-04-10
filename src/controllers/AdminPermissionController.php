<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 20.04.2016
 */

namespace skeeks\cms\rbac\controllers;

use skeeks\cms\backend\actions\BackendModelAction;
use skeeks\cms\backend\controllers\BackendModelStandartController;
use skeeks\cms\backend\IBackendComponent;
use skeeks\cms\backend\IHasInfoActions;
use skeeks\cms\Exception;
use skeeks\cms\helpers\RequestResponse;
use skeeks\cms\IHasPermissions;
use skeeks\cms\modules\admin\actions\AdminAction;
use skeeks\cms\rbac\CmsManager;
use skeeks\cms\rbac\models\AuthItem;
use skeeks\cms\rbac\models\CmsAuthItem;
use skeeks\cms\rbac\models\searchs\AuthItem as AuthItemSearch;
use skeeks\yii2\form\fields\HtmlBlock;
use skeeks\yii2\form\fields\SelectField;
use Yii;
use yii\base\Event;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\rbac\Item;
use yii\rbac\Role;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\widgets\ActiveForm;

/**
 * AuthItemController implements the CRUD actions for AuthItem model.
 */
class AdminPermissionController extends BackendModelStandartController
{
    public function init()
    {
        $this->name = \Yii::t('skeeks/rbac', "Privileges");
        $this->modelShowAttribute = "name";
        $this->modelPkAttribute = "name";
        $this->modelClassName = CmsAuthItem::class;

        $this->generateAccessActions = false;
        $this->permissionName = CmsManager::PERMISSION_ROOT_ACCESS;

        parent::init();
    }

    public function actions()
    {
        return ArrayHelper::merge(parent::actions(), [
            'index'  => [
                "filters" => [
                    'visibleFilters' => [
                        'name',
                    ],
                ],
                'grid'    => [
                    'on init' => function (Event $e) {
                        /**
                         * @var $dataProvider ActiveDataProvider
                         * @var $query ActiveQuery
                         */
                        $query = $e->sender->dataProvider->query;
                        $dataProvider = $e->sender->dataProvider;

                        $query->andWhere([
                            CmsAuthItem::tableName().".type" => Role::TYPE_PERMISSION,
                        ]);

                        /* $query->select([
                             CmsContentProperty::tableName().'.*',
                             //'countElementProperties' => new Expression("count(*)"),
                             'countElementProperties' => $subQuery,
                         ]);*/
                    },

                    'defaultOrder'   => [
                        'name' => SORT_DESC,
                    ],
                    'visibleColumns' => [
                        'checkbox',
                        'actions',

                        'name',
                    ],

                    'columns' => [
                        'name' => [
                            'attribute' => 'name',
                            'label'     => \Yii::t('skeeks/rbac', 'Role'),
                            'format'    => 'raw',
                            'value'     => function (CmsAuthItem $cmsAuthItem) {
                                return \yii\helpers\Html::a($cmsAuthItem->name, "#", [
                                        'class' => "sx-trigger-action",
                                    ])."<div style='color: gray'>{$cmsAuthItem->description}</div>";
                            },
                        ],
                    ],
                ],
            ],
            'view'   => [
                "class"    => BackendModelAction::className(),
                "name"     => \Yii::t('skeeks/rbac', "Watch"),
                "icon"     => "fa fa-eye",
                "callback" => [$this, "actionView"],
            ],
            'create' => [
                'fields' => [$this, 'updateFields'],
            ],
            'update' => [
                'fields' => [$this, 'updateFields'],
            ],

            "update-data" => [
                "class"    => AdminAction::className(),
                "name"     => \Yii::t('app', "Update privileges"),
                "icon"     => "glyphicon glyphicon-retweet",
                "method"   => "post",
                "request"  => "ajax",
                'callback' => [$this, 'actionUpdateData'],
            ],

            "delete" => [
                'callback' => [$this, 'actionDelete'],
            ],
        ]);
    }

    public function updateFields($action)
    {
        $model = $action->model;
        $model->type = Item::TYPE_PERMISSION;

        return [
            'name',
            'description',
            'rule_name' => [
                'class' => SelectField::class,
                'items' => \yii\helpers\ArrayHelper::map(
                    Yii::$app->authManager->getRules(),
                    'name', 'name'
                ),
            ],
            [
                'class'   => HtmlBlock::class,
                'content' => '<div style="display: none">',
            ],
            'type',

            [
                'class'   => HtmlBlock::class,
                'content' => '</div>',
            ],
        ];
    }

    /**
     * @return Role
     * @throws NotFoundHttpException
     */
    /*public function getModel()
    {
        if ($this->_model === null) {
            if ($pk = \Yii::$app->request->get($this->requestPkParamName)) {
                $this->_model = $this->findModel($pk);
            }
        }
        return $this->_model;
    }*/

    /**
     * Finds the AuthItem model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return AuthItem      the loaded model
     * @throws HttpException if the model cannot be found
     */
    /*protected function findModel($id)
    {
        $item = Yii::$app->getAuthManager()->getPermission($id);
        if ($item) {
            return new AuthItem($item);
        } else {
            throw new NotFoundHttpException(\Yii::t('app', 'The requested page does not exist.'));
        }
    }*/

    public function actionUpdateData()
    {
        $rr = new RequestResponse();
        if ($rr->isRequestAjaxPost()) {
            foreach (\Yii::$app->getComponents(true) as $id => $component) {
                $component = \Yii::$app->get($id);

                if ($component instanceof IBackendComponent) {
                    foreach ($component->getMenu()->data as $itemData) {
                        $this->_initMenuItem($itemData);
                    }
                }
            }

            $rr->success = true;
            $rr->message = \Yii::t("app", "Update completed");
            return $rr;
        }
        return [];
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

                /**
                 * @var $controller \yii\web\Controller|IHasPermissions
                 */
                list($controller, $route) = \Yii::$app->createController($url);

                if ($controller) {
                    if ($controller instanceof IHasPermissions) {
                        $controller->isAllow;

                        if ($controller instanceof IHasInfoActions) {
                            if ($actions = $controller->getAllActions()) {
                                foreach ($actions as $action) {
                                    $action->isAllow;
                                }
                            }
                        }
                    }
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
     * Displays a single AuthItem model.
     * @param string $id
     * @return mixed
     */
    public function actionView()
    {
        $model = $this->model;
        $id = $model->name;
        //$model = $this->findModel($id);
        $authManager = Yii::$app->getAuthManager();
        $avaliable = $assigned = [
            'Permission' => [],
            'Routes'     => [],
        ];
        $children = array_keys($authManager->getChildren($id));
        $children[] = $id;
        foreach ($authManager->getPermissions() as $name => $role) {
            if (in_array($name, $children)) {
                continue;
            }
            if (isset($name[0])) {
                $avaliable[$name[0] === '/' ? 'Routes' : 'Permission'][$name] = $name.' — '.$role->description;
            }
        }
        foreach ($authManager->getChildren($id) as $name => $child) {
            $assigned[$name[0] === '/' ? 'Routes' : 'Permission'][$name] = $name.' — '.$child->description;;
        }
        $avaliable = array_filter($avaliable);
        $assigned = array_filter($assigned);
        return $this->render('view', ['model' => $model, 'avaliable' => $avaliable, 'assigned' => $assigned]);
    }

    /**
     * Creates a new AuthItem model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    /*public function actionCreate()
    {
        $model = new AuthItem(null);
        $model->type = Item::TYPE_PERMISSION;
        if (\Yii::$app->request->isAjax && !\Yii::$app->request->isPjax) {
            $model->load(\Yii::$app->request->post());
            \Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        if ($model->load(Yii::$app->getRequest()->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->name]);
        } else {
            return $this->render('create', ['model' => $model,]);
        }
    }*/

    /**
     * Updates an existing AuthItem model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param string $id
     * @return mixed
     */
    /*public function actionUpdate()
    {
        $model = $this->model;
        $id = $model->name;
        $model = $this->findModel($id);
        if (\Yii::$app->request->isAjax && !\Yii::$app->request->isPjax) {
            $model->load(\Yii::$app->request->post());
            \Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($model);
        }
        if (\Yii::$app->request->isAjax) {
            if ($model->load(\Yii::$app->request->post()) && $model->save()) {
                \Yii::$app->getSession()->setFlash('success', \Yii::t('app', 'Saved successfully'));
            } else {
                \Yii::$app->getSession()->setFlash('error', \Yii::t('app', 'Failed to save'));
            }
        }
        return $this->render('update', ['model' => $model,]);
    }*/

    /**
     * Deletes an existing Game model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @return mixed
     */
    public function actionDelete()
    {
        $rr = new RequestResponse();
        if ($rr->isRequestAjaxPost()) {
            try {
                $model = $this->model;
                $id = $model->name;
                //$model = $this->findModel($id);
                if (!in_array($model->item->name, CmsManager::protectedPermissions())) {
                    if (\Yii::$app->getAuthManager()->remove($model->item)) {
                        $rr->message = \Yii::t('app', 'Record deleted successfully');
                        $rr->success = true;
                    } else {
                        $rr->message = \Yii::t('app', 'Record deleted unsuccessfully');
                        $rr->success = false;
                    }
                } else {
                    $rr->message = \Yii::t('app', 'This entry can not be deleted!');
                    $rr->success = false;
                }
            } catch (\Exception $e) {
                $rr->message = $e->getMessage();
                $rr->success = false;
            }
            return (array)$rr;
        }
    }

    /**
     * Assign or remove items
     * @param string $id
     * @param string $action
     * @return array
     */
    public function actionAssign($id, $action)
    {
        $post = Yii::$app->getRequest()->post();
        $roles = $post['roles'];
        $manager = Yii::$app->getAuthManager();
        $parent = $manager->getPermission($id);
        $error = [];
        if ($action == 'assign') {
            foreach ($roles as $role) {
                $child = $manager->getPermission($role);
                try {
                    $manager->addChild($parent, $child);
                } catch (\Exception $exc) {
                    $error[] = $exc->getMessage();
                }
            }
        } else {
            foreach ($roles as $role) {
                $child = $manager->getPermission($role);
                try {
                    $manager->removeChild($parent, $child);
                } catch (\Exception $exc) {
                    $error[] = $exc->getMessage();
                }
            }
        }
        Yii::$app->getResponse()->format = Response::FORMAT_JSON;
        return [
            $this->actionRoleSearch($id, 'avaliable', $post['search_av']),
            $this->actionRoleSearch($id, 'assigned', $post['search_asgn']),
            $error,
        ];
    }

    /**
     * Search role
     * @param string $id
     * @param string $target
     * @param string $term
     * @return array
     */
    public function actionRoleSearch($id, $target, $term = '')
    {
        $result = [
            'Roles'      => [],
            'Permission' => [],
            'Routes'     => [],
        ];
        $authManager = Yii::$app->authManager;
        if ($target == 'avaliable') {
            $children = array_keys($authManager->getChildren($id));
            $children[] = $id;
            foreach ($authManager->getRoles() as $name => $role) {
                if (in_array($name, $children)) {
                    continue;
                }
                if (empty($term) or strpos($name, $term) !== false) {
                    $result['Roles'][$name] = $name.' — '.$role->description;
                }
            }
            foreach ($authManager->getPermissions() as $name => $role) {
                if (in_array($name, $children)) {
                    continue;
                }
                if (empty($term) or strpos($name, $term) !== false) {
                    if (isset($name[0])) {
                        $result[$name[0] === '/' ? 'Routes' : 'Permission'][$name] = $name. " — ".$role->description;
                    } else {
                        $result['Permission'][$name] = $name. " — ".$role->description;
                    }
                }
            }
        } else {
            foreach ($authManager->getChildren($id) as $name => $child) {
                if (empty($term) or strpos($name, $term) !== false) {
                    if ($child->type == Item::TYPE_ROLE) {
                        $result['Roles'][$name] = $name. " — ".$child->description;
                    } else {
                        if (isset($name[0])) {
                            $result[$name[0] === '/' ? 'Routes' : 'Permission'][$name] = $name. " — ".$child->description;
                        }

                    }
                }
            }
        }
        return Html::renderSelectOptions('', array_filter($result));
    }

    /**
     * Выбор файла
     * @return string
     */
    public function actionPermissionForRole()
    {
        $rr = new RequestResponse();

        if (!$permissionName = \Yii::$app->request->post('permissionName')) {
            $rr->success = false;
            $rr->message = "Некорректные параметры";
            return $rr;
        }

        $permission = \Yii::$app->authManager->getPermission($permissionName);
        if (!$permission) {
            $rr->success = false;
            $rr->message = "Привилегия не найдена";
            return $rr;
        }

        $rolesValues = (array)\Yii::$app->request->post('roles');
        ///$rolesValues[] = CmsManager::ROLE_ROOT; //у root пользователя нельзя отобрать права

        foreach (\Yii::$app->authManager->getRoles() as $role) {
            if (in_array($role->name, $rolesValues)) {
                if (!\Yii::$app->authManager->hasChild($role, $permission)) {
                    \Yii::$app->authManager->addChild($role, $permission);
                }
            } else {
                if (\Yii::$app->authManager->hasChild($role, $permission)) {
                    \Yii::$app->authManager->removeChild($role, $permission);
                }
            }

            $rr->message = "Права доступа сохранены";
            $rr->success = true;
        }

        return $rr;
    }
}