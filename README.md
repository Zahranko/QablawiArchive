# QablawiArchive — File Vault

A lightweight PHP file upload and preview gallery, deployed to Hostinger
straight from GitHub.

- [Deploying from GitHub](#deploying-from-github) — Hostinger pulls from the repo
- [Manual setup](#1-upload-the-files) — if you'd rather drag files in yourself

---

## Deploying from GitHub

Hostinger pulls the code from GitHub itself — there are no FTP credentials
anywhere, and nothing leaves Hostinger's network. A push to `main` fires a
webhook, Hostinger pulls, the site updates.

GitHub Actions (`.github/workflows/ci.yml`) runs alongside it as the check: it
lints every PHP file and fails if `uploads/.htaccess` or the root `.htaccess`
has lost its protective rule. It does not deploy.

### One-time setup

**1. Empty the target directory first.** Hostinger refuses to set up a
repository in a folder that already has files in it. In **File Manager**,
delete Hostinger's placeholder `public_html/index.html` (and `default.php`
if present). If you have your own files there, move them out — this step is
the one that trips people up.

**2. hPanel → Websites → your site → Advanced → GIT.**

| Field | Value |
|---|---|
| Repository | `https://github.com/Zahranko/QablawiArchive.git` |
| Branch | `main` |
| Directory | leave blank for `public_html`, or e.g. `vault` for a subfolder |

Click **Create**. The repository is public, so the HTTPS URL works with no
authentication. If you ever make it private, switch to the SSH URL
(`git@github.com:Zahranko/QablawiArchive.git`) and add the SSH key Hostinger
shows you to GitHub under **Settings → Deploy keys**.

**3. Turn on automatic deployment.** The GIT page now shows your repository
with a **webhook URL**. Copy it, then in GitHub go to **Settings → Webhooks →
Add webhook**:

- *Payload URL*: the URL you copied
- *Content type*: `application/json`
- *Which events*: **Just the push event**
- *Active*: checked

GitHub sends a ping immediately — a green tick under **Recent Deliveries**
means it's wired up.

**4. Set the folder permissions, once.** Deployment copies code, not
permissions. In File Manager, right-click `uploads/` → **Permissions** → **755**.
The app creates the folder on first upload if it's missing, but doing it now
avoids a confusing first error.

Without the webhook you can still deploy — the GIT page has a **Deploy** button
you press by hand. The webhook just saves you the trip.

### What a deploy does and doesn't touch

Hostinger runs a pull into the existing working tree, which only touches files
Git tracks. `uploads/` is gitignored apart from `.htaccess`, so every file your
visitors have uploaded is untracked and survives every deploy untouched.

The one thing to know: because the repository is cloned *into* the web root,
`public_html/.git/` is a real directory behind your domain. The root `.htaccess`
in this repo blocks it — `RedirectMatch 404 /\.git` — which is why CI fails the
build if that line ever disappears. Verify it once after the first deploy by
visiting `https://yourdomain.com/.git/config`; you want a 404, not a download.

### If you'd rather push over FTP instead

The FTP-based workflow was removed in favour of the above. It lives in the Git
history if you want it back (`git log -- .github/workflows/deploy.yml`), and it
needs `FTP_SERVER`, `FTP_USERNAME` and `FTP_PASSWORD` as repository secrets.
Don't run both methods against the same folder.

---

## Manual setup

If you'd rather not use Git deployment at all.

### 1. Upload the files

Put these in your domain's document root (`public_html/`, or a subfolder like
`public_html/vault/`), keeping the structure exactly as it is:

```
public_html/
├── .htaccess            <-- blocks /.git and directory listings
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

### 2. Create and permission the uploads folder

If `uploads/` isn't there, create it. Then right-click it → **Permissions**:

- `uploads/` → **755** (`rwxr-xr-x`)
- `.htaccess`, `index.php`, `upload.php`, `delete.php`, `uploads/.htaccess` → **644**

755 is enough on Hostinger because PHP runs as your own user. **Do not use 777** —
it lets any other account on the server write into your folder.

### 3. Check the PHP limits

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

### 4. Visit the page

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

**Repository exposure.** Git deployment clones into the web root, so the root
`.htaccess` returns 404 for anything under `/.git` — otherwise the full history
is downloadable from the live site. It also disables directory listings and
denies `README.md` and the dotfiles.

**Output.** Every filename is escaped with `htmlspecialchars()` before it hits
the page and `rawurlencode()`d in URLs, so a crafted name can't inject markup.

## Worth knowing

The gallery is public: anyone with the URL can upload and view. If this is for
anything non-public, put HTTP Basic Auth on the folder (hPanel → **Advanced →
Password Protect Directories**) or add a login before going live.
