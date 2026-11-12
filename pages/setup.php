<?php
include '../../../include/db.php';
include '../../../include/authenticate.php';
require_once dirname(__FILE__, 2) . '/include/functions.php';

// Admins only
if (!checkperm('a')) {
    exit($lang['error-permissiondenied']);
}

$self        = $_SERVER['PHP_SELF'];
$plugins_url = $baseurl_short . 'pages/team/team_plugins.php';
$plugins_dir = plugin_manager_plugins_dir();
$user        = (int) ($userref ?? 0);
$message     = '';
$error       = '';
$pending     = null;  // upload waiting for "Overwrite / Cancel"
$token       = '';
$action      = (string) ($_POST['action'] ?? '');

plugin_manager_pending_cleanup();
plugin_manager_cleanup_workdirs();

// Result of an activate/deactivate (shown after the redirect)
$done        = (string) ($_GET['done'] ?? '');
$done_plugin = (string) ($_GET['plugin'] ?? '');
if (in_array($done, array('activated', 'deactivated'), true) && plugin_manager_valid_name($done_plugin)) {
    $message = 'Plugin "' . $done_plugin . '" ' . $done . '.';
}

// ---- Handle form actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $error = 'The upload is larger than the server allows (post_max_size = ' . ini_get('post_max_size') . ').';
} elseif ($action === 'upload') {
    $f = $_FILES['plugin_zip'] ?? null;
    if (!is_array($f) || !isset($f['error']) || is_array($f['error'])) {
        $error = 'Choose a .zip file to upload.';
    } elseif ((int) $f['error'] !== UPLOAD_ERR_OK) {
        $error = plugin_manager_upload_error($f['error']);
    } elseif (!preg_match('/\.zip\z/i', (string) $f['name']) || !is_uploaded_file($f['tmp_name'])) {
        $error = 'Only .zip archives can be uploaded.';
    } else {
        $name   = '';
        $info   = array();
        $result = plugin_manager_install($f['tmp_name'], false, $name, $error, null, $info);
        if ($result === 'ok') {
            $message = 'Plugin "' . $name . '" installed. Activate it in the list below.';
        } elseif ($result === 'exists') {
            $token = plugin_manager_pending_save($f['tmp_name'], $name, $info, $user, (string) $f['name']);
            if ($token === '') {
                $error = 'Could not keep the uploaded file for the overwrite decision (temporary folder not writable).';
            } else {
                $pending = plugin_manager_pending_get($token, $user);
            }
        }
    }
} elseif ($action === 'overwrite' || $action === 'cancel') {
    $token = (string) ($_POST['token'] ?? '');
    $p     = plugin_manager_pending_get($token, $user);
    if ($p === null) {
        $error = 'The upload has expired or was already handled. Upload the file again.';
    } elseif ($action === 'cancel') {
        $message = 'Upload cancelled. Plugin "' . $p['name'] . '" was not changed.';
    } else {
        $name   = '';
        $result = plugin_manager_install($p['zip'], true, $name, $error, $p['name']);
        if ($result === 'ok') {
            $message = 'Plugin "' . $name . '" overwritten with the uploaded version.';
        }
    }
    plugin_manager_pending_drop($token);
    $token = '';
} elseif ($action === 'activate' || $action === 'deactivate') {
    // The page is reloaded afterwards, so the header and the list use the new plugin status
    $name = (string) ($_POST['plugin'] ?? '');
    if (plugin_manager_valid_name($name) && plugin_manager_toggle($name, $action === 'activate', $error)) {
        redirect($self . '?done=' . $action . 'd&plugin=' . rawurlencode($name));
        exit;
    }
    $error = $error !== '' ? $error : 'Select a plugin.';
} elseif ($action === 'delete') {
    $name    = (string) ($_POST['delete_name'] ?? '');
    $confirm = trim((string) ($_POST['delete_confirm'] ?? ''));
    if (!plugin_manager_valid_name($name)) {
        $error = 'Select a plugin to delete.';
    } elseif ($confirm !== $name && strtolower($confirm) !== 'delete') {
        $error = 'Not deleted: type the plugin name "' . $name . '" or the word delete to confirm.';
    } elseif (plugin_manager_delete($name, $error)) {
        $message = 'Plugin "' . $name . '" deleted.' . ($error !== '' ? ' ' . $error : '');
        $error   = '';
    }
} elseif ($action === 'reset') {
    if (trim((string) ($_POST['reset_confirm'] ?? '')) !== 'RESET') {
        $error = 'Not reset: type RESET (capital letters) to confirm.';
    } elseif (plugin_manager_reset()) {
        $message = 'Plugin Manager was reset: uploads waiting for a decision and leftover work folders were deleted. Installed plugins were not changed.';
    } else {
        $error = 'Some files could not be deleted (check permissions of ' . plugin_manager_base_dir() . ').';
    }
}

// ---- Prepare the page ----
$list      = plugin_manager_list();
$active    = plugin_manager_active();
// Until 1.12 this plugin was called "pluginadmin" (since 1.12: renamed, the old folder is a separate plugin)
if (isset($list['pluginadmin']) && plugin_manager_self() !== 'pluginadmin') {
    $message = trim($message . ' The old version of this plugin ("pluginadmin", renamed to "' . plugin_manager_self()
        . '" in 1.12) is still installed - deactivate and delete it in the list below.');
}
$writable  = is_writable($plugins_dir);
$has_zip   = class_exists('ZipArchive');
// Gear icon (inline SVG, does not depend on an icon font)
$gear      = '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58a.49.49 0 0 0 .12-.61l-1.92-3.32a.49.49 0 0 0-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54a.48.48 0 0 0-.48-.41h-3.84a.47.47 0 0 0-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96a.48.48 0 0 0-.59.22L2.74 8.87a.47.47 0 0 0 .12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58a.49.49 0 0 0-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32a.48.48 0 0 0-.12-.61l-2.01-1.58zM12 15.6A3.6 3.6 0 1 1 12 8.4a3.6 3.6 0 0 1 0 7.2z"/></svg>';
$deletable = array();
foreach ($list as $n => $p) {
    if (plugin_manager_delete_blocker($n, $active) === '') {
        $deletable[] = $n;
    }
}
$current = $pending !== null && isset($list[$pending['name']]) ? $list[$pending['name']] : null;
$nonce   = isset($CSP_nonce) ? ' nonce="' . htmlspecialchars($CSP_nonce) . '"' : '';
$h       = function ($s) { return htmlspecialchars((string) $s); };

include '../../../include/header.php';
?>
<div class="BasicsBox pa-page">
    <h1>Plugin Manager</h1>
    <?php
    renderBreadcrumbs([
        ['title' => $lang['systemsetup'],   'href' => $baseurl_short . 'pages/admin/admin_home.php', 'menu' => true],
        ['title' => $lang['pluginmanager'], 'href' => $plugins_url],
        ['title' => 'Plugin Manager'],
    ]);
    ?>

    <?php if ($message) { ?><div class="PageInformal"><?php echo $h($message); ?></div><?php } ?>
    <?php if ($error) { ?><div class="FormError"><?php echo $h($error); ?></div><?php } ?>
    <?php if (!$writable) { ?>
        <div class="FormError">The web server cannot write to <code><?php echo $h($plugins_dir); ?></code>. Uploading and deleting plugins will not work until it has write access.</div>
    <?php } ?>
    <?php if (!$has_zip) { ?>
        <div class="FormError">The PHP zip extension is missing (install <code>php-zip</code> and restart PHP-FPM). Uploading is disabled.</div>
    <?php } ?>

    <style<?php echo $nonce; ?>>
        .pa-box { margin: 0 0 16px; padding: 10px 14px; border: 1px solid rgba(128, 128, 128, 0.45); border-radius: 6px; }
        .pa-box h3 { margin: 0 0 8px; font-size: 1.05em; }
        .pa-box p { margin: 0.5em 0; }
        .pa-warn { border-color: #b26a00; }
        .pa-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0.8em 0; }
        .pa-field { display: block; margin: 0.6em 0; }
        .pa-field > span { display: block; margin-bottom: 3px; }
        .pa-field select, .pa-field input[type=text] { width: 360px; max-width: 100%; box-sizing: border-box; }
        .pa-table { border-collapse: collapse; width: 100%; }
        .pa-table th, .pa-table td { text-align: left; padding: 5px 8px; border-bottom: 1px solid rgba(128, 128, 128, 0.3); vertical-align: top; }
        .pa-muted { opacity: 0.7; }
        .pa-on { color: #2e7d32; font-weight: bold; }
        .pa-warn-text { color: #b26a00; font-size: 0.9em; }
        .pa-info { white-space: nowrap; }
        .pa-tip { position: relative; display: inline-block; cursor: help; width: 1.4em; height: 1.4em; line-height: 1.4em; text-align: center;
                  border-radius: 50%; border: 1px solid rgba(128, 128, 128, 0.6); font-weight: bold; font-style: normal; }
        .pa-tip-text { visibility: hidden; opacity: 0; transition: opacity 0.15s; position: absolute; z-index: 1000; left: 50%; bottom: calc(100% + 8px);
                       transform: translateX(-50%); width: 320px; max-width: 70vw; padding: 8px 10px; border-radius: 6px; text-align: left;
                       font-weight: normal; line-height: 1.4; white-space: normal; background: #263238; color: #fff; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); }
        .pa-tip-text::after { content: ""; position: absolute; top: 100%; left: 50%; margin-left: -6px; border: 6px solid transparent; border-top-color: #263238; }
        .pa-tip:hover .pa-tip-text, .pa-tip:focus .pa-tip-text { visibility: visible; opacity: 1; }
        .pa-tip-text b { display: block; margin-bottom: 4px; }
        .pa-tip-text span { display: block; opacity: 0.8; font-size: 0.9em; margin-top: 4px; }
        .pa-doc { margin-left: 6px; }
        .pa-cfg { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 4px; text-decoration: none; color: inherit; }
        .pa-cfg svg { display: block; width: 18px; height: 18px; fill: currentColor; }
        a.pa-cfg:hover, a.pa-cfg:focus { background: rgba(128, 128, 128, 0.2); }
        .pa-cfg-off { opacity: 0.3; cursor: not-allowed; }
        .pa-toggle { width: 8.5em; box-sizing: border-box; text-align: center; }
    </style>

    <?php if ($pending !== null) { ?>
        <!-- Plugin already exists: overwrite or cancel -->
        <div class="pa-box pa-warn">
            <h3>Plugin "<?php echo $h($pending['name']); ?>" already exists</h3>
            <table class="pa-table">
                <tr><th></th><th>Title</th><th>Version</th></tr>
                <tr><td>Installed</td><td><?php echo $h($current['title'] ?? ''); ?></td><td><?php echo $h($current['version'] ?? ''); ?></td></tr>
                <tr><td>Uploaded (<?php echo $h($pending['file']); ?>)</td><td><?php echo $h($pending['info']['title'] ?? ''); ?></td><td><?php echo $h($pending['info']['version'] ?? ''); ?></td></tr>
            </table>
            <p>Overwriting replaces the whole folder <code><?php echo $h($current['path'] ?? $plugins_dir . '/' . $pending['name']); ?></code> with the content of the archive (files that are not in the archive are removed).
                <?php if (isset($active[$pending['name']])) { ?><b>The plugin is active</b> - the new files are used immediately.<?php } ?></p>
            <form method="post" action="<?php echo $h($self); ?>" class="pa-row">
                <?php generateFormToken('plugin_manager'); ?>
                <input type="hidden" name="token" value="<?php echo $h($token); ?>">
                <button type="submit" name="action" value="overwrite">Overwrite</button>
                <button type="submit" name="action" value="cancel">Cancel</button>
            </form>
        </div>
    <?php } ?>

    <!-- Upload -->
    <div class="pa-box">
        <h3>Upload a plugin</h3>
        <p>Upload a <b>.zip</b> archive that contains the plugin folder (<code>&lt;name&gt;/&lt;name&gt;.yaml</code> ...) or the plugin files directly (<code>&lt;name&gt;.yaml</code> in the root of the archive). It is unpacked to <code><?php echo $h($plugins_dir); ?>/&lt;name&gt;</code>.
            The plugin name (the name of the .yaml file) may contain only lowercase letters, numbers and "_".
            If the plugin already exists (also in filestore/plugins) you can choose to overwrite it in its current folder or cancel.</p>
        <form method="post" action="<?php echo $h($self); ?>" enctype="multipart/form-data">
            <?php generateFormToken('plugin_manager'); ?>
            <div class="pa-row">
                <input type="file" name="plugin_zip" accept=".zip,application/zip" required>
                <button type="submit" name="action" value="upload"<?php echo ($writable && $has_zip) ? '' : ' disabled'; ?>>Upload</button>
            </div>
        </form>
        <p class="pa-muted">Server limits: upload_max_filesize = <?php echo $h(ini_get('upload_max_filesize')); ?>, post_max_size = <?php echo $h(ini_get('post_max_size')); ?> (nginx: client_max_body_size).</p>
    </div>

    <!-- Installed plugins -->
    <div class="pa-box">
        <h3>Installed plugins (<?php echo count($list); ?>)</h3>
        <form method="post" action="<?php echo $h($self); ?>" id="pa-toggle-form">
            <?php generateFormToken('plugin_manager'); ?>
            <input type="hidden" name="plugin" id="pa-toggle-plugin" value="">
            <table class="pa-table">
                <tr><th>Folder</th><th>Title</th><th>Version</th><th>Info</th><th>Status</th><th>Options</th><th></th></tr>
                <?php foreach ($list as $n => $p) {
                    $is_active = isset($active[$n]);
                    $blocker   = plugin_manager_toggle_blocker($n, !$is_active, $active, $list);
                    $doc       = plugin_manager_doc_url($p);
                    $cfg       = plugin_manager_config_url($p, $baseurl);
                    $extra     = array_filter(array(
                        $p['author'] !== '' ? 'Author: ' . $p['author'] : '',
                        $p['category'] !== '' ? 'Category: ' . $p['category'] : '',
                    )); ?>
                    <tr>
                        <td>
                            <code><?php echo $h($n); ?></code><?php echo $p['is_link'] ? ' <span class="pa-muted">(link)</span>' : ''; ?>
                            <?php if ($p['location'] === 'filestore') { ?><span class="pa-muted" title="<?php echo $h($p['path']); ?>">(filestore)</span><?php } ?>
                            <?php if ($p['shadowed'] !== '') { ?><br><span class="pa-warn-text" title="<?php echo $h($p['shadowed']); ?>">Duplicate name: a second copy in <?php echo $h(dirname($p['shadowed'])); ?> is ignored by ResourceSpace.</span><?php } ?>
                        </td>
                        <td><?php echo $p['has_yaml'] ? $h($p['title']) : '<i class="pa-muted">no ' . $h($n) . '.yaml</i>'; ?></td>
                        <td><?php echo $h($p['version']); ?></td>
                        <td class="pa-info">
                            <?php if ($p['desc'] !== '' || $extra) { ?>
                                <i class="pa-tip" tabindex="0" aria-label="Plugin information">i<span class="pa-tip-text" role="tooltip">
                                    <b><?php echo $h($p['title'] !== '' ? $p['title'] : $n); ?></b>
                                    <?php echo $p['desc'] !== '' ? $h($p['desc']) : '<i>No description.</i>'; ?>
                                    <?php foreach ($extra as $x) { ?><span><?php echo $h($x); ?></span><?php } ?>
                                </span></i>
                            <?php } ?>
                            <?php if ($doc !== '') { ?>
                                <a class="pa-doc" href="<?php echo $h($doc); ?>" target="_blank" rel="noopener noreferrer">Doc</a>
                            <?php } ?>
                        </td>
                        <td><?php
                            if (($active[$n] ?? '') === 'config') {
                                echo '<span class="pa-on">active</span> <span class="pa-muted">(config.php)</span>';
                            } else {
                                echo $is_active ? '<span class="pa-on">active</span>' : '<span class="pa-muted">inactive</span>';
                            } ?></td>
                        <td>
                            <?php if ($cfg !== '' && $is_active) { ?>
                                <a class="pa-cfg" href="<?php echo $h($cfg); ?>" title="Options of <?php echo $h($n); ?>" aria-label="Options of <?php echo $h($n); ?>"><?php echo $gear; ?></a>
                            <?php } elseif ($cfg !== '') { ?>
                                <span class="pa-cfg pa-cfg-off" title="Activate the plugin to open its options"><?php echo $gear; ?></span>
                            <?php } else { ?>
                                <span class="pa-cfg pa-cfg-off" title="This plugin has no options page"><?php echo $gear; ?></span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($blocker === '') { ?>
                                <button type="submit" name="action" value="<?php echo $is_active ? 'deactivate' : 'activate'; ?>" class="pa-toggle" data-name="<?php echo $h($n); ?>"><?php echo $is_active ? 'Deactivate' : 'Activate'; ?></button>
                            <?php } else { ?>
                                <span class="pa-muted" title="<?php echo $h($blocker); ?>">-</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </form>
    </div>

    <!-- Delete -->
    <div class="pa-box" id="pa-delete">
        <h3>Delete a plugin</h3>
        <p>Removes the plugin folder (from <code><?php echo implode('</code> or <code>', array_map($h, plugin_manager_plugin_dirs())); ?></code>). <b>This cannot be undone.</b> Active plugins must be deactivated first (Deactivate button in the list above).</p>
        <?php if ($deletable) { ?>
            <form method="post" action="<?php echo $h($self); ?>" id="pa-delete-form">
                <?php generateFormToken('plugin_manager'); ?>
                <label class="pa-field">
                    <span>Plugin</span>
                    <select name="delete_name" id="pa-delete-name">
                        <option value="">- select -</option>
                        <?php foreach ($deletable as $n) { ?>
                            <option value="<?php echo $h($n); ?>"><?php echo $h($n); ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label class="pa-field">
                    <span>To confirm, type the plugin name or the word <b>delete</b></span>
                    <input type="text" name="delete_confirm" id="pa-delete-confirm" autocomplete="off" spellcheck="false">
                </label>
                <div class="pa-row">
                    <button type="submit" name="action" value="delete" id="pa-delete-btn"<?php echo $writable ? '' : ' disabled'; ?>>Delete plugin</button>
                </div>
            </form>
        <?php } else { ?>
            <p><i>No plugins can be deleted (all plugins are active).</i></p>
        <?php } ?>
    </div>

    <!-- Reset -->
    <div class="pa-box">
        <h3>Reset plugin</h3>
        <p>Deletes everything Plugin Manager stored: uploads waiting for "Overwrite / Cancel" (<code><?php echo $h(plugin_manager_base_dir()); ?></code>)
            and work folders left behind by interrupted installs or deletes. <b>Installed plugins are not changed.</b></p>
        <form method="post" action="<?php echo $h($self); ?>" id="pa-reset">
            <?php generateFormToken('plugin_manager'); ?>
            <div class="pa-row">
                <label>To confirm, type <b>RESET</b> <input type="text" name="reset_confirm" autocomplete="off" spellcheck="false"></label>
                <button type="submit" name="action" value="reset">Reset plugin</button>
            </div>
        </form>
    </div>

    <script<?php echo $nonce; ?>>
    // Activate / Deactivate buttons: send the plugin name of the clicked row
    (function () {
        var field = document.getElementById('pa-toggle-plugin');
        [].forEach.call(document.querySelectorAll('.pa-toggle'), function (b) {
            b.addEventListener('click', function (e) {
                var name = b.getAttribute('data-name');
                if (b.value === 'deactivate' && !confirm('Deactivate the plugin "' + name + '"?')) {
                    e.preventDefault();
                    return;
                }
                field.value = name;
            });
        });
    })();

    // Reset: confirmation
    (function () {
        var f = document.getElementById('pa-reset');
        if (!f) { return; }
        f.addEventListener('submit', function (e) {
            if (f.reset_confirm.value.trim() !== 'RESET') { alert('Type RESET to confirm.'); e.preventDefault(); return; }
            if (!confirm('Delete the uploads waiting for a decision and leftover work folders? Installed plugins are not changed.')) { e.preventDefault(); }
        });
    })();

    // Delete: the button is enabled only after typing the plugin name or "delete"
    (function () {
        var sel = document.getElementById('pa-delete-name');
        var inp = document.getElementById('pa-delete-confirm');
        var btn = document.getElementById('pa-delete-btn');
        var writable = <?php echo $writable ? 'true' : 'false'; ?>;
        if (!sel || !inp || !btn) { return; }

        function confirmed() {
            var v = inp.value.trim();
            return sel.value !== '' && (v === sel.value || v.toLowerCase() === 'delete');
        }
        function update() { btn.disabled = !writable || !confirmed(); }

        sel.addEventListener('change', update);
        inp.addEventListener('input', update);
        update();

        document.getElementById('pa-delete-form').addEventListener('submit', function (e) {
            if (!confirmed() || !confirm('Delete the plugin folder "' + sel.value + '"? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    })();
    </script>
</div>
<?php
include '../../../include/footer.php';
