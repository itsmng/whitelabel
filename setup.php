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

define('PLUGIN_WHITELABEL_VERSION', '3.0.5');

function plugin_init_whitelabel() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['whitelabel'] = true;
    $PLUGIN_HOOKS['change_profile']['whitelabel'] = array('PluginWhitelabelProfile', 'changeProfile');

    Plugin::registerClass('PluginWhitelabelProfile', array('addtabon' => array('Profile')));

    // Adds the "White Label Theme" tab under Profile > Preferences,
    // letting each user pick their own Theme (palette + CSS bundled
    // together - see PluginWhitelabelPreference / PluginWhitelabelTheme).
    Plugin::registerClass('PluginWhitelabelPreference', array('addtabon' => array('Preference')));

    if (Session::haveRight("profile", UPDATE)) {
        $PLUGIN_HOOKS['config_page']['whitelabel'] = 'front/config.form.php';
    }

    // Resolve, for the current request, which CSS applies:
    //   - the current user's chosen Theme, if any and active
    //   - otherwise the plugin-wide default Theme, if any and active
    //   - otherwise the legacy global whitelabel.css (pre-Themes installs)
    // This runs on every request (plugin_init executes after the session
    // is loaded), so the resolution is always up to date.
    $cssHooks = ["uploads/whitelabel.css"];
    if (class_exists('PluginWhitelabelResolver')) {
        try {
            $cssHooks = PluginWhitelabelResolver::getCssHooks();
        } catch (\Throwable $e) {
            // Never let theme resolution break page rendering (e.g. right
            // after upgrading, before the DB migration has been run):
            // silently fall back to the legacy stylesheet.
            $cssHooks = ["uploads/whitelabel.css"];
        }
    }
    $PLUGIN_HOOKS['add_css']['whitelabel'] = array_merge(
        $cssHooks,
        ["uploads/css_configuration.css"]
    );
}

function plugin_version_whitelabel() {
    return array(
        'name'           => "White Label",
        'version'        => PLUGIN_WHITELABEL_VERSION,
        'author'         => 'ITSM Dev Team, Théodore Clément, Airoine, HOP!',
        'license'        => 'GPLv3+',
        'homepage'       => 'https://github.com/itsmng/whitelabel',
        'minGlpiVersion' => '9.5'
    );
}

function plugin_whitelabel_check_prerequisites() {
    if (version_compare(ITSM_VERSION, '2.0', 'lt')) {
        echo "This plugin requires ITSM >= 2.0";
        return false;
    }
    // check rights on ./bak directory
    if (!is_writable(Plugin::getPhpDir('whitelabel') . '/bak')) {
        echo "The directory " . Plugin::getPhpDir('whitelabel') . "/bak must be writable";
        return false;
    }
    return true;
}

function plugin_whitelabel_check_config() {
    return true;
}
