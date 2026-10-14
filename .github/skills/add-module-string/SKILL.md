---
name: add-module-string
description: 'Add, change or translate a back-office UI string in the internautenimage PrestaShop module. Use when: adding $this->l() text, renaming a label/message, new form field or error message, missing German translation, fixing untranslated strings, syncing translations/en.php and de.php, md5 translation keys.'
argument-hint: 'English text to add or change (optional)'
---

# Add or Change a Module String

Translations use legacy PrestaShop `$_MODULE` files. The key is
`<{internautenimage}prestashop>internautenimage_` + `md5(<English source text>)`, so **changing the English text in code orphans the old translation**. Both files must get the new key.

## Procedure

1. **Write the code** in [internautenimage.php](../../../internautenimage/internautenimage.php):
   - Always `$this->l('English text')` with a **single-quoted literal** — no variables, concatenation or double quotes (they are not translatable).
   - Dynamic parts go through placeholders: `sprintf($this->l('%1$d images imported'), (int) $n)`. Use numbered `%1$d`/`%2$s` when there is more than one placeholder, so German can reorder.
   - Reuse an existing string if the exact text already exists (search [en.php](../../../internautenimage/translations/en.php) first).
2. **Find missing keys**: run `php .github/skills/add-module-string/scripts/check-translations.php` from the repo root. It prints the ready-to-paste `$_MODULE[...]` lines per file (keys already computed with PrestaShop's escaping of `'`).
3. **Append** the printed lines to the end of [en.php](../../../internautenimage/translations/en.php) (value = English text) and [de.php](../../../internautenimage/translations/de.php) (replace value with German, drop the `// TODO translate` comment).
4. **German style**: real umlauts (`ä`/`ö`/`ü`, UTF-8), but Swiss `ss` instead of `ß`. Older entries still use `ue`/`ae`/`oe` — don't copy that. Keep placeholders identical; use existing terms (Shop-Kontext, Produktfilter, Marke, Kategoriebild, Vorschau, übersprungen).
5. **When replacing text**: delete the old key from both files only if the script no longer finds the old string in the code (it reports such keys as "unused"; don't mass-delete pre-existing unused keys unless asked).
6. **Verify**: rerun the script (exit code 0, "0 missing" for both files) and `php -l` on the module file and both translation files.
