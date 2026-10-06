<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG - White Label
 * ITSM Dev Team, Théodore Clément, Airoine, HOP!
 * ---------------------------------------------------------------------
 *
 * PluginWhitelabelTheme
 *
 * A Theme is THE unit of visual personalization:
 *   Theme = White Label's 12 colors (own values) + Custom CSS
 *
 * These are the SAME 12 fields White Label has always had (Primary
 * Color, Secondary Color, Header Background Color, ...) - just moved
 * from a single global row to one row per theme. A user (or the
 * plugin's global default) never selects colors and a CSS file
 * separately: they select a Theme, and this class is the single place
 * responsible for turning "Theme #3" into "these 12 colors + this CSS".
 */
class PluginWhitelabelTheme extends CommonDBTM {

    public static $rightname = 'plugin_whitelabel_whitelabel';

    /** Maximum accepted length for the custom_css field, to avoid abuse. */
    const CUSTOM_CSS_MAX_LENGTH = 100000;

    static function canDelete() {
        return self::canPurge();
    }

    public static function getTypeName($nb = 0) {
        return _n('White Label Theme', 'White Label Themes', $nb, 'whitelabel');
    }

    public static function getIcon() {
        return 'fas fa-palette';
    }

    public static function isValidHexColor($value) {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value);
    }

    /**
     * This theme's own 12 colors, using White Label's real field names
     * (see PluginWhitelabelPalette::FIELDS), reading from this row's
     * own columns and falling back to the referenced preset (then the
     * built-in default preset) only for anything genuinely missing or
     * invalid - normal operation always uses this theme's own stored
     * colors.
     */
    public function getColors() {
        $fallback = PluginWhitelabelPalette::get($this->fields['palette'] ?? '')
            ?? PluginWhitelabelPalette::get(PluginWhitelabelPalette::getDefaultKey());

        $colors = [];
        foreach (PluginWhitelabelPalette::FIELDS as $field) {
            $value = $this->fields[$field] ?? '';
            $colors[$field] = self::isValidHexColor($value) ? $value : $fallback[$field];
        }
        return $colors;
    }

    /**
     * ------------------------------------------------------------------
     * CSS sanitization
     * ------------------------------------------------------------------
     * The custom CSS is authored by a Super-Admin, but is still rendered
     * to every user's browser, so it must never be able to smuggle HTML
     * or JavaScript. We always serve it back through a dedicated .css
     * file (Content-Type: text/css), which already prevents it from
     * being interpreted as HTML/JS by the browser. On top of that we
     * strip actual <script>/<style> tag patterns, javascript: URLs and
     * expression() calls - but NOT bare '<'/'>' characters, since those
     * are legitimate, common CSS syntax (child combinators such as
     * `.a > .b`, attribute selectors, etc.); stripping them silently
     * corrupts real, harmless CSS instead of blocking anything unsafe.
     *
     * IMPORTANT: ITSM-NG (like GLPI, which it forks) auto-sanitizes every
     * submitted form field - HTML-encoding '<', '>', '&', quotes, etc. -
     * as a generic, global XSS defense applied before plugin code ever
     * sees $_POST. That is correct and desired for fields that get
     * echoed back into HTML pages, but custom_css is instead written
     * VERBATIM into a standalone .css file, never interpreted as HTML -
     * so left HTML-encoded, every '>' typed by the admin (e.g. in a
     * child-combinator selector like `tbody.table-light > tr`) becomes
     * the literal four characters "&gt;" in the generated .css file.
     * That is not valid CSS syntax, so browsers silently DROP the whole
     * selector (and therefore the whole rule) - the admin's CSS loads
     * without any error, but entire rules using '>', '<' or '&' quietly
     * do nothing. We undo that encoding here, before any of the pattern
     * checks below, so what lands in the .css file is the CSS the admin
     * actually typed.
     */
    /**
     * Undoes what the core did to a submitted form value, in reverse
     * order. The core applies TWO layers to every $_POST string:
     *   1. HTML-encoding ('>' -> '&gt;' / '&#62;')
     *   2. SQL escaping, mysqli_real_escape_string style (newline ->
     *      backslash + 'n', backslash -> two backslashes, quote ->
     *      backslash + quote, NUL -> backslash + '0')
     * and the DB layer expects values to stay in that escaped form
     * (it does NOT escape again when inserting).
     *
     * stripslashes() is NOT the inverse of SQL escaping (line breaks
     * would become the literal letters "rn" and CSS escapes such as
     * "\00d7" would lose their backslash), so every escape sequence is
     * decoded explicitly here.
     *
     * Only for request input (prepareInputForAdd/Update). Values read
     * back from the database are already unescaped by MySQL.
     */
    public static function decodeSubmittedCss($raw) {
        $css = (string) $raw;

        // A raw newline can only be present if no SQL escaping happened
        // (escaping turns every CR/LF into a 2-character sequence), so
        // in that case backslashes are real CSS and must be left alone.
        if (strpos($css, "\n") === false && strpos($css, "\r") === false) {
            $css = preg_replace_callback('/\\\\(.)/s', function ($m) {
                switch ($m[1]) {
                    case 'n': return "\n";
                    case 'r': return "\r";
                    case '0': return "\0";
                    case 'Z': return "\x1a";
                    default:  return $m[1]; // \\  \'  \"
                }
            }, $css);
        }

        return html_entity_decode($css, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Clean text in, clean text out (no SQL/HTML decoding of slashes).
     * Entities are still decoded, so rows stored with '&gt;' instead of
     * '>' are repaired when their CSS file is regenerated.
     */
    public static function sanitizeCustomCss($css) {
        $css = html_entity_decode((string) $css, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Hard length cap (applied AFTER decoding: a decoded '>' is one
        // character, not the four of '&gt;', so capping first could
        // truncate mid-entity and still miscount the real length).
        if (strlen($css) > self::CUSTOM_CSS_MAX_LENGTH) {
            $css = substr($css, 0, self::CUSTOM_CSS_MAX_LENGTH);
        }

        // Strip null bytes and actual tag/script constructs - never bare
        // '<'/'>' characters, which are valid CSS syntax.
        $css = str_replace("\0", '', $css);
        $css = preg_replace('/<\s*\/?\s*(script|style)\b[^>]*>/i', '', $css);
        $css = preg_replace('/javascript\s*:/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/@import\b/i', '', $css);
        $css = preg_replace('/-moz-binding\s*:/i', '', $css);
        $css = preg_replace('/(?<![-\w])behavior\s*:/i', '', $css);

        return trim($css);
    }

    function prepareInputForAdd($input) {
        return $this->preparePaletteCssAndColors($input, true);
    }

    function prepareInputForUpdate($input) {
        return $this->preparePaletteCssAndColors($input, false);
    }

    private function preparePaletteCssAndColors($input, $isAdd) {
        if (isset($input['palette']) && !PluginWhitelabelPalette::exists($input['palette'])) {
            $input['palette'] = PluginWhitelabelPalette::getDefaultKey();
        }
        if (isset($input['custom_css'])) {
            global $DB;
            // decode core encoding -> sanitize clean text -> re-escape
            // for the DB layer, which stores values as given.
            $clean = self::sanitizeCustomCss(self::decodeSubmittedCss($input['custom_css']));
            $input['custom_css'] = $DB->escape($clean);
        }

        // Each of the 12 colors is validated independently: an
        // invalid/missing value falls back to the selected preset's
        // color rather than silently saving a broken color, so the
        // theme is never left in a half-configured state.
        $preset = PluginWhitelabelPalette::get($input['palette'] ?? '')
            ?? PluginWhitelabelPalette::get(PluginWhitelabelPalette::getDefaultKey());

        foreach (PluginWhitelabelPalette::FIELDS as $field) {
            if (!$isAdd && !array_key_exists($field, $input)) {
                // Partial update (e.g. from setAsDefault()): leave
                // untouched columns alone.
                continue;
            }
            $value = $input[$field] ?? '';
            $input[$field] = self::isValidHexColor($value) ? $value : $preset[$field];
        }

        return $input;
    }

    function post_addItem() {
        $this->regenerateCssFile();
        $this->handleDefaultFlag();
    }

    function post_updateItem($history = 1) {
        $this->regenerateCssFile();
        $this->handleDefaultFlag();
    }

    /**
     * If this theme was just marked as default, make sure it is the only
     * one (mirrors the "Default theme" setting stored on the config).
     */
    private function handleDefaultFlag() {
        if (!empty($this->input['is_default'])) {
            self::setAsDefault($this->fields['id']);
        }
    }

    /**
     * Sets the given theme as the plugin-wide default theme. Also clears
     * the is_default flag on every other theme so there is always at
     * most one default.
     */
    public static function setAsDefault($theme_id) {
        global $DB;

        $table = self::getTable();
        $DB->update($table, ['is_default' => 0], ['id' => ['<>', (int) $theme_id]]);
        $DB->update($table, ['is_default' => 1], ['id' => (int) $theme_id]);

        $brand = new PluginWhitelabelBrand();
        $brand->update(['id' => 1, 'default_theme_id' => (int) $theme_id]);
    }

    /**
     * Resets this theme's colors (and only its colors - name, custom
     * CSS, active/default flags are left untouched) back to the base
     * default palette, exactly like White Label's original global
     * "Reset" button used to reset all colors to COLORS_DEFAULT.
     */
    public function resetColorsToDefault() {
        $defaults = PluginWhitelabelPalette::get(PluginWhitelabelPalette::getDefaultKey());
        $data = ['id' => $this->fields['id'], 'palette' => PluginWhitelabelPalette::getDefaultKey()];
        foreach (PluginWhitelabelPalette::FIELDS as $field) {
            $data[$field] = $defaults[$field];
        }
        return $this->update($data);
    }

    /**
     * Before a theme is purged, make sure no one is left pointing to it:
     * clear it from the global default and from every user preference
     * that references it, so they safely fall back to the default theme.
     */
    function pre_purgeItem() {
        global $DB;

        $brand = new PluginWhitelabelBrand();
        if ($brand->getFromDB(1) && (int) ($brand->fields['default_theme_id'] ?? 0) === (int) $this->fields['id']) {
            $brand->update(['id' => 1, 'default_theme_id' => 0]);
        }

        $DB->update(
            PluginWhitelabelUserpref::getTable(),
            ['plugin_whitelabel_themes_id' => 0],
            ['plugin_whitelabel_themes_id' => $this->fields['id']]
        );

        $cssFile = self::getCssPath($this->fields['id']);
        if (file_exists($cssFile)) {
            unlink($cssFile);
        }

        // Also remove any content-hashed copies of this theme's CSS
        // (uploads/theme_<id>-<hash>.css) created by
        // PluginWhitelabelResolver's cache-busting, so a deleted theme
        // doesn't leave orphan files behind.
        foreach (glob(dirname($cssFile) . '/theme_' . (int) $this->fields['id'] . '-*.css') ?: [] as $hashed) {
            @unlink($hashed);
        }

        return true;
    }

    /**
     * Path of the generated static CSS file for a given theme id.
     */
    public static function getCssPath($theme_id) {
        return Plugin::getPhpDir('whitelabel') . '/uploads/theme_' . (int) $theme_id . '.css';
    }

    /**
     * Regenerates the standalone .css file for this theme:
     *   :root { --bs-primary: ...; --bs-secondary: ...; ... }
     *   <sanitized custom CSS>
     *
     * Uses the exact same --bs-* variable names White Label has always
     * generated (see the historical generateMainTemplate()), so the
     * core itsm2.scss stylesheet is themed exactly as before - just
     * from a per-theme file instead of a single global one.
     *
     * The colors and the custom CSS are always written together, from
     * the same Theme row, so it is structurally impossible to end up
     * with "colors of theme A" + "CSS of theme B".
     */
    public function regenerateCssFile() {
        // Always work from what is really stored (post_addItem /
        // post_updateItem may still hold the escaped request input).
        if (!empty($this->fields['id'])) {
            $this->getFromDB($this->fields['id']);
        }
        $colors = $this->getColors();

        $content = "@charset \"UTF-8\";\n";
        $content .= ":root {\n";
        foreach ($colors as $field => $value) {
            $cssVar = str_replace('_', '-', $field);
            $content .= "  --bs-{$cssVar}: {$value};\n";
        }
        $content .= "}\n\n";

        // Generic rule consuming --bs-nav-submenu-text above; kept here
        // (as White Label has always shipped it) so every generated
        // theme file is fully self-contained.
        $content .= "nav#menu .menu-content ul.sub-menu li a,\n";
        $content .= "nav#menu .menu-content ul.sub-menu li a i,\n";
        $content .= "nav#menu .menu-content ul.sub-menu li a span,\n";
        $content .= "nav#menu .menu-content ul.sub-menu li:hover a,\n";
        $content .= "nav#menu .menu-content ul.sub-menu li.active a,\n";
        $content .= ".menu-top nav#menu .menu-content ul.sub-menu li a,\n";
        $content .= ".menu-close nav#menu .menu-content ul.sub-menu li a i {\n";
        $content .= "    color: var(--bs-nav-submenu-text) !important;\n";
        $content .= "}\n\n";

        $content .= "/* Custom CSS for theme \"" . preg_replace('/[^\p{L}\p{N} _.\-]/u', '', (string) $this->fields['name']) . "\" */\n";
        $content .= self::sanitizeCustomCss($this->fields['custom_css'] ?? '') . "\n";

        file_put_contents(self::getCssPath($this->fields['id']), $content);
    }

    /**
     * Only active themes are selectable in Profile > Preferences and in
     * the "Default theme" dropdown. Returns an empty list gracefully if
     * the table does not exist yet (e.g. right after upgrading, before
     * the migration has been run).
     */
    public static function getActiveThemes() {
        global $DB;

        $values = [];
        if (!$DB->tableExists(self::getTable())) {
            return $values;
        }

        try {
            $iterator = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => ['is_active' => 1],
                'ORDER' => 'name ASC',
            ]);
            foreach ($iterator as $row) {
                $values[$row['id']] = $row['name'];
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $values;
    }

    public function rawSearchOptions() {
        $tab = [];

        $tab[] = [
            'id'       => 1,
            'table'    => $this->getTable(),
            'field'    => 'name',
            'name'     => __('Name'),
            'datatype' => 'itemlink',
            'itemlink_type' => $this->getType(),
        ];

        $tab[] = [
            'id'       => 2,
            'table'    => $this->getTable(),
            'field'    => 'is_active',
            'name'     => __('Active'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => 3,
            'table'    => $this->getTable(),
            'field'    => 'is_default',
            'name'     => __('Default theme', 'whitelabel'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => $this->getTable(),
            'field'    => 'primary',
            'name'     => __('Primary Color', 'whitelabel'),
            'datatype' => 'specific',
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = []) {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if ($field === 'primary' && self::isValidHexColor($values['primary'] ?? '')) {
            $color = $values['primary'];
            return "<span style='display:inline-block;width:1em;height:1em;border:1px solid #999;background:{$color};vertical-align:middle;margin-right:.4em;'></span>{$color}";
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Renders the create/edit form for a single theme, using the same
     * Twig-based form renderer already used by the plugin's Settings
     * page (renderTwigForm), so the UI stays consistent with the rest
     * of White Label, and using White Label's real, already-translated
     * color field labels.
     */
    public function showForm($ID, $options = []) {
        if (!self::canView()) {
            return false;
        }

        $ID = (int) $ID;
        $isNew = ($ID <= 0);
        if (!$isNew) {
            $this->check($ID, READ);
        } else {
            $this->check(-1, CREATE);
            $preset = PluginWhitelabelPalette::get(PluginWhitelabelPalette::getDefaultKey());
            $this->fields = [
                'id'          => 0,
                'name'        => '',
                'palette'     => PluginWhitelabelPalette::getDefaultKey(),
                'custom_css'  => '',
                'is_active'   => 1,
                'is_default'  => 0,
            ] + $preset;
        }

        $buttons = [
            [
                'name'  => $isNew ? 'add' : 'update',
                'type'  => 'submit',
                'value' => $isNew ? __('Add') : __('Save'),
                'class' => 'btn btn-primary',
            ],
        ];
        if (!$isNew && self::canUpdate()) {
            $buttons[] = [
                'name'  => 'reset',
                'type'  => 'submit',
                'value' => __('Reset', 'whitelabel'),
                'class' => 'btn btn-secondary',
                'onclick' => "return confirm('" . __('Reset this theme\'s colors to the defaults?', 'whitelabel') . "');",
            ];
        }
        if (!$isNew && self::canPurge()) {
            $buttons[] = [
                'name'  => 'purge',
                'type'  => 'submit',
                'value' => __('Delete permanently'),
                'class' => 'btn btn-danger',
                'onclick' => "return confirm('" . __('Delete this theme? Users on it will fall back to the default theme.', 'whitelabel') . "');",
            ];
        }

        $colorInputs = [];
        foreach (PluginWhitelabelPalette::getFieldLabels() as $field => $label) {
            $colorInputs[$label] = [
                'id'     => 'wl_color_' . $field,
                'name'   => $field,
                'type'   => 'color',
                'value'  => $this->fields[$field] ?? '',
                'col_lg' => 6,
                'col_md' => 6,
            ];
        }

        $form = [
            'action'  => Plugin::getWebDir('whitelabel') . '/front/theme.form.php',
            'buttons' => $buttons,
            'content' => [
                __('White Label Theme', 'whitelabel') => [
                    'visible' => true,
                    'inputs'  => [
                        __('Name') => [
                            'name'  => 'name',
                            'type'  => 'text',
                            'value' => $this->fields['name'],
                            'col_lg' => 6,
                            'col_md' => 6,
                        ],
                        __('Load a preset', 'whitelabel') => [
                            'id'     => 'wl_palette_preset',
                            'name'   => 'palette',
                            'type'   => 'select',
                            'values' => PluginWhitelabelPalette::getDropdownValues(),
                            'value'  => $this->fields['palette'],
                            'col_lg' => 6,
                            'col_md' => 6,
                        ],
                        __('Active', 'whitelabel') => [
                            'name'  => 'is_active',
                            'type'  => 'checkbox',
                            'value' => $this->fields['is_active'],
                            'col_lg' => 3,
                            'col_md' => 3,
                        ],
                        __('Set as default theme', 'whitelabel') => [
                            'name'  => 'is_default',
                            'type'  => 'checkbox',
                            'value' => $this->fields['is_default'],
                            'col_lg' => 3,
                            'col_md' => 3,
                        ],
                    ],
                ],
                __('Colors', 'whitelabel') => [
                    'visible' => true,
                    'inputs'  => $colorInputs,
                ],
                __('Custom CSS', 'whitelabel') => [
                    'visible' => true,
                    'inputs'  => [
                        sprintf(__('Custom CSS for this theme (%s)', 'whitelabel'), 'CSS') => [
                            'name'  => 'custom_css',
                            'type'  => 'textarea',
                            'value' => $this->fields['custom_css'],
                            'rows'  => 14,
                            'col_lg' => 12,
                            'col_md' => 12,
                        ],
                    ],
                ],
            ],
        ];

        renderTwigForm($form, '', ['id' => $ID, 'noEntity' => true]);

        // "Load a preset" only updates the color pickers client-side; it
        // never saves anything by itself, and never overwrites colors
        // the admin already tweaked without an explicit click.
        $presetsJson = json_encode(PluginWhitelabelPalette::PALETTES);
        $fieldsJson = json_encode(PluginWhitelabelPalette::FIELDS);
        echo <<<HTML
        <script>
        (function() {
            var presets = {$presetsJson};
            var fields = {$fieldsJson};
            var select = document.getElementById('wl_palette_preset');
            if (!select) { return; }
            select.addEventListener('change', function() {
                var preset = presets[select.value];
                if (!preset) { return; }
                fields.forEach(function(field) {
                    var input = document.getElementById('wl_color_' + field);
                    if (input && preset[field]) {
                        input.value = preset[field];
                        input.dispatchEvent(new Event('change'));
                    }
                });
            });
        })();
        </script>
        HTML;

        return true;
    }
}
