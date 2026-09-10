# 🔐 Security model and deployment

Orbit uses several independent controls for uploads, accounts and tenant data. These controls reduce specific risks; they do not guarantee that software, PHP extensions, hosting or future modules are vulnerability-free.

## 🖼️ Uploads: private, validated and reconstructed

The only current upload endpoint is **Settings → General → Logo**. Access requires an authenticated active account and `settings.manage` in that account's workspace.

1. Only PNG, JPEG and WebP are accepted. A simple filename with a single extension is required, such as `company-logo.png`. PHP, PHTML, PHAR, scripts, HTML, SVG and executable double extensions are rejected, including files with forged browser MIME headers.
2. Input is limited to **2 MiB**, **4096 pixels per side**, and **4,194,304 total pixels**. The decoder also checks available PHP memory before allocating image canvases.
3. Fileinfo, image signatures and the actual decoder must agree with the filename extension. A plausible image header alone is insufficient.
4. GD decodes the image, copies its pixels to a fresh canvas and reencodes it. Original EXIF/comments, extra frames and appended executable payloads are discarded. Animated images become a static image. Both GD and Fileinfo are required; uploads fail closed when unavailable.
5. Only reconstructed image bytes are written, using a server-generated random name under `storage/app/private/workspace-logos/{workspace}/sanitized/`. User input never determines the workspace or stored basename.
6. `/settings/logo` derives ownership from the current account, validates the stored path, and returns an explicit raster MIME type with `nosniff`, sandbox CSP and private/no-store caching. Pre-hardening logos are reconstructed before delivery too; their original private files remain until replaced or removed.

There is **no generic private-storage download/upload route**. Do not expose private storage with an alias or symlink. `storage:link` is unnecessary for this starter, and Apache rules deny `/storage` requests. Successful image sanitization is not a malware scan and cannot fix a vulnerability in an outdated PHP/GD decoder.

## 🧱 Apache / cPanel execution controls

- The domain document root must be the project's **`public/` directory**.
- `public/.htaccess` rejects executable extensions and executable double extensions; only the root `index.php` front controller is allowed. Hidden configuration, backups and directory listings are denied; ACME challenge files remain accessible.
- The project-root `.htaccess` denies access if the full project is accidentally exposed. This is an additional Apache defense, not a replacement for the correct document root.
- Keep application code read-only for the web process where your hosting permissions allow. Only `storage/` and `bootstrap/cache/` need runtime write access. Do not use permissions `777`.
- These rules require Apache/LiteSpeed to honor `.htaccess`, authorization and rewrite directives. Nginx ignores `.htaccess`; configure equivalent server rules before using Nginx.

The Windows Apache regression script creates an isolated fixture site on loopback with a real PHP handler and deliberately executable test extensions. It checks denied files, normal front-controller routing, static assets, ACME and accidental project-root exposure, then removes its own fixtures. It does not change your existing Apache configuration:

```powershell
powershell -NoProfile -File tests/security/apache-smoke.ps1
```

## 👤 Authentication and tenant authorization

- Controllers derive tenant scope from the authenticated account; submitted tenant IDs do not switch workspaces. Route permissions and controller rules protect editing and role assignment.
- Users cannot assign permissions they do not hold. Protected owners/superadmins and self-role/status changes have additional checks.
- All state-changing web routes require Laravel CSRF protection. Login, reset and sensitive account/settings updates are rate-limited.
- Passwords use Laravel hashing with a 12-character mixed-case/number policy for new passwords. A 72-byte limit and NUL rejection avoid bcrypt truncation/errors.
- Changing your own email or password requires your current password through **both** Profile and Users Management. Non-sensitive profile/layout changes remain straightforward.
- Credential changes revoke relevant database sessions, remember tokens and reset tokens. `auth.session` also checks the password hash on authenticated requests. Deactivated accounts and suspended clients are blocked and have their credentials revoked.
- Public README demo credentials work only for development. `saas:demo` refuses live environments; production login and existing sessions reject the public demo password even after copying a development database. Create real users with `saas:install` and use private passwords.
- Password-reset links use configured `APP_URL`; production rejects a mismatched Host header. Set the exact canonical domain in `APP_URL`. This starter does not support arbitrary tenant hostnames.

## 🌐 Browser and secrets

- Blade escapes user content, and JavaScript uses safe text insertion. Stored notification links are filtered before rendering and may only point to internal paths.
- Enforced CSP allows locally shipped scripts and blocks inline JavaScript, event handlers, frames and plugins. Inline **styles** remain allowed for the dashboard's layout/chart behavior. New script code should live in a local asset file.
- Responses include `nosniff`, frame denial, no-referrer, same-origin resource/opener policy, restricted browser features and private/no-store cache headers. HTTPS responses include HSTS.
- Session defaults enable encryption, HttpOnly and SameSite=Lax; secure cookies default on for production. Keep HTTPS enabled and do not cache authenticated responses at a CDN.
- SMTP settings require an actual platform superadmin. SMTP passwords are encrypted with `APP_KEY`, hidden from serialization and never echoed or flashed back into forms. Live SMTP requires TLS/SSL with a 15-second timeout, including `.env` fallback and previously saved plaintext settings. Private mail servers are supported but must offer encryption for a live deployment.
- `.env` variants, Composer auth, dependencies and local artifacts are ignored by Git. Keep `APP_KEY` and database/SMTP credentials private; preserve the key with encrypted backups.

## 🛠️ Production runtime requirements

The project retains compatibility with **PHP 8.2.12** as originally requested. That is a historical compatibility floor, **not a secure deployment recommendation**. Use the latest security-patched supported PHP release available from your host, with patched GD/Fileinfo, Apache/LiteSpeed and MySQL/MariaDB. Application code cannot repair interpreter or image-decoder CVEs. PHP's official [supported versions](https://www.php.net/supported-versions.php) and [release changelog](https://www.php.net/ChangeLog-8.php) describe its support and fixes.

Set these values for a real deployment:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-real-domain.example
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Then install locked production dependencies and rebuild caches:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
composer audit --locked --no-dev
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

If TLS terminates at a reverse proxy, configure **only your actual trusted proxy addresses** so Laravel correctly detects HTTPS. Do not trust arbitrary forwarded headers or set a wildcard proxy merely to silence a configuration issue.

## 🧪 Verification and continuing maintenance

The September 2026 hardening suite covers executable/MIME/polyglot upload attempts, damaged/decompression-heavy images, legacy logo delivery, cross-tenant paths, account takeover routes, session revocation, public demo guards, genuine CSRF failures, Host poisoning, stored HTML/URL payloads and SMTP encryption enforcement.

```bash
php artisan test --compact
composer audit --locked
php artisan view:cache
```

For a running local demo instance, install `tests/browser` development dependencies and run:

```bash
node tests/browser/smoke.cjs
node tests/browser/security.cjs
```

GitHub Actions runs locked-dependency advisory checks and the MySQL suite on pushes, pull requests and a weekly schedule. An empty Composer audit means no advisories were reported for those locked packages at that time; it does not cover PHP itself or unknown vulnerabilities. Rerun the suite when adding modules or changing dependencies. Multi-factor authentication and an external independent penetration test are not included in this pass.

The upload approach follows [OWASP's upload guidance](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html); deployment requirements also follow [Laravel's deployment documentation](https://laravel.com/docs/12.x/deployment).
