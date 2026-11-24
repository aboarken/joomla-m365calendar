<?php

/**
 * Builds the installable package: dist/pkg_m365calendar-<version>.zip
 * containing lib_m365graph.zip, com_m365calendar.zip and plg_task_m365calendarsync.zip.
 *
 * Usage: php build/build.php [--version=1.0.0]
 *
 * @copyright   (C) 2026 M365 Calendar contributors
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "The PHP zip extension is required.\n");
    exit(1);
}

$root  = dirname(__DIR__);
$src   = $root . '/src';
$dist  = $root . '/dist';
$stage = $dist . '/.stage';
$opts  = getopt('', ['version::']);

$manifest = simplexml_load_file($src . '/pkg/pkg_m365calendar.xml');
$version  = (string) ($opts['version'] ?? $manifest->version);

if (!preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/', $version)) {
    fwrite(STDERR, "Invalid version: $version\n");
    exit(1);
}

/**
 * Remove a directory tree.
 */
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

/**
 * Copy a directory tree.
 */
function rcopy(string $from, string $to): void
{
    @mkdir($to, 0755, true);

    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

    foreach ($items as $item) {
        $target = $to . '/' . substr($item->getPathname(), strlen($from) + 1);
        $item->isDir() ? @mkdir($target, 0755, true) : copy($item->getPathname(), $target);
    }
}

/**
 * Zip a directory (sorted entries, fixed timestamps for reproducible builds).
 */
function zipDir(string $dir, string $zipFile): void
{
    $files = [];
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($items as $item) {
        if ($item->isFile()) {
            $files[] = substr($item->getPathname(), strlen($dir) + 1);
        }
    }

    sort($files);

    $zip = new ZipArchive();

    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create $zipFile");
    }

    foreach ($files as $file) {
        $name = str_replace('\\', '/', $file);
        $zip->addFile($dir . '/' . $file, $name);
        $zip->setMtimeName($name, 1764547200);
    }

    $zip->close();
}

/**
 * Set <version> and <creationDate> in a manifest.
 */
function stampManifest(string $file, string $version): void
{
    $xml = file_get_contents($file);
    $xml = preg_replace('#<version>[^<]*</version>#', '<version>' . $version . '</version>', $xml, 1);
    file_put_contents($file, $xml);
}

/**
 * Very small CSS minifier (comments and whitespace).
 */
function minifyCss(string $css): string
{
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);

    return trim(str_replace(';}', '}', $css)) . "\n";
}

rrmdir($stage);
@mkdir($stage . '/packages', 0755, true);

// Lint PHP and XML before packaging.
$errors = 0;
$items  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));

foreach ($items as $item) {
    $path = $item->getPathname();

    if (str_ends_with($path, '.php')) {
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        $errors += $code !== 0 ? 1 : 0;
    } elseif (str_ends_with($path, '.xml') && @simplexml_load_file($path) === false) {
        fwrite(STDERR, "Invalid XML: $path\n");
        $errors++;
    } elseif (str_ends_with($path, '.ini') && @parse_ini_file($path) === false) {
        fwrite(STDERR, "Invalid INI: $path\n");
        $errors++;
    } elseif (str_ends_with($path, '.json') && json_decode((string) file_get_contents($path)) === null) {
        fwrite(STDERR, "Invalid JSON: $path\n");
        $errors++;
    }
}

if ($errors > 0) {
    fwrite(STDERR, "$errors file(s) failed linting.\n");
    exit(1);
}

$extensions = [
    'lib_m365graph'             => 'lib_m365graph.xml',
    'com_m365calendar'          => 'm365calendar.xml',
    'plg_task_m365calendarsync' => 'm365calendarsync.xml',
];

foreach ($extensions as $name => $manifestFile) {
    $dir = $stage . '/' . $name;
    rcopy($src . '/' . $name, $dir);
    copy($root . '/LICENSE', $dir . '/LICENSE.txt');
    stampManifest($dir . '/' . $manifestFile, $version);

    if ($name === 'com_m365calendar') {
        foreach (glob($dir . '/media/css/*.css') as $css) {
            if (!str_ends_with($css, '.min.css')) {
                file_put_contents(substr($css, 0, -4) . '.min.css', minifyCss((string) file_get_contents($css)));
            }
        }

        foreach (glob($dir . '/media/js/*.js') as $js) {
            if (!str_ends_with($js, '.min.js')) {
                copy($js, substr($js, 0, -3) . '.min.js');
            }
        }
    }

    zipDir($dir, $stage . '/packages/' . $name . '.zip');
    rrmdir($dir);
    echo "Built $name.zip\n";
}

$pkg = $stage . '/pkg';
@mkdir($pkg, 0755, true);
rcopy($src . '/pkg', $pkg);
rename($stage . '/packages', $pkg . '/packages');
copy($root . '/LICENSE', $pkg . '/LICENSE.txt');
stampManifest($pkg . '/pkg_m365calendar.xml', $version);

$out = $dist . '/pkg_m365calendar-' . $version . '.zip';
zipDir($pkg, $out);
rrmdir($stage);

echo "\nPackage: $out\n";
echo 'SHA-256: ' . hash_file('sha256', $out) . "\n";
echo 'SHA-384: ' . hash_file('sha384', $out) . "  (use in updates/pkg_m365calendar.xml <sha384>)\n";
