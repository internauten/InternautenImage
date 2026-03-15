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

### via script
```bash
cd scripts
./push-tag-from-module-version.sh
```


## Test nach Neustart

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}'
```

```bash
curl -I http://localhost:8080/de/
```

```bash
docker exec prestashop php -r 'echo "upload_max_filesize=".ini_get("upload_max_filesize").PHP_EOL; echo "post_max_size=".ini_get("post_max_size").PHP_EOL;'
```

## License

This project is licensed under the MIT License. See details [`LICENSE`](LICENSE).

Copyright (c) 2026 die.internauten.ch GmbH