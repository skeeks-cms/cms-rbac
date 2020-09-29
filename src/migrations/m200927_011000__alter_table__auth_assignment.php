<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 28.08.2015
 */

use yii\db\Migration;

class m200927_011000__alter_table__auth_assignment extends Migration
{
    public function safeUp()
    {
        $tableName = "auth_assignment";

        $this->dropForeignKey("auth_assignment_user_id", $tableName);
        $this->dropForeignKey("auth_assignment__item_name", $tableName);

        $this->dropIndex("PRIMARY", $tableName);

        $this->renameColumn($tableName, 'user_id', 'cms_user_id');

        $this->addColumn($tableName, "cms_site_id", $this->integer());
        $this->addColumn($tableName, "id", $this->primaryKey(11)->notNull() . " AUTO_INCREMENT FIRST");

        $this->createIndex("uniq", $tableName, ['item_name', 'cms_user_id', 'cms_site_id'], true);

        $this->addForeignKey(
            "{$tableName}__cms_site_id", $tableName,
            'cms_site_id', '{{%cms_site}}', 'id', 'CASCADE', 'CASCADE'
        );

        $this->addForeignKey(
            "{$tableName}__cms_user_id", $tableName,
            'cms_user_id', '{{%cms_user}}', 'id', 'CASCADE', 'CASCADE'
        );

        $this->addForeignKey(
            "{$tableName}__item_name", $tableName,
            'item_name', '{{%auth_item}}', 'name', 'CASCADE', 'CASCADE'
        );
    }

    public function safeDown()
    {
        echo "m200410_121000__alter_table__cms_tree cannot be reverted.\n";
        return false;
    }
}