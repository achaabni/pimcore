<?php
/**
 * Pimcore
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.pimcore.org/license
 *
 * @category   Pimcore
 * @package    Object_Class
 * @copyright  Copyright (c) 2009-2010 elements.at New Media Solutions GmbH (http://www.elements.at)
 * @license    http://www.pimcore.org/license     New BSD License
 */

class Object_Localizedfield_Resource_Mysql extends Pimcore_Model_Resource_Mysql_Abstract {

    public function getTableName () {
        return "object_localized_data_" . $this->model->getClass()->getId();
    }

    public function save () {
        $this->delete();

        foreach ($this->model->getItems() as $language => $items) {

            $insertData = array(
                "ooo_id" => $this->model->getObject()->getId(),
                "language" => $language
            );

            foreach ($this->model->getClass()->getFielddefinition("localizedfields")->getFielddefinitions() as $fd) {
                $insertData[$fd->getName()] = $fd->getDataForResource($items[$fd->getName()]);
            }
            
            $this->db->insert($this->getTableName(), $insertData);
        }
    }

    public function delete () {

        try {
            $this->db->delete($this->getTableName(), "ooo_id = '" . $this->model->getObject()->getId() . "'");
        } catch (Exception $e) {
            $this->createUpdateTable();
        }
    }

    public function load () {

        $items = array();

        $data = $this->db->fetchAll("SELECT * FROM " . $this->getTableName() . " WHERE ooo_id = '" . $this->model->getObject()->getId() . "'");
        foreach ($data as $row) {
            foreach ($this->model->getClass()->getFielddefinition("localizedfields")->getFielddefinitions() as $fd) {
                $items[$row["language"]][$fd->getName()] = $fd->getDataFromResource($row[$fd->getName()]);
            }
        }

        $this->model->setItems($items);

        return $items;
    }

    public function createLocalizedViews () {

        $languages = array();
        $conf = Zend_Registry::get("pimcore_config_system");
        if($conf->general->validLanguages) {
            $languages = explode(",",$conf->general->validLanguages);
        }

        $defaultView = 'object_' . $this->model->getClass()->getId();
        
        foreach ($languages as $language) {
            try {

                $this->dbexec('CREATE OR REPLACE VIEW `object_localized_' . $this->model->getClass()->getId() . '_' . $language . '` AS SELECT * FROM `' . $defaultView . '` left JOIN `' . $this->getTableName() . '` ON `' . $defaultView . '`.`o_id` = `' . $this->getTableName() . '`.`ooo_id` WHERE `' . $this->getTableName() . '`.`language` = \'' . $language . '\';');
            }
            catch (Exception $e) {
                Logger::error($e);
            }
        }

        $concats = array();
        foreach ($this->model->getClass()->getFielddefinition("localizedfields")->getFielddefinitions() as $fd) {
            $concats[] = "group_concat(" . $this->getTableName() . "." . $fd->getName() . ") AS `" . $fd->getName() . "`";
        }

        // and now the default view for query where the locale is missing
        $this->dbexec('CREATE OR REPLACE VIEW `object_localized_' . $this->model->getClass()->getId() . '_default` AS SELECT `' . $defaultView . '`.*, ' . implode(",",$concats) . ' FROM `' . $defaultView . '` left JOIN `' . $this->getTableName() . '` ON `' . $defaultView . '`.`o_id` = `' . $this->getTableName() . '`.`ooo_id` GROUP BY `' . $defaultView . '`.`o_id`;');
    }

    public function createUpdateTable () {

        $table = $this->getTableName();

        $this->dbexec("CREATE TABLE IF NOT EXISTS `" . $table . "` (
		  `ooo_id` int(11) NOT NULL default '0',
		  `language` varchar(5) default NULL,
          INDEX `ooo_id` (`ooo_id`),
          INDEX `language` (`language`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

        $existingColumns = $this->getValidTableColumns($table, false); // no caching of table definition
        $columnsToRemove = $existingColumns;
        $protectedColums = array("ooo_id", "language");

        foreach ($this->model->getClass()->getFielddefinition("localizedfields")->getFielddefinitions() as $value) {

            $key = $value->getName();

            // nullable & default value
            list($defaultvalue, $nullable) = $this->getDefaultValueAndNullableForField($value);

            if (is_array($value->getColumnType())) {
                // if a datafield requires more than one field
                foreach ($value->getColumnType() as $fkey => $fvalue) {
                    $this->addModifyColumn($table, $key . "__" . $fkey, $fvalue, $defaultvalue, $nullable);
                    $protectedColums[] = $key . "__" . $fkey;
                }
            }
            else {
                if ($value->getColumnType()) {
                    $this->addModifyColumn($table, $key, $value->getColumnType(), $defaultvalue, $nullable);
                    $protectedColums[] = $key;
                }
            }
            $this->addIndexToField($value,$table);
        }

        $this->removeUnusedColumns($table, $columnsToRemove, $protectedColums);

        $this->createLocalizedViews();
    }

    // @TODO the following methods dublicates Object_Class_Resource_Mysql
    private function getDefaultValueAndNullableForField ($field) {

        $nullable = "NULL";
        if ($field->getMandatory()) {
            $nullable = "NOT NULL";
        }

        $defaultvalue = "";
        if (method_exists($field, 'getDefaultValue') && $field->getDefaultValue() !== null) {
            $defaultvalue = " DEFAULT '" . $field->getDefaultValue() . "'";
        } else if (method_exists($field, 'getDefaultValue') && $field->getDefaultValue() === null and $nullable == "NULL"){
            $defaultvalue = " DEFAULT NULL";
        }

        return array($defaultvalue, $nullable);
    }

    private function addIndexToField ($field, $table) {

        if ($field->getIndex()) {
            if (is_array($field->getColumnType())) {
                // multicolumn field
                foreach ($field->getColumnType() as $fkey => $fvalue) {
                    $columnName = $field->getName() . "__" . $fkey;
                    try {
                        $this->dbexec("ALTER TABLE `" . $table . "` ADD INDEX `p_index_" . $columnName . "` (`" . $columnName . "`);");
                    } catch (Exception $e) {}
                }
            } else {
                // single -column field
                $columnName = $field->getName();
                try {
                    $this->dbexec("ALTER TABLE `" . $table . "` ADD INDEX `p_index_" . $columnName . "` (`" . $columnName . "`);");
                } catch (Exception $e) {}
            }
        } else {
            if (is_array($field->getColumnType())) {
                // multicolumn field
                foreach ($field->getColumnType() as $fkey => $fvalue) {
                    $columnName = $field->getName() . "__" . $fkey;
                    try {
                        $this->dbexec("ALTER TABLE `" . $table . "` DROP INDEX `p_index_" . $columnName . "`;");
                    } catch (Exception $e) {}
                }
            } else {
                // single -column field
                $columnName = $field->getName();
                try {
                    $this->dbexec("ALTER TABLE `" . $table . "` DROP INDEX `p_index_" . $columnName . "`;");
                } catch (Exception $e) {}
            }
        }
    }

    private function addModifyColumn ($table, $colName, $type, $default, $null) {

        $existingColumns = $this->getValidTableColumns($table, false);
        $existingColName = null;

        // check for existing column case insensitive eg a rename from myInput to myinput
        $matchingExisting = preg_grep('/^' . preg_quote($colName, '/') . '$/i', $existingColumns);
        if(is_array($matchingExisting) && !empty($matchingExisting)) {
            $existingColName = current($matchingExisting);
        }

        if ($existingColName === null) {
            $this->dbexec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $colName . '` ' . $type . $default . ' ' . $null . ';');
        } else {
            $this->dbexec('ALTER TABLE `' . $table . '` CHANGE COLUMN `' . $existingColName . '` `' . $colName . '` ' . $type . $default . ' ' . $null . ';');
        }
    }

    private function removeUnusedColumns ($table, $columnsToRemove, $protectedColumns) {
        if (is_array($columnsToRemove) && count($columnsToRemove) > 0) {
            foreach ($columnsToRemove as $value) {
                //if (!in_array($value, $protectedColumns)) {
                if (!in_array(strtolower($value), array_map('strtolower', $protectedColumns))) {
                    $this->dbexec('ALTER TABLE `' . $table . '` DROP COLUMN `' . $value . '`;');
                }
            }
        }
    }

    private function dbexec($sql) {
        $this->db->exec($sql);
        $this->logSql($sql);
    }

    private function logSql ($sql) {
        $this->_sqlChangeLog[] = $sql;
    }

    public function __destruct () {

        // write sql change log for deploying to production system
        if(!empty($this->_sqlChangeLog)) {
            $log = implode("\n\n\n", $this->_sqlChangeLog);

            $filename = "db-change-log_".time()."_class-".$this->model->getClass()->getId().".sql";
            $file = PIMCORE_SYSTEM_TEMP_DIRECTORY."/".$filename;
            if(defined("PIMCORE_DB_CHANGELOG_DIRECTORY")) {
                $file = PIMCORE_DB_CHANGELOG_DIRECTORY."/".$filename;
            }

            file_put_contents($file, $log);
        }
    }
}
