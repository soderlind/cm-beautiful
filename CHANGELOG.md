# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-02-20

### Added

- Full WCAG 2.1 contrast compliance for custom accents: contrast CSS variables (`--cmb-accent-contrast`, `--cmb-accent-d10-contrast`, `--cmb-accent-d20-contrast`, `--cmb-accent-d30-contrast`) auto-select black or white text based on luminance.
- Admin bar coverage: `.ab-item`, `.ab-icon`, `.ab-label`, `.ab-submenu`, and collapse button all respect WCAG contrast.
- JavaScript live preview updates contrast colors in real time as you pick a custom color.

### Changed

- Higher-specificity CSS selectors with `!important` ensure contrast overrides WordPress core styles.

## [1.0.2] - 2026-02-19

### Added

- WCAG-compliant contrast colors for admin sidebar and admin bar — text color automatically switches between black and white based on accent luminance.
- Internationalization (i18n) support with translatable preset labels and WP-CLI build scripts (`npm run i18n`).
- Pest 4 test suite with Brain Monkey for WordPress function mocking (`composer test`).
- Vitest 4 for JavaScript unit testing (`npm test`).
- Preset labels are now translation-ready via `get_preset_label()` method.

## [1.0.1] - 2026-02-18

### Changed

- Night mode rewritten: replaced `filter: invert()` with explicit `background-color`, `color`, and `border-color` overrides across all key admin elements — sidebar, admin bar, content area, headings, form inputs, buttons, notices, list tables, metaboxes, cards, screen options, and footer. No `filter` property is used, avoiding stacking-context issues that broke admin menu clicks.
- Night mode now applies dark colours to the admin sidebar and admin bar regardless of the active accent colour preset.

### Fixed

- Live-preview race condition: the Iris `change` callback now guards against the `follow_wp` option.
- `CMB_Plugin::init()` double-call guard to prevent duplicate hook registration.

## [1.0.0] - 2026-02-17

### Added

- Per-user colour presets: Follow WordPress, Neutral Blue, Indigo, Teal, Green, Amber, Red, Slate.
- Custom accent colour picker via WordPress Iris.
- Live preview on the profile page.
- Themed admin background (10% tint of the accent colour).
- Night mode via explicit dark CSS colour overrides (no filter); palette defined as CSS custom properties.
- GitHub-based automatic update checker.
- Clean paginated uninstall routine.

[1.1.0]: https://github.com/soderlind/cm-beautiful/compare/1.0.2...1.1.0
[1.0.2]: https://github.com/soderlind/cm-beautiful/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/soderlind/cm-beautiful/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/soderlind/cm-beautiful/releases/tag/1.0.0
