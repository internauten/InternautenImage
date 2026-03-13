# InternautenImage PrestaShop Module

This project contains an installable PrestaShop module that exports product images as a ZIP directly from the standard module configuration page.

## Module Directory

- `internautenimage`

## Installable ZIP

- find it under releses

## Installation in PrestaShop

1. In the PrestaShop back office, go to **Modules > Module Manager**.
2. Click **Upload a module**.
3. Upload `internautenimage-module-1.1.1.zip`.
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
