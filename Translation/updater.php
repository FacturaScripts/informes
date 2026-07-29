<?php
if (php_sapi_name() !== "cli") {
    die("Please use command line: php updater.php");
}

// scan json files
chdir(__DIR__);
$files = [];
$langs = 'ca_ES,cs_CZ,de_DE,en_EN,es_AR,es_CL,es_CO,es_CR,es_DO,es_EC,es_ES,es_GT,es_MX,es_PA,es_PE,es_UY,eu_ES,fr_FR,gl_ES,it_IT,pl_PL,pt_BR,pt_PT,tr_TR,va_ES';
foreach (explode(',', $langs) as $lang) {
    $files[] = $lang . '.json';
}
foreach (scandir(__DIR__, SCANDIR_SORT_ASCENDING) as $filename) {
    if (is_file($filename) && substr($filename, -5) === '.json' && false === in_array($filename, $files)) {
        $files[] = $filename;
    }
}

// download json from facturascripts.com
$errors = 0;
$context = stream_context_create([
    'http' => [
        'timeout' => 30,
    ],
]);
foreach ($files as $filename) {
    $url = "https://facturascripts.com/EditLanguage?action=json&idproject=175&code=" . substr($filename, 0, -5);
    $newContent = @file_get_contents($url, false, $context);
    if (false === $newContent) {
        fwrite(STDERR, "Error downloading " . $filename . ". Keeping local file.\n");
        $errors++;
        continue;
    }

    if (strlen(trim($newContent)) <= 2) {
        fwrite(STDERR, "Empty response for " . $filename . ". Keeping local file.\n");
        $errors++;
        continue;
    }

    json_decode($newContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        fwrite(STDERR, "Invalid JSON for " . $filename . ". Keeping local file.\n");
        $errors++;
        continue;
    }

    $oldContent = file_exists($filename) ? file_get_contents($filename) : '';
    if ($newContent === $oldContent) {
        echo "Skip " . $filename . "\n";
        continue;
    }

    if (false === file_put_contents($filename, $newContent, LOCK_EX)) {
        fwrite(STDERR, "Error writing " . $filename . ".\n");
        $errors++;
        continue;
    }

    echo "Download " . $filename . "\n";
}

exit($errors > 0 ? 1 : 0);
