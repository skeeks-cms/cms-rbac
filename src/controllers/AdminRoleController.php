<?php
/**
 * AdminRoleController
 *
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010-2014 SkeekS (Sx)
 * @date 27.01.2015
 * @since 1.0.0
 */

namespace skeeks\cms\rbac\controllers;

use skeeks\cms\backend\actions\BackendModelAction;
use skeeks\cms\backend\controllers\BackendModelStandartController;
use skeeks\cms\backend\grid\DefaultActionColumn;
use skeeks\cms\helpers\RequestResponse;
use skeeks\cms\modules\admin\actions\AdminAction;
use skeeks\cms\modules\admin\actions\modelEditor\AdminOneModelEditAction;
use skeeks\cms\rbac\CmsManager;
use skeeks\cms\rbac\models\AuthItem;
use skeeks\cms\rbac\models\CmsAuthItem;
use skeeks\cms\rbac\models\searchs\AuthItem as AuthItemSearch;
use skeeks\yii2\form\fields\HtmlBlock;
use Yii;
use yii\base\Event;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
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
class AdminRoleController extends BackendModelStandartController
{
    public function init()
    {
        $this->name = \Yii::t('skeeks/rbac', "Managing roles");
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
                            CmsAuthItem::tableName().".type" => Role::TYPE_ROLE
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
                            'label' => \Yii::t('skeeks/rbac', 'Role'),
                            'format' => 'raw',
                            'value' => function (CmsAuthItem $cmsAuthItem) {
                                return \yii\helpers\Html::a($cmsAuthItem->name, "#", [
                                    'class' => "sx-trigger-action",
                                ]). "<div style='color: gray'>{$cmsAuthItem->description}</div>";
                            }
                        ]
                    ]
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
            /*'create' => [
                'class'    => AdminAction::className(),
                'callback' => [$this, 'actionCreate'],
            ],
            "update" => [
                'class'    => AdminOneModelEditAction::className(),
                'callback' => [$this, 'actionUpdate'],
            ],*/
            "delete" => [
                'callback' => [$this, 'actionDelete'],
            ],
        ]);
    }

    public function updateFields($action)
    {
        $model = $action->model;
        $model->type = Item::TYPE_ROLE;
        
        return [
            'name',
            'description',
            [
                'class' => HtmlBlock::class,
                'content' => '<div style="display: none">',
            ],
            'type',

            [
                'class' => HtmlBlock::class,
                'content' => '</div>',
            ],
        ];
    }
    /**
     * @return Role
     * @throws NotFoundHttpException
     */
    public function getModel()
    {
        if ($this->_model === null) {
            if ($pk = \Yii::$app->request->get($this->requestPkParamName)) {
                $this->_model = $this->findModel($pk);
            }
        }
        return $this->_model;
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
        $model = $this->findModel($id);
        $authManager = Yii::$app->getAuthManager();
        $avaliable = $assigned = [
            'Roles'      => [],
            'Permission' => [],
            'Routes'     => [],
        ];
        $children = array_keys($authManager->getChildren($id));
        $children[] = $id;
        foreach ($authManager->getRoles() as $name => $role) {
            if (in_array($name, $children)) {
                continue;
            }
            $avaliable['Roles'][$name] = $name.' — '.$role->description;
        }
        foreach ($authManager->getPermissions() as $name => $role) {
            if (in_array($name, $children)) {
                continue;
            }

            if (isset($name[0])) {
                $avaliable[$name[0] === '/' ? 'Routes' : 'Permission'][$name] = $name.' — '.$role->description;
            }
        }
        foreach ($authManager->getChildren($id) as $name => $child) {
            if ($child->type == Item::TYPE_ROLE) {
                $assigned['Roles'][$name] = $name.' — '.$child->description;
            } else {
                if (isset($name[0])) {
                    $assigned[$name[0] === '/' ? 'Routes' : 'Permission'][$name] = $name.' — '.$child->description;
                } else {
                    $assigned['Permission'][$name] = $name.' — '.$child->description;
                }
            }
        }
        $avaliable = array_filter($avaliable);
        $assigned = array_filter($assigned);
        return $this->render('view', ['model' => $model, 'avaliable' => $avaliable, 'assigned' => $assigned]);
    }

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
                $model = $this->findModel($id);
                if (!in_array($model->item->name, CmsManager::protectedRoles())) {
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
    public function actionAssign()
    {
        $action = Yii::$app->getRequest()->get('action');
        $id = Yii::$app->getRequest()->get('id');
        $post = Yii::$app->getRequest()->post();
        $roles = ArrayHelper::getValue($post, 'roles');
        $manager = Yii::$app->getAuthManager();
        $parent = $manager->getRole($id);
        $error = [];
        if ($action == 'assign') {
            foreach ($roles as $role) {
                $child = $manager->getRole($role);
                $child = $child ?: $manager->getPermission($role);
                try {
                    $manager->addChild($parent, $child);
                } catch (\Exception $e) {
                    $error[] = $e->getMessage();
                }
            }
        } else {
            if ($roles) {
                foreach ($roles as $role) {
                    $child = $manager->getRole($role);
                    $child = $child ?: $manager->getPermission($role);
                    try {
                        $manager->removeChild($parent, $child);
                    } catch (\Exception $e) {
                        $error[] = $e->getMessage();
                    }
                }
            }

        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
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
     * Finds the AuthItem model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param string $id
     * @return AuthItem      the loaded model
     * @throws HttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        $item = Yii::$app->getAuthManager()->getRole($id);
        if ($item) {
            return new AuthItem($item);
        } else {
            throw new NotFoundHttpException(\Yii::t('app', 'The requested page does not exist.'));
        }
    }
}