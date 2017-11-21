<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 25.08.2015
 */
namespace skeeks\cms\rbac\widgets\adminPermissionForRoles\assets;
use skeeks\cms\base\AssetBundle;

/**
 * Class AdminPermissionForRolesWidgetAsset
 * @package skeeks\cms\rbac\widgets\adminPermissionForRoles\assets
 */
class AdminPermissionForRolesWidgetAsset extends AssetBundle
{
    public $sourcePath = '@skeeks/cms/rbac/widgets/adminPermissionForRoles/assets/src';

    public $css             = [];

    public $js              = [
        'app.js'
    ];

    public $depends         = [
        'skeeks\sx\assets\Core'
    ];
}