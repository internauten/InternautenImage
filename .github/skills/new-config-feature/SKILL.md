---
name: new-config-feature
description: 'Scaffold a new feature/section on the internautenimage module configuration page. Use when: adding a new form, button, panel, bulk action, export/import, image tool or batch operation to getContent(); new HelperForm with shop scope, overwrite and dry-run switches; feature needs version bump and README section.'
argument-hint: 'Short description of the new feature'
---

# New Configuration-Page Feature

Every feature in [internautenimage.php](../../../internautenimage/internautenimage.php) follows the same 4-part pattern. **Reference implementation**: the manufacturer name image feature — copy its shape:

| Part | Reference method |
|---|---|
| Form | `renderManufacturerNameImageForm()` |
| Submit branch | `Tools::isSubmit('submitInternautenManufacturerNameImage')` in `getContent()` |
| Worker | `generateManufacturerNameImages()` |
| Result list | `buildManufacturerNameImageDetailsHtml()` |

## Procedure

1. **Clarify** (ask if unclear): what the feature reads/writes, whether it is destructive (writes or deletes images), which shop/product scope applies.
2. **Pick names** (`<Feature>` in PascalCase, `<KEY>` short uppercase):
   - submit name `submitInternauten<Feature>`
   - field names `INTERN_AUTENIMAGE_<KEY>_*` (keep the `INTERN_AUTENIMAGE` misspelling) — use a feature-specific `<KEY>` so fields don't clash with other forms on the same page.
3. **Form** `render<Feature>Form()`: `HelperForm` with `submit_action`, `currentIndex = AdminController::$currentIndex . '&configure=' . $this->name`, `token = Tools::getAdminTokenLite('AdminModules')`. Repopulate `fields_value` from `Tools::getValue()` with casts. Use a unique `id` per switch value (`<key>_dry_run_on`). Append the call to the `return` chain at the end of `getContent()`.
4. **Destructive features** must have:
   - `_SHOP_SCOPE` select (`current`/`all`)
   - `_OVERWRITE` switch, default `0`
   - `_DRY_RUN` switch ("Preview only"), default **`1`**
5. **Submit branch** in `getContent()` (before the `displayInformation` block): read inputs with `(string)`/`(bool)` casts, call the worker in `try/catch (Exception $e)`, show `displayConfirmation(sprintf(...))` (separate preview/real messages with `%1$d`/`%2$d`), then `displayWarning($this->build<Feature>DetailsHtml($result['details']))` if details exist; on error `displayError($this->l('… failed: ') . $e->getMessage())`.
6. **Worker** `<verb><Feature>($shopScope, $overwrite = false, $dryRun = false)`:
   - `@set_time_limit(0);` normalize `$shopScope = $shopScope === 'all' ? 'all' : 'current';`
   - throw `Exception` with a translated message for preconditions (missing PHP extension, nothing found)
   - per item: skip with a `reason` key, count, never write when `$dryRun`, never overwrite existing files unless `$overwrite`
   - return `['<done-count-key>' => (int) n, 'skipped' => (int) n, 'details' => [['<item>' => label, 'reason' => key], ...]]`
   - reuse helpers: `getManufacturersForScope()`, `getCategoriesWithImageForScope()`, `getProductIdsForScope()`, `isCategoryInScope()`; SQL via `DbQuery`/`Db::getInstance()` with `(int)`/`pSQL()`
   - image writes: `ImageManager::resize()` to `.jpg` + every thumbnail from `ImageType::getImagesTypes('<products|categories|manufacturers>')` (see `copyImageToManufacturer()`); products also `Hook::exec('actionWatermark', …)`
7. **Details HTML** `build<Feature>DetailsHtml(array $details)`: limit 200 entries, map each `reason` key to a `$this->l()` text, escape every value with `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`, end with `'... and %d more entries.'`.
8. **Strings**: follow the [add-module-string skill](../add-module-string/SKILL.md) — run its check script and add every new key to `en.php` and `de.php`.
9. **Version + docs**: bump `$this->version` (new feature → minor, fix → patch). Add a German section `## Neue Funktion <Name>` to [README.md](../../../README.md) with `(Modul vX.Y.0)`, a bullet per new method, and requirements/safety notes — same style as the existing sections.
10. **Verify**: `php -l internautenimage/internautenimage.php`, translation check exits 0, and the PHP 8.1 rules from [AGENTS.md](../../../AGENTS.md) hold. Don't tag or push a release.
