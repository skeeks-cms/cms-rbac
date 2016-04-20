<?php
return [

    'components' =>
    [
        'authManager' => [
            'class' => '\skeeks\cms\rbac\DbManager',
        ],
    ],

    'modules' =>
    [
        'rbac' => [
            'class'                         => 'skeeks\cms\rbac\RbacModule',
            'controllerNamespace'           => 'skeeks\cms\rbac\console\controllers',
        ]
    ]
];