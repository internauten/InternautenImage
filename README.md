# InternautenImage PrestaShop Module

[![Release Workflow](https://github.com/internauten/InternautenImage/actions/workflows/release.yml/badge.svg)](https://github.com/internauten/InternautenImage/actions/workflows/release.yml)
[![Latest Release](https://img.shields.io/github/v/release/internauten/InternautenImage?sort=semver)](https://github.com/internauten/InternautenImage/releases)
[![GitHub Sponsors](https://img.shields.io/badge/Sponsor-GitHub%20Sponsors-pink)](https://github.com/sponsors/internauten)
[![GitHub stars](https://img.shields.io/github/stars/internauten/InternautenImage?style=social)](https://github.com/internauten/InternautenImage/stargazers)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

This project contains an installable PrestaShop module that exports product images as a ZIP directly from the standard module configuration page.

## Module Directory

- `internautenimage`

## Installable ZIP

- find it under releses

## Installation in PrestaShop

1. In the PrestaShop back office, go to **Modules > Module Manager**.
2. Click **Upload a module**.
3. Upload `internautenimage-module-x.x.x.zip`.
4. Install **Internauten Product Image Export**.

## Usage in Configuration Page

1. In Module Manager, click **Configure** on the module.
2. Select language.
3. Select shop scope:
   - Current shop only
   - All shops
4. Select product filter:
   - All products
   - Active products only
5. Click **Create and download ZIP**.
6. The download starts directly in the browser.

## Import via ZIP Upload

The module provides a second button for import:

1. Select shop scope and product filter.
2. Upload a ZIP file.
3. Click **Upload and import ZIP**.

Import Rules:

- File name must match the product reference, e.g. `ABC123.jpg`.
- Additional images: `ABC123_1.jpg`, `ABC123_2.jpg`, ...
- Suffixes `_1`, `_2` are interpreted as 2nd, 3rd, ... image order.
- Image legend is set to the product name in all languages.

## Category Image Export/Import

The module also supports category image export and import as ZIP:

1. Choose shop scope.
2. Export category images or upload a category ZIP.

Category filename rules:

- `cat_<categoryId>.jpg` (example: `cat_12.jpg`)
- `cat_pre_<categoryId>.jpg` (example: `cat_pre_12.jpg`)

## ZIP Naming Rules

- First image of a product: `REFERENCE.jpg`
- Second image: `REFERENCE_1.jpg`
- Third image: `REFERENCE_2.jpg`

If a reference contains special characters, they are sanitized for file names.

## Technical Behavior

- Reads all products in the selected shop scope.
- Language is selectable in the configuration page.
- Shop scope is selectable (current shop or all shops).
- Product filter is selectable (all or active only).
- Copies image files into a temporary directory first.
- Creates the ZIP in the cache directory.
- Sends the ZIP directly as a download from the configuration page.
- Cleans up temporary files after export.

## Release Tagging

GitHub Releases are created automatically when you push a tag in this format:

- vX.X.X (example: v1.1.2)

Create and push a release tag:

```bash
git tag v1.1.2
git push origin v1.1.2
```

The workflow then builds and uploads:

- internautenimage-module-v1.1.2.zip

### via script

```bash
cd scripts
./tag-release.sh
```

## Neue Funktion Transfer to Marken

Neuer Bereich „Kategoriebilder zu Marken kopieren" ist eingebaut (Modul v1.2.0).

Funktionsweise in `internautenimage.php`:

- `copyCategoryImagesToManufacturers()` holt alle Kategorien mit vorhandener Bilddatei und alle Marken im gewählten Shop-Kontext, normalisiert beide Namen (kleinschreiben, Umlaute/Akzente auflösen, Leer- und Sonderzeichen entfernen) und vergleicht sie.
- `copyImageToManufacturer()` schreibt via `ImageManager::resize()` nach `_PS_MANU_IMG_DIR_` und erzeugt zusätzlich alle Thumbnails aus `ImageType::getImagesTypes('manufacturers')` – ein Regenerieren entfällt.
- `renderCategoryToManufacturerForm()` liefert das Formular mit Shop-Kontext, Zuordnungsmodus (exakt / enthält), Schalter zum Überschreiben bestehender Markenbilder und Vorschau-Modus (standardmässig an).
- `buildManufacturerCopyDetailsHtml()` zeigt pro Kategorie das Ergebnis mit Grund an.

Sicherheitsnetze beim Matching:

- Mehrdeutige Treffer (mehrere Marken passen) werden übersprungen statt willkürlich zugeordnet.
- Eine Marke bekommt nur ein Bild; weitere Kategorien mit demselben Ziel werden als übersprungen gemeldet.
- Bestehende Markenbilder bleiben erhalten, solange „Überschreiben" aus ist.

Empfehlung: erst mit „Nur Vorschau = Ja" laufen lassen, Trefferliste prüfen, dann scharf schalten.

Made changes.

## Weiteres/Zukünftiges

Frage: muss nach dem bild upload die "Wiederherstellen der Vorschaubilder" ausgeführt werden?

Nein – im Normalfall nicht.

`importSingleProductImage()` erzeugt nach dem Anlegen des `Image`-Objekts bereits selbst alle Thumbnails: es speichert das Original als `.jpg` und rendert danach für jeden Eintrag aus `ImageType::getImagesTypes('products')` die passende Grösse (`internautenimage.php:1734-1743`). Anschliessend läuft noch `actionWatermark`, sodass auch Wasserzeichen-Module greifen. Für Kategoriebilder passiert dasselbe in `importSingleCategoryImage()`.

Regenerieren ist nur in diesen Fällen nötig:

- **PS 8.1+ mit WebP/AVIF**: Ist unter Design > Bildereinstellungen ein anderes Format als JPEG aktiv, fehlen die `.webp`/`.avif`-Varianten, da das Modul nur `.jpg` schreibt.
- **High-DPI-Bilder** (`_2x`-Varianten), falls in deiner Shop-Konfiguration aktiviert.
- Wenn du **nach** dem Import einen neuen Bildtyp anlegst oder Masse änderst.
- Wenn einzelne Dateien laut Skip-Liste als „Bild konnte nicht verarbeitet werden" gemeldet wurden und du danach manuell nachbesserst.

Wenn dein Shop auf WebP/AVIF läuft, kann ich den Import so erweitern, dass er die konfigurierten Formate direkt mitgeneriert – dann entfällt der Regenerierungslauf ganz.

## Develope

Verfahren wie in [`WoWGetPrestaLocal`](https://github.com/internauten/WoWGetPrestaLocal).

## License

This project is licensed under the MIT License. See details [`LICENSE`](LICENSE).

Copyright (c) 2026 die.internauten.ch GmbH
