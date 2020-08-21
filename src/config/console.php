<?php
return [
    'modules' => [
        'rbac' => [
            'controllerNamespace' => 'skeeks\cms\rbac\console\controllers',
        ]
    ],

    'controllerMap' => [
        'migrate' => [
            'migrationPath' => [
                '@skeeks/cms/rbac/migrations',
            ],
        ],
    ]
];