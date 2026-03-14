<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class InternautenImage extends Module
{
    public function __construct()
    {
        $this->name = 'internautenimage';
        $this->tab = 'administration';
        $this->version = '0.0.5';
        $this->author = 'Internauten';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Internauten Product Image Export and Import');
        $this->description = $this->l('Exports product images as a ZIP directly from the PrestaShop configuration page and allows importing images from a ZIP.');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install();
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    public function getContent()
    {
        if ((string) Tools::getValue('internautenimage_ajax') === '1') {
            $action = (string) Tools::getValue('internautenimage_action');
            $progressKey = preg_replace('/[^a-z0-9]/', '', (string) Tools::getValue('progress_key'));

            if ($action === 'get_progress') {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                $data = '{"current":0,"total":0}';
                if ($progressKey !== '') {
                    $progressFile = _PS_CACHE_DIR_ . 'internautenimage_progress_' . $progressKey . '.json';
                    if (is_file($progressFile)) {
                        $raw = (string) file_get_contents($progressFile);
                        if ($raw !== '') {
                            $data = $raw;
                        }
                    }
                }
                header('Content-Type: application/json');
                echo $data;
                exit;
            }

            if ($action === 'import') {
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_write_close();
                }
                try {
                    $shopScope = (string) Tools::getValue('INTERN_AUTENIMAGE_SHOP_SCOPE', 'current');
                    $productFilter = (string) Tools::getValue('INTERN_AUTENIMAGE_PRODUCT_FILTER', 'all');
                    $result = $this->importArchive($shopScope, $productFilter, $progressKey);
                    if ($progressKey !== '') {
                        @unlink(_PS_CACHE_DIR_ . 'internautenimage_progress_' . $progressKey . '.json');
                    }
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'imported' => (int) $result['imported'], 'skipped' => (int) $result['skipped']]);
                } catch (Exception $e) {
                    if ($progressKey !== '') {
                        @unlink(_PS_CACHE_DIR_ . 'internautenimage_progress_' . $progressKey . '.json');
                    }
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
            }
        }

        $html = '';

        if (Tools::isSubmit('submitInternautenImageImport')) {
            try {
                $shopScope = (string) Tools::getValue('INTERN_AUTENIMAGE_SHOP_SCOPE', 'current');
                $productFilter = (string) Tools::getValue('INTERN_AUTENIMAGE_PRODUCT_FILTER', 'all');
                $result = $this->importArchive($shopScope, $productFilter);

                $html .= $this->displayConfirmation(
                    sprintf(
                        $this->l('Import completed. %d images imported, %d files skipped.'),
                        (int) $result['imported'],
                        (int) $result['skipped']
                    )
                );
            } catch (Exception $e) {
                $html .= $this->displayError($this->l('Import failed: ') . $e->getMessage());
            }
        }

        if (Tools::isSubmit('submitInternautenImageExport')) {
            try {
                $langId = (int) Tools::getValue('INTERN_AUTENIMAGE_LANG_ID', (int) $this->context->language->id);
                $shopScope = (string) Tools::getValue('INTERN_AUTENIMAGE_SHOP_SCOPE', 'current');
                $productFilter = (string) Tools::getValue('INTERN_AUTENIMAGE_PRODUCT_FILTER', 'all');
                $zipPath = $this->buildArchive($langId, $shopScope, $productFilter);
                $this->sendArchive($zipPath);
                exit;
            } catch (Exception $e) {
                $html .= $this->displayError($this->l('Export failed: ') . $e->getMessage());
            }
        }

        $html .= $this->displayInformation(
            $this->l('Export: Creates a ZIP with product images. Import: Accepts a ZIP, maps images by product reference, and sets image legends to the product name in all languages.')
        );

        return $html . $this->renderExportForm() . $this->renderImportForm();
    }

    protected function renderExportForm()
    {
        $languageOptions = [];
        $languages = Language::getLanguages(false);
        foreach ($languages as $language) {
            $languageOptions[] = [
                'id_option' => (int) $language['id_lang'],
                'name' => (string) $language['name'],
            ];
        }

        $shopScopeOptions = [
            [
                'id_option' => 'current',
                'name' => $this->l('Current shop only'),
            ],
            [
                'id_option' => 'all',
                'name' => $this->l('All shops'),
            ],
        ];

        $productFilterOptions = [
            [
                'id_option' => 'all',
                'name' => $this->l('All products'),
            ],
            [
                'id_option' => 'active',
                'name' => $this->l('Active products only'),
            ],
        ];

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Image Export (ZIP Download)'),
                    'icon' => 'icon-picture',
                ],
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Language'),
                        'name' => 'INTERN_AUTENIMAGE_LANG_ID',
                        'required' => true,
                        'options' => [
                            'query' => $languageOptions,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Images are loaded for the selected language.'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Shop scope'),
                        'name' => 'INTERN_AUTENIMAGE_SHOP_SCOPE',
                        'required' => true,
                        'options' => [
                            'query' => $shopScopeOptions,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Choose whether to export from the current shop only or from all shops.'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Product filter'),
                        'name' => 'INTERN_AUTENIMAGE_PRODUCT_FILTER',
                        'required' => true,
                        'options' => [
                            'query' => $productFilterOptions,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Choose whether to export all products or active products only.'),
                    ],
                ],
                'description' => $this->l('Click export to download the ZIP directly.'),
                'submit' => [
                    'title' => $this->l('Create and download ZIP'),
                    'class' => 'btn btn-primary pull-right',
                    'name' => 'submitInternautenImageExport',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitInternautenImageExport';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value = [
            'INTERN_AUTENIMAGE_LANG_ID' => (int) Tools::getValue('INTERN_AUTENIMAGE_LANG_ID', (int) $this->context->language->id),
            'INTERN_AUTENIMAGE_SHOP_SCOPE' => (string) Tools::getValue('INTERN_AUTENIMAGE_SHOP_SCOPE', 'current'),
            'INTERN_AUTENIMAGE_PRODUCT_FILTER' => (string) Tools::getValue('INTERN_AUTENIMAGE_PRODUCT_FILTER', 'all'),
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    protected function renderImportForm()
    {
        $shopScopeOptions = [
            [
                'id_option' => 'current',
                'name' => $this->l('Current shop only'),
            ],
            [
                'id_option' => 'all',
                'name' => $this->l('All shops'),
            ],
        ];

        $productFilterOptions = [
            [
                'id_option' => 'all',
                'name' => $this->l('All products'),
            ],
            [
                'id_option' => 'active',
                'name' => $this->l('Active products only'),
            ],
        ];

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Image Import (ZIP Upload)'),
                    'icon' => 'icon-upload',
                ],
                'enctype' => 'multipart/form-data',
                'input' => [
                    [
                        'type' => 'select',
                        'label' => $this->l('Shop scope'),
                        'name' => 'INTERN_AUTENIMAGE_SHOP_SCOPE',
                        'required' => true,
                        'options' => [
                            'query' => $shopScopeOptions,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Product filter'),
                        'name' => 'INTERN_AUTENIMAGE_PRODUCT_FILTER',
                        'required' => true,
                        'options' => [
                            'query' => $productFilterOptions,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'file',
                        'label' => $this->l('ZIP file'),
                        'name' => 'INTERN_AUTENIMAGE_IMPORT_ZIP',
                        'required' => true,
                        'desc' => $this->l('File names must contain the product reference. Suffixes like _1, _2, ... are interpreted as 2nd, 3rd, ... image order.'),
                    ],
                ],
                'description' => $this->l('Images are extracted on the server and mapped to products by reference.'),
                'submit' => [
                    'title' => $this->l('Upload and import ZIP'),
                    'class' => 'btn btn-default pull-right',
                    'name' => 'submitInternautenImageImport',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitInternautenImageImport';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value = [
            'INTERN_AUTENIMAGE_SHOP_SCOPE' => (string) Tools::getValue('INTERN_AUTENIMAGE_SHOP_SCOPE', 'current'),
            'INTERN_AUTENIMAGE_PRODUCT_FILTER' => (string) Tools::getValue('INTERN_AUTENIMAGE_PRODUCT_FILTER', 'all'),
        ];

        $formHtml = $helper->generateForm([$fieldsForm]);
        $ajaxUrl = json_encode(
            AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules')
        );

        $lProgress    = $this->l('Import progress');
        $lUploading   = json_encode($this->l('Uploading...'));
        $lProcessing  = json_encode($this->l('Processing...'));
        $lImporting   = json_encode($this->l('Importing'));
        $lDone        = json_encode($this->l('Import completed.'));
        $lImported    = json_encode($this->l('images imported,'));
        $lSkipped     = json_encode($this->l('files skipped.'));
        $lFailed      = json_encode($this->l('Import failed:'));
        $lUnknown     = json_encode($this->l('An unknown error occurred.'));
        $lServerErr   = json_encode($this->l('Server error during import.'));
        $lNetworkErr  = json_encode($this->l('Network error during import.'));

        $progressHtml = '
<div id="internautenimage-progress-wrap" style="display:none;margin-top:15px;" class="panel">
    <div class="panel-heading"><i class="icon-spinner icon-spin"></i> ' . $lProgress . '</div>
    <div class="panel-body">
        <div class="progress">
            <div id="internautenimage-progress-bar"
                 class="progress-bar progress-bar-striped active"
                 role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                 style="width:2%;min-width:2em;transition:width 0.4s ease;">0%</div>
        </div>
        <p id="internautenimage-progress-text" style="margin-top:8px;"></p>
        <div id="internautenimage-result" style="display:none;margin-top:10px;"></div>
    </div>
</div>
<script>
(function () {
    var ajaxUrl = ' . $ajaxUrl . ';
    var btn = document.querySelector(\'button[name="submitInternautenImageImport"]\');
    if (!btn) { return; }
    var form = btn.closest(\'form\');
    if (!form) { return; }
    var wrap = document.getElementById(\'internautenimage-progress-wrap\');
    var bar  = document.getElementById(\'internautenimage-progress-bar\');
    var txt  = document.getElementById(\'internautenimage-progress-text\');
    var res  = document.getElementById(\'internautenimage-result\');

    form.addEventListener(\'submit\', function (e) {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }

        var key = Math.random().toString(36).slice(2) + Date.now().toString(36);
        var fd  = new FormData(form);
        fd.append(\'internautenimage_ajax\', \'1\');
        fd.append(\'internautenimage_action\', \'import\');
        fd.append(\'progress_key\', key);

        wrap.style.display = \'block\';
        res.style.display  = \'none\';
        res.textContent    = \'\';
        bar.className      = \'progress-bar progress-bar-striped active\';
        bar.style.width    = \'2%\';
        bar.textContent    = \'0%\';
        txt.style.display  = \'block\';
        txt.textContent    = ' . $lUploading . ';
        btn.disabled = true;

        var poll = setInterval(function () {
            var x = new XMLHttpRequest();
            x.open(\'GET\', ajaxUrl + \'&internautenimage_ajax=1&internautenimage_action=get_progress&progress_key=\' + encodeURIComponent(key), true);
            x.onload = function () {
                try {
                    var d = JSON.parse(x.responseText);
                    if (d.total > 0) {
                        var p = Math.min(99, Math.round(d.current / d.total * 100));
                        bar.style.width = p + \'%\';
                        bar.setAttribute(\'aria-valuenow\', p);
                        bar.textContent = p + \'%\';
                        txt.textContent = ' . $lImporting . ' + \' \' + d.current + \' / \' + d.total;
                    }
                } catch (ex) {}
            };
            x.send();
        }, 700);

        var xhr = new XMLHttpRequest();
        xhr.open(\'POST\', ajaxUrl, true);

        xhr.upload.onprogress = function (e) {
            if (!e.lengthComputable || e.total === 0) { return; }
            var p = Math.max(2, Math.round(e.loaded / e.total * 10));
            if (e.loaded < e.total) {
                bar.style.width = p + \'%\';
                bar.textContent = p + \'%\';
            } else {
                txt.textContent = ' . $lProcessing . ';
            }
        };

        xhr.onload = function () {
            clearInterval(poll);
            bar.style.width = \'100%\';
            bar.setAttribute(\'aria-valuenow\', 100);
            bar.textContent = \'100%\';
            bar.classList.remove(\'active\');
            var ok = false;
            if (xhr.status === 200) {
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.success) {
                        ok = true;
                        bar.classList.add(\'progress-bar-success\');
                        res.className   = \'alert alert-success\';
                        res.textContent = ' . $lDone . ' + \' \' + r.imported + \' \' + ' . $lImported . ' + \' \' + r.skipped + \' \' + ' . $lSkipped . ';
                    } else {
                        res.className   = \'alert alert-danger\';
                        res.textContent = ' . $lFailed . ' + \' \' + r.error;
                    }
                } catch (ex) {
                    res.className   = \'alert alert-danger\';
                    res.textContent = ' . $lUnknown . ';
                }
            } else {
                res.className   = \'alert alert-danger\';
                res.textContent = ' . $lServerErr . ';
            }
            if (!ok) { bar.classList.add(\'progress-bar-danger\'); }
            txt.style.display = \'none\';
            res.style.display = \'block\';
            btn.disabled = false;
        };

        xhr.onerror = function () {
            clearInterval(poll);
            bar.classList.remove(\'active\');
            bar.classList.add(\'progress-bar-danger\');
            res.className   = \'alert alert-danger\';
            res.textContent = ' . $lNetworkErr . ';
            txt.style.display = \'none\';
            res.style.display = \'block\';
            btn.disabled = false;
        };

        xhr.send(fd);
    });
}());
</script>';

        return $formHtml . $progressHtml;
    }

    protected function buildArchive($langId, $shopScope, $productFilter)
    {
        @set_time_limit(0);

        $langId = (int) $langId;
        if ($langId <= 0 || !Language::getLanguage($langId)) {
            $langId = (int) $this->context->language->id;
        }

        $shopScope = $shopScope === 'all' ? 'all' : 'current';
        $productFilter = $productFilter === 'active' ? 'active' : 'all';

        $tempDir = _PS_CACHE_DIR_ . 'internautenimage_' . uniqid('', true) . DIRECTORY_SEPARATOR;
        if (!@mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
            throw new Exception($this->l('Temporary directory could not be created.'));
        }

        $productIds = $this->getProductIdsForScope($shopScope, $productFilter);
        $contextShopId = (int) $this->context->shop->id;
        $shopIdForProduct = $shopScope === 'current' ? $contextShopId : null;

        $copiedFiles = 0;

        foreach ($productIds as $productId) {
            $product = new Product((int) $productId, false, $langId, $shopIdForProduct);

            if (!Validate::isLoadedObject($product)) {
                continue;
            }

            $baseReference = !empty($product->reference)
                ? (string) $product->reference
                : ('product_' . (int) $product->id);

            $safeReference = $this->sanitizeReference($baseReference);
            $images = $product->getImages($langId);

            if (empty($images)) {
                continue;
            }

            $position = 0;
            foreach ($images as $img) {
                $image = new Image((int) $img['id_image']);
                $sourcePath = _PS_PROD_IMG_DIR_ . $image->getExistingImgPath() . '.jpg';

                if (!is_file($sourcePath)) {
                    continue;
                }

                $suffix = $position === 0 ? '' : '_' . $position;
                $filename = $safeReference . $suffix . '.jpg';
                $targetPath = $this->uniquePath($tempDir . $filename);

                if (@copy($sourcePath, $targetPath)) {
                    $copiedFiles++;
                    $position++;
                }
            }
        }

        if ($copiedFiles === 0) {
            $this->removeDirectory($tempDir);
            throw new Exception($this->l('No product images found.'));
        }

        $zipPath = _PS_CACHE_DIR_ . 'internauten_produktbilder_export_' . date('Ymd_His') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->removeDirectory($tempDir);
            throw new Exception($this->l('ZIP file could not be created.'));
        }

        $files = scandir($tempDir);
        if ($files !== false) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $path = $tempDir . $file;
                if (is_file($path)) {
                    $zip->addFile($path, $file);
                }
            }
        }

        $zip->close();
        $this->removeDirectory($tempDir);

        if (!is_file($zipPath)) {
            throw new Exception($this->l('ZIP file was not generated.'));
        }

        return $zipPath;
    }

    protected function getProductIdsForScope($shopScope, $productFilter)
    {
        $ids = [];
        $db = Db::getInstance();
        $onlyActive = $productFilter === 'active';

        if ($shopScope === 'current' && Shop::isFeatureActive()) {
            $shopId = (int) $this->context->shop->id;
            $rows = $db->executeS(
                'SELECT DISTINCT ps.id_product FROM ' . _DB_PREFIX_ . 'product_shop ps '
                . 'WHERE ps.id_shop = ' . $shopId
                . ($onlyActive ? ' AND ps.active = 1' : '')
                . ' ORDER BY ps.id_product ASC'
            );
        } elseif ($shopScope === 'all' && Shop::isFeatureActive()) {
            $rows = $db->executeS(
                'SELECT DISTINCT ps.id_product FROM ' . _DB_PREFIX_ . 'product_shop ps '
                . ($onlyActive ? 'WHERE ps.active = 1 ' : '')
                . 'ORDER BY ps.id_product ASC'
            );
        } else {
            $rows = $db->executeS(
                'SELECT p.id_product FROM ' . _DB_PREFIX_ . 'product p ORDER BY p.id_product ASC'
            );
        }

        if ($rows === false) {
            return $ids;
        }

        foreach ($rows as $row) {
            if (isset($row['id_product'])) {
                $ids[] = (int) $row['id_product'];
            }
        }

        return $ids;
    }

    protected function importArchive($shopScope, $productFilter, $progressKey = '')
    {
        if (!isset($_FILES['INTERN_AUTENIMAGE_IMPORT_ZIP'])) {
            throw new Exception($this->l('No ZIP file provided.'));
        }

        $uploaded = $_FILES['INTERN_AUTENIMAGE_IMPORT_ZIP'];
        if ((int) $uploaded['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->l('File upload failed.'));
        }

        $originalName = (string) $uploaded['name'];
        if (strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
            throw new Exception($this->l('Please upload a ZIP file.'));
        }

        $tmpZipPath = _PS_CACHE_DIR_ . 'internautenimage_import_' . uniqid('', true) . '.zip';
        if (!@move_uploaded_file((string) $uploaded['tmp_name'], $tmpZipPath)) {
            throw new Exception($this->l('Uploaded file could not be processed.'));
        }

        $extractDir = _PS_CACHE_DIR_ . 'internautenimage_extract_' . uniqid('', true) . DIRECTORY_SEPARATOR;
        if (!@mkdir($extractDir, 0775, true) && !is_dir($extractDir)) {
            @unlink($tmpZipPath);
            throw new Exception($this->l('Temporary extraction directory could not be created.'));
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZipPath) !== true) {
            @unlink($tmpZipPath);
            $this->removeDirectory($extractDir);
            throw new Exception($this->l('ZIP file could not be opened.'));
        }

        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            @unlink($tmpZipPath);
            $this->removeDirectory($extractDir);
            throw new Exception($this->l('ZIP file could not be extracted.'));
        }
        $zip->close();
        @unlink($tmpZipPath);

        $shopScope = $shopScope === 'all' ? 'all' : 'current';
        $productFilter = $productFilter === 'active' ? 'active' : 'all';

        $files = $this->collectImageFiles($extractDir);
        if (empty($files)) {
            $this->removeDirectory($extractDir);
            throw new Exception($this->l('No image files found in the ZIP.'));
        }

        $totalFiles = count($files);
        $processedFiles = 0;
        $this->updateProgress($progressKey, 0, $totalFiles);

        $grouped = [];
        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $info = $this->parseReferenceFromFilename($filename);

            $reference = $this->resolveReferenceForImport($info['baseReference'], $info['index'], $shopScope, $productFilter);
            if ($reference === null || $reference === '') {
                continue;
            }

            if (!isset($grouped[$reference])) {
                $grouped[$reference] = [];
            }

            $grouped[$reference][] = [
                'path' => $filePath,
                'index' => (int) $info['index'],
            ];
        }

        $imported = 0;
        $skipped = 0;

        foreach ($grouped as $reference => $items) {
            $productId = $this->findProductIdByReference($reference, $shopScope, $productFilter);
            if ($productId <= 0) {
                $skipped += count($items);
                $processedFiles += count($items);
                $this->updateProgress($progressKey, $processedFiles, $totalFiles);
                continue;
            }

            usort($items, function ($a, $b) {
                if ((int) $a['index'] === (int) $b['index']) {
                    return strcmp((string) $a['path'], (string) $b['path']);
                }
                return ((int) $a['index'] < (int) $b['index']) ? -1 : 1;
            });

            $nextPosition = $this->getNextImagePosition($productId);
            $shopIdForProduct = ($shopScope === 'current') ? (int) $this->context->shop->id : null;
            $product = new Product((int) $productId, false, null, $shopIdForProduct);
            if (!Validate::isLoadedObject($product)) {
                $skipped += count($items);
                $processedFiles += count($items);
                $this->updateProgress($progressKey, $processedFiles, $totalFiles);
                continue;
            }

            foreach ($items as $item) {
                if ($this->importSingleProductImage($product, (string) $item['path'], $nextPosition)) {
                    $imported++;
                    $nextPosition++;
                } else {
                    $skipped++;
                }
                $processedFiles++;
                $this->updateProgress($progressKey, $processedFiles, $totalFiles);
            }
        }

        $knownCount = 0;
        foreach ($grouped as $groupItems) {
            $knownCount += count($groupItems);
        }
        $skipped += max(0, $totalFiles - $knownCount);

        $this->updateProgress($progressKey, $totalFiles, $totalFiles);
        $this->removeDirectory($extractDir);

        return [
            'imported' => (int) $imported,
            'skipped' => (int) $skipped,
        ];
    }

    protected function collectImageFiles($directory)
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $result = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $ext = strtolower((string) pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                continue;
            }

            $result[] = (string) $fileInfo->getPathname();
        }

        return $result;
    }

    protected function parseReferenceFromFilename($filename)
    {
        $base = (string) pathinfo((string) $filename, PATHINFO_FILENAME);
        $index = 0;
        $baseReference = $base;

        if (preg_match('/^(.*)_([0-9]+)$/', $base, $matches)) {
            $baseReference = (string) $matches[1];
            $index = (int) $matches[2];
        }

        return [
            'baseReference' => $baseReference,
            'index' => $index,
        ];
    }

    protected function resolveReferenceForImport($baseReference, $index, $shopScope, $productFilter)
    {
        $baseReference = trim((string) $baseReference);
        if ($baseReference === '') {
            return null;
        }

        if ($this->findProductIdByReference($baseReference, $shopScope, $productFilter) > 0) {
            return $baseReference;
        }

        if ((int) $index > 0) {
            $original = $baseReference . '_' . (int) $index;
            if ($this->findProductIdByReference($original, $shopScope, $productFilter) > 0) {
                return $original;
            }
        }

        return null;
    }

    protected function findProductIdByReference($reference, $shopScope, $productFilter)
    {
        $reference = pSQL((string) trim((string) $reference));
        if ($reference === '') {
            return 0;
        }

        $db = Db::getInstance();
        $shopScope = $shopScope === 'all' ? 'all' : 'current';
        $productFilter = $productFilter === 'active' ? 'active' : 'all';
        $onlyActive = $productFilter === 'active';

        $query = new DbQuery();
        $query->select('p.id_product');
        $query->from('product', 'p');
        $query->where("p.reference = '" . $reference . "'");

        if (Shop::isFeatureActive()) {
            $query->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product');

            if ($shopScope === 'current') {
                $shopId = (int) $this->context->shop->id;
                $query->where('ps.id_shop = ' . $shopId);
            }

            if ($onlyActive) {
                $query->where('ps.active = 1');
            }
        }

        $query->orderBy('p.id_product ASC');
        $query->limit(1);

        $rows = $db->executeS($query);
        if ($rows === false || empty($rows) || !isset($rows[0]['id_product'])) {
            return 0;
        }

        $id = (int) $rows[0]['id_product'];
        return $id > 0 ? $id : 0;
    }

    protected function getNextImagePosition($productId)
    {
        $sql = 'SELECT MAX(i.position) FROM ' . _DB_PREFIX_ . 'image i WHERE i.id_product = ' . (int) $productId;
        $maxValue = Db::getInstance()->getValue($sql);
        if ($maxValue === false || $maxValue === null || $maxValue === '') {
            return 0;
        }

        return ((int) $maxValue) + 1;
    }

    protected function importSingleProductImage(Product $product, $sourcePath, $position)
    {
        if (!is_file($sourcePath)) {
            return false;
        }

        $isFirstForProduct = ((int) $this->getNextImagePosition((int) $product->id) === 0);

        $image = new Image();
        $image->id_product = (int) $product->id;
        $image->position = (int) $position;
        $image->cover = $isFirstForProduct ? 1 : 0;
        $image->legend = $this->buildImageLegendFromProduct($product);

        if (!$image->add()) {
            return false;
        }

        $targetPath = $image->getPathForCreation() . '.jpg';
        if (!ImageManager::resize($sourcePath, $targetPath)) {
            $image->delete();
            return false;
        }

        $types = ImageType::getImagesTypes('products');
        foreach ($types as $type) {
            $thumbPath = $image->getPathForCreation() . '-' . stripslashes((string) $type['name']) . '.jpg';
            ImageManager::resize(
                $targetPath,
                $thumbPath,
                (int) $type['width'],
                (int) $type['height']
            );
        }

        Hook::exec('actionWatermark', ['id_image' => (int) $image->id, 'id_product' => (int) $product->id]);

        return true;
    }

    protected function buildImageLegendFromProduct(Product $product)
    {
        $languages = Language::getLanguages(false);
        $legend = [];

        foreach ($languages as $language) {
            $langId = (int) $language['id_lang'];
            $name = '';

            if (isset($product->name[$langId]) && (string) $product->name[$langId] !== '') {
                $name = (string) $product->name[$langId];
            } elseif (isset($product->name[$this->context->language->id])) {
                $name = (string) $product->name[$this->context->language->id];
            }

            $legend[$langId] = $name;
        }

        return $legend;
    }

    protected function sendArchive($zipPath)
    {
        $downloadName = 'internauten_product_images_' . date('Ymd_His') . '.zip';
        $size = (string) filesize($zipPath);

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Content-Length: ' . $size);
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        readfile($zipPath);
        @unlink($zipPath);
    }

    protected function sanitizeReference($reference)
    {
        $reference = trim((string) $reference);
        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '_', $reference);
        $sanitized = preg_replace('/_+/', '_', (string) $sanitized);

        if ($sanitized === null || $sanitized === '') {
            return 'product';
        }

        return $sanitized;
    }

    protected function uniquePath($path)
    {
        if (!file_exists($path)) {
            return $path;
        }

        $dir = dirname($path);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $name = pathinfo($path, PATHINFO_FILENAME);
        $counter = 1;

        do {
            $candidate = $dir . DIRECTORY_SEPARATOR . $name . '__dup' . $counter . ($ext !== '' ? '.' . $ext : '');
            $counter++;
        } while (file_exists($candidate));

        return $candidate;
    }

    protected function updateProgress($progressKey, $current, $total)
    {
        if ($progressKey === '') {
            return;
        }
        $progressFile = _PS_CACHE_DIR_ . 'internautenimage_progress_' . $progressKey . '.json';
        file_put_contents($progressFile, json_encode(['current' => (int) $current, 'total' => (int) $total]));
    }

    protected function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files !== false) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($path)) {
                    $this->removeDirectory($path);
                } else {
                    @unlink($path);
                }
            }
        }

        @rmdir($dir);
    }
}
