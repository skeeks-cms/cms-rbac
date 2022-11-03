<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 20.03.2015
 */

namespace skeeks\cms\rbac;

use Yii;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use yii\caching\TagDependency;
use yii\db\Connection;
use yii\db\Query;
use yii\di\Instance;
use yii\helpers\ArrayHelper;
use yii\rbac\Assignment;
use yii\rbac\Item;
use yii\rbac\Permission;
use yii\rbac\Role;
use yii\rbac\Rule;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
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

        $this->db = Instance::ensure($this->db, Connection::className());
        if ($this->enableCaching) {
            $this->cache = Instance::ensure($this->cache, Cache::className());
        } else {
            $this->cache = null;
        }
    }

    /**
     * Доступ суперадминистратора
     */
    const PERMISSION_ROOT_ACCESS = 'cms.root';

    /**
     * Роль администратора
     */
    const PERMISSION_ROLE_ADMIN_ACCESS = 'cms.admin-role-access';
    /**
     * Доступ к админке
     */
    const PERMISSION_ADMIN_ACCESS = 'cms.admin-access';

    const PERMISSION_ADMIN_DASHBOARDS_EDIT = 'cms.admin-dashboards-edit';

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
    const ROLE_WORKER = 'worker';

    static public function protectedRoles()
    {
        return [
            static::ROLE_ROOT,
            static::ROLE_ADMIN,
            static::ROLE_MANGER,
            static::ROLE_EDITOR,
            static::ROLE_USER,
            static::ROLE_GUEST,
            static::ROLE_WORKER,
        ];
    }

    static public function protectedPermissions()
    {
        return [
            static::PERMISSION_ADMIN_ACCESS,
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


    const CACHE_PART_ITEMS = 'sx.items';
    const CACHE_PART_CHILDREN = 'sx.children';
    const CACHE_PART_RULES = 'sx.rules';

    //По умолчанию проверяем что может роль гостя
    public $defaultRoles = [self::ROLE_GUEST];

    /**
     * @var boolean Enable caching
     */
    public $enableCaching = false;

    /**
     * @var string|Cache Cache component
     */
    public $cache = 'cache';

    /**
     * @var integer Cache duration
     */
    public $cacheDuration = 0;

    /**
     * @var Item[]
     * itemName => item
     */
    private $_items;

    /**
     * @var array
     * itemName => childName[]
     */
    private $_children;

    /**
     * @var array
     * userId => itemName[]
     */
    private $_assignments = [];

    /**
     * @var Rule[]
     * ruleName => rule
     */
    private $_rules;


    /**
     * @inheritdoc
     */
    public function checkAccess($userId, $permissionName, $params = [])
    {
        $this->_loadItems();
        $this->_loadChildren();
        $this->_loadRules();
        $assignments = $this->getAssignments($userId);

        return $this->checkAccessRecursive($userId, $permissionName, $params, $assignments);
    }

    /**
     * @inheritdoc
     */
    public function getAssignments($userId)
    {
        $this->_loadAssignments($userId);

        return $this->_assignments[$userId];
    }

    /**
     * @inheritdoc
     */
    protected function checkAccessRecursive($user, $itemName, $params, $assignments)
    {
        if (!isset($this->_items[$itemName])) {
            return false;
        }

        /** @var Item $item */
        $item = $this->_items[$itemName];
        Yii::trace($item instanceof Role ? \Yii::t('app', 'Checking role').": $itemName" : \Yii::t('app',
                'Checking permission')." : $itemName", __METHOD__);

        if (!$this->executeRule($user, $item, $params)) {
            return false;
        }

        if (in_array($itemName, $assignments) || in_array($itemName, $this->defaultRoles)) {
            return true;
        }

        foreach ($this->_children as $parentName => $children) {
            if (in_array($itemName, $children) && $this->checkAccessRecursive($user, $parentName, $params,
                    $assignments)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function addChild($parent, $child)
    {
        $this->_loadItems();
        $this->_loadChildren();
        parent::addChild($parent, $child);

        $this->_children[$parent->name][] = $child->name;
        $this->_invalidate(self::CACHE_PART_CHILDREN);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function removeChild($parent, $child)
    {
        $result = parent::removeChild($parent, $child);
        if ($this->_children !== null) {
            $query = (new Query)
                ->select('child')
                ->from($this->itemChildTable)
                ->where(['parent' => $parent->name]);
            $this->_children[$parent->name] = $query->column($this->db);
        }
        $this->_invalidate(self::CACHE_PART_CHILDREN);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function hasChild($parent, $child)
    {
        $this->_loadChildren();

        return isset($this->_children[$parent->name]) && in_array($child->name, $this->_children[$parent->name]);
    }


    /**
     * @inheritdoc
     */
    public function assign($role, $userId)
    {
        $assignment = new Assignment([
            'userId'    => $userId,
            'roleName'  => $role->name,
            'createdAt' => time(),
        ]);

        $this->db->createCommand()
            ->insert($this->assignmentTable, [
                'cms_user_id' => $assignment->userId,
                'item_name'   => $assignment->roleName,
                'created_at'  => $assignment->createdAt,
            ])->execute();

        unset($this->checkAccessAssignments[(string)$userId]);

        if (isset($this->_assignments[$userId]) && !in_array($role->name, $this->_assignments[$userId])) {
            $this->_assignments[$userId][] = $role->name;
        }
        return $assignment;
    }

    /**
     * @param $role
     * @param $userId
     * @return bool
     * @throws \yii\db\Exception
     */
    protected function _parentRevoke($role, $userId)
    {
        if ($this->isEmptyUserId($userId)) {
            return false;
        }

        unset($this->checkAccessAssignments[(string)$userId]);
        return $this->db->createCommand()
                ->delete($this->assignmentTable, ['cms_user_id' => (string)$userId,
                                                  'item_name' => $role->name])
                ->execute() > 0;
    }
    /**
     * @inheritdoc
     */
    public function revoke($role, $userId)
    {
        $result = $this->_parentRevoke($role, $userId);

        unset($this->_assignments[$userId]);
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function revokeAll($userId)
    {
        if (empty($userId)) {
            return false;
        }

        $result = parent::revokeAll($userId);

        $this->_assignments[$userId] = [];

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getAssignment($roleName, $userId)
    {
        $this->_loadItems();
        $this->_loadAssignments($userId);
        if (in_array($roleName, $this->_assignments[$userId]) && isset($this->_items[$roleName])) {
            return $this->_items[$roleName];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getItems($type)
    {
        $this->_loadItems();
        $items = [];

        foreach ($this->_items as $name => $item) {
            /** @var Item $item */
            if ($item->type == $type) {
                $items[$name] = $item;
            }
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    public function removeItem($item)
    {
        parent::removeItem($item);

        $this->_assignments = [];
        $this->_children = $this->_items = null;
        $this->_invalidate([self::CACHE_PART_ITEMS, self::CACHE_PART_CHILDREN]);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getItem($name)
    {
        $this->_loadItems();

        return isset($this->_items[$name]) ? $this->_items[$name] : null;
    }

    /**
     * @inheritdoc
     */
    public function updateRule($name, $rule)
    {
        parent::updateRule($name, $rule);

        if ($rule->name !== $name) {
            $this->_items = null;
            $this->_invalidate(self::CACHE_PART_ITEMS);
        }
        if ($this->_rules !== null) {
            unset($this->_rules[$name]);
            $this->_rules[$rule->name] = $rule;
        }
        $this->_invalidate(self::CACHE_PART_RULES);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getRule($name)
    {
        $this->_loadRules();

        return isset($this->_rules[$name]) ? $this->_rules[$name] : null;
    }

    /**
     * @inheritdoc
     */
    public function getRules()
    {
        $this->_loadRules();

        return $this->_rules;
    }

    /**
     * @inheritdoc
     */
    public function getRolesByUser($userId)
    {
        $this->_loadItems();
        $roles = [];
        foreach ($this->getAssignments($userId) as $name) {
            $roles[$name] = $this->_items[$name];
        }

        return $roles;
    }

    /**
     * @inheritdoc
     */
    public function getPermissionsByRole($roleName)
    {
        $childrenList = $this->getChildrenList();
        $result = [];
        $this->getChildrenRecursive($roleName, $childrenList, $result);
        if (empty($result)) {
            return [];
        }
        $this->_loadItems();
        $permissions = [];
        foreach (array_keys($result) as $itemName) {
            if (isset($this->_items[$itemName]) && $this->_items[$itemName] instanceof Permission) {
                $permissions[$itemName] = $this->_items[$itemName];
            }
        }

        return $permissions;
    }

    /**
     * @inheritdoc
     */
    protected function getChildrenList()
    {
        $this->_loadChildren();
        return $this->_children;
    }

    /**
     * @inheritdoc
     */
    public function getPermissionsByUser($userId)
    {
        $childrenList = $this->getChildrenList();
        $result = [];
        foreach ($this->getAssignments($userId) as $roleName) {
            $this->getChildrenRecursive($roleName, $childrenList, $result);
        }

        if (empty($result)) {
            return [];
        }

        $this->_loadItems();
        $permissions = [];
        foreach (array_keys($result) as $itemName) {
            if (isset($this->_items[$itemName]) && $this->_items[$itemName] instanceof Permission) {
                $permissions[$itemName] = $this->_items[$itemName];
            }
        }

        return $permissions;
    }

    /**
     * @inheritdoc
     */
    public function getChildren($name)
    {
        $this->_loadItems();
        $this->_loadChildren();
        $items = [];
        if (isset($this->_children[$name])) {
            foreach ($this->_children[$name] as $itemName) {
                $items[$itemName] = $this->_items[$itemName];
            }
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    public function removeAll()
    {
        $this->_children = [];
        $this->_items = [];
        $this->_assignments = [];
        $this->_rules = [];
        $this->removeAllAssignments();
        $this->db->createCommand()->delete($this->itemChildTable)->execute();
        $this->db->createCommand()->delete($this->itemTable)->execute();
        $this->db->createCommand()->delete($this->ruleTable)->execute();

        $this->_invalidate([self::CACHE_PART_ITEMS, self::CACHE_PART_CHILDREN, self::CACHE_PART_RULES]);
    }

    /**
     * @inheritdoc
     */
    protected function removeAllItems($type)
    {
        parent::removeAllItems($type);

        $this->_assignments = [];
        $this->_children = $this->_items = null;

        $this->_invalidate([self::CACHE_PART_ITEMS, self::CACHE_PART_CHILDREN]);
    }

    /**
     * @inheritdoc
     */
    public function removeAllRules()
    {
        parent::removeAllRules();

        $this->_rules = [];
        $this->_items = null;

        $this->_invalidate([self::CACHE_PART_ITEMS, self::CACHE_PART_RULES]);
    }

    /**
     * @inheritdoc
     */
    public function removeAllAssignments()
    {
        parent::removeAllAssignments();
        $this->_assignments = [];
    }

    /**
     * @inheritdoc
     */
    protected function removeRule($rule)
    {
        parent::removeRule($rule);

        if ($this->_rules !== null) {
            unset($this->_rules[$rule->name]);
        }
        $this->_items = null;

        $this->_invalidate([self::CACHE_PART_ITEMS, self::CACHE_PART_RULES]);

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function addRule($rule)
    {
        parent::addRule($rule);

        if ($this->_rules !== null) {
            $this->_rules[$rule->name] = $rule;
        }
        $this->_invalidate(self::CACHE_PART_RULES);

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function updateItem($name, $item)
    {
        parent::updateItem($name, $item);

        if ($item->name !== $name) {
            $this->_assignments = [];
            $this->_children = null;
            $this->_invalidate(self::CACHE_PART_CHILDREN);
        }
        $this->_items = null;
        $this->_invalidate(self::CACHE_PART_RULES);

        return true;
    }

    /**
     * @inheritdoc
     */
    protected function addItem($item)
    {
        parent::addItem($item);

        if ($this->_items !== null) {
            $this->_items[$item->name] = $item;
        }
        $this->_invalidate(self::CACHE_PART_ITEMS);

        return true;
    }

    /**
     * Invalidate cache
     * @param string $parts
     */
    private function _invalidate($parts)
    {
        if ($this->enableCaching) {
            TagDependency::invalidate($this->cache, $parts);
        }
    }

    /**
     * Build key cache
     * @param string $part
     * @return mixed
     */
    private function _buildKey($part)
    {
        return [__CLASS__, $part];
    }

    /**
     * Get data from cache
     * @param string $part
     * @return mixed
     */
    private function _getFromCache($part)
    {
        if ($this->enableCaching) {
            return $this->cache->get($this->_buildKey($part));
        }

        return false;
    }

    /**
     * Save data to cache
     * @param string $part
     * @param mixed  $data
     */
    private function _saveToCache($part, $data)
    {
        if ($this->enableCaching) {
            $this->cache->set($this->_buildKey($part), $data, $this->cacheDuration, new TagDependency([
                'tags' => $part,
            ]));
        }
    }

    /**
     * Load data. If avaliable in memory, get from memory
     * If no, get from cache. If no avaliable, get from database.
     */
    private function _loadItems()
    {

        $part = self::CACHE_PART_ITEMS;
        if ($this->_items === null && ($this->_items = $this->_getFromCache($part)) === false) {
            $query = (new Query)->from($this->itemTable);

            $this->_items = [];
            foreach ($query->all($this->db) as $row) {
                $this->_items[$row['name']] = $this->populateItem($row);
            }
            $this->_saveToCache($part, $this->_items);
        }

    }

    /**
     * Load data. If avaliable in memory, get from memory
     * If no, get from cache. If no avaliable, get from database.
     */
    private function _loadChildren()
    {

        $part = self::CACHE_PART_CHILDREN;
        if ($this->_children === null && ($this->_children = $this->_getFromCache($part)) === false) {
            $query = (new Query)->from($this->itemChildTable);

            $this->_children = [];
            foreach ($query->all($this->db) as $row) {
                if (isset($this->_items[$row['parent']], $this->_items[$row['child']])) {
                    $this->_children[$row['parent']][] = $row['child'];
                }
            }
            $this->_saveToCache($part, $this->_children);
        }

    }

    /**
     * Load data. If avaliable in memory, get from memory
     * If no, get from cache. If no avaliable, get from database.
     */
    private function _loadRules()
    {

        $part = self::CACHE_PART_RULES;
        if ($this->_rules === null && ($this->_rules = $this->_getFromCache($part)) === false) {
            $query = (new Query)->from($this->ruleTable);

            $this->_rules = [];
            foreach ($query->all($this->db) as $row) {
                $rule = @unserialize($row['data']);
                if ($rule instanceof Rule) {
                    $this->_rules[$row['name']] = $rule;
                }
            }
            $this->_saveToCache($part, $this->_rules);
        }

    }

    /**
     * Load data. If avaliable in memory, get from memory
     * If no, get from cache. If no avaliable, get from database.
     */
    private function _loadAssignments($userId)
    {
        if (!isset($this->_assignments[$userId])) {
            $query = (new Query)
                ->select('item_name')
                ->from($this->assignmentTable)
                ->where(['cms_user_id' => $userId])
            ;

            $this->_assignments[$userId] = $query->column($this->db);
        }
    }

    /**
     * @inheritdoc
     */
    public function removeChildren($parent)
    {
        $result = parent::removeChildren($parent);
        if ($this->_children !== null) {
            unset($this->_children[$parent->name]);
        }
        $this->_invalidate(self::CACHE_PART_CHILDREN);

        return $result;
    }

}