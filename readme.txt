=== Color Me Beautiful ===
Contributors:      PerS
Tags:              admin, colour, color, theme, personalise, night mode, dark mode
Requires at least: 6.8
Tested up to:      6.9
Requires PHP:      8.3
Stable tag:        1.1.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Personalise the WordPress admin with your own accent colour. Per-user colour presets, custom colour picker, themed background, and night mode.

== Description ==

Color Me Beautiful lets each WordPress user independently choose an accent colour for the admin interface. Colours are applied instantly across the entire admin chrome — sidebar menu, admin bar, buttons, links, and focus rings — using CSS custom properties with no page reload required.

**Features**

* 8 built-in colour presets: Follow WordPress, Neutral Blue, Indigo, Teal, Green, Amber, Red, and Slate
* Custom colour picker — choose any colour using the WordPress Iris colour picker
* Live preview — see the colour applied across the admin in real time as you pick, before saving
* Themed background — the admin page background tints subtly to complement the chosen accent colour
* Night mode — a single checkbox applies a dark colour theme to the entire admin interface using explicit CSS colour overrides; no CSS filter is used, so stacking contexts and event handling are completely unaffected
* Per-user settings — each user's preferences are stored in user meta and affect only their own session
* Follow WordPress — delegates colour management entirely to the active WordPress admin colour scheme, emitting no override CSS
* Clean uninstall — deleting the plugin removes all stored user preferences via a paginated batch process

**Settings location**

All settings are on the standard WordPress user profile page (Users → Your Profile), in a dedicated "Color Me Beautiful" section. There is no separate admin menu entry.

== Installation ==

1. Download `cm-beautiful.zip` from the [GitHub releases page](https://github.com/soderlind/cm-beautiful/releases/latest).
2. In your WordPress admin go to **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the downloaded zip file and click **Install Now**.
4. Click **Activate Plugin**.
5. Go to **Users → Your Profile**, scroll to the **Color Me Beautiful** section, and choose your colour.

== Frequently Asked Questions ==

= Does this affect all users or just me? =

Just you. Each user's colour preference is stored separately in their own user meta. Administrators can also change the colour for other users by editing their profile.

= What happens if I choose "Follow WordPress"? =

The plugin emits no override CSS at all. Your WordPress admin colour scheme (set in Users → Your Profile → Admin Color Scheme) is used as normal.

= Does it work with all WordPress admin colour schemes? =

Yes. When a custom colour is active the plugin sets `--wp-admin-theme-color` as well as its own `--cmb-accent` custom properties, so third-party code and the block editor that reads the native WP variable will also pick up the chosen colour.

= Will my colour choice survive a plugin update? =

Yes. Preferences are stored as standard WordPress user meta (`cmb_ui_preset`, `cmb_ui_custom_accent`, `cmb_ui_night_mode`) and are unaffected by plugin updates.

= What is night mode? =

Night mode applies a dark colour palette to the WordPress admin interface using explicit `background-color`, `color`, and `border-color` overrides on the key admin elements (body, content wrappers, headings, form inputs, buttons, notices, list tables, metaboxes, cards, screen options, and footer). A set of CSS custom properties (`--cmb-nm-bg`, `--cmb-nm-surface`, `--cmb-nm-raised`, `--cmb-nm-text`, `--cmb-nm-muted`, `--cmb-nm-border`, `--cmb-nm-input`) defines the dark palette. No `filter` property is used — `filter` creates new CSS stacking contexts that re-parent `position: fixed` descendants and cause admin menu items to stop responding to clicks.

= Does the plugin add anything to the database permanently? =

Only standard WordPress user meta rows (at most three per user who saves a preference). Deleting the plugin removes all of them automatically.

= What are the server requirements? =

WordPress 6.8 or higher and PHP 8.3 or higher.

== Screenshots ==

1. The Color Me Beautiful section on the user profile page, showing the preset dropdown, custom colour picker, and night mode toggle.
2. Example of the Indigo preset applied to the WordPress admin sidebar and admin bar.
3. Night mode enabled on the admin dashboard.

== Changelog ==

= 1.1.0 =
* WCAG 2.1 contrast compliance for custom accents: contrast CSS variables (--cmb-accent-contrast, --cmb-accent-d10-contrast, etc.) auto-select black or white text based on luminance.
* Full admin bar coverage: .ab-item, .ab-icon, .ab-label, .ab-submenu, and collapse button all respect WCAG contrast.
* JavaScript live preview updates contrast colors in real time as you pick a custom color.
* Higher-specificity selectors with !important ensure contrast overrides WordPress core styles.

= 1.0.2 =
* Added WCAG-compliant contrast colors for admin sidebar and admin bar - text color automatically switches between black and white based on accent luminance.
* Added internationalization (i18n) support with translatable preset labels and WP-CLI build scripts.
* Added Pest 4 test suite with Brain Monkey for WordPress function mocking.
* Added Vitest 4 for JavaScript unit testing.
* Developer tooling: composer test and npm test scripts.

= 1.0.1 =
* Night mode rewritten: replaced `filter: invert()` approach with explicit `background-color`, `color`, and `border-color` overrides across all key admin elements (sidebar, admin bar, content area, headings, form inputs, buttons, notices, list tables, metaboxes, cards, screen options, footer). No `filter` property is used — avoids stacking-context issues that broke admin menu clicks.
* Night mode now covers the admin sidebar and admin bar with dark colours independently of the accent colour choice.
* Fixed live-preview race condition: the Iris `change` callback now guards against the `follow_wp` option to prevent incorrect colour application during preset selection.
* Fixed `CMB_Plugin::init()` to prevent duplicate hook registration if called more than once.

= 1.0.0 =
* Initial release.
* Per-user colour presets (Follow WordPress, Neutral Blue, Indigo, Teal, Green, Amber, Red, Slate).
* Custom accent colour picker via WordPress Iris.
* Live preview on the profile page.
* Themed admin background (10 % tint of the accent colour).
* Night mode via explicit dark CSS colour overrides (no filter); palette defined as CSS custom properties.
* GitHub-based automatic update checker.
* Clean paginated uninstall routine.

== Upgrade Notice ==

= 1.1.0 =
Full WCAG 2.1 contrast compliance for admin bar and sidebar. No database changes.

= 1.0.2 =
WCAG contrast compliance for custom accent colors. Developer tooling only otherwise — i18n support and test suites added. No database or UI changes.

= 1.0.1 =
Night mode CSS reworked; no database changes. Safe to update.

= 1.0.0 =
Initial release — no upgrade steps required.
