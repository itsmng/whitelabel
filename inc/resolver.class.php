<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG - White Label
 * ITSM Dev Team, Théodore Clément, Airoine, HOP!
 * ---------------------------------------------------------------------
 *
 * PluginWhitelabelResolver
 *
 * Implements the theme resolution hierarchy described in the project
 * spec:
 *
 *   user preference (if it points to an ACTIVE theme)
 *         |
 *         v  (none / inactive / deleted)
 *   White Label default theme (if ACTIVE)
 *         |
 *         v  (none configured / inactive / deleted)
 *   legacy behaviour: the original global brand colors (COLORS_DEFAULT
 *   merged with whatever is stored in glpi_plugin_whitelabel_brands),
 *   exactly as White Label behaved before Themes existed. This keeps
 *   existing installations working unchanged until an admin opts in.
 *
 * This is the ONLY place that decides "which theme applies now" so the
 * add_css hook, the Preference tab and the Settings page all agree.
 */
class PluginWhitelabelResolver {

    /**
     * True once the Themes tables have actually been created in the
     * database. This can briefly be false right after a fork/upgrade
     * whose files were dropped in place but whose migration hasn't run
     * yet (the admin still needs to click "Install/Update" on the
     * plugin screen) - in that window we must silently behave like the
     * pre-Themes plugin instead of fataling on every single page.
     */
    public static function themeTablesReady() {
        global $DB;

        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $ready = $DB->tableExists(PluginWhitelabelTheme::getTable())
                && $DB->tableExists(PluginWhitelabelUserpref::getTable())
                && $DB->fieldExists(PluginWhitelabelBrand::getTable(), 'default_theme_id');
        } catch (\Throwable $e) {
            $ready = false;
        }

        return $ready;
    }

    /**
     * Returns the active PluginWhitelabelTheme row to use for the given
     * user (or the current session user if omitted), or null if none
     * applies and the legacy global colors should be used instead.
     */
    public static function getEffectiveTheme($users_id = null) {
        if (!self::themeTablesReady()) {
            return null;
        }

        if ($users_id === null) {
            $loginId = class_exists('Session') ? Session::getLoginUserID() : false;
            $users_id = $loginId ?: 0;
        }

        try {
            // 1) explicit user preference, only if it still points to an
            //    active theme.
            if ($users_id) {
                $pref = PluginWhitelabelUserpref::getForUser($users_id);
                if ($pref && !empty($pref['plugin_whitelabel_themes_id'])) {
                    $theme = self::getActiveThemeById($pref['plugin_whitelabel_themes_id']);
                    if ($theme) {
                        return $theme;
                    }
                }
            }

            // 2) plugin-wide default theme, only if active.
            $brand = new PluginWhitelabelBrand();
            if ($brand->getFromDB(1) && !empty($brand->fields['default_theme_id'])) {
                $theme = self::getActiveThemeById($brand->fields['default_theme_id']);
                if ($theme) {
                    return $theme;
                }
            }
        } catch (\Throwable $e) {
            // Never let a resolution problem break page rendering:
            // fall back to the legacy global colors instead.
            return null;
        }

        // 3) nothing configured / everything inactive or deleted: legacy.
        return null;
    }

    private static function getActiveThemeById($theme_id) {
        $theme = new PluginWhitelabelTheme();
        if ($theme->getFromDB((int) $theme_id) && !empty($theme->fields['is_active'])) {
            return $theme;
        }
        return null;
    }

    /**
     * Returns the CSS href(s) to register via $PLUGIN_HOOKS['add_css']
     * for the current request.
     *
     * `uploads/whitelabel.css` is ALWAYS included: it only
     * carries the global, non-theme file assets (favicon, login/homepage
     * logos - e.g. the --logo-homepage variable the core stylesheet's
     * #c_logo header logo depends on), never colors, so there is no
     * conflict with a theme's own generated file, which is appended
     * after it (and wins the cascade) whenever a theme resolves.
     *
     * Both entries point at a content-hashed COPY of the real file (see
     * versionedHook() below) rather than the canonical filename itself:
     * these generated .css files are served as plain static files by
     * the webserver, under the SAME canonical filename every time
     * (uploads/theme_3.css never changes name when an admin edits
     * Theme #3). Without something that changes whenever the content
     * actually changes, browsers happily keep serving a stale cached
     * copy after a Theme is saved - which looks exactly like "the
     * plugin isn't applying my CSS" even though the server-side file
     * was regenerated correctly.
     *
     * A `?t=<mtime>` query string cannot be used instead: ITSM-NG's
     * add_css loader validates each hook entry with file_exists() on the
     * exact string, so an entry with a query string is silently dropped.
     * A real file on disk per distinct content avoids that check.
     */
    public static function getCssHooks() {
        $pluginDir = Plugin::getPhpDir('whitelabel');
        $hooks = [];

        $legacyHook = self::versionedHook('uploads/whitelabel.css', $pluginDir . '/uploads/whitelabel.css');
        $hooks[] = $legacyHook ?: 'uploads/whitelabel.css';

        try {
            $theme = self::getEffectiveTheme();
        } catch (\Throwable $e) {
            $theme = null;
        }

        if ($theme) {
            $cssPath = PluginWhitelabelTheme::getCssPath($theme->fields['id']);
            if (file_exists($cssPath)) {
                $relative = 'uploads/theme_' . $theme->fields['id'] . '.css';
                $themeHook = self::versionedHook($relative, $cssPath);
                $hooks[] = $themeHook ?: $relative;
            }
        }

        return $hooks;
    }

    /**
     * Ensures a content-hashed copy of the given canonical CSS file
     * exists on disk (e.g. uploads/theme_3-a1b2c3d4e5.css next to
     * uploads/theme_3.css) and returns its plugin-relative path, or
     * null if the canonical file doesn't exist.
     *
     * The hashed copy is only (re)written when the content actually
     * changed (its hash differs from any existing copy), and older
     * hashed copies for the same canonical file are deleted right
     * after, so saving a Theme repeatedly never accumulates files - at
     * most one hashed copy per canonical file exists at a time, besides
     * the canonical file itself (which other code, e.g.
     * PluginWhitelabelTheme::pre_purgeItem(), still manages).
     */
    private static function versionedHook($relativePath, $absolutePath) {
        if (!file_exists($absolutePath)) {
            return null;
        }

        $dir  = dirname($absolutePath);
        $base = pathinfo($absolutePath, PATHINFO_FILENAME);
        $ext  = pathinfo($absolutePath, PATHINFO_EXTENSION);

        $hash = substr(md5_file($absolutePath), 0, 10);
        $versionedName = $base . '-' . $hash . '.' . $ext;
        $versionedPath = $dir . '/' . $versionedName;

        if (!file_exists($versionedPath)) {
            if (!@copy($absolutePath, $versionedPath)) {
                // Couldn't write the hashed copy (e.g. permissions):
                // fall back to serving the canonical file directly
                // rather than losing the stylesheet entirely.
                return $relativePath;
            }
            foreach (glob($dir . '/' . $base . '-*.' . $ext) ?: [] as $old) {
                if ($old !== $versionedPath) {
                    @unlink($old);
                }
            }
        }

        $relativeDir = dirname($relativePath);
        return ($relativeDir !== '.' ? $relativeDir . '/' : '') . $versionedName;
    }
}
