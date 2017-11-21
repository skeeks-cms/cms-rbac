<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */
return
    [
        'users' =>
            [
                'items' =>
                    [
                        [
                            "label" => \Yii::t('skeeks/rbac', 'Roles'),
                            "url" => ["rbac/admin-role"],
                            "img" => ['skeeks\cms\rbac\assets\RbacAsset', 'icons/users-role.png'],
                            'enabled' => true,
                            'priority' => 500,
                        ],

                        [
                            "label" => \Yii::t('skeeks/rbac', 'Privileges'),
                            "url" => ["rbac/admin-permission"],
                            "img" => ['skeeks\cms\rbac\assets\RbacAsset', 'icons/access.png'],
                            'enabled' => true,
                            'priority' => 500,
                        ],
                    ]
            ]
    ];