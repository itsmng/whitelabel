<?php
/**
 * ---------------------------------------------------------------------
 * ITSM-NG - White Label
 * ITSM Dev Team, Théodore Clément, Airoine, HOP!
 * ---------------------------------------------------------------------
 */
include("../../../inc/includes.php");

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}

$theme = new PluginWhitelabelTheme();

if (isset($_POST['add'])) {
    $theme->check(-1, CREATE, $_POST);
    if ($newID = $theme->add($_POST)) {
        Html::redirect(Plugin::getWebDir("whitelabel") . "/front/theme.form.php?id=" . $newID);
    }
    Html::back();
} else if (isset($_POST['update'])) {
    $theme->check($_POST['id'], UPDATE);
    $theme->update($_POST);
    Html::back();
} else if (isset($_POST['reset'])) {
    $theme->check($_POST['id'], UPDATE);
    if ($theme->getFromDB($_POST['id'])) {
        $theme->resetColorsToDefault();
        Session::addMessageAfterRedirect(__('Theme colors reset to the defaults.', 'whitelabel'));
    }
    Html::back();
} else if (isset($_POST['purge'])) {
    $theme->check($_POST['id'], PURGE);
    $theme->delete($_POST, 1);
    Html::redirect(Plugin::getWebDir("whitelabel") . "/front/theme.php");
} else {
    Html::header(
        PluginWhitelabelTheme::getTypeName(1),
        $_SERVER["PHP_SELF"],
        "config",
        "PluginWhitelabelTheme"
    );

    if (!PluginWhitelabelTheme::canView()) {
        Html::displayRightError();
    }

    $theme->display(['id' => $_GET["id"]]);

    Html::footer();
}
