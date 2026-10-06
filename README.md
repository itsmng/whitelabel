# ITSM Whitelabel

**Version 3.1.0 — ITSM Dev Team, Théodore Clément, Airoine, HOP!**

## Changelog

- **3.1.0** — White Label Themes. A theme bundles the 12 colors and a
  custom CSS as one unit; a Super-Admin creates, edits, activates and
  deletes themes and picks a default one; each user picks a theme in
  Profile → Preferences → White Label. Resolution order: user
  preference → default theme → legacy global colors. Colors are
  configured only through themes (the old global color section is
  gone). Custom CSS is decoded, sanitized, size-capped and always served
  as a standalone `.css` file; generated files are content-hashed so
  browsers never serve a stale copy. Existing installs get a default
  "Light" theme seeded from their current colors, so nothing changes
  visually on upgrade. A "Reset" button restores a theme's colors to the
  defaults. Added `tests/css_roundtrip_test.php`.

## Purpose

This plugins aims to allow its users to modify the look of their [itsm](https://github.com/itsmng/itsm-ng) deployment, unifying their internal software's appearance.

## White Label Themes

A **Theme** bundles White Label's 12 colors (the same fields the plugin
has always had: Primary Color, Secondary Color, Primary Text Color,
Secondary Text Color, Header Background/Text Color, Nav Background/Text
Color, Nav Submenu/Submenu Text Color, Nav Hover Color, Favorite Color)
and a **Custom CSS**, together as one indissociable unit. **This is the
ONLY place colors are configured** - there is no general/global color
setting anywhere else. Each theme's colors are fully independent and
editable (color pickers, labeled and translated exactly as before),
with the "Load a preset" dropdown only there to quickly prefill them
from a built-in preset:

```
Theme
├── 12 colors  (own values; a preset can prefill them, then freely edited)
└── Custom CSS
```

You can never pick colors and a CSS separately - selecting a Theme
always applies both together.

* **Administration → Plugins → White Label → Manage White Label Themes**
  lets a Super-Admin create, edit, activate/deactivate and delete
  Themes, and choose one as the **Default theme** for every user who
  hasn't picked a personal one.
* **Profile → Preferences → White Label** lets each user pick their own
  Theme from the list of active Themes (or "use the default theme").

### Resolution order

```
user's personal preference (if it points to an ACTIVE theme)
        |
        v
White Label's default theme (if ACTIVE)
        |
        v
legacy global colors (pre-0.0.1 behaviour, unchanged)
```

Deleting a Theme currently in use, or deactivating it, never leaves a
user "stuck": they transparently fall back to the default theme.

### CSS variables

Each Theme generates the same `--bs-*` custom properties White Label
has always produced, so `itsm2.scss` and any Custom CSS can rely on
them exactly as before:

```
--bs-primary
--bs-secondary
--bs-primary-text
--bs-secondary-text
--bs-header
--bs-header-text
--bs-nav
--bs-nav-text
--bs-nav-submenu
--bs-nav-submenu-text
--bs-nav-hover
--bs-favorite
```

Adding a brand-new preset only requires adding one entry to
`PluginWhitelabelPalette::PALETTES` (`inc/palette.class.php`) - no other
file needs to change.

### Security

Custom CSS is authored by a Super-Admin only, sanitized on save (no
`<script>`/`<style>` tags, no `javascript:`/`expression()`, size-capped),
and always served back as a standalone `.css` file - never inlined into
an HTML page - so it cannot be used to inject HTML or JavaScript.

## Installation

Installing this plugin is done following the standard process for itsm plugins, simply clone the git or download a release and place it within itsm's `plugins` folder.

Don't forget to set Apache rights, and enjoy !

## Features

 * Set UI's color : 
   * Header colors :
      * Primary color
      * Header icons color
      * Menu colors
         * Menu color
         * Menu text color
         * Active menu color
         * On hover menu color
         * Dropdown menu background color
         * Dropdown menu text color
         * Dropdown menu text hover color

   * Alert colors :
      * Alert background color
      * Alert text color
      * Alert header background color
      * Alert header text color

   * Table colors :
      * Table header background color
      * Table header text color

   * Object colors :
      * Object name color

   * Button colors :
      * Button color
      * Secondary button background color
      * Secondary button text color
      * Secondary button box-shadow color
      * Submit button background color
      * Submit button text color
      * Submit button box-shadow color
      * Vsubmit button background color
      * Vsubmit button text color
      * Vsubmit button box-shadow color
   * Change logo in header and login page
   * Change favicon
