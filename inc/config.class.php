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
        $colors = $brand->getTheme();
        $field_labels = [
            'primary' => __('Primary Color'),
            'secondary' => __('Secondary Color'),
            'primary_text' => __('Primary Text Color'),
            'secondary_text' => __('Secondary Text Color'),
            'header' => __('Header Background Color'),
            'header_text' => __('Header Text Color'),
            'nav' => __('Nav Background Color'),
            'nav_text' => __('Nav Text Color'),
            'nav_submenu' => __('Nav Submenu Color'),
            'nav_hover' => __('Nav Hover Color'),
            'favorite' => __('Favorite Color'),
        ];

        $form = [
            'action' => Plugin::getWebDir("whitelabel")."/front/config.form.php",
            'buttons' => [
                [
                    'name' => 'update',
                    'type' => 'submit',
                    'value' => __('Save'),
                    'class' => 'btn btn-secondary'
                ],
                [
                    'name' => 'reset',
                    'type' => 'submit',
                    'value' => __('Reset'),
                    'class' => 'btn btn-secondary'
                ]
            ],
            'content' => [
                __('Colors') => [
                    'visible' => true,
                    'inputs' => []
                ],
                __('Files') => [
                    'visible' => true,
                    'inputs' => [
                        sprintf(__('Favicon (%s)', 'whitelabel'), Document::getMaxUploadSize()) => [
                            'id' => 'FavoriteIconFilePicker',
                            'name' => 'favicon',
                            'type' => 'imageUpload',
                            'value' => $colors['favicon'],
                            'external' => true,
                            'accept' => '.ico',
                        ],
                        sprintf(__('Logo (%s)', 'whitelabel'), Document::getMaxUploadSize()) => [
                            'id' => 'LogoFilePicker',
                            'name' => 'logo_file',
                            'type' => 'imageUpload',
                            'value' => $colors['logo_file'],
                            'external' => true,
                            'accept' => '.png',
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
        foreach ($field_labels as $name => $title) {
            $form['content'][__('Colors')]['inputs'][$title] = [
                'name' => $name,
                'type' => 'color',
                'value' => $colors[$name],
                'col_lg' => 3,
                'col_md' => 4,
            ];
        }
        renderTwigForm($form);
    }
}
