# Changelog - Plugin Manager

Plugin version numbers: 1.00, 1.01, 1.02 … (+0.01 for every change). The current version is in `plugin_manager.yaml`.

## [1.15] - 2026-11-13
- Documentation: English is the main language - change history translated to English.

## [1.14] - 2026-11-13
- Documentation: installation on Apache (Ubuntu/Debian and the RHEL family) – user and group that run PHP (mod_php / PHP-FPM), permissions, no directory listings (Options -Indexes), SELinux; LimitRequestBody, write access to plugins/ and php-pecl-zip.

## [1.13] - 2026-11-12
- "Upload a plugin" box below the plugin list; bigger Options icon; centred columns Version, Info, Status, Options, Activate; outline Deactivate button; collapsible "Reset plugin" box.

## [1.12] - 2026-11-12
- New name: Plugin Manager (folder plugin_manager, previously Plugin Admin / pluginadmin); the page reminds you to deactivate and delete the old plugin pluginadmin; new repository s29829/plugin_manager.

## [1.11] - 2026-11-11
- Clean-up of the change history (no change in the plugin).

## [1.10] - 2026-11-11
- Documentation: installation with Plugin Manager or on the command line (curl, unzip, chown) without bash scripts; releases contain only the zip archive.

## [1.09] - 2026-11-11
- Files waiting for "Overwrite / Cancel" in <filestore>/plugin_manager/data_<key>/pending/ (previously the temporary folder); "Reset plugin" (deletes these files and other working folders, does not change installed plugins); all `@` calls removed (files and permissions are checked before use).

## [1.08] - 2026-11-11
- Changes in the documentation and release tools (no change in the plugin).

## [1.07] - 2026-11-11
- Changes in the documentation and release tools (no change in the plugin).

## [1.06] - 2026-11-11
- Self-contained zip package: LICENSE-COMMERCIAL.md in the plugin folder, license and documentation in the package; installation instructions for the zip archive.

## [1.05] - 2026-11-11
- License PolyForm Noncommercial 1.0.0 (file LICENSE; commercial use only with the author's written permission); "License" section in the documentation.

## [1.04] - 2026-11-11
- Documentation in the plugin folder: README.md (EN) and README.pl.md (PL), with the version in which each feature appeared.

## [1.03] - 2026-11-09
- Follows the ResourceSpace guidelines: both plugin locations are supported – `plugins/` and `filestore/plugins` (list, delete and overwrite in the plugin's current folder; names checked for uniqueness in both; warning about a duplicate name).
- Names of newly uploaded plugins only with lower-case letters, digits and `_` (the name is part of the hook function names).

## [1.02] - 2026-11-09
- "Options" column with a cog icon (inline SVG, link to the plugin's `config_url`) – active only for activated plugins.
- "Activate" / "Deactivate" buttons with the same width.

## [1.01] - 2026-11-08
- "Info" column: tooltip with `desc`, author and category from the YAML file and a "Doc" link from `info_url`.
- "Delete" buttons in the list replaced with "Activate" / "Deactivate" (ResourceSpace functions `activate_plugin` / `deactivate_plugin`).

## [1.00] - 2026-11-08
- First version: upload of plugins as a `.zip` archive (unpacked to `/plugins/<name>`; if the plugin already exists – choice "Overwrite" / "Cancel") and deletion of a plugin folder after confirming with the plugin name or the word `delete`. Active plugins and Plugin Manager itself cannot be deleted.
