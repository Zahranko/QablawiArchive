# QablawiArchive — File Vault

A lightweight PHP file upload and preview gallery, deployed to Hostinger from
GitHub.

- [Deploying from GitHub](#deploying-from-github) — the CI/CD setup
- [Manual setup](#1-upload-the-files) — if you'd rather drag files in yourself

---

## Deploying from GitHub

`.github/workflows/deploy.yml` runs on every push to `main`: it lints all PHP,
refuses to continue if `uploads/.htaccess` is missing or no longer disables the
PHP engine, then publishes over FTPS.

### One-time setup

**1. Get the FTP details.** hPanel → **Files → FTP Accounts**. You need the
*FTP hostname* (something like `ftp.yourdomain.com`, or the server IP), the
*FTP username*, and the password. Create a new FTP account here rather than
reusing your hPanel login, so the credentials in GitHub only reach this one
site; if they ever leak you delete that account and nothing else is affected.

**2. Add them as GitHub secrets.** In the repo → **Settings → Secrets and
variables → Actions → New repository secret**:

| Secret | Value |
|---|---|
| `FTP_SERVER` | the FTP hostname |
| `FTP_USERNAME` | the FTP account username |
| `FTP_PASSWORD` | that account's password |

Never put these in a file in the repo — secrets are the only place they belong.

**3. Set the target folder, if it isn't `public_html/`.** Same page, the
**Variables** tab → `FTP_SERVER_DIR`, e.g. `public_html/vault/`. The trailing
slash matters. Skip this and it deploys to `public_html/`.

**4. Push to `main`.** Watch the run under the **Actions** tab.

### What it will and won't touch

The action uploads only files that changed, and removes remote files that it
previously deployed and you have since deleted from the repo. Visitor uploads
were never deployed, so they are never in that set and are left alone —
provided `dangerous-clean-slate` stays `false`. Don't turn it on; it would
delete the entire remote folder before uploading, taking every uploaded file
with it.

`uploads/` is gitignored except for `.htaccess`, so the security file ships but
the uploaded content stays out of version control.

### First deploy

The workflow copies code, not permissions. After the first successful run, go
to File Manager once and confirm `uploads/` exists and is **755** — see step 2
below. The app creates the folder on first upload if it's missing, but setting
it yourself avoids a confusing first error.

### Alternative: Hostinger's built-in Git

If you'd rather not keep FTP credentials in GitHub at all, hPanel →
**Advanced → GIT** can pull straight from the repository, with a webhook URL
you paste into GitHub (**Settings → Webhooks**) for automatic deploys. It has
no lint step, and it deploys whatever is on the branch — but nothing leaves
Hostinger. Either approach works; don't run both at once.

---

## Manual setup

## 1. Upload the files

Put these in your domain's document root (`public_html/`, or a subfolder like
`public_html/vault/`), keeping the structure exactly as it is:

```
public_html/
├── index.php
├── upload.php
├── delete.php
└── uploads/
    └── .htaccess        <-- do not skip this file
```

In hPanel: **Files → File Manager**, drag the files in. The File Manager hides
dotfiles by default — turn on **Settings → Show hidden files** so you can
confirm `uploads/.htaccess` actually arrived. If it didn't, create it there and
paste the contents in.

## 2. Create and permission the uploads folder

If `uploads/` isn't there, create it. Then right-click it → **Permissions**:

- `uploads/` → **755** (`rwxr-xr-x`)
- `index.php`, `upload.php`, `delete.php`, `uploads/.htaccess` → **644** (`rw-r--r--`)

755 is enough on Hostinger because PHP runs as your own user. **Do not use 777** —
it lets any other account on the server write into your folder.

## 3. Check the PHP limits

hPanel → **Advanced → PHP Configuration → PHP options**. The script caps uploads
at 5 MB, so make sure the server allows at least that:

| Setting            | Value    |
|--------------------|----------|
| `upload_max_filesize` | 8M or more |
| `post_max_size`       | 8M or more |
| `file_uploads`        | On       |

PHP 7.4+ is required; 8.1 or newer is recommended. `fileinfo` is enabled on
Hostinger by default — the MIME check needs it, and `mbstring` is needed for the
name sanitiser.

To change the 5 MB cap, edit `MAX_BYTES` in `upload.php` **and** `MAX_MB` in
`index.php` so the form and the server agree.

## 4. Visit the page

Open `https://yourdomain.com/` (or `/vault/`). Upload a PDF or image, and it
appears in the gallery below the form.

---

## Search and delete

**Search** is a plain GET form — typing a name and pressing Search reloads the
page as `index.php?q=invoice` and shows only files whose name contains that
text, case-insensitively. It needs no JavaScript, the URL is shareable, and the
count reads "3 of 12 files" while a search is active. **Clear** returns to the
full list.

**Delete** is the trash icon on each tile. It posts to `delete.php` rather than
using a link, because a GET request that destroys data can be triggered by a
prefetch, a crawler or an `<img>` tag on another site. The browser asks for
confirmation first, and any active search term is carried through the redirect
so you land back on the same filtered view.

`delete.php` applies the same defences as the upload path: CSRF token,
`basename()` on the submitted name, the extension whitelist, and finally a
`realpath()` check that the resolved file's parent directory really is
`uploads/` — which also stops a symlink from pointing somewhere else. Deletion
is permanent; there is no recycle bin.

---

## How the security works

**Path traversal.** The custom name goes through `safe_stem()`, which runs
`basename()` and then strips everything that isn't a letter, digit, space, dash
or underscore. `../../../etc/passwd` becomes `etcpasswd`. The extension is never
taken from user input — it's read from the real uploaded filename and checked
against the whitelist, then re-attached by the script.

**Fake file types.** An extension whitelist alone is weak, so `upload.php` also
reads the file's real MIME type with `finfo` and requires it to match the
extension, and runs `getimagesize()` on anything claiming to be an image. A
`.jpg` that is actually PHP source is rejected.

**Execution.** Even if something did slip through, `uploads/.htaccess` turns off
the PHP engine and removes every script handler for that folder, so an uploaded
file can only be downloaded, never run. This is the single most important file
in the project.

**Size.** Checked twice — the browser sees `MAX_FILE_SIZE`, and `upload.php`
re-checks `$_FILES['upload_file']['size']` server-side, because the browser value
is trivially forged. Oversized posts that exceed `post_max_size` arrive with an
empty `$_FILES`, and that case is handled too.

**CSRF.** The form carries a per-session token compared with `hash_equals()`, so
another site can't make your visitors post files to it.

**Overwrites.** A name that's already taken gets `-2`, `-3` … appended rather
than silently replacing the existing file.

**Output.** Every filename is escaped with `htmlspecialchars()` before it hits
the page and `rawurlencode()`d in URLs, so a crafted name can't inject markup.

## Worth knowing

The gallery is public: anyone with the URL can upload and view. If this is for
anything non-public, put HTTP Basic Auth on the folder (hPanel → **Advanced →
Password Protect Directories**) or add a login before going live.
