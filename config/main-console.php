<?php
return [

    'components' =>
    [
        'authManager' => [
            'class' => '\skeeks\cms\rbac\DbManager',
        ],

        'i18n' => [
            'translations' =>
            [
                'skeeks/rbac' => [
                    'class'             => 'yii\i18n\PhpMessageSource',
                    'basePath'          => '@skeeks/cms/rbac/messages',
                    'fileMap' => [
                        'skeeks/rbac' => 'main.php',
                    ],
                ]
            ]
        ],
    ],

    'modules' =>
    [
        'rbac' => [
            'class'                         => 'skeeks\cms\rbac\RbacModule',
            'controllerNamespace'           => 'skeeks\cms\rbac\controllers',
        ]
    ]
];