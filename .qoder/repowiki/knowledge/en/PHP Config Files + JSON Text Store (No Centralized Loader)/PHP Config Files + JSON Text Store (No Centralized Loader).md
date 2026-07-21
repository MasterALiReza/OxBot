---
kind: configuration_system
name: PHP Config Files + JSON Text Store (No Centralized Loader)
category: configuration_system
scope:
    - '**'
source_files:
    - config.php
    - panel/inc/config.php
    - vpnbot/Default/config.php
    - text.json
---

MirzaBot has no unified configuration framework. Runtime settings are scattered across plain PHP files and one large JSON dictionary, loaded via direct require/include statements rather than a shared loader or environment abstraction.

Sources and where they live:
- Root runtime secrets and connection: config.php defines $dbhost, $dbname, $usernamedb, $passworddb, $APIKEY, $adminnumber, $domainhosts, $usernamebot, $backup_secure_token, and $request_exec_timeout. It also opens a global PDO $pdo instance.
- Panel bootstrap: panel/inc/config.php sets secure session options, then requires ../../config.php and ../../function.php; provides DB helpers (db_query, db_fetchAll, ...), CSRF helpers, flash messages, login-rate limiter, role label/tag helpers, and loads UI strings from text.json via languagechange().
- Bot token per branch: vpnbot/Default/config.php (and vpnbot/update/config.php) holds $ApiToken for the Telegram Bot API; each agent branch ships its own copy.
- Cron workers: cronbot/*.php scripts start by require_once __DIR__ . '/../config.php' plus botapi.php, panels.php, function.php, so they share the same root config.
- Payment gateways: payment/*.php are standalone webhook handlers that include their own DB/connection logic; they do not go through panel/inc/config.php.
- Language and feature toggles: text.json at repo root is a ~2500-line i18n dictionary keyed by locale (fa, en, ru, zh). Many admin-bot prompts and panel labels are read from it via languagechange(__DIR__ . '/../../text.json').

How it is loaded:
There is no central Config::load() or .env parser. Each subsystem bootstraps itself by requiring config.php (root) and any local helpers it needs. The web panel additionally requires panel/inc/config.php which wraps the root config with session/CSRF/db helpers. Cron jobs and payment scripts follow the same pattern.

What is not present:
- No .env, dotenv, phpdotenv, or similar env-file loader.
- No YAML/TOML/JSON-based app config (only text.json for i18n).
- No feature-flag library or runtime toggle registry.
- No secret manager integration (secrets are plain variables in checked-in files).

Conventions developers should follow:
- Put new runtime constants in config.php and access them as globals after requiring it.
- For web-panel pages, require panel/inc/config.php once at the top to get sessions, CSRF, and DB helpers.
- For CLI/cron scripts, require_once __DIR__ . '/../config.php' plus the needed modules (botapi.php, panels.php, function.php).
- Keep sensitive values out of version control; replace placeholders like {database_url} before deployment.
- Do not add new configuration sources directly into text.json unless they are user-facing strings; operational flags belong in config.php.
- If you introduce a new subsystem, mirror the existing bootstrap pattern: a small config.php-style file that exposes typed globals, and have consumers require it explicitly.