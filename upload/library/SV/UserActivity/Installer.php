<?php

class SV_UserActivity_Installer
{
    public static function install($existingAddOn, /** @noinspection PhpUnusedLocalVariableInspection */
                                   array $addOnData, /** @noinspection PhpUnusedLocalVariableInspection */
                                   SimpleXMLElement $xml)
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $version = isset($existingAddOn['version_id']) ? $existingAddOn['version_id'] : 0;
        $db = XenForo_Application::getDb();

        $db->query(
            "
            CREATE TABLE IF NOT EXISTS xf_sv_user_activity (
              `content_type` VARBINARY(25) NOT NULL,
              `content_id` INT(10) UNSIGNED NOT NULL,
              `timestamp` INT(10) UNSIGNED NOT NULL,
              `blob` TEXT NOT NULL,
              PRIMARY KEY (`content_type`, `content_id`),
              INDEX `timestamp` (`timestamp` ASC, `content_type` ASC, `content_id` ASC)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
        "
        );

        return true;
    }

    public static function uninstall()
    {
        $db = XenForo_Application::getDb();

        $db->query(
            "
            DROP TABLE IF EXISTS xf_sv_user_activity
        "
        );

        return true;
    }
}
