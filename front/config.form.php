<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG
 * Copyright (C) 2022 ITSM-NG and contributors.
 *
 * https://www.itsm-ng.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of ITSM-NG.
 *
 * ITSM-NG is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * ITSM-NG is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ITSM-NG. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */
include("../../../inc/includes.php");
require_once("../inc/config.class.php");

$plugin = new Plugin();

if($plugin->isActivated("whitelabel")) {
    $brand = new PluginWhitelabelBrand();
    $config = new PluginWhitelabelConfig();
    if(isset($_POST["update"])) {
        Session::checkRight("config", UPDATE);
        $_POST['id'] = 1;
        $brand->update($_POST);
        Session::addMessageAfterRedirect(__('<p><b>Settings applied !</b></p><p><i>If you have any error, do the command in the ITSM-NG installation folder : <b>bin/console system:clear_cache</b></i></p>', 'whitelabel'));
    }

    if(isset($_POST["update_default_theme"])) {
        Session::checkRight("config", UPDATE);
        $theme_id = (int) ($_POST['default_theme_id'] ?? 0);
        if ($theme_id > 0) {
            PluginWhitelabelTheme::setAsDefault($theme_id);
        } else {
            $brand->update(['id' => 1, 'default_theme_id' => 0]);
        }
        Session::addMessageAfterRedirect(__('Default theme updated.', 'whitelabel'));
    }

    Html::header("WhiteLabel", $_SERVER["PHP_SELF"], "config", Plugin::class);
    $config->showConfigForm();
} else {
    Html::header("settings", '', "config", "plugins");
    echo "<div class='center'><br><br><img src=\"".$CFG_GLPI["root_doc"]."/pics/warning.png\" alt='warning'><br><br>";
    echo "<b>Please enable the plugin before configuring it</b></div>";
    Html::footer();
}

Html::footer();
