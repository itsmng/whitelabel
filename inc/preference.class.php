<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG - White Label
 * ITSM Dev Team, Théodore Clément, Airoine, HOP!
 * ---------------------------------------------------------------------
 *
 * PluginWhitelabelPreference
 *
 * Registered via Plugin::registerClass(..., ['addtabon' => ['Preference']])
 * so it shows up as a tab in Profile > Preferences. The user can only
 * pick a Theme here - never a palette or a CSS separately - because the
 * dropdown lists PluginWhitelabelTheme rows, not their internal fields.
 */
class PluginWhitelabelPreference extends CommonDBTM {

    public static function getTypeName($nb = 0) {
        return __('White Label', 'whitelabel');
    }

    /**
     * Only offered when the item being displayed is the Preference
     * container (i.e. we are really on the "my preferences" screen).
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
        if ($item instanceof Preference) {
            return self::getTypeName();
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
        if (!($item instanceof Preference)) {
            return false;
        }
        self::showPreferenceForm();
        return true;
    }

    private static function showPreferenceForm() {
        $users_id = Session::getLoginUserID();

        $activeThemes = PluginWhitelabelTheme::getActiveThemes();

        $brand = new PluginWhitelabelBrand();
        $defaultThemeId = 0;
        if ($brand->getFromDB(1)) {
            $defaultThemeId = (int) ($brand->fields['default_theme_id'] ?? 0);
        }
        $defaultThemeName = null;
        if ($defaultThemeId && isset($activeThemes[$defaultThemeId])) {
            $defaultThemeName = $activeThemes[$defaultThemeId];
        }

        $pref = PluginWhitelabelUserpref::getForUser($users_id);
        $currentThemeId = $pref ? (int) $pref['plugin_whitelabel_themes_id'] : 0;
        // If the stored preference points to a theme that is no longer
        // active/existing, treat it as "no preference" in the UI too.
        if ($currentThemeId && !isset($activeThemes[$currentThemeId])) {
            $currentThemeId = 0;
        }

        $useDefaultLabel = $defaultThemeName
            ? sprintf(__('Use the default theme (%s)', 'whitelabel'), $defaultThemeName)
            : __('Use the default theme', 'whitelabel');

        $values = [0 => $useDefaultLabel] + $activeThemes;

        $form = [
            'action'  => Plugin::getWebDir('whitelabel') . '/front/userpref.form.php',
            'buttons' => [
                [
                    'name'  => 'update',
                    'type'  => 'submit',
                    'value' => __('Save'),
                    'class' => 'btn btn-primary',
                ],
            ],
            'content' => [
                __('White Label', 'whitelabel') => [
                    'visible' => true,
                    'inputs'  => [
                        __('White Label Theme', 'whitelabel') => [
                            'name'   => 'plugin_whitelabel_themes_id',
                            'type'   => 'select',
                            'values' => $values,
                            'value'  => $currentThemeId,
                            'col_lg' => 6,
                            'col_md' => 6,
                        ],
                    ],
                ],
            ],
        ];

        renderTwigForm($form, '', ['noEntity' => true]);
    }
}
