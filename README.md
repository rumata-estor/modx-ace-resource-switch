# MODX Ace Resource Switch

Switch the **resource content** editor between **CKEditor** (visual mode) and **Ace** (code mode) using the existing **Rich Text / richtext** checkbox in MODX Revolution. No core modifications or transport package are needed.

[Русская инструкция](README.ru.md)

## Compatibility

Tested on **MODX Revolution 3.2.4-pl**, **Ace 1.9.10** and **CKEditor** (`ckeditor.version = 1.4.7-ce` on the test site). Other RTEs and MODX 2 are **not tested**.

Requires Ace and CKEditor extras to be installed and enabled.

## Install

1. In MODX **System Settings**, set `use_editor = Yes`, `which_editor = CKEditor`, and `which_element_editor = Ace`. Check context overrides, if any.
2. In **Elements → Plugins**, create a plugin named `AceResourceEditor`.
3. Paste the contents of [`AceResourceEditor.plugin.php`](AceResourceEditor.plugin.php) into the plugin code field **without adding `<?php`**.
4. Assign the **`OnDocFormPrerender`** event and enable the plugin.
5. Clear MODX cache and reload the manager page. On a test resource, toggle **Use Rich Text Editor** in resource settings.

When `richtext = 1`, CKEditor is shown; when `richtext = 0`, Ace is shown without reloading the page.

## Important behavior

- Switching into and out of CKEditor **without editing** restores the original HTML byte-for-byte, including comments and line endings.
- If you **edit in CKEditor**, its output may reformat HTML, whitespace, and comments. The plugin warns you when returning to Ace.
- Saving syncs the active editor with the MODX resource content field.
- Applies to standard resource content, not TVs or element editing. Leaves Ace and CKEditor package files unchanged.

To uninstall, disable/delete the `AceResourceEditor` plugin and clear the MODX cache. Revert any optional site-specific settings manually.

## Tests

Run `node tests/switch.test.cjs` (Node.js 18+; no npm dependencies). This checks editor switching, HTML preservation, save synchronization and delayed CKEditor initialization. Browser tests are still recommended before production use.

License: [GPL-2.0-or-later](LICENSE). This is a standalone integration plugin, not an official MODX or Ace release.
