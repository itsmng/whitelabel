<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG - White Label
 * ITSM Dev Team, Théodore Clément, Airoine, HOP!
 * ---------------------------------------------------------------------
 */
include("../../../inc/includes.php");

Session::checkLoginUser();

if (isset($_POST['update'])) {
    $theme_id = (int) ($_POST['plugin_whitelabel_themes_id'] ?? 0);

    // A user may only ever save their own preference - never someone
    // else's - and only towards a theme that actually exists (0 = "use
    // the default theme").
    if ($theme_id !== 0) {
        $theme = new PluginWhitelabelTheme();
        if (!$theme->getFromDB($theme_id) || empty($theme->fields['is_active'])) {
            $theme_id = 0;
        }
    }

    PluginWhitelabelUserpref::setForUser(Session::getLoginUserID(), $theme_id);
    Session::addMessageAfterRedirect(__('White Label theme preference saved.', 'whitelabel'));
}

Html::back();
