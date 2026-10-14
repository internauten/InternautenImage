<?php
// Usage: php check-translations.php
// Lists $this->l() strings of the module that are missing in translations/en.php and de.php.
// Exit code 1 if anything is missing.

$root = dirname(__DIR__, 4);
$moduleFile = $root . '/internautenimage/internautenimage.php';
$translationDir = $root . '/internautenimage/translations';
$prefix = '<{internautenimage}prestashop>internautenimage_';

// Same key derivation as PrestaShop's Translate::getModuleTranslation().
function translationKey($string)
{
    return md5(preg_replace('/\\\\*\'/', '\\\'', $string));
}

function phpQuote($string)
{
    return "'" . addcslashes($string, "'\\") . "'";
}

function loadTranslations($file)
{
    $GLOBALS['_MODULE'] = [];
    include $file;

    return isset($GLOBALS['_MODULE']) && is_array($GLOBALS['_MODULE']) ? $GLOBALS['_MODULE'] : [];
}

$tokens = token_get_all((string) file_get_contents($moduleFile));
$strings = [];
$count = count($tokens);

for ($i = 0; $i < $count; $i++) {
    if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_VARIABLE || $tokens[$i][1] !== '$this') {
        continue;
    }
    $j = $i + 1;
    if (!isset($tokens[$j], $tokens[$j + 1], $tokens[$j + 2])
        || !is_array($tokens[$j]) || $tokens[$j][0] !== T_OBJECT_OPERATOR
        || !is_array($tokens[$j + 1]) || $tokens[$j + 1][1] !== 'l'
        || $tokens[$j + 2] !== '(') {
        continue;
    }
    $k = $j + 3;
    while (isset($tokens[$k]) && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
        $k++;
    }
    $line = is_array($tokens[$i]) ? $tokens[$i][2] : 0;
    if (!isset($tokens[$k]) || !is_array($tokens[$k]) || $tokens[$k][0] !== T_CONSTANT_ENCAPSED_STRING) {
        fwrite(STDERR, "WARN line $line: \$this->l() without a string literal (not translatable)\n");
        continue;
    }
    $literal = $tokens[$k][1];
    if ($literal[0] !== "'") {
        fwrite(STDERR, "WARN line $line: use single quotes in \$this->l(): $literal\n");
        continue;
    }
    $value = strtr(substr($literal, 1, -1), ['\\\\' => '\\', "\\'" => "'"]);
    $strings[translationKey($value)] = $value;
}

$missingTotal = 0;
foreach (['en', 'de'] as $iso) {
    $file = $translationDir . '/' . $iso . '.php';
    $existing = loadTranslations($file);
    $missing = [];
    foreach ($strings as $key => $value) {
        if (!isset($existing[$prefix . $key])) {
            $missing[$key] = $value;
        }
    }
    $obsolete = 0;
    foreach (array_keys($existing) as $existingKey) {
        if (!isset($strings[substr($existingKey, strlen($prefix))])) {
            $obsolete++;
        }
    }

    echo "== $iso.php: " . count($missing) . " missing, $obsolete unused (left as is)\n";
    foreach ($missing as $key => $value) {
        $marker = $iso === 'en' ? '' : '  // TODO translate';
        echo '$_MODULE[' . phpQuote($prefix . $key) . '] = ' . phpQuote($value) . ";$marker\n";
    }
    $missingTotal += count($missing);
}

exit($missingTotal > 0 ? 1 : 0);
