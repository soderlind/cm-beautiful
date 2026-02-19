# Color Me Beautiful

Personalise the WordPress admin with your own accent colour. Each user independently picks a colour preset or adds a custom colour via the WP colour picker. Changes apply instantly across the entire admin chrome — sidebar, admin bar, buttons, links, and focus rings — with no page reload required.

<img width="100%" alt="Screenshot 2026-02-19 at 21 07 39" src="https://github.com/user-attachments/assets/9445e733-0655-4dbe-8e4f-c4f9f0f0daf6" />



## Features

- **8 built-in presets** — Follow WordPress (default), Neutral Blue, Indigo, Teal, Green, Amber, Red, Slate
- **Custom colour picker** — any hex colour via the WordPress Iris picker
- **Live preview** — the admin chrome updates in real time as you pick, without saving
- **Themed background** — the admin page background tints to complement the chosen accent colour
- **Night mode** — one checkbox applies a dark colour theme to the entire admin using explicit CSS colour overrides; no `filter` property is used, so stacking contexts and event handling are completely unaffected
- **Per-user** — each user's preference is stored in user meta and only affects their own session
- **Follow WordPress** — selecting this option emits no override CSS at all; WordPress manages its own admin chrome entirely
- **Clean uninstall** — deleting the plugin removes all `cmb_ui_*` user meta via paginated batch cleanup

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 6.8+ |
| PHP | 8.3+ |

## Installation

1. Download the latest [`cm-beautiful.zip`](https://github.com/soderlind/cm-beautiful/releases/latest/download/cm-beautiful.zip).
2. In WordPress go to **Plugins → Add New → Upload Plugin** and upload the zip.
3. Activate the plugin.

The plugin updates itself automatically via GitHub releases using [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker).

## Usage

1. Go to **Users → Your Profile** (or edit any user profile as an admin).
2. Scroll to the **Color Me Beautiful** section.
3. Pick a **Colour Preset** from the dropdown, or set a **Custom Accent** colour.
4. Optionally enable **Night Mode**.
5. Click **Update Profile** to save.

The chosen colour is applied to every admin screen immediately on next page load. The live preview on the profile page shows the effect before you save.

## How it works

### PHP layer (page load)

`CMB_Admin_Theme` hooks into `admin_head` and reads the current user's saved preset and custom accent from user meta. When a concrete colour is resolved it emits a single `<style id="cmb-admin-vars">` block containing:

- A `:root {}` block overriding `--wp-admin-theme-color` and a set of `--cmb-accent*` / `--cmb-bg` custom properties.
- Admin chrome CSS rules written as `var(--cmb-accent, var(--wp-admin-theme-color, #2271b1))` — the fallback chain means the rules degrade gracefully if the variables are ever absent.

For **Follow WordPress** no CSS is emitted — WordPress manages its own chrome and this plugin does not interfere.

When night mode is enabled, a dark CSS custom property palette (`--cmb-nm-bg`, `--cmb-nm-surface`, `--cmb-nm-raised`, `--cmb-nm-text`, `--cmb-nm-muted`, `--cmb-nm-border`, `--cmb-nm-input`) is emitted to `:root`, and direct `background-color`, `color`, and `border-color` overrides are applied to the key WP admin elements. No `filter` property is used — `filter` creates new stacking contexts which re-parent `position: fixed` elements and cause admin menu items to stop responding to clicks.

### JS layer (live preview)

`profile-color-picker.js` updates the same CSS custom properties on `document.documentElement.style` as you interact with the preset dropdown or colour picker. No page reload, no CSS string rebuild.

A priority-1 `admin_head` inline script captures `--wp-admin-theme-color` into `window._cmbNativeWpColor` before any override is applied. This is used by `clearLiveTheme()` to correctly restore the Follow WordPress colour when switching back to that option in the live preview.

## Release process

Two GitHub Actions workflows are included:

| Workflow | Trigger |
|---|---|
| `on-release-add-zip.yml` | Automatically builds and attaches `cm-beautiful.zip` when a GitHub release is created |
| `manually-build-zip.yml` | Manual dispatch — enter a tag name to build and publish a specific release |

Both workflows run `composer install --no-dev` and strip dev artefacts (`.github/`, dot-files, `composer.json`, etc.) from the zip.

## Development

```bash
git clone https://github.com/soderlind/cm-beautiful.git
cd cm-beautiful
composer install          # includes dev dependencies
```

The only runtime Composer dependency is `yahnis-elsts/plugin-update-checker`. Everything else in `includes/` and `assets/` is plain PHP/CSS/JS with no build step required.

## Changelog

### 1.0.1

- Night mode rewritten: replaced `filter: invert()` with explicit `background-color`, `color`, and `border-color` overrides across all key admin elements — sidebar, admin bar, content area, headings, form inputs, buttons, notices, list tables, metaboxes, cards, screen options, and footer. No `filter` property is used, avoiding the stacking-context issues that broke admin menu clicks.
- Night mode now applies dark colours to the admin sidebar and admin bar regardless of the active accent colour preset.
- Fixed live-preview race condition: the Iris `change` callback now guards against the `follow_wp` option.
- Fixed `CMB_Plugin::init()` double-call guard to prevent duplicate hook registration.

### 1.0.0

Initial release.

## Licence

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
