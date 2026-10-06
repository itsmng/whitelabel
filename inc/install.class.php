<?php
/**
 * ---------------------------------------------------------------------
 * LICENSE
 *
 * This file is part of Whitelabel.
 *
 * Formcreator is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Whitelabel is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Whitelabel. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @link      https://github.com/pluginsGLPI/formcreator/
 * @link      https://pluginsglpi.github.io/formcreator/
 * @link      http://plugins.glpi-project.org/#/plugin/formcreator
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginWhitelabelInstall {
    function isPluginInstalled() {
        global $DB;

        $query = "SHOW TABLES LIKE 'glpi_plugin_whitelabel%'";
        $tables = $DB->request($query);
        return count($tables) > 0;
    }

    function install($migration) {
        global $DB;

        $migration = new Migration(101);
        //get default values for fields
        $default_colors = PluginWhitelabelBrand::COLORS_DEFAULT;
        $default_files = PluginWhitelabelBrand::FILES_DEFAULT;
        $table = PluginWhitelabelBrand::getTable();
        if (!$DB->tableExists($table)) {
            $query = "CREATE TABLE " . $table . " (
                id int(11) NOT NULL AUTO_INCREMENT,
                version varchar(255) NOT NULL DEFAULT '" . PLUGIN_WHITELABEL_VERSION . "',
                favicon varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '".$default_files['favicon']."',
                logo_login varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '".$default_files['logo_login']."',
                logo_homepage varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '".$default_files['logo_homepage']."',
                css_configuration varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '".$default_files['css_configuration']."',
                default_theme_id int(11) NOT NULL DEFAULT '0',";
            foreach ($default_colors as $k => $v){
                $query .= "`".$k."` varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '".$v."',";
            }
            $query .= "PRIMARY KEY (`id`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
            $DB->queryOrDie($query, $DB->error());
            $DB->queryOrDie("INSERT INTO `" . $table . "` VALUES ()", $DB->error());
        }

        $this->installThemeTables($DB);
        $this->seedDefaultThemeIfNone();

        $brand = new PluginWhitelabelBrand();
        if (count($brand->fields)) {
            $brand->regenerateLegacyCssFile();
        }

        if (!$DB->tableExists("glpi_plugin_whitelabel_profiles")) {
            $query = "CREATE TABLE `glpi_plugin_whitelabel_profiles` (
                `id` int(11) NOT NULL default '0' COMMENT 'RELATION to glpi_profiles (id)',
                `right` char(1) collate utf8_unicode_ci default NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

            $DB->queryOrDie($query, $DB->error());

            include_once(GLPI_ROOT."/plugins/whitelabel/inc/profile.class.php");
            PluginWhitelabelProfile::createAdminAccess($_SESSION['glpiactiveprofile']['id']);

            foreach (PluginWhitelabelProfile::getRightsGeneral() as $right) {
                PluginWhitelabelProfile::addDefaultProfileInfos($_SESSION['glpiactiveprofile']['id'],[$right['field'] => $right['default']]);
            }
        }

        // Update 2.0
        if($DB->tableExists("glpi_plugin_whitelabel_brand")) {

            foreach ($default_value_css::all_value() as $k=>$v){
                if(!$DB->fieldExists('glpi_plugin_whitelabel_brand',$k)){
                    $query = "ALTER TABLE glpi_plugin_whitelabel_brand ADD COLUMN ".$k." varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '".$v."'";
                    $DB->queryOrDie($query, $DB->error());
                }
            }
            if($DB->fieldExists('glpi_plugin_whitelabel_brand', 'brand_color')) {
                $query = "ALTER TABLE glpi_plugin_whitelabel_brand DROP COLUMN brand_color";
                $DB->queryOrDie($query, $DB->error());
            }
        }

        $migration->executeMigration();

        // Create backup of resources that will be altered
        if (!file_exists(Plugin::getPhpDir("whitelabel")."/bak/index.php.bak")) {
            $pluginPath = Plugin::getPhpDir("whitelabel");
            copy(GLPI_ROOT . "/pics/favicon.ico", $pluginPath . "/bak/favicon.ico.bak");
            copy(GLPI_ROOT."/index.php", Plugin::getPhpDir("whitelabel")."/bak/index.php.bak");
        }

        $loginPage = file_get_contents(GLPI_ROOT."/index.php");
        $patchMap = [
            "Html::scss('css/itsm2.scss')," =>
            "Html::scss('css/itsm2.scss'), Html::css('". Plugin::getWebDir("whitelabel", false)."/uploads/whitelabel.css'),",
            "login_logo_itsm.png" => "login_logo_whitelabel.png"
        ];
        $patchedLogin = strtr($loginPage, $patchMap);
        file_put_contents(GLPI_ROOT."/index.php", $patchedLogin);

        return true;
    }

    /**
     * Creates the two tables backing the multi-theme system if they do
     * not exist yet: the Themes catalog and the per-user preferences.
     */
    function installThemeTables($DB) {
        $themesTable = PluginWhitelabelTheme::getTable();
        if (!$DB->tableExists($themesTable)) {
            $lightPreset = PluginWhitelabelPalette::get('light');
            $query = "CREATE TABLE `" . $themesTable . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
                `palette` varchar(64) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'light',
                `custom_css` longtext COLLATE utf8_unicode_ci,
                `is_active` tinyint(1) NOT NULL DEFAULT '1',
                `is_default` tinyint(1) NOT NULL DEFAULT '0',";
            foreach (PluginWhitelabelPalette::FIELDS as $field) {
                $query .= "`" . $field . "` varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '" . $lightPreset[$field] . "',";
            }
            $query .= "
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `is_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
            $DB->queryOrDie($query, $DB->error());
        }

        $userprefTable = PluginWhitelabelUserpref::getTable();
        if (!$DB->tableExists($userprefTable)) {
            $query = "CREATE TABLE `" . $userprefTable . "` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `users_id` int(11) NOT NULL DEFAULT '0',
                `plugin_whitelabel_themes_id` int(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                UNIQUE KEY `users_id` (`users_id`),
                KEY `plugin_whitelabel_themes_id` (`plugin_whitelabel_themes_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
            $DB->queryOrDie($query, $DB->error());
        }
    }

    /**
     * On a brand-new install (or right after upgrading a pre-Themes
     * install), seed a single "Light" theme mirroring the legacy
     * COLORS_DEFAULT colors so behaviour is visibly unchanged out of
     * the box, and set it as the default theme.
     */
    function seedDefaultThemeIfNone() {
        global $DB;

        $themesTable = PluginWhitelabelTheme::getTable();
        $count = countElementsInTable($themesTable);
        if ($count > 0) {
            return;
        }

        $theme = new PluginWhitelabelTheme();
        $id = $theme->add([
            'name'       => 'Light',
            'palette'    => 'light',
            'custom_css' => '',
            'is_active'  => 1,
            'is_default' => 1,
        ]);

        if ($id) {
            PluginWhitelabelTheme::setAsDefault($id);
        }
    }

    function uninstall() {
        global $DB;

        // Drop tables
        if($DB->tableExists('glpi_plugin_whitelabel_brands')) {
            $DB->queryOrDie("DROP TABLE `glpi_plugin_whitelabel_brands`",$DB->error());
        }

        if($DB->tableExists('glpi_plugin_whitelabel_profiles')) {
            $DB->queryOrDie("DROP TABLE `glpi_plugin_whitelabel_profiles`",$DB->error());
        }

        if ($DB->tableExists(PluginWhitelabelTheme::getTable())) {
            $DB->queryOrDie("DROP TABLE `" . PluginWhitelabelTheme::getTable() . "`", $DB->error());
        }

        if ($DB->tableExists(PluginWhitelabelUserpref::getTable())) {
            $DB->queryOrDie("DROP TABLE `" . PluginWhitelabelUserpref::getTable() . "`", $DB->error());
        }

        // Clear profiles
        foreach (PluginWhitelabelProfile::getRightsGeneral() as $right) {
            $query = "DELETE FROM `glpi_profilerights` WHERE `name` = '".$right['field']."'";
            $DB->query($query);

            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }

        // Clear uploads
        $files = glob(Plugin::getPhpDir("whitelabel")."/uploads/*"); // Get all file names in `uploads`

        foreach($files as $file){ // Iterate files
            if(is_file($file)) unlink($file); // Delete file
        }

        // Clear patches
        if (is_file(Plugin::getPhpDir("whitelabel")."/bak/bak.php.bak")) {
            copy(Plugin::getPhpDir("whitelabel")."/bak/index.php.bak", GLPI_ROOT."/index.php");
            copy(Plugin::getPhpDir("whitelabel")."/bak/favicon.ico.bak", GLPI_ROOT."/pics/favicon.ico");
        }

        // Clear bakups
        $files = glob(Plugin::getPhpDir("whitelabel")."/bak/*");

        foreach($files as $file){
            if(is_file($file)) unlink($file);
        }

        return true;
    }

    function upgrade($migration) {
        global $DB;
        $brand = new PluginWhitelabelBrand();
        if (!count($brand->fields)) {
            $DB->queryOrDie("ALTER TABLE `glpi_plugin_whitelabel_brand` ADD `version` VARCHAR(255) NOT NULL DEFAULT '2.2.0' AFTER `id`");
            $table = 'glpi_plugin_whitelabel_brand';
            $newTable = PluginWhitelabelBrand::getTable();
            $migration->renameTable($table, $newTable);
            $version = '2.2.0';
            $migration->executeMigration();
        }
        $version = $brand->getVersion();
        $table = PluginWhitelabelBrand::getTable();

        // Run unconditionally on every upgrade() call, regardless of
        // which $version the switch below matches: the legacy
        // version-based switch below has dead-end branches from before
        // Themes existed (e.g. no case for every possible stored
        // version string), so relying on it alone to create the new
        // Themes tables could silently never run for some installs.
        // addThemeSupport() is itself idempotent (checks tableExists /
        // fieldExists first), so calling it every time is safe.
        $this->addThemeSupport($DB, $migration, $table);

        switch ($version) {
            case '2.2.0':
                copy(Plugin::getPhpDir("whitelabel")."/bak/index.php.bak", GLPI_ROOT."/index.php");
                $loginPage = file_get_contents(GLPI_ROOT."/index.php");
                $patchMap = [
                    "Html::scss('css/itsm2.scss')," =>
                    "Html::scss('css/itsm2.scss'), Html::css('". Plugin::getWebDir("whitelabel", false)."/uploads/whitelabel.css'),",
                    "login_logo_itsm.png" => "login_logo_whitelabel.png"
                ];
                $patchedLogin = strtr($loginPage, $patchMap);
                file_put_contents(GLPI_ROOT."/index.php", $patchedLogin);

                $colors = PluginWhitelabelBrand::COLORS_DEFAULT;
                $addedFields = [
                    'menu_text_color' => 'header_text',
                    'menu_color' => 'nav',
                    'menu_text_color' => 'nav_text',
                    'primary_color' => 'header',
                    'table_header_text_color' => 'primary',
                    'header_icons_color' => 'header_text',
                ];
                foreach ($addedFields as $old => $new) {
                    $color = $DB->request("SELECT ".$old." FROM "
                        . $table . " WHERE id = 1");
                    $migration->addField($table, $new,
                        "varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '".
                        iterator_to_array($color)[0][$old]."'");
                }
                $migration->addField($table, 'favorite',
                    "varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '"
                    . $colors['favorite']."'");
                
                if (!$DB->fieldExists($table, 'nav_submenu_text')) {
                    $migration->addField($table, 'nav_submenu_text',
                        "varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '"
                        . $colors['nav_submenu_text']."'");
                }
                
                $changedFields = [
                    'header_icons_color' => 'primary_text',
                    'menu_color' => 'secondary',
                    'menu_text_color' => 'secondary_text',
                    'menu_active_color' => 'nav_submenu',
                    'menu_onhover_color' => 'nav_hover',
                ];
                foreach ($changedFields as $old => $new) {
                    $DB->queryOrDie("ALTER TABLE `". $table
                        . "` CHANGE `" . $old . "` `" . $new
                        . "` varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '"
                        . $colors[$old] . "'");
                }
                $toDrop = [
                    'primary_color', 'dropdown_menu_background_color',
                    'dropdown_menu_text_color', 'dropdown_menu_text_hover_color',
                    'alert_background_color', 'alert_text_color', 'alert_header_background_color',
                    'alert_header_text_color', 'table_header_background_color', 'table_header_text_color',
                    'object_name_color', 'button_color', 'secondary_button_background_color',
                    'secondary_button_text_color', 'secondary_button_box_shadow_color',
                    'submit_button_background_color', 'submit_button_text_color',
                    'submit_button_box_shadow_color', 'vsubmit_button_background_color',
                    'vsubmit_button_text_color', 'vsubmit_button_box_shadow_color',
                ];
                foreach ($toDrop as $field) {
                    $migration->dropField($table, $field);
                }
                $DB->queryOrDie("ALTER TABLE `". $table
                    . "` CHANGE `logo_central` `logo_file` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT ''");
                $DB->queryOrDie("UPDATE `" . $table
                    . "` SET `version` = '3.0.1' WHERE `id` = 1");

                $migration->executeMigration();
                break;
                
            case '3.0.0':
                $colors = PluginWhitelabelBrand::COLORS_DEFAULT;
                if (!$DB->fieldExists($table, 'nav_submenu_text')) {
                    $migration->addField($table, 'nav_submenu_text',
                        "varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '"
                        . $colors['nav_submenu_text']."'");
                    $DB->queryOrDie("UPDATE `" . $table
                        . "` SET `version` = '3.0.1' WHERE `id` = 1");
                    $migration->executeMigration();
                }
                break;

            case '3.0.2':
                if (!$DB->fieldExists($table, 'logo_login')) {
                    $DB->queryOrDie("ALTER TABLE `" . $table . "` ADD COLUMN `logo_login` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `favicon`");
                }
                if (!$DB->fieldExists($table, 'logo_homepage')) {
                    $DB->queryOrDie("ALTER TABLE `" . $table . "` ADD COLUMN `logo_homepage` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '' AFTER `logo_login`");
                }

                if ($DB->fieldExists($table, 'logo_file')) {
                    $DB->queryOrDie("UPDATE `" . $table . "` SET `logo_login` = `logo_file` WHERE `logo_file` IS NOT NULL AND `logo_file` != ''");
                    $DB->queryOrDie("UPDATE `" . $table . "` SET `logo_homepage` = `logo_file` WHERE `logo_file` IS NOT NULL AND `logo_file` != ''");
                    
                    $migration->dropField($table, 'logo_file');
                }

                $DB->queryOrDie("UPDATE `" . $table . "` SET `version` = '" . PLUGIN_WHITELABEL_VERSION . "' WHERE `id` = 1");
                $migration->executeMigration();
                break;

            case '3.0.3':
                $DB->queryOrDie("UPDATE `" . $table . "` SET `version` = '" . PLUGIN_WHITELABEL_VERSION . "' WHERE `id` = 1");
                $migration->executeMigration();
                break;
        }

        // Whatever the legacy switch above matched (or didn't - some
        // very old installs can be stuck on a stored version string
        // that isn't handled by any case), always make sure the stored
        // version reflects reality once the Themes tables are in place.
        if ($DB->tableExists($table)) {
            $DB->queryOrDie("UPDATE `" . $table . "` SET `version` = '" . PLUGIN_WHITELABEL_VERSION . "' WHERE `id` = 1");
        }

        return true;
    }

    /**
     * Adds the multi-theme system to an existing pre-Themes install:
     *   - default_theme_id column on the global config table
     *   - the two new tables (themes catalog + per-user preferences)
     *   - one "Light" theme seeded from the legacy colors, set as
     *     default, so nothing visibly changes until an admin creates
     *     more themes and/or users pick one in their Preferences.
     */
    private function addThemeSupport($DB, $migration, $table) {
        if (!$DB->fieldExists($table, 'default_theme_id')) {
            $migration->addField($table, 'default_theme_id', "int(11) NOT NULL DEFAULT '0'");
        }

        $this->installThemeTables($DB);
        $this->addPerThemeColorColumns($DB, $migration);
        $this->seedDefaultThemeIfNone();

        // Colors are no longer part of this legacy file: force a
        // regeneration so stale color variables are dropped.
        $brand = new PluginWhitelabelBrand();
        if (count($brand->fields)) {
            $brand->regenerateLegacyCssFile();
        }

        // Every upgrade run regenerates every existing theme's CSS file
        // unconditionally (not just when columns were just added): the
        // CSS *generation* logic itself can change between versions
        // (e.g. a sanitization fix), and an admin should never have to
        // manually re-save every theme just to pick up such a fix.
        // regenerateCssFile() is cheap (one file write per theme) and
        // purely derived from already-stored data, so this is safe to
        // run on every upgrade.
        $this->regenerateAllThemeCssFiles($DB);
    }

    private function regenerateAllThemeCssFiles($DB) {
        $themesTable = PluginWhitelabelTheme::getTable();
        if (!$DB->tableExists($themesTable)) {
            return;
        }
        $iterator = $DB->request(['FROM' => $themesTable]);
        foreach ($iterator as $row) {
            $theme = new PluginWhitelabelTheme();
            if ($theme->getFromDB($row['id'])) {
                $theme->regenerateCssFile();
            }
        }
    }

    /**
     * Each theme manages its own 12 colors directly
     * (instead of only pointing at a shared, read-only palette preset),
     * using the SAME field names White Label has always had (Primary
     * Color, Secondary Color, ...). Adds the corresponding columns if
     * missing, and - only the very first time, right when the columns
     * are created - backfills every existing theme's colors from the
     * preset its `palette` column was already pointing to, so nothing
     * visually changes for existing themes; from that point on, each
     * theme's own columns are the source of truth and are never
     * overwritten again by this method.
     */
    private function addPerThemeColorColumns($DB, $migration) {
        $themesTable = PluginWhitelabelTheme::getTable();
        if (!$DB->tableExists($themesTable)) {
            return;
        }

        $firstField = PluginWhitelabelPalette::FIELDS[0];
        $needsBackfill = !$DB->fieldExists($themesTable, $firstField);

        $lightPreset = PluginWhitelabelPalette::get('light');
        foreach (PluginWhitelabelPalette::FIELDS as $field) {
            if (!$DB->fieldExists($themesTable, $field)) {
                $migration->addField(
                    $themesTable,
                    $field,
                    "varchar(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT '" . $lightPreset[$field] . "'"
                );
            }
        }
        $migration->executeMigration();

        if ($needsBackfill) {
            foreach (PluginWhitelabelPalette::PALETTES as $paletteKey => $preset) {
                $data = [];
                foreach (PluginWhitelabelPalette::FIELDS as $field) {
                    $data[$field] = $preset[$field];
                }
                $DB->update($themesTable, $data, ['palette' => $paletteKey]);
            }

            // Regenerate every existing theme's CSS file so it reflects
            // its newly-backfilled, individually-editable colors.
            $iterator = $DB->request(['FROM' => $themesTable]);
            foreach ($iterator as $row) {
                $theme = new PluginWhitelabelTheme();
                if ($theme->getFromDB($row['id'])) {
                    $theme->regenerateCssFile();
                }
            }
        }
    }
}
