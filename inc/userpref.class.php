<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG - White Label
 * ITSM Dev Team, Théodore Clément, Airoine, HOP!
 * ---------------------------------------------------------------------
 *
 * PluginWhitelabelUserpref
 *
 * Stores the personal White Label theme preference of a user.
 * On purpose this table has a single meaningful column besides the
 * user reference: plugin_whitelabel_themes_id. It NEVER stores a
 * palette or a CSS blob - those always live on PluginWhitelabelTheme,
 * so it is structurally impossible to end up with a palette from one
 * theme and the CSS from another.
 */
class PluginWhitelabelUserpref extends CommonDBTM {

    public static $rightname = 'plugin_whitelabel_whitelabel';

    public static function getTypeName($nb = 0) {
        return __('White Label user preference', 'whitelabel');
    }

    /**
     * A user always manages their own row, regardless of the plugin's
     * profile rights matrix (this is a personal preference, like the
     * language or the list_limit fields on the User form).
     */
    public static function canPurge() {
        return true;
    }

    /**
     * Returns the row for a given user, or null if they never set a
     * preference (also returns null gracefully if the table does not
     * exist yet, e.g. right after upgrading before the migration has
     * been run).
     */
    public static function getForUser($users_id) {
        global $DB;

        if (!$DB->tableExists(self::getTable())) {
            return null;
        }

        try {
            $iterator = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => ['users_id' => (int) $users_id],
                'LIMIT' => 1,
            ]);
            foreach ($iterator as $row) {
                return $row;
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * Sets (creates or updates) the theme preference of a user. Passing
     * 0 clears the preference, falling back to the plugin's default
     * theme.
     */
    public static function setForUser($users_id, $theme_id) {
        $pref = new self();
        $existing = self::getForUser($users_id);

        $data = [
            'users_id'                     => (int) $users_id,
            'plugin_whitelabel_themes_id'   => (int) $theme_id,
        ];

        if ($existing) {
            $data['id'] = $existing['id'];
            return $pref->update($data);
        }
        return $pref->add($data);
    }
}
