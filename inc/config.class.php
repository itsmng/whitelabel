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
class PluginWhitelabelConfig extends CommonDBTM {
    /**
     * Displays the configuration page for the plugin
     *
     * @return void
     */
    public function showConfigForm() {
        global $CFG_GLPI;

        if (!Session::haveRight("plugin_whitelabel_whitelabel",UPDATE)) {
            return false;
        }

        $brand = new PluginWhitelabelBrand();
        $brandFiles = $brand->getTheme();
        $favicon = isset($brandFiles['favicon']) ? $brandFiles['favicon'] : '';
        $logo_login = isset($brandFiles['logo_login']) ? $brandFiles['logo_login'] : '';
        $logo_homepage = isset($brandFiles['logo_homepage']) ? $brandFiles['logo_homepage'] : '';

        echo "<div class='center mb-3'>";
        echo "<a class='btn btn-outline-secondary' href='" . Plugin::getWebDir("whitelabel") . "/front/theme.php'>";
        echo "<i class='fas fa-palette'></i>&nbsp;" . __('Manage White Label Themes', 'whitelabel');
        echo "</a>";
        echo "</div>";

        $defaultThemeId = isset($brand->fields['default_theme_id']) ? (int) $brand->fields['default_theme_id'] : 0;
        $themeValues = [0 => __('None (built-in default look)', 'whitelabel')] + PluginWhitelabelTheme::getActiveThemes();

        $defaultThemeForm = [
            'action'  => Plugin::getWebDir("whitelabel")."/front/config.form.php",
            'buttons' => [
                [
                    'name' => 'update_default_theme',
                    'type' => 'submit',
                    'value' => __('Save'),
                    'class' => 'btn btn-secondary'
                ],
            ],
            'content' => [
                __('White Label Themes', 'whitelabel') => [
                    'visible' => true,
                    'inputs' => [
                        __('Default theme', 'whitelabel') => [
                            'name'   => 'default_theme_id',
                            'type'   => 'select',
                            'values' => $themeValues,
                            'value'  => $defaultThemeId,
                            'col_lg' => 6,
                            'col_md' => 6,
                        ],
                    ],
                ],
            ],
        ];
        renderTwigForm($defaultThemeForm, '', ['noEntity' => true]);
        echo "<hr>";

        $form = [
            'action' => Plugin::getWebDir("whitelabel")."/front/config.form.php",
            'buttons' => [
                [
                    'name' => 'update',
                    'type' => 'submit',
                    'value' => __('Save'),
                    'class' => 'btn btn-secondary'
                ],
            ],
            'content' => [
                __('Files', 'whitelabel') => [
                    'visible' => true,
                    'inputs' => [
                        sprintf(__('Favicon (%s)', 'whitelabel'), Document::getMaxUploadSize()) => [
                            'id' => 'FavoriteIconFilePicker',
                            'name' => 'favicon',
                            'type' => 'imageUpload',
                            'value' => $favicon,
                            'external' => true,
                            'accept' => '.ico',
                        ],
                        sprintf(__('Logo Login Page (%s)', 'whitelabel'), Document::getMaxUploadSize()) => [
                            'id' => 'LogoLoginFilePicker',
                            'name' => 'logo_login',
                            'type' => 'imageUpload',
                            'value' => $logo_login,
                            'external' => true,
                            'accept' => '.png,.jpg,.jpeg,.svg',
                        ],
                        sprintf(__('Logo Homepage (%s)', 'whitelabel'), Document::getMaxUploadSize()) => [
                            'id' => 'LogoHomepageFilePicker',
                            'name' => 'logo_homepage',
                            'type' => 'imageUpload',
                            'value' => $logo_homepage,
                            'external' => true,
                            'accept' => '.png,.jpg,.jpeg,.svg',
                        ],
                        sprintf(__('Import your CSS configuration (%s)', 'whitelabel'), Document::getMaxUploadSize()) => [
                            'id' => 'CssFilePicker',
                            'name' => 'css_configuration',
                            'type' => 'file',
                            'value' => '',
                            'accept' => '.css'
                        ],
                    ]
                ]
            ]
        ];
        renderTwigForm($form);
    }
}
