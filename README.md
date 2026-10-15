# InternautenImage PrestaShop Module

[![Release Workflow](https://github.com/internauten/InternautenImage/actions/workflows/release.yml/badge.svg)](https://github.com/internauten/InternautenImage/actions/workflows/release.yml)
[![Latest Release](https://img.shields.io/github/v/release/internauten/InternautenImage?sort=semver)](https://github.com/internauten/InternautenImage/releases)
[![GitHub Sponsors](https://img.shields.io/badge/Sponsor-GitHub%20Sponsors-pink)](https://github.com/sponsors/internauten)
[![GitHub stars](https://img.shields.io/github/stars/internauten/InternautenImage?style=social)](https://github.com/internauten/InternautenImage/stargazers)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

This project contains an installable PrestaShop module that exports and imports product images as a ZIP directly from the standard module configuration page.

Features at a glance:

- Product image export and import (ZIP)
- Category image export and import (ZIP, including category thumbnails)
- Copy category images to manufacturers with matching names
- Generate name images for manufacturers without an image
- Scope preview with counters for products and manufacturers without images

## Module Directory

- `internautenimage`

## Installable ZIP

- find it under [Releases](https://github.com/internauten/InternautenImage/releases)

## Requirements

- PrestaShop **9.1.4** or newer (PHP 8.1+)
- PHP extensions: ZipArchive, GD (for manufacturer name images)

Versionen bis 1.3.0 unterstützten PrestaShop 1.7/8. Ab Modul v2.0.0 wird nur noch PrestaShop 9.1.4+ unterstützt; ältere Shops lassen die Installation nicht mehr zu.

## Installation in PrestaShop

1. In the PrestaShop back office, go to **Modules > Module Manager**.
2. Click **Upload a module**.
3. Upload `internautenimage-module-x.x.x.zip`.
4. Install **Internauten Product Image Export and Import**.

## Scope Preview

At the top of the configuration page the module shows:

- **Products without images** for the selected shop scope and product filter
- **Manufacturers without images** for the selected shop scope

The panel **Scope preview** lets you change shop scope and product filter and refresh these counters without starting an export or import.

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
2. Select the import mode:
   - **Replace existing images** (default): existing product images are deleted before the new ones are added.
   - **Add new images additionally**: new images are appended after the existing ones.
   - **Skip products with existing images**: only products without images get images.
3. Upload a ZIP file.
4. Click **Upload and import ZIP**.

The upload runs in the background with a progress bar (upload and import). If the ZIP exceeds `upload_max_filesize` or `post_max_size`, the module shows the server limits instead of failing silently.

Import Rules:

- Accepted formats: `jpg`, `jpeg`, `png`, `gif`, `webp`; subfolders inside the ZIP are scanned too. Images are stored as JPG.
- File name must match the product reference, e.g. `ABC123.jpg`.
- Additional images: `ABC123_1.jpg`, `ABC123_2.jpg`, ...
- Suffixes `_1`, `_2` are interpreted as 2nd, 3rd, ... image order.
- If no product `ABC123` exists, a reference that itself ends in `_1` (e.g. `ABC123_1`) is matched as a whole.
- If the product has no images yet, the first imported image becomes the cover image.
- Image legend is set to the product name in all languages.
- All product thumbnails are generated directly; the `actionWatermark` hook is triggered.

After the import the module lists every skipped file with the reason (no matching reference, product outside the scope, product already has images in skip mode, invalid image).

## Category Image Export/Import

The module also supports category image export and import as ZIP:

1. Choose shop scope.
2. Export category images or upload a category ZIP.

Category filename rules:

- `cat_<categoryId>.jpg` (example: `cat_12.jpg`) → category image
- `cat_pre_<categoryId>.jpg` (example: `cat_pre_12.jpg`) → category thumbnail (`<id>_thumb.jpg`)

Only categories inside the selected shop scope are imported. All category image types are generated directly, and skipped files are listed with the reason.

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

Create and push a release tag manualy:

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

Reads Version from internautenimage.php, prevents taging if not all files are Published.

## Neue Funktion Transfer to Marken

Neuer Bereich „Kategoriebilder zu Marken kopieren" ist eingebaut (Modul v1.2.0).

Funktionsweise in `internautenimage.php`:

- `copyCategoryImagesToManufacturers()` holt alle Kategorien mit vorhandener Bilddatei und alle Marken im gewählten Shop-Kontext, normalisiert beide Namen (kleinschreiben, Umlaute/Akzente auflösen, Leer- und Sonderzeichen entfernen) und vergleicht sie.
- `copyImageToManufacturer()` schreibt via `ImageManager::resize()` nach `_PS_MANU_IMG_DIR_` und erzeugt zusätzlich alle Thumbnails aus `ImageType::getImagesTypes('manufacturers')` – ein Regenerieren entfällt.
- `renderCategoryToManufacturerForm()` liefert das Formular mit Shop-Kontext, Zuordnungsmodus (exakt / enthält), Schalter zum Überschreiben bestehender Markenbilder und Vorschau-Modus (standardmässig an).
- `buildManufacturerCopyDetailsHtml()` zeigt pro Kategorie das Ergebnis mit Grund an.

## Neue Funktion Namensbilder für Marken ohne Bild

Neuer Bereich „Namensbilder für Marken ohne Bild generieren" (Modul v1.3.0).

Funktionsweise in `internautenimage.php`:

- `generateManufacturerNameImages()` durchläuft alle Marken im gewählten Shop-Kontext und erzeugt für jede Marke ohne Bilddatei ein JPG mit dem Markennamen.
- `writeManufacturerNameImage()` zeichnet den Namen per GD zentriert auf eine weisse Fläche von 200x150 Pixel, speichert nach `_PS_MANU_IMG_DIR_` und erzeugt alle Thumbnails aus `ImageType::getImagesTypes('manufacturers')`.
- `splitManufacturerNameIntoLines()` transliteriert den Namen nach ASCII und bricht ihn auf maximal 3 Zeilen à 16 Zeichen um.
- `renderManufacturerNameImageForm()` liefert das Formular mit Shop-Kontext, Schalter zum Überschreiben bestehender Markenbilder und Vorschau-Modus (standardmässig an).
- `buildManufacturerNameImageDetailsHtml()` protokolliert im BO jede Marke mit Ergebnis und Grund (generiert, Vorschau, bestehendes Bild, kein Name, Fehler).
- `getManufacturersWithoutImageCount()` zeigt oben auf der Konfigurationsseite die Anzahl Marken ohne Bild an.

Voraussetzung: PHP-Erweiterung GD. Fehlt sie, bricht die Funktion mit einer Meldung ab.

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

Verfahren wie in [`WoWGetPrestaLocal`](https://github.com/internauten/WoWGetPrestaLocal) dokumentiert.

## License

This project is licensed under the MIT License. See details [`LICENSE`](LICENSE).

Copyright (c) 2026 die.internauten.ch GmbH
