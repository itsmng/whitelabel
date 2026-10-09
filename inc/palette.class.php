<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG - White Label
 * ITSM Dev Team, Théodore Clément, Airoine, HOP!
 * ---------------------------------------------------------------------
 *
 * PluginWhitelabelPalette
 *
 * Centralized registry of preset color sets that a Theme's "Load a
 * preset" action can prefill. On purpose this uses EXACTLY the same
 * field names White Label has always used (PluginWhitelabelBrand::
 * COLORS_DEFAULT / the historical global "Colors" settings), so:
 *   - the labels shown to the admin are the plugin's real, existing,
 *     already-translated labels (Primary Color, Secondary Color, ...)
 *   - the generated CSS uses the real --bs-* variables itsm2.scss has
 *     always consumed, not made-up ones.
 *
 * Adding a new preset only requires adding one entry to self::PALETTES
 * below - no other file needs to change.
 */
class PluginWhitelabelPalette {

    /**
     * The 12 color fields White Label has always exposed (see
     * PluginWhitelabelBrand::COLORS_DEFAULT), in display order. Kept
     * here as the single source of truth for both the DB columns on
     * PluginWhitelabelTheme and the CSS variables generated for each
     * theme (--bs-<name with _ replaced by ->).
     */
    const FIELDS = [
        'primary', 'secondary', 'primary_text', 'secondary_text',
        'header', 'header_text', 'nav', 'nav_text',
        'nav_submenu', 'nav_submenu_text', 'nav_hover', 'favorite',
    ];

    /**
     * Central, extensible list of built-in presets. Key = preset
     * identifier ("palette" column on a Theme, used only to remember
     * which preset a theme was last loaded from). Value = one color per
     * field in self::FIELDS.
     */
    const PALETTES = [
        'light' => [
            'label'            => 'Light',
            'primary'          => '#0b0624',
            'secondary'        => '#0e2045',
            'primary_text'     => '#ffffff',
            'secondary_text'   => '#000000',
            'header'           => '#0b0624',
            'header_text'      => '#ffffff',
            'nav'              => '#0e2045',
            'nav_text'         => '#ffffff',
            'nav_submenu'      => '#0b0624',
            'nav_submenu_text' => '#ffffff',
            'nav_hover'        => '#0e2045',
            'favorite'         => '#ffff00',
        ],
        'dark' => [
            'label'            => 'Dark',
            'primary'          => '#5865f2',
            'secondary'        => '#2b2d42',
            'primary_text'     => '#f2f2f5',
            'secondary_text'   => '#f2f2f5',
            'header'           => '#141420',
            'header_text'      => '#f2f2f5',
            'nav'              => '#1e1e2d',
            'nav_text'         => '#f2f2f5',
            'nav_submenu'      => '#141420',
            'nav_submenu_text' => '#f2f2f5',
            'nav_hover'        => '#33354a',
            'favorite'         => '#f2b84b',
        ],
        'blue' => [
            'label'            => 'Blue',
            'primary'          => '#0d6efd',
            'secondary'        => '#0a3d91',
            'primary_text'     => '#ffffff',
            'secondary_text'   => '#ffffff',
            'header'           => '#0a3d91',
            'header_text'      => '#ffffff',
            'nav'              => '#0d6efd',
            'nav_text'         => '#ffffff',
            'nav_submenu'      => '#0a3d91',
            'nav_submenu_text' => '#ffffff',
            'nav_hover'        => '#0b5ed7',
            'favorite'         => '#ffc107',
        ],
        'green' => [
            'label'            => 'Green',
            'primary'          => '#1f9d55',
            'secondary'        => '#0e4a2a',
            'primary_text'     => '#ffffff',
            'secondary_text'   => '#ffffff',
            'header'           => '#0e4a2a',
            'header_text'      => '#ffffff',
            'nav'              => '#1f9d55',
            'nav_text'         => '#ffffff',
            'nav_submenu'      => '#0e4a2a',
            'nav_submenu_text' => '#ffffff',
            'nav_hover'        => '#187f44',
            'favorite'         => '#ffc107',
        ],
        'purple' => [
            'label'            => 'Purple',
            'primary'          => '#7c3aed',
            'secondary'        => '#3b0a6e',
            'primary_text'     => '#ffffff',
            'secondary_text'   => '#ffffff',
            'header'           => '#3b0a6e',
            'header_text'      => '#ffffff',
            'nav'              => '#7c3aed',
            'nav_text'         => '#ffffff',
            'nav_submenu'      => '#3b0a6e',
            'nav_submenu_text' => '#ffffff',
            'nav_hover'        => '#6425c9',
            'favorite'         => '#ffc107',
        ],
        'orange' => [
            'label'            => 'Orange',
            'primary'          => '#f2760c',
            'secondary'        => '#7a3705',
            'primary_text'     => '#ffffff',
            'secondary_text'   => '#ffffff',
            'header'           => '#7a3705',
            'header_text'      => '#ffffff',
            'nav'              => '#f2760c',
            'nav_text'         => '#ffffff',
            'nav_submenu'      => '#7a3705',
            'nav_submenu_text' => '#ffffff',
            'nav_hover'        => '#cf6209',
            'favorite'         => '#ffc107',
        ],
    ];

    /**
     * Labels for each of the 12 color fields, using the exact same
     * (already translated - see locales/*.po) strings White Label's
     * historical global Settings page has always used.
     */
    public static function getFieldLabels() {
        return [
            'primary'          => __('Primary Color', 'whitelabel'),
            'secondary'        => __('Secondary Color', 'whitelabel'),
            'primary_text'     => __('Primary Text Color', 'whitelabel'),
            'secondary_text'   => __('Secondary Text Color', 'whitelabel'),
            'header'           => __('Header Background Color', 'whitelabel'),
            'header_text'      => __('Header Text Color', 'whitelabel'),
            'nav'              => __('Nav Background Color', 'whitelabel'),
            'nav_text'         => __('Nav Text Color', 'whitelabel'),
            'nav_submenu'      => __('Nav Submenu Color', 'whitelabel'),
            'nav_submenu_text' => __('Nav Submenu Text Color', 'whitelabel'),
            'nav_hover'        => __('Nav Hover Color', 'whitelabel'),
            'favorite'         => __('Favorite Color', 'whitelabel'),
        ];
    }

    /**
     * @return array key => label, for use in the "Load a preset" dropdown
     */
    public static function getDropdownValues() {
        $values = [];
        foreach (self::PALETTES as $key => $palette) {
            $values[$key] = $palette['label'];
        }
        return $values;
    }

    /**
     * @param string $key preset identifier
     * @return array|null the 12 field colors for that preset, or null
     */
    public static function get($key) {
        return self::PALETTES[$key] ?? null;
    }

    public static function exists($key) {
        return isset(self::PALETTES[$key]);
    }

    /**
     * Preset to fall back on if a theme references an unknown key
     * (should not normally happen, but keeps CSS generation resilient).
     */
    public static function getDefaultKey() {
        return 'light';
    }
}
