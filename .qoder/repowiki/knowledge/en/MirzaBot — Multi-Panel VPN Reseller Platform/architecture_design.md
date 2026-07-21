All children share a single PHP runtime and one MySQL database (configured in root `config.php` via PDO with utf8mb4). The central `panels.php` class `ManagePanel` is the cross-cutting adapter layer: it loads every panel driver (`Marzban.php`, `x-ui_single.php`, `MHSanaei-3.2.php`, `hiddify.php`, `marzneshin.php`, `WGDashboard.php`, `s_ui.php`, `ibsng.php`, `mikrotik.php`) and dispatches user lifecycle calls by reading the `type` column from the `marzban_panel` table, so the same business flow (create/read/update/delete) works uniformly across Marzban, xUI, Hiddify, WGDashboard, s-ui, iBSSG and MikroTik backends.

- `telegram_bot` and `web_panel` are two HTTP entrypoints into the same codebase; both call `ManagePanel::createUser()` / `DataUser()` etc., which route to the appropriate panel adapter.
- `rest_api` exposes the same operations as JSON endpoints behind Apache rewrite rules, reusing the same `ManagePanel` and DB helpers (`select()`, `update()`, `setjob()`).
- `payment_gateways` webhook scripts credit balances and then trigger the same invoice/user-creation path used by the bot/panel.
- `cron_jobs` background workers read the `invoice`/`user` tables and call the same panel adapters to expire or reset peers.
- `maintenance_scripts` operate directly on the shared DB and panel APIs for diagnostics.

The only cross-child wiring point is `config.php` (DB credentials, bot token, admin number, domain hosts); there is no process-level orchestrator — each child is independently deployed under the same document root and shares state through MySQL.