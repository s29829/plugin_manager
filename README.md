# Plugin Manager – documentation

**English** | [Polski](README.pl.md)

Documentation for plugin version **1.14** (ResourceSpace 11).
*(since X.YY)* marks the plugin version in which a feature first appeared. Features without
a mark have been available since 1.00. Change history (in Polish): [CHANGELOG.md](CHANGELOG.md).

The plugin lets you manage ResourceSpace plugins in the browser, without server access. You can
upload a plugin as a `.zip` archive (also to overwrite an existing one), activate or deactivate
it, open its options or delete its folder.

**Where:** System > Plugins > Plugin Manager > **Options**. Only administrators
(permission `a`) can open this page.


**Name** *(since 1.12)*: up to version 1.11 the plugin was called **Plugin Admin** (folder
`pluginadmin`). For ResourceSpace it is a different plugin: install `plugin_manager`, activate it,
then deactivate and delete `pluginadmin` in its list (the page reminds you while it is installed).
The old folder `<filestore>/pluginadmin` holds only uploads that were waiting for a decision and can
be deleted. Not to be confused with ResourceSpace's own page System > Plugins.

---

## Upload a plugin

The **Upload a plugin** box is below the list of installed plugins *(since 1.13; before: above it)*.

1. Choose a `.zip` file and click **Upload**.
2. The plugin is checked and unpacked to `<RS_ROOT>/plugins/<name>`.
3. Then activate it in the **Installed plugins** list.

Accepted archive layouts:

```
name/name.yaml, name/hooks/…      (one top folder; it may have another name, e.g. name-main)
name.yaml, hooks/…                (plugin files directly in the archive)
```

The plugin name comes from the name of the `.yaml` file. It may contain only **lowercase
letters, numbers and `_`**, because it is part of the hook function names *(since 1.03)*.

**If the plugin already exists**, the page compares the title and version of the installed and
the uploaded plugin and shows two buttons. Since 1.03 plugins in `filestore/plugins` are checked
as well.

- **Overwrite** – replaces the **whole** plugin folder with the content of the archive. Files
  that are not in the archive are removed. The plugin stays in its current folder (`plugins/`
  or `filestore/plugins`) *(since 1.03)*. If it is active, the new files are used immediately.
- **Cancel** – changes nothing.

The uploaded file waits for this decision for up to one hour.

Upload safeguards:

- an archive may contain at most 20,000 files and 500 MB once unpacked,
- paths that leave the plugin folder (`..`, absolute paths) and symbolic links are refused,
- system junk (`__MACOSX`, `.DS_Store`, `.git`, `Thumbs.db` …) is skipped,
- the plugin is first unpacked to a temporary folder and moved into place afterwards. When
  overwriting, the old version is restored if anything fails.

The file size limit depends on the server: `upload_max_filesize` and `post_max_size` in PHP and
`client_max_body_size` in nginx or `LimitRequestBody` in Apache (default 1 GB since Apache 2.4.53).
The current PHP values are shown below the form.

## Installed plugins

A table of all plugins in `plugins/` and `filestore/plugins` *(second folder: since 1.03)*:

| Column | Meaning |
|---|---|
| **Folder** | Folder name. Notes: "(link)" means a symbolic link; "(filestore)" means a plugin in `filestore/plugins` *(since 1.03)*; "Duplicate name" means the same name exists in both folders, and ResourceSpace uses the first one, in `plugins/` *(since 1.03)*. |
| **Title**, **Version** | From the plugin's YAML file. |
| **Info** *(since 1.01)* | "i" icon – tooltip with description, author and category. "Doc" links to the documentation (`info_url`). |
| **Status** | `active` / `inactive`. "(config.php)" means the plugin is enabled in `config.php`. |
| **Options** *(since 1.02)* | The gear icon opens the plugin's options page (only for active plugins that have one). |
| **Activate** *(since 1.01; column title since 1.13)* | **Activate** / **Deactivate** – the same functions as ResourceSpace's Plugins page. Deactivating asks for confirmation. **Deactivate** is an outline button *(since 1.13)*. |

The columns Version, Info, Status, Options and Activate are centred, and the gear icon is larger
*(since 1.13)*.

The status cannot be changed here when:

- it is Plugin Manager itself (use System > Plugins),
- the plugin is enabled by `$plugins` in `config.php` (it can only be disabled there),
- the plugin is blocked by `$disabled_plugins`,
- the plugin has no `<name>.yaml` file.

Hover over "-" to see the reason.

## Delete a plugin

1. Select the plugin. The list contains only inactive plugins, and never Plugin Manager itself.
2. Type its name or the word `delete`. Only then is the **Delete plugin** button enabled.
3. Confirm in the dialog.

The plugin folder is moved first and deleted afterwards, so a failed delete never leaves half
a plugin on the site. **A delete cannot be undone.** A symbolic link is removed without touching
its target. Since 1.03 plugins in `filestore/plugins` can be deleted as well.

## Rules of use

1. **Before overwriting an active plugin**, remember that the new files work immediately.
   Upload tested versions; if in doubt, deactivate the plugin first.
2. **The archive must contain the complete plugin.** Overwrite removes files that are not in
   the `.zip`.
3. **Plugins from this repository**: pack the folder `src/plugins/<name>` (the plugin folder
   itself in the archive). The version from `<name>.yaml` appears in the overwrite comparison.
4. **Deactivate a plugin before deleting it.** Deleting the folder does not remove tables or
   settings the plugin may have stored in the database.

## Installation and requirements

### Download and installation

The plugin is distributed as a `plugin_manager-<version>.zip` archive on the releases page:
<https://github.com/s29829/plugin_manager/releases>. The archive contains the folder `plugin_manager/` with the code,
license and documentation. Below, `<RS_ROOT>` is the ResourceSpace installation folder (e.g.
`/var/www/resourcespace`) and `www-data:www-data` is the user and group that run PHP – with Apache on RHEL, Rocky, Alma or
Fedora `apache:apache` (see "nginx or Apache" below).

Plugin Manager cannot install itself – do the first installation from the command line. It
needs the PHP zip extension and write access of the web server to `<RS_ROOT>/plugins` (see
"Requirements").

**In the browser:** Plugin Manager > Upload a plugin > choose the `.zip` file > Upload (when
updating: **Overwrite**).

**From the command line (CLI)** – download, unpack and set the permissions:

```bash
VER=<version>                    # version number from the releases page, e.g. 1.00
cd /tmp
curl -fLO https://github.com/s29829/plugin_manager/releases/download/v$VER/plugin_manager-$VER.zip
sudo rm -rf <RS_ROOT>/plugins/plugin_manager        # only when updating (the plugin data is in the filestore)
sudo unzip -q plugin_manager-$VER.zip -d <RS_ROOT>/plugins/
sudo chown -R www-data:www-data <RS_ROOT>/plugins/plugin_manager
sudo find <RS_ROOT>/plugins/plugin_manager -type d -exec chmod 755 {} \;
sudo find <RS_ROOT>/plugins/plugin_manager -type f -exec chmod 644 {} \;
```

**With git:** the repository <https://github.com/s29829/plugin_manager> contains the complete plugin folder:

```bash
sudo git clone https://github.com/s29829/plugin_manager <RS_ROOT>/plugins/plugin_manager
sudo chown -R www-data:www-data <RS_ROOT>/plugins/plugin_manager
```

Update: `sudo git -C <RS_ROOT>/plugins/plugin_manager pull`.

### nginx or Apache

The plugin works the same with both web servers. Only the user that runs PHP, the service names
and a few server settings differ – use the user and group from the table in the commands above:

| | nginx + PHP-FPM (Ubuntu / Debian) | Apache (Ubuntu / Debian) | Apache (RHEL, Rocky, Alma, Fedora) |
|---|---|---|---|
| User:group that runs PHP | `www-data:www-data` | `www-data:www-data` | `apache:apache` |
| Reload after an update | `sudo systemctl restart php8.3-fpm` | mod_php: `sudo systemctl reload apache2`; PHP-FPM: `sudo systemctl restart php8.3-fpm` | mod_php: `sudo systemctl reload httpd`; PHP-FPM: `sudo systemctl restart php-fpm` |

- **Who runs PHP:** with Apache and mod_php the Apache user; with PHP-FPM (nginx or Apache) the user
  of the PHP-FPM pool (`user =` / `group =` in `/etc/php/<version>/fpm/pool.d/www.conf`, on the RHEL
  family `/etc/php-fpm.d/www.conf`). Check: `ps -o user= -C php-fpm8.3,php-fpm,apache2,httpd | sort -u`.
- **Permissions:** folders `755`, files `644`, owner = that user and group (commands above). The plugin
  files only need to be readable; `<filestore>` must be writable for that user – exactly as
  ResourceSpace itself requires.
- **No directory listings (Apache):** Apache on Ubuntu / Debian allows listings under `/var/www`
  (`Options Indexes` in `apache2.conf`). The plugin keeps its settings in
  `<filestore>/<plugin>/data_<key>/`, and a listing would show the folder name. Switch listings off for
  ResourceSpace (VirtualHost or `apache2.conf` / `httpd.conf`) and reload Apache:

  ```apache
  <Directory <RS_ROOT>>
      Options -Indexes +FollowSymLinks
  </Directory>
  ```

  nginx shows no listings unless `autoindex on` is set.
- **SELinux (RHEL family):** after unpacking restore the file labels:
  `sudo restorecon -R <RS_ROOT>/plugins/plugin_manager`. The filestore needs the writable label (usually set when
  ResourceSpace was installed): `sudo semanage fcontext -a -t httpd_sys_rw_content_t "<filestore>(/.*)?"`
  and `sudo restorecon -R <filestore>`.
- **Plugin Manager writes to `<RS_ROOT>/plugins`:** the user that runs PHP needs write access there
  (`sudo chown -R <user>:<group> <RS_ROOT>/plugins`); with SELinux also the writable label:
  `sudo semanage fcontext -a -t httpd_sys_rw_content_t "<RS_ROOT>/plugins(/.*)?"` and
  `sudo restorecon -R <RS_ROOT>/plugins`. PHP `zip` extension: `php8.3-zip` on Ubuntu / Debian,
  `php-pecl-zip` on the RHEL family; restart PHP-FPM or Apache afterwards.

Finally: System > Plugins, activate "Plugin Manager" and click **Options**. The plugin creates its
data folder `<filestore>/plugin_manager` itself – it only needs write access to the filestore, which
ResourceSpace requires anyway.

### Requirements

- The PHP `zip` extension is required (e.g. `sudo apt install php8.3-zip`, on the RHEL family `php-pecl-zip`, then restart PHP-FPM or Apache).
- The user that runs PHP (`www-data`, with Apache on the RHEL family `apache`) needs write access to `<RS_ROOT>/plugins`, otherwise uploading and
  deleting plugins does not work: `sudo chown -R www-data:www-data <RS_ROOT>/plugins`.
- Uploads waiting for a decision ("Overwrite / Cancel") are stored in
  `<filestore>/plugin_manager/data_<key>/pending/` *(since 1.09; before that in `<temp>/plugin_manager/`)*.
  The plugin has no other stored settings. Work folders `.plugin_manager_*` in the plugins folder are
  removed automatically after one hour.

**The plugin creates its folders itself** on first use (activation, opening the options page).
It only needs write access to the filestore, which ResourceSpace requires anyway.

## Reset plugin *(since 1.09)*

The box is collapsed – click its title to open it *(since 1.13)*; it opens by itself after a failed reset.

The **Reset plugin** box (after typing `RESET`) deletes everything Plugin Manager stored: uploads
waiting for a decision (`<filestore>/plugin_manager`, also the old folder `<temp>/plugin_manager`) and
work folders left behind by interrupted installs or deletes. **Installed plugins are not
changed.**

## License *(since 1.05)*

- The plugin is licensed under the **PolyForm Noncommercial License 1.0.0** (file
  [LICENSE](LICENSE)).
- It is **free** for private and noncommercial use, and for noncommercial organizations
  (education, public institutions, charities). You may change and share it for these purposes,
  keeping the license text.
- **Commercial use** (a company, services for clients, a paid product) requires the author's
  written permission: <https://github.com/s29829>. Details: `LICENSE-COMMERCIAL.md` in the
  repository.
