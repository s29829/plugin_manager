<?php
// Helper functions for the Plugin Manager page.
// Layout on disk:
//   <ResourceSpace root>/plugins/<name>/<name>.yaml          an installed plugin (new uploads go here)
//   <filestore>/plugins/<name>/<name>.yaml                    plugins can also live here ($storagedir)
//   <plugins folder>/.plugin_manager_*                           short-lived work folders (install / delete)
//   <filestore>/plugin_manager/data_<key>/pending/<token>.zip + .json   uploads waiting for "Overwrite / Cancel"

const PLUGIN_MANAGER_MAX_ENTRIES = 20000;           // files + folders in one archive
const PLUGIN_MANAGER_MAX_BYTES   = 524288000;       // 500 MB uncompressed (zip bomb protection)
const PLUGIN_MANAGER_PENDING_TTL = 3600;            // seconds an upload waits for "Overwrite / Cancel"

// Name of this plugin (its folder name)
function plugin_manager_self()
{
    return basename(dirname(__FILE__, 2));
}

// Main plugins folder (<ResourceSpace root>/plugins); uploads are installed here
function plugin_manager_plugins_dir()
{
    return dirname(__FILE__, 3);
}

// All folders ResourceSpace loads plugins from, in its lookup order: plugins/ first, then filestore/plugins
function plugin_manager_plugin_dirs()
{
    $dirs  = array('plugins' => plugin_manager_plugins_dir());
    $store = (string) ($GLOBALS['storagedir'] ?? '');
    if ($store !== '' && is_dir($store . '/plugins')) {
        $real = realpath($store . '/plugins');
        if ($real !== false && $real !== realpath($dirs['plugins'])) {
            $dirs['filestore'] = rtrim($store, '/') . '/plugins';
        }
    }
    return $dirs;
}

// Names of existing plugin folders: letters, numbers, "_" and "-"
function plugin_manager_valid_name($name)
{
    return is_string($name) && preg_match('/^[A-Za-z0-9_-]{1,64}\z/', $name) === 1;
}

// Names allowed for NEW uploads: lowercase letters, numbers and "_" only, because the name
// becomes part of the hook function names (HookPluginnamePagenameHookname)
function plugin_manager_upload_name_ok($name)
{
    return is_string($name) && preg_match('/^[a-z0-9_]{1,64}\z/', $name) === 1;
}

// Path of an installed plugin (the first match, like ResourceSpace), '' when not installed
function plugin_manager_find($name)
{
    if (!plugin_manager_valid_name($name)) {
        return '';
    }
    foreach (plugin_manager_plugin_dirs() as $dir) {
        if (file_exists($dir . '/' . $name) || is_link($dir . '/' . $name)) {
            return $dir . '/' . $name;
        }
    }
    return '';
}

// Reads a few simple keys from a plugin YAML text
function plugin_manager_yaml_parse($text)
{
    $info = array('name' => '', 'version' => '', 'title' => '', 'desc' => '', 'author' => '', 'category' => '', 'info_url' => '', 'config_url' => '');
    if (preg_match_all('/^(name|version|title|desc|author|category|info_url|config_url)[ \t]*:[ \t]*(.*?)[ \t]*$/mi', (string) $text, $m, PREG_SET_ORDER)) {
        foreach ($m as $row) {
            $key = strtolower($row[1]);
            if ($info[$key] === '') {
                $info[$key] = trim($row[2], "\"' ");
            }
        }
    }
    return $info;
}

function plugin_manager_yaml_info($file)
{
    $text = (is_file($file) && is_readable($file)) ? (string) file_get_contents($file, false, null, 0, 65536) : '';
    return plugin_manager_yaml_parse($text);
}

// All plugins: name => info (title, version, ..., has_yaml, is_link, path, location, shadowed)
// When the same name exists in both folders, the one ResourceSpace uses is listed and the other
// one is reported in 'shadowed'.
function plugin_manager_list()
{
    $out = array();
    foreach (plugin_manager_plugin_dirs() as $location => $dir) {
        foreach ((is_dir($dir) && is_readable($dir) ? scandir($dir) : false) ?: array() as $n) {
            if ($n === '' || $n[0] === '.' || !plugin_manager_valid_name($n) || !is_dir($dir . '/' . $n)) {
                continue;
            }
            if (isset($out[$n])) {
                $out[$n]['shadowed'] = $dir . '/' . $n;
                continue;
            }
            $yaml    = $dir . '/' . $n . '/' . $n . '.yaml';
            $out[$n] = plugin_manager_yaml_info($yaml) + array(
                'has_yaml' => is_file($yaml),
                'is_link'  => is_link($dir . '/' . $n),
                'path'     => $dir . '/' . $n,
                'location' => $location,
                'shadowed' => '',
            );
        }
    }
    uksort($out, 'strnatcasecmp');
    return $out;
}

// Active plugins: name => 'db' (activated on the Plugins page) or 'config' (forced by $plugins in config.php)
function plugin_manager_active()
{
    $out = array();
    if (function_exists('ps_query')) {
        try {
            foreach (ps_query('SELECT name FROM plugins WHERE inst_version IS NOT NULL') as $row) {
                $out[(string) $row['name']] = 'db';
            }
        } catch (Throwable $e) {
            // table not available - fall back to $plugins only
        }
    }
    foreach ((array) ($GLOBALS['plugins'] ?? array()) as $p) {
        if (is_string($p) && !isset($out[$p])) {
            $out[$p] = 'config';
        }
    }
    return $out;
}

// Documentation link from the YAML (info_url), only http(s) URLs
function plugin_manager_doc_url(array $info)
{
    $url = (string) ($info['info_url'] ?? '');
    return preg_match('#^https?://[^\s"<>]+\z#i', $url) === 1 ? $url : '';
}

// Options page of the plugin (config_url from the YAML, relative to $baseurl), '' when none
function plugin_manager_config_url(array $info, $baseurl)
{
    $path = (string) ($info['config_url'] ?? '');
    if ($path === '') {
        return '';
    }
    $path = '/' . ltrim($path, '/');
    if (preg_match('#^/[A-Za-z0-9_./?=&%-]+\z#', $path) !== 1 || strpos($path, '..') !== false) {
        return '';
    }
    return rtrim((string) $baseurl, '/') . $path;
}

// ---------------------------------------------------------------------------
// Activate / deactivate (same functions as ResourceSpace's Plugins page)
// ---------------------------------------------------------------------------

function plugin_manager_load_plugin_functions()
{
    if (!function_exists('activate_plugin')) {
        $f = dirname(__FILE__, 4) . '/include/plugin_functions.php';
        if (is_readable($f)) {
            require_once $f;
        }
    }
    return function_exists('activate_plugin') && function_exists('deactivate_plugin');
}

// Reason why the plugin cannot be (de)activated here, '' when it can
function plugin_manager_toggle_blocker($name, $activate, array $active, array $list)
{
    if (!isset($list[$name])) {
        return 'Plugin not found.';
    }
    if ($name === plugin_manager_self()) {
        return 'Plugin Manager cannot change its own status (use System > Plugins).';
    }
    if ($activate) {
        if (isset($active[$name])) {
            return 'The plugin is already active.';
        }
        if (!$list[$name]['has_yaml']) {
            return 'The plugin has no ' . $name . '.yaml file.';
        }
        if (in_array($name, (array) ($GLOBALS['disabled_plugins'] ?? array()), true)) {
            return 'The plugin is blocked by $disabled_plugins in config.php.';
        }
    } else {
        if (!isset($active[$name])) {
            return 'The plugin is not active.';
        }
        if ($active[$name] === 'config') {
            return 'The plugin is enabled by $plugins in config.php and can only be deactivated there.';
        }
    }
    return '';
}

// Activates or deactivates a plugin. Returns true or false + $error.
function plugin_manager_toggle($name, $activate, &$error)
{
    $error = plugin_manager_toggle_blocker($name, $activate, plugin_manager_active(), plugin_manager_list());
    if ($error !== '') {
        return false;
    }
    if (!plugin_manager_load_plugin_functions()) {
        $error = 'The ResourceSpace plugin functions are not available.';
        return false;
    }
    if ($activate) {
        if (activate_plugin($name) === false) {
            $error = 'ResourceSpace could not activate the plugin "' . $name . '".';
            return false;
        }
    } else {
        deactivate_plugin($name);
    }
    if (function_exists('clear_query_cache')) {
        clear_query_cache('plugins');
    }
    $now_active = plugin_manager_active();
    $ok         = $activate ? isset($now_active[$name]) : !isset($now_active[$name]) || $now_active[$name] !== 'db';
    if (!$ok) {
        $error = 'The status of the plugin "' . $name . '" did not change.';
    }
    return $ok;
}

// Deletes a file, a symlink (not its target) or a folder with all its content
function plugin_manager_rrmdir($path)
{
    if (is_link($path) || is_file($path)) {
        return is_writable(dirname($path)) && unlink($path);
    }
    if (!is_dir($path)) {
        return !file_exists($path);
    }
    $entries = is_readable($path) ? scandir($path) : false;
    if ($entries === false) {
        return false;
    }
    $ok = true;
    foreach ($entries as $e) {
        if ($e !== '.' && $e !== '..') {
            $ok = plugin_manager_rrmdir($path . '/' . $e) && $ok;
        }
    }
    return $ok && is_writable(dirname($path)) && rmdir($path);
}

// Removes work folders left behind by an interrupted install/delete (older than 1 hour)
function plugin_manager_cleanup_workdirs()
{
    foreach (plugin_manager_plugin_dirs() as $dir) {
        foreach (glob($dir . '/.plugin_manager_*', GLOB_NOSORT) ?: array() as $p) {
            if (filemtime($p) < time() - 3600) {
                plugin_manager_rrmdir($p);
            }
        }
    }
}

// Work folder inside $dir (same file system, so rename() is a quick move)
function plugin_manager_workdir($kind, $dir)
{
    return $dir . '/.plugin_manager_' . $kind . '_' . bin2hex(random_bytes(6));
}

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------

// Reason why a plugin cannot be deleted, '' when it can
function plugin_manager_delete_blocker($name, array $active)
{
    $path = plugin_manager_find($name);
    if ($path === '' || !is_dir($path)) {
        return 'Plugin not found.';
    }
    if ($name === plugin_manager_self()) {
        return 'Plugin Manager cannot delete itself.';
    }
    if (isset($active[$name])) {
        return 'The plugin is active. Deactivate it first.';
    }
    return '';
}

// Deletes the plugin folder. The folder is first renamed, so a failed delete
// never leaves a half-deleted plugin that ResourceSpace would try to load.
function plugin_manager_delete($name, &$error)
{
    $error = plugin_manager_delete_blocker($name, plugin_manager_active());
    if ($error !== '') {
        return false;
    }
    $path   = plugin_manager_find($name);
    $parent = dirname($path);
    if (is_link($path)) {
        if (is_writable(dirname($path)) && unlink($path)) {
            return true;
        }
        $error = 'Could not remove the link ' . $path . ' (check permissions).';
        return false;
    }
    $trash = plugin_manager_workdir('del', $parent);
    if (!is_writable($parent) || !rename($path, $trash)) {
        $error = 'Could not delete ' . $path . ' (the web server needs write access to ' . $parent . ').';
        return false;
    }
    if (!plugin_manager_rrmdir($trash)) {
        $error = 'The plugin was removed, but some files could not be deleted from ' . $trash . '.';
    }
    return true;
}

// ---------------------------------------------------------------------------
// Upload / install
// ---------------------------------------------------------------------------

// Checks an archive and finds the plugin in it. Accepted layouts:
//   <name>/<name>.yaml ...   (one top folder; its name may differ, e.g. "<name>-main")
//   <name>.yaml ...          (plugin files directly in the archive root)
// Returns array(name, info, entries => [index => [rel, dir]]) or null + $error.
function plugin_manager_inspect_zip($zipfile, &$error)
{
    $error = '';
    if (!class_exists('ZipArchive')) {
        $error = 'The PHP zip extension is not installed on the server (e.g. apt install php-zip).';
        return null;
    }
    $zip = new ZipArchive();
    if ($zip->open($zipfile) !== true) {
        $error = 'The file is not a valid .zip archive.';
        return null;
    }
    $count = $zip->numFiles;
    if ($count > PLUGIN_MANAGER_MAX_ENTRIES) {
        $zip->close();
        $error = 'The archive contains too many files (' . $count . ').';
        return null;
    }

    $items = array();
    $total = 0;
    for ($i = 0; $i < $count; $i++) {
        $st  = $zip->statIndex($i);
        $raw = $st ? str_replace('\\', '/', (string) $st['name']) : '';
        if ($raw === '' || strpos($raw, "\0") !== false || $raw[0] === '/' || preg_match('#^[A-Za-z]:#', $raw)) {
            $zip->close();
            $error = 'The archive contains an unsafe path: ' . $raw;
            return null;
        }
        // Junk added by macOS / Windows / version control
        if (preg_match('#(^|/)(__MACOSX|\.git|\.svn)(/|$)#', $raw) || preg_match('#(^|/)(\.DS_Store|Thumbs\.db|desktop\.ini)$#i', $raw)) {
            continue;
        }
        $parts = array_values(array_filter(explode('/', $raw), 'strlen'));
        foreach ($parts as $p) {
            if ($p === '.' || $p === '..') {
                $zip->close();
                $error = 'The archive contains an unsafe path: ' . $raw;
                return null;
            }
        }
        if (!$parts) {
            continue;
        }
        // Symbolic links are refused (they could point outside the plugin folder)
        $opsys = $attr = 0;
        if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX
            && (($attr >> 16) & 0170000) === 0120000) {
            $zip->close();
            $error = 'The archive contains a symbolic link (not allowed): ' . $raw;
            return null;
        }
        $total += (int) $st['size'];
        if ($total > PLUGIN_MANAGER_MAX_BYTES) {
            $zip->close();
            $error = 'The unpacked archive would be larger than ' . round(PLUGIN_MANAGER_MAX_BYTES / 1048576) . ' MB.';
            return null;
        }
        $items[$i] = array('parts' => $parts, 'dir' => substr($raw, -1) === '/');
    }
    if (!$items) {
        $zip->close();
        $error = 'The archive is empty.';
        return null;
    }

    // One top folder and no files in the root -> the plugin is inside that folder
    $tops      = array();
    $root_file = false;
    foreach ($items as $it) {
        $tops[$it['parts'][0]] = true;
        if (count($it['parts']) === 1 && !$it['dir']) {
            $root_file = true;
        }
    }
    $strip = (count($tops) === 1 && !$root_file) ? 1 : 0;
    $top   = $strip ? (string) key($tops) : '';

    $entries = array();
    $yamls   = array(); // yaml base name => zip index
    foreach ($items as $i => $it) {
        $rel = array_slice($it['parts'], $strip);
        if (!$rel) {
            continue; // the top folder itself
        }
        $entries[$i] = array('rel' => implode('/', $rel), 'dir' => $it['dir']);
        if (count($rel) === 1 && !$it['dir'] && preg_match('/^(.+)\.yaml\z/i', $rel[0], $mm)) {
            $yamls[$mm[1]] = $i;
        }
    }

    if (!$yamls) {
        $zip->close();
        $error = 'No plugin definition found. The archive must contain <name>/<name>.yaml (or <name>.yaml in its root).';
        return null;
    }
    if (count($yamls) === 1) {
        $name = (string) key($yamls);
    } elseif ($top !== '' && isset($yamls[$top])) {
        $name = $top;
    } else {
        $zip->close();
        $error = 'The archive contains more than one .yaml file in the plugin folder (' . implode(', ', array_keys($yamls)) . '); cannot tell which plugin it is.';
        return null;
    }
    if (!plugin_manager_upload_name_ok($name)) {
        $zip->close();
        $error = 'Invalid plugin name "' . $name . '": use only lowercase letters, numbers and "_" (the name is used in hook function names).';
        return null;
    }
    $info = plugin_manager_yaml_parse((string) $zip->getFromIndex($yamls[$name]));
    $zip->close();

    return array('name' => $name, 'info' => $info, 'entries' => $entries);
}

// Unpacks the checked entries into $dest (which must not exist yet)
function plugin_manager_extract($zipfile, array $insp, $dest, &$error)
{
    $zip = new ZipArchive();
    if ($zip->open($zipfile) !== true) {
        $error = 'Cannot open the archive.';
        return false;
    }
    if (is_dir($dest) || !is_writable(dirname($dest)) || !mkdir($dest, 0755)) {
        $zip->close();
        $error = 'Cannot create a folder in ' . dirname($dest) . ' (the web server needs write access).';
        return false;
    }
    $written = 0;
    foreach ($insp['entries'] as $i => $e) {
        $target = $dest . '/' . $e['rel'];
        $folder = $e['dir'] ? $target : dirname($target);
        if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
            $zip->close();
            $error = 'Cannot create folder ' . $e['rel'];
            return false;
        }
        if ($e['dir']) {
            continue;
        }
        $in  = $zip->getStream((string) $zip->getNameIndex($i));
        $out = (is_dir(dirname($target)) && is_writable(dirname($target))) ? fopen($target, 'wb') : false;
        if (!$in || !$out) {
            if ($in) { fclose($in); }
            if ($out) { fclose($out); }
            $zip->close();
            $error = 'Cannot unpack ' . $e['rel'];
            return false;
        }
        $n = stream_copy_to_stream($in, $out, PLUGIN_MANAGER_MAX_BYTES - $written + 1);
        fclose($in);
        fclose($out);
        $written += (int) $n;
        if ($n === false || $written > PLUGIN_MANAGER_MAX_BYTES) {
            $zip->close();
            $error = $n === false ? 'Cannot unpack ' . $e['rel'] : 'The unpacked archive is too large.';
            return false;
        }
        chmod($target, 0644);
    }
    $zip->close();
    return true;
}

// Installs the plugin from a zip file.
// Returns 'ok', 'exists' (plugin exists and $overwrite is false) or 'error' (+ $error).
// A new plugin goes to plugins/. An existing plugin is replaced as a whole in the folder where
// it is installed (plugins/ or filestore/plugins), so no second copy with the same name is made;
// it is restored if anything fails.
function plugin_manager_install($zipfile, $overwrite, &$name, &$error, $expected_name = null, &$info = null)
{
    $error = '';
    $insp  = plugin_manager_inspect_zip($zipfile, $error);
    if ($insp === null) {
        return 'error';
    }
    $name = $insp['name'];
    $info = $insp['info'];
    if ($expected_name !== null && $name !== $expected_name) {
        $error = 'The archive no longer matches the plugin "' . $expected_name . '".';
        return 'error';
    }
    $found  = plugin_manager_find($name);
    $exists = $found !== '';
    $target = $exists ? $found : plugin_manager_plugins_dir() . '/' . $name;
    $dir    = dirname($target);
    if ($exists && !$overwrite) {
        return 'exists';
    }
    if (!is_writable($dir)) {
        $error = 'The web server cannot write to ' . $dir . '.';
        return 'error';
    }

    $stage = plugin_manager_workdir('new', $dir);
    if (!plugin_manager_extract($zipfile, $insp, $stage, $error)) {
        plugin_manager_rrmdir($stage);
        return 'error';
    }
    $backup = null;
    if ($exists) {
        $backup = plugin_manager_workdir('old', $dir);
        if (!is_writable($dir) || !rename($target, $backup)) {
            plugin_manager_rrmdir($stage);
            $error = 'Cannot replace ' . $target . ' (the web server needs write access to it).';
            return 'error';
        }
    }
    if (!rename($stage, $target)) {
        if ($backup !== null) {
            rename($backup, $target);
        }
        plugin_manager_rrmdir($stage);
        $error = 'Cannot move the unpacked plugin to ' . $target . '.';
        return 'error';
    }
    if ($backup !== null) {
        plugin_manager_rrmdir($backup);
    }
    return 'ok';
}

// ---------------------------------------------------------------------------
// Uploads waiting for "Overwrite / Cancel"
// ---------------------------------------------------------------------------

// $storagedir of ResourceSpace (default: <ResourceSpace root>/filestore)
function plugin_manager_storage_dir()
{
    $s = (string) ($GLOBALS['storagedir'] ?? '');
    return rtrim($s !== '' ? $s : dirname(__FILE__, 4) . '/filestore', '/');
}

// <filestore>/plugin_manager - everything the plugin stores (since 1.09; removed by "Reset plugin")
function plugin_manager_base_dir()
{
    return plugin_manager_storage_dir() . '/plugin_manager';
}

// Uploads waiting for "Overwrite / Cancel": <filestore>/plugin_manager/data_<key>/pending
// (before 1.09: <temp dir>/plugin_manager). The key comes from $scramble_key.
function plugin_manager_pending_dir()
{
    $key = (string) ($GLOBALS['scramble_key'] ?? '');
    $dir = plugin_manager_base_dir() . '/data' . ($key !== '' ? '_' . substr(md5($key . 'plugin_manager'), 0, 12) : '') . '/pending';
    if (!is_dir($dir)) {
        $parent = is_dir(plugin_manager_base_dir()) ? plugin_manager_base_dir() : plugin_manager_storage_dir();
        if (is_dir($parent) && is_writable($parent)) {
            mkdir($dir, 0700, true);
        }
    }
    return $dir;
}

function plugin_manager_pending_cleanup()
{
    foreach (glob(plugin_manager_pending_dir() . '/*') ?: array() as $f) {
        if (is_file($f) && filemtime($f) < time() - PLUGIN_MANAGER_PENDING_TTL && is_writable($f)) {
            unlink($f);
        }
    }
}

// Keeps the uploaded file until the user decides. Returns the token or ''.
function plugin_manager_pending_save($uploaded_tmp, $name, array $info, $user, $filename)
{
    $token = bin2hex(random_bytes(16));
    $dir   = plugin_manager_pending_dir();
    if (!is_dir($dir) || !is_writable($dir) || !is_uploaded_file($uploaded_tmp) || !move_uploaded_file($uploaded_tmp, $dir . '/' . $token . '.zip')) {
        return '';
    }
    $meta = array('name' => $name, 'info' => $info, 'user' => (int) $user, 'file' => (string) $filename);
    if (file_put_contents($dir . '/' . $token . '.json', json_encode($meta)) === false) {
        unlink($dir . '/' . $token . '.zip');
        return '';
    }
    return $token;
}

// Returns the pending upload (meta + 'zip' path) or null
function plugin_manager_pending_get($token, $user)
{
    if (!is_string($token) || preg_match('/^[a-f0-9]{32}\z/', $token) !== 1) {
        return null;
    }
    $dir  = plugin_manager_pending_dir();
    $file = $dir . '/' . $token . '.json';
    $meta = (is_file($file) && is_readable($file)) ? json_decode((string) file_get_contents($file), true) : null;
    if (!is_array($meta) || (int) ($meta['user'] ?? -1) !== (int) $user || !is_file($dir . '/' . $token . '.zip')) {
        return null;
    }
    $meta['zip'] = $dir . '/' . $token . '.zip';
    return $meta;
}

function plugin_manager_pending_drop($token)
{
    if (is_string($token) && preg_match('/^[a-f0-9]{32}\z/', $token) === 1) {
        $dir = plugin_manager_pending_dir();
        foreach (array('.zip', '.json') as $ext) {
            if (is_file($dir . '/' . $token . $ext)) {
                unlink($dir . '/' . $token . $ext);
            }
        }
    }
}

// "Reset plugin": deletes everything the plugin stored (<filestore>/plugin_manager: uploads waiting
// for a decision) and the work folders left behind by interrupted installs or deletes.
// Installed plugins are NOT changed. Returns true when everything could be deleted.
function plugin_manager_reset()
{
    $ok = plugin_manager_rrmdir(plugin_manager_base_dir());
    foreach (plugin_manager_plugin_dirs() as $dir) {
        foreach (glob($dir . '/.plugin_manager_*', GLOB_NOSORT) ?: array() as $p) {
            $ok = plugin_manager_rrmdir($p) && $ok;
        }
    }
    $old = rtrim((string) (function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir()), '/') . '/plugin_manager';
    if (is_dir($old)) {
        $ok = plugin_manager_rrmdir($old) && $ok; // folder of versions before 1.09
    }
    return $ok;
}

// Upload error code -> message
function plugin_manager_upload_error($code)
{
    switch ((int) $code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The file is too large (limit: ' . ini_get('upload_max_filesize') . ').';
        case UPLOAD_ERR_PARTIAL:
            return 'The file was only partly uploaded. Try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'Choose a .zip file to upload.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
            return 'The server cannot store the uploaded file (temporary folder).';
        default:
            return 'Upload failed (error ' . (int) $code . ').';
    }
}
