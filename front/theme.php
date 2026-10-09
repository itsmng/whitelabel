<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG - White Label
 * ITSM Dev Team, Théodore Clément, Airoine, HOP!
 * ---------------------------------------------------------------------
 */
include("../../../inc/includes.php");

Html::header(
    PluginWhitelabelTheme::getTypeName(2),
    $_SERVER["PHP_SELF"],
    "config",
    "PluginWhitelabelTheme"
);

if (!PluginWhitelabelTheme::canView()) {
    Html::displayRightError();
}

echo "<div class='center mb-3'>";
echo "<a class='btn btn-outline-secondary' href='" . Plugin::getWebDir("whitelabel") . "/front/config.form.php'>";
echo "<i class='fas fa-arrow-left'></i>&nbsp;" . __('Settings', 'whitelabel');
echo "</a>&nbsp;";
if (PluginWhitelabelTheme::canCreate()) {
    echo "<a class='btn btn-primary' href='" . Plugin::getWebDir("whitelabel") . "/front/theme.form.php'>";
    echo "<i class='fas fa-plus'></i>&nbsp;" . __('Add a theme', 'whitelabel');
    echo "</a>";
}
echo "</div>";

Search::show('PluginWhitelabelTheme');

Html::footer();
