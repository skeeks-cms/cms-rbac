<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 28.08.2015
 */

use yii\db\Migration;

class m200821_100601__change_charset extends Migration
{
    public function safeUp()
    {
        $tableName = "auth_assignment";
        $this->dropForeignKey("auth_assignment_ibfk_1", $tableName);
        $this->db->createCommand("ALTER TABLE `auth_assignment` CHANGE `item_name` `item_name` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;")->execute();


        $tableName = "auth_item_child";
        $this->dropForeignKey("auth_item_child_ibfk_1", $tableName);
        $this->dropForeignKey("auth_item_child_ibfk_2", $tableName);
        $this->db->createCommand("ALTER TABLE `auth_item_child` CHANGE `parent` `parent` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;")->execute();
        $this->db->createCommand("ALTER TABLE `auth_item_child` CHANGE `child` `child` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;")->execute();


        $tableName = "auth_item";
        $this->dropForeignKey("auth_item_ibfk_1", $tableName);
        $this->db->createCommand("ALTER TABLE `auth_item` CHANGE `data` `data` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci;")->execute();
        $this->db->createCommand("ALTER TABLE `auth_item` CHANGE `description` `description` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci;")->execute();
        $this->db->createCommand("ALTER TABLE `auth_item` CHANGE `rule_name` `rule_name` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci;")->execute();
        $this->db->createCommand("ALTER TABLE `auth_item` CHANGE `name` `name` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;")->execute();


        $this->db->createCommand("ALTER TABLE `auth_rule` CHANGE `name` `name` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;")->execute();
        $this->db->createCommand("ALTER TABLE `auth_rule` CHANGE `data` `data` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci;")->execute();

        $this->db->createCommand("ALTER TABLE `auth_rule` CHARACTER SET utf8, COLLATE utf8_general_ci;")->execute();
        $this->db->createCommand("ALTER TABLE `auth_item` CHARACTER SET utf8, COLLATE utf8_general_ci;")->execute();
        $this->db->createCommand("ALTER TABLE `auth_item_child` CHARACTER SET utf8, COLLATE utf8_general_ci;")->execute();
        $this->db->createCommand("ALTER TABLE `auth_assignment` CHARACTER SET utf8, COLLATE utf8_general_ci;")->execute();



        $this->addForeignKey(
            "auth_assignment__item_name", "auth_assignment",
            'item_name', '{{%auth_item}}', 'name', 'CASCADE', 'CASCADE'
        );


        $this->addForeignKey(
            "auth_item_child__parent", "auth_item_child",
            'parent', '{{%auth_item}}', 'name', 'CASCADE', 'CASCADE'
        );
        $this->addForeignKey(
            "auth_item_child__child", "auth_item_child",
            'child', '{{%auth_item}}', 'name', 'CASCADE', 'CASCADE'
        );

        $this->addForeignKey(
            "auth_item__rule_name", "auth_item",
            'rule_name', '{{%auth_rule}}', 'name', 'SET NULL', 'CASCADE'
        );
    }

    public function safeDown()
    {
        echo "m200821_100601__change_charset cannot be reverted.\n";
        return false;
    }
}