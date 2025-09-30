This project is a small WordPress plugin used as a coding test. The goal of this file is to give an AI coding agent the exact, actionable knowledge needed to make correct edits quickly.

Key places to look
- Plugin bootstrap: `wpmudev-plugin-test.php` (root of the plugin). It loads `vendor/autoload.php` if present and calls `WPMUDEV\PluginTest\Loader::instance()` on `init`.
- Core loader & base classes: `core/class-loader.php`, `core/class-base.php`, `core/class-singleton.php` — follow the existing Singleton/Base pattern when adding services.
- Admin pages: `app/admin-pages/` (example: `class-googledrive-settings.php`) — this file shows how React is bootstrapped, how script assets are localized, and which REST routes are exposed to the JS app.
- REST endpoints: `app/endpoints/v1/` — follow the existing namespace `WPMUDEV\PluginTest\Endpoints\V1` and registration pattern. The admin page localizes the REST route roots.
- Assets and build: `assets/` and `assets/js/*.asset.php` (webpack-generated manifest). Build tooling lives in `package.json` and `scripts/*.js`.

Big picture architecture (short)
- PHP bootstraps a single Loader (`WPMUDEV\PluginTest\Loader`) which registers admin pages and REST endpoint classes during `init`.
- Admin UI is a React app compiled with webpack; the PHP side registers/enqueues a compiled JS file and passes config via `wp_localize_script` as `wpmudevDriveTest` (see `prepare_assets()` in `class-googledrive-settings.php`).
- REST routes are versioned under `wpmudev/v1/drive/*` and must check nonces/permissions and sanitize input.
- Credentials and tokens are stored as WordPress options (examples: `wpmudev_plugin_tests_auth`, `wpmudev_drive_access_token`, `wpmudev_drive_token_expires`). Prefer option APIs and follow existing naming.

Developer workflows (concrete commands)
- Install PHP deps: `composer install` (project root). The plugin checks for `vendor/autoload.php` before requiring it.
- Install JS deps: `npm install`
- Build assets (dev watch): `npm run watch`
- Compile production assets: `npm run compile`
- Full packaging (composer + compile + translate + zip): `npm run build` (runs `scripts/build.js`). On Windows the script uses PowerShell `Compress-Archive`; fallback options exist (archiver).
- Localization tool: `npm run translate` runs `wp i18n make-pot`.

Packaging & size notes (important)
- The build script compiles assets and copies only files listed in `scripts/build.js`/`scripts/package-build.js` into `build/<pkg.name>/` before zipping. It does NOT intentionally copy `node_modules/` or `vendor/` unless you modify the copy list.
- The plugin's admin enqueuing attempts to load `node_modules/@wpmudev/shared-ui/dist/*` if that path exists at runtime (`class-googledrive-settings.php`). Shipping `node_modules/` inside the plugin is fragile and increases ZIP size. Preferred options:
  - Treat React as external (the enqueue already replaces `react`/`react-dom` with `wp-element` at runtime). Keep webpack externals aligned with this.
  - Ship compiled/shared UI CSS/JS under `assets/` (or vendorize the specific dist files) instead of bundling or shipping the entire `node_modules/` tree.

Project conventions and patterns (do this when editing)
- Namespaces: `WPMUDEV\PluginTest` root. File/class naming uses `class-*.php` with PSR-ish namespacing inside `core/` and `app/`.
- Singletons/Base: most services extend `Base`/`Singleton`. Use `::instance()` and `->init()` like the Loader.
- Asset manifests: compiled assets produce a `*.asset.php` associative array used by `script_data()`; rely on that for dependency/version info.
- Localized JS object: `wpmudevDriveTest` contains important runtime values:
  - `dom_element_id` — root element id for React
  - `restEndpointSave` / `restEndpointAuth` / `restEndpointFiles` / `restEndpointUpload` / `restEndpointDownload` / `restEndpointCreate`
  - `nonce` — use this for REST requests checking `wp_rest` nonce
  - `authStatus`, `hasCredentials`, `redirectUri`
- Options & keys: search for `wpmudev_` and `wpmudev_plugin_tests_auth` — reuse key names or add new ones consistently.

Testing and CI
- PHPUnit config: `phpunit.xml.dist` is present. Tests live under `tests/`. Run phpunit from plugin root (ensure composer dev deps installed).
- Linting/standards: `phpcs.ruleset.xml` exists. Follow WordPress Coding Standards.

What an AI agent should do first on any change
1. Read `core/class-loader.php` to see how new services must be registered.
2. If changing JS, inspect the corresponding `assets/js/*.asset.php` and `webpack.config.js` to keep dependencies/externals in sync (avoid bundling React if the enqueue expects `wp-element`).
3. When adding REST endpoints match the existing namespace (`Endpoints\V1`) and register them from the Loader `init()` flow.
4. Run `npm run compile` and `npm run build` locally to validate packaging behavior; the build script will report zip size.

Files to reference while coding
- `wpmudev-plugin-test.php` (bootstrap)
- `core/class-loader.php` (service registration)
- `app/admin-pages/class-googledrive-settings.php` (example of enqueue/localize and option names)
- `app/endpoints/v1/` (REST endpoint examples)
- `assets/js/*.asset.php` and `assets/js/*.min.js` (compiled assets & manifests)
- `scripts/build.js`, `scripts/package-build.js` (packaging behavior)

If anything in these notes is unclear, or you want me to expand examples (REST handler, WP nonce usage, or webpack externals snippet), tell me which area to expand and I will update this file.
