# Contribution Guidelines for WordPress Plugins

* All projects **must follow the KISS principle (Keep It Simple, Stupid)**.
* Design, implementation, and architecture must prioritize simplicity, clarity, and maintainability.
* Avoid unnecessary abstraction, over-engineering, or premature optimization.

## Compatibility

* **WordPress:** by default, plugins must support **the latest stable WordPress version and the two previous major versions**.

  * For WordPress 6.9: WordPress 6.7, 6.8, and 6.9.
* **PHP:** by default, plugins must support **all PHP versions that were officially available on the release date of the supported WordPress version**.

  * For WordPress 6.9: PHP 8.2 to 8.5.
  * Exceptions may exist for compatibility reasons and **must be clearly documented**.
* **Database:** by default, plugins must support **MariaDB LTS versions supported at the time of the WordPress release**.

  * For WordPress 6.9: MariaDB 10.6 or newer.
* **Installation modes:** plugins **must be compatible with WordPress Multisite by default**.

  * Multisite compatibility **must be explicitly confirmed** and indicated in the plugin header as `Network: true`.
  * If not specified, the plugin is assumed to be Multisite-compatible. Any exception must be documented.
* **Backward compatibility:** avoid breaking changes unless strictly necessary and clearly documented.

## Codebase requirements

* All code **must be written in U.S. English**.
* All documentation, comments, and inline explanations **must use U.S. English**.
* All project documentation **must be stored in the `docs/` directory**.
* Files and directories must follow WordPress naming conventions and be case-consistent.

## Architecture & style

* **By default, plugins must be implemented using classes**.
* When creating a new project, the chosen structure (**functions, classes, namespaces, or a combination**) **must be explicitly confirmed and documented**.
* Each plugin must be self-contained within its own directory.
* Implement functionality in a modular and maintainable way; prefer classes or traits over global functions when practical.
* Minimize external dependencies. Any new dependency must be documented in `README.md`, including justification and usage instructions.
* Avoid tight coupling with themes or other plugins unless explicitly required.

## Coding standards & security

* Follow the **PHP Coding Standards** and **WordPress Coding Standards** (`WordPress-Core`, `WordPress-Docs`, `WordPress-Extra`) without exception.
* **PHPCS must be executed** when making code changes to validate all modified files.
* All code **must be written with security as a primary concern**.
* Validate, sanitize, and escape **all input and output** following WordPress best practices.
* Avoid direct access to globals (`$_GET`, `$_POST`, `$_REQUEST`, `$_SERVER`) without validation.

  * **Use WordPress filters and APIs whenever possible**.
* Apply **nonces** to all state-changing actions (forms, AJAX, REST endpoints).
* Enforce proper **capability checks** for every privileged action.
* Design and review code to mitigate common **OWASP Top 10** risks (e.g. XSS, CSRF, SQL injection, privilege escalation, insecure deserialization).
* Public functions, methods, classes, hooks, and filters **must be fully documented using phpDoc**.

### Security audits

* When a security audit is requested, analysis **must cover OWASP and WordPress security best practices**, including:

  * Capability checks
  * Nonces
  * Validation & sanitization
  * Output escaping
  * Data encryption & sensitive information handling
  * Display masking (when necessary)
  * Direct access prevention
  * Data cleanup on uninstall
  * Form field validation according to field type
  * Input constraints (size and/or pattern) where possible
  * Error handling
  * Secure by default principles
  * Compliance & standards (GDPR compliance, accessibility, WordPress.org Plugin Guidelines)

## Database usage & persistence

* **All database access must use WordPress database APIs**. Direct database access is not allowed, **including in `uninstall.php`**.
* Prefer **caching mechanisms** (Object Cache, Transients API) whenever possible.
* Always create an `uninstall.php` file.
* If the plugin provides an administrative interface:

  * Include an option allowing users to decide whether plugin data should be deleted on uninstall.
  * **By default, plugin data must be preserved** unless the user explicitly opts in to removal.

## Capabilities & roles

* By default, all administrative functionality **must be restricted to administrator-level capabilities** (e.g. `manage_options`), unless explicitly specified otherwise.
* Custom capabilities must be clearly documented and justified.
* Capability checks must be applied both in UI rendering and in execution logic.

## Performance & scalability

* Avoid unnecessary database queries and repeated computations.
* Use WordPress APIs (Options API, Transients API, Object Cache) where appropriate.
* Ensure code behaves correctly under high-traffic and Multisite environments.

## Internationalization & accessibility

* All user-facing strings **must be internationalized** using WordPress i18n functions.
* Plugins must be compatible with right-to-left (RTL) languages.
* Admin and frontend UI must follow WordPress accessibility guidelines where applicable.

## Testing & QA

* Update or add automated tests (unit or integration) when relevant.
* Validate changes on a real WordPress installation using supported WordPress, PHP, and MariaDB versions.
* Test both single-site and Multisite scenarios when applicable.
* Review `wp-content/debug.log` and browser console logs during manual testing.
* No PHP notices, warnings, or deprecated messages are allowed.

## Build & deployment

* The `bin/` directory **must contain a `deploy.sh` script** that generates a distributable ZIP of the plugin, ready for delivery to end users.
* The deploy script **must read the plugin version from the plugin headers**.
* Executing `deploy.sh` must generate distributable artifacts **in the parent directory of the plugin** (typically `wp-content/plugins/`).
* The generated package **must be ready for distribution** and exclude:

  * Development files
  * Tests
  * CI configuration
  * Tooling artifacts
* Plugins using Composer must:

  * Respect PHP version constraints for the supported WordPress versions
  * Bundle a **production-optimized dependency set**, excluding `require-dev` packages.
* **Deploys must always be executed manually. Automatic or unattended deploys are not allowed.**
* Generated artifacts must be reproducible from the same source state.

## Git & repository access

* All Git operations **must be performed manually**.
* **Pull requests and automatic commits are not permitted**.
* Git may be used to inspect history, compare versions, or review prior implementations.
* No tooling may push commits or tags automatically.

## Git workflow & changelogs

* Use clear, imperative commit messages (≤72 characters) when commits are created.
* Keep changes focused and minimal in scope.
* Update `CHANGELOG.md` when adding features or fixing bugs, following the existing format.
* Reflect user-facing changes in `readme.txt` and `changelog.txt` (WordPress.org format) when applicable.
* When creating or updating `readme.txt` and `changelog.txt`, **the base template and structure defined in `DOCUMENTATION-readme.txt.md` and `DOCUMENTATION-changelog.txt.md` must be followed**.
* Each change set must document the WordPress, PHP, and database versions used for testing.

## Licensing & compliance

* Plugins **must be distributed under GPLv3.0 or later by default**.
* Third-party code must be license-compatible and properly attributed.
* No obfuscated, minified (without source), or encrypted PHP code is allowed.

## Communication

* Discuss significant or breaking changes before implementation.
* Provide sufficient context for review, including testing steps, assumptions, and known limitations.
* Security-related issues must be reported responsibly and not disclosed publicly without coordination.