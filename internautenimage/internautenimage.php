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
        $this->version = '1.0.0';
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
                    $importMode = (string) Tools::getValue('INTERN_AUTENIMAGE_IMPORT_MODE', 'add');
                    $result = $this->importArchive($shopScope, $productFilter, $progressKey, $importMode);
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
                $importMode = (string) Tools::getValue('INTERN_AUTENIMAGE_IMPORT_MODE', 'add');
                $result = $this->importArchive($shopScope, $productFilter, '', $importMode);

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

        $shopScopeForInfo = (string) Tools::getValue('INTERN_AUTENIMAGE_SHOP_SCOPE', 'current');
        $productFilterForInfo = (string) Tools::getValue('INTERN_AUTENIMAGE_PRODUCT_FILTER', 'all');
        $shopScopeForInfo = $shopScopeForInfo === 'all' ? 'all' : 'current';
        $productFilterForInfo = $productFilterForInfo === 'active' ? 'active' : 'all';

        $shopScopeLabel = $shopScopeForInfo === 'all'
            ? $this->l('All shops')
            : $this->l('Current shop only');
        $productFilterLabel = $productFilterForInfo === 'active'
            ? $this->l('Active products only')
            : $this->l('All products');

        $productsWithoutImageCount = $this->getProductsWithoutImageCount($shopScopeForInfo, $productFilterForInfo);
        $html .= $this->displayInformation(
            sprintf(
                $this->l('Products without images: %1$d (Shop scope: %2$s, Product filter: %3$s)'),
                (int) $productsWithoutImageCount,
                (string) $shopScopeLabel,
                (string) $productFilterLabel
            )
        );

        return $html
            . $this->renderScopeSelectionForm($shopScopeForInfo, $productFilterForInfo)
            . $this->renderExportForm()
            . $this->renderImportForm();
    }

    protected function renderScopeSelectionForm($shopScope, $productFilter)
    {
        $shopScope = $shopScope === 'all' ? 'all' : 'current';
        $productFilter = $productFilter === 'active' ? 'active' : 'all';

        $actionUrl = $this->context->link->getAdminLink('AdminModules', true, [], [
            'configure' => $this->name,
        ]);

        $html = '';
        $html .= '<div class="panel">';
        $html .= '<div class="panel-heading"><i class="icon-filter"></i> ' . $this->l('Scope preview') . '</div>';
        $html .= '<div class="panel-body">';
        $html .= '<p>' . $this->l('Change shop scope and product filter without starting export/import.') . '</p>';
        $html .= '<form method="post" action="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="row">';
        $html .= '<div class="col-lg-4">';
        $html .= '<label class="control-label" for="ii-scope-select">' . $this->l('Shop scope') . '</label>';
        $html .= '<select id="ii-scope-select" name="INTERN_AUTENIMAGE_SHOP_SCOPE" class="form-control">';
        $html .= '<option value="current"' . ($shopScope === 'current' ? ' selected="selected"' : '') . '>' . $this->l('Current shop only') . '</option>';
        $html .= '<option value="all"' . ($shopScope === 'all' ? ' selected="selected"' : '') . '>' . $this->l('All shops') . '</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-lg-4">';
        $html .= '<label class="control-label" for="ii-filter-select">' . $this->l('Product filter') . '</label>';
        $html .= '<select id="ii-filter-select" name="INTERN_AUTENIMAGE_PRODUCT_FILTER" class="form-control">';
        $html .= '<option value="all"' . ($productFilter === 'all' ? ' selected="selected"' : '') . '>' . $this->l('All products') . '</option>';
        $html .= '<option value="active"' . ($productFilter === 'active' ? ' selected="selected"' : '') . '>' . $this->l('Active products only') . '</option>';
        $html .= '</select>';
        $html .= '</div>';
        $html .= '<div class="col-lg-4" style="padding-top:25px;">';
        $html .= '<button type="submit" class="btn btn-default"><i class="icon-refresh"></i> ' . $this->l('Apply selection') . '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
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

        $importModeOptions = [
            [
                'id_option' => 'add',
                'name' => $this->l('Add new images additionally'),
            ],
            [
                'id_option' => 'skip',
                'name' => $this->l('Skip products with existing images'),
            ],
            [
                'id_option' => 'replace',
                'name' => $this->l('Replace existing images'),
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
                        'type' => 'select',
                        'label' => $this->l('Import mode'),
                        'name' => 'INTERN_AUTENIMAGE_IMPORT_MODE',
                        'required' => true,
                        'options' => [
                            'query' => $importModeOptions,
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                        'desc' => $this->l('Choose how to handle products that already have images.'),
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
            'INTERN_AUTENIMAGE_IMPORT_MODE' => (string) Tools::getValue('INTERN_AUTENIMAGE_IMPORT_MODE', 'add'),
        ];

        $formHtml = $helper->generateForm([$fieldsForm]);
        $ajaxUrl = json_encode(
            AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules')
        );

        $lProgress    = $this->l('Import progress');
        $lUploading   = json_encode($this->l('Uploading...'));
        $lUploadingPct = json_encode($this->l('Uploading') . ' ');
        $lProcessing  = json_encode($this->l('Processing...'));
        $lImporting   = json_encode($this->l('Importing'));
        $lDone        = json_encode($this->l('Import completed.'));
        $lImported    = json_encode($this->l('images imported,'));
        $lSkipped     = json_encode($this->l('files skipped.'));
        $lFailed      = json_encode($this->l('Import failed:'));
        $lUnknown     = json_encode($this->l('An unknown error occurred.'));
        $lServerErr   = json_encode($this->l('Server error during import.'));
        $lNetworkErr  = json_encode($this->l('Network error during import.'));
        $lTimeoutErr  = json_encode($this->l('Upload timed out. Please try a smaller ZIP or increase server limits.'));
        $lDebugPrefix = json_encode($this->l('Debug')); 
        $lTooLargeClient = json_encode(
            sprintf(
                $this->l('ZIP is too large for server limits (upload_max_filesize=%s, post_max_size=%s).'),
                (string) ini_get('upload_max_filesize'),
                (string) ini_get('post_max_size')
            )
        );
        $uploadMaxBytes = (int) $this->iniSizeToBytes((string) ini_get('upload_max_filesize'));
        $postMaxBytes = (int) $this->iniSizeToBytes((string) ini_get('post_max_size'));
        $clientMaxBytes = 0;
        if ($uploadMaxBytes > 0 && $postMaxBytes > 0) {
            $clientMaxBytes = min($uploadMaxBytes, $postMaxBytes);
        } elseif ($uploadMaxBytes > 0) {
            $clientMaxBytes = $uploadMaxBytes;
        } elseif ($postMaxBytes > 0) {
            $clientMaxBytes = $postMaxBytes;
        }
        $clientMaxBytesJs = (int) $clientMaxBytes;

        $progressHtml = '
<div id="internautenimage-progress-wrap" style="display:none;margin-top:15px;" class="panel">
    <div class="panel-heading"><i id="internautenimage-progress-icon" class="icon-spinner"></i> ' . $lProgress . '</div>
    <div class="panel-body">
        <div class="progress">
            <div id="internautenimage-progress-bar"
                 class="progress-bar progress-bar-striped active"
                 role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                 style="width:2%;min-width:2em;transition:width 0.4s ease;">0%</div>
        </div>
        <p id="internautenimage-progress-text" style="margin-top:8px;"></p>
        <div id="internautenimage-result" style="display:none;margin-top:10px;"></div>
        <pre id="internautenimage-debug" style="display:none;margin-top:10px;max-height:180px;overflow:auto;white-space:pre-wrap;background:#f7f7f9;border:1px solid #ddd;padding:8px;"></pre>
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
    var dbg  = document.getElementById(\'internautenimage-debug\');
    var icon = document.getElementById(\'internautenimage-progress-icon\');
    var uploadPhaseDone = false;
    var clientMaxBytes = ' . $clientMaxBytesJs . ';

    var setSpinnerActive = function (active, success) {
        if (!icon) { return; }
        icon.className = active ? \'icon-spinner icon-spin\' : (success ? \'icon-check\' : \'icon-remove\');
    };

    var setDebug = function (label, xhr) {
        if (!dbg) { return; }
        var status = (xhr && typeof xhr.status !== \'undefined\') ? xhr.status : \'n/a\';
        var body = (xhr && typeof xhr.responseText === \'string\') ? xhr.responseText : \'\';
        if (body.length > 1200) {
            body = body.slice(0, 1200) + \'...\';
        }
        dbg.style.display = \'block\';
        dbg.textContent = ' . $lDebugPrefix . ' + \' [\' + label + \'] status=\' + status + (body ? (\'\\n\' + body) : \'\');
    };

    form.addEventListener(\'submit\', function (e) {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }

        var fileInput = form.querySelector(\'input[name="INTERN_AUTENIMAGE_IMPORT_ZIP"]\');
        var selectedFile = (fileInput && fileInput.files && fileInput.files.length) ? fileInput.files[0] : null;
        if (selectedFile && clientMaxBytes > 0 && selectedFile.size > clientMaxBytes) {
            wrap.style.display = \'block\';
            setSpinnerActive(false, false);
            bar.className      = \'progress-bar progress-bar-danger\';
            bar.style.width    = \'100%\';
            bar.setAttribute(\'aria-valuenow\', 100);
            bar.textContent    = \'100%\';
            txt.style.display  = \'none\';
            res.className      = \'alert alert-danger\';
            res.textContent    = ' . $lTooLargeClient . ';
            res.style.display  = \'block\';
            return;
        }

        var key = Math.random().toString(36).slice(2) + Date.now().toString(36);
        var fd  = new FormData(form);
        fd.append(\'progress_key\', key);

        wrap.style.display = \'block\';
        setSpinnerActive(true, false);
        res.style.display  = \'none\';
        res.textContent    = \'\';
        if (dbg) {
            dbg.style.display = \'none\';
            dbg.textContent = \'\';
        }
        bar.className      = \'progress-bar progress-bar-striped active\';
        bar.style.width    = \'2%\';
        bar.textContent    = \'0%\';
        txt.style.display  = \'block\';
        txt.textContent    = ' . $lUploading . ';
        btn.disabled = true;

        var pollProgress = function () {
            var x = new XMLHttpRequest();
            x.open(\'GET\', ajaxUrl + \'&internautenimage_ajax=1&internautenimage_action=get_progress&progress_key=\' + encodeURIComponent(key) + \'&_ts=\' + Date.now(), true);
            x.setRequestHeader(\'Cache-Control\', \'no-cache\');
            x.setRequestHeader(\'Pragma\', \'no-cache\');
            x.onload = function () {
                if (x.status >= 400) {
                    setDebug(\'progress\', x);
                }
                try {
                    var d = JSON.parse(x.responseText);
                    if (d.total > 0) {
                        var p = Math.min(99, Math.round(d.current / d.total * 100));
                        bar.style.width = p + \'%\';
                        bar.setAttribute(\'aria-valuenow\', p);
                        bar.textContent = p + \'%\';
                        txt.textContent = ' . $lImporting . ' + \' \' + d.current + \' / \' + d.total;
                    } else if (uploadPhaseDone) {
                        txt.textContent = ' . $lProcessing . ';
                    }
                } catch (ex) {}
            };
            x.onerror = function () {
                setDebug(\'progress-network-error\', x);
            };
            x.send();
        };
        pollProgress();
        var poll = setInterval(pollProgress, 700);

        var xhr = new XMLHttpRequest();
        xhr.open(\'POST\', ajaxUrl + \'&internautenimage_ajax=1&internautenimage_action=import&progress_key=\' + encodeURIComponent(key), true);
        xhr.timeout = 0;

        xhr.upload.onprogress = function (e) {
            if (!e.lengthComputable || e.total === 0) { return; }
            var p = Math.max(2, Math.round(e.loaded / e.total * 10));
            if (e.loaded < e.total) {
                bar.style.width = p + \'%\';
                bar.textContent = p + \'%\';
                txt.textContent = ' . $lUploadingPct . ' + Math.round((e.loaded / e.total) * 100) + \'%\';
            } else {
                uploadPhaseDone = true;
                txt.textContent = ' . $lProcessing . ';
            }
        };

        xhr.upload.onloadend = function () {
            uploadPhaseDone = true;
            txt.textContent = ' . $lProcessing . ';
        };

        xhr.onload = function () {
            clearInterval(poll);
            setDebug(\'import\', xhr);
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
            setSpinnerActive(false, ok);
            txt.style.display = \'none\';
            res.style.display = \'block\';
            btn.disabled = false;
        };

        xhr.onerror = function () {
            clearInterval(poll);
            setDebug(\'import-network-error\', xhr);
            bar.classList.remove(\'active\');
            bar.classList.add(\'progress-bar-danger\');
            setSpinnerActive(false, false);
            res.className   = \'alert alert-danger\';
            res.textContent = ' . $lNetworkErr . ';
            txt.style.display = \'none\';
            res.style.display = \'block\';
            btn.disabled = false;
        };

        xhr.ontimeout = function () {
            clearInterval(poll);
            setDebug(\'import-timeout\', xhr);
            bar.classList.remove(\'active\');
            bar.classList.add(\'progress-bar-danger\');
            setSpinnerActive(false, false);
            res.className   = \'alert alert-danger\';
            res.textContent = ' . $lTimeoutErr . ';
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

    protected function getProductsWithoutImageCount($shopScope, $productFilter)
    {
        $shopScope = $shopScope === 'all' ? 'all' : 'current';
        $productFilter = $productFilter === 'active' ? 'active' : 'all';
        $onlyActive = $productFilter === 'active';

        $query = new DbQuery();
        $query->select('COUNT(DISTINCT p.id_product)');
        $query->from('product', 'p');
        $query->leftJoin('image', 'i', 'i.id_product = p.id_product');
        $query->where('i.id_image IS NULL');

        if (Shop::isFeatureActive()) {
            $query->innerJoin('product_shop', 'ps', 'ps.id_product = p.id_product');

            if ($shopScope === 'current') {
                $query->where('ps.id_shop = ' . (int) $this->context->shop->id);
            }

            if ($onlyActive) {
                $query->where('ps.active = 1');
            }
        }

        $value = Db::getInstance()->getValue($query);
        if ($value === false || $value === null || $value === '') {
            return 0;
        }

        return (int) $value;
    }

    protected function importArchive($shopScope, $productFilter, $progressKey = '', $importMode = 'add')
    {
        @set_time_limit(0);
        @ignore_user_abort(true);

        $importMode = in_array($importMode, ['replace', 'skip', 'add'], true) ? $importMode : 'add';
        if (!isset($_FILES['INTERN_AUTENIMAGE_IMPORT_ZIP'])) {
            if ($this->isPostTooLarge()) {
                throw new Exception($this->getUploadLimitMessage());
            }
            throw new Exception($this->l('No ZIP file provided.'));
        }

        $uploaded = $_FILES['INTERN_AUTENIMAGE_IMPORT_ZIP'];
        if ((int) $uploaded['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage((int) $uploaded['error']));
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
        $progressTotalUnits = max(1, $totalFiles * 2);
        $preparedFiles = 0;
        $processedFiles = 0;
        $this->updateProgress($progressKey, 0, $progressTotalUnits);

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

            $preparedFiles++;
            if (($preparedFiles % 5) === 0 || $preparedFiles === $totalFiles) {
                $this->updateProgress($progressKey, $preparedFiles, $progressTotalUnits);
            }
        }

        $imported = 0;
        $skipped = 0;

        foreach ($grouped as $reference => $items) {
            $productId = $this->findProductIdByReference($reference, $shopScope, $productFilter);
            if ($productId <= 0) {
                $skipped += count($items);
                $processedFiles += count($items);
                $this->updateProgress($progressKey, min($progressTotalUnits, $totalFiles + $processedFiles), $progressTotalUnits);
                continue;
            }

            $hasExistingImages = ($this->getNextImagePosition($productId) > 0);

            if ($importMode === 'skip' && $hasExistingImages) {
                $skipped += count($items);
                $processedFiles += count($items);
                $this->updateProgress($progressKey, min($progressTotalUnits, $totalFiles + $processedFiles), $progressTotalUnits);
                continue;
            }

            if ($importMode === 'replace' && $hasExistingImages) {
                $this->deleteProductImages($productId);
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
                $this->updateProgress($progressKey, min($progressTotalUnits, $totalFiles + $processedFiles), $progressTotalUnits);
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
                if (($processedFiles % 3) === 0 || $processedFiles >= $totalFiles) {
                    $this->updateProgress($progressKey, min($progressTotalUnits, $totalFiles + $processedFiles), $progressTotalUnits);
                }
            }
        }

        $knownCount = 0;
        foreach ($grouped as $groupItems) {
            $knownCount += count($groupItems);
        }
        $skipped += max(0, $totalFiles - $knownCount);

        $this->updateProgress($progressKey, $progressTotalUnits, $progressTotalUnits);
        $this->removeDirectory($extractDir);

        return [
            'imported' => (int) $imported,
            'skipped' => (int) $skipped,
        ];
    }

    protected function deleteProductImages($productId)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT id_image FROM ' . _DB_PREFIX_ . 'image WHERE id_product = ' . (int) $productId
        );
        if ($rows === false) {
            return;
        }
        foreach ($rows as $row) {
            $image = new Image((int) $row['id_image']);
            $image->delete();
        }
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

    protected function isPostTooLarge()
    {
        if (!isset($_SERVER['CONTENT_LENGTH'])) {
            return false;
        }

        $contentLength = (int) $_SERVER['CONTENT_LENGTH'];
        $postMaxSize = $this->iniSizeToBytes((string) ini_get('post_max_size'));

        if ($postMaxSize <= 0) {
            return false;
        }

        return $contentLength > $postMaxSize;
    }

    protected function iniSizeToBytes($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        $last = strtolower(substr($value, -1));
        $number = (float) $value;

        switch ($last) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
                break;
            default:
                break;
        }

        return (int) round($number);
    }

    protected function getUploadLimitMessage()
    {
        return sprintf(
            $this->l('Uploaded ZIP is too large. Server limits: upload_max_filesize=%s, post_max_size=%s.'),
            (string) ini_get('upload_max_filesize'),
            (string) ini_get('post_max_size')
        );
    }

    protected function getUploadErrorMessage($errorCode)
    {
        switch ((int) $errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return $this->getUploadLimitMessage();
            case UPLOAD_ERR_PARTIAL:
                return $this->l('File upload was interrupted. Please try again.');
            case UPLOAD_ERR_NO_FILE:
                return $this->l('No ZIP file provided.');
            case UPLOAD_ERR_NO_TMP_DIR:
                return $this->l('Upload failed: temporary directory is missing on the server.');
            case UPLOAD_ERR_CANT_WRITE:
                return $this->l('Upload failed: server cannot write the uploaded file.');
            case UPLOAD_ERR_EXTENSION:
                return $this->l('Upload was blocked by a PHP extension.');
            default:
                return $this->l('File upload failed.');
        }
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
