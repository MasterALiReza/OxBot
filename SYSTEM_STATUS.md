# OxBot & WGDashboard Multi-Server Integration Status

## Latest System Updates & Fixes (2026-07-19)

### 1. Turkey Server (`ID=4` / `212.115.103.111`) Fix
- **Root Cause Resolved**: The WGDashboard panel record in the bot database (`marzban_panel`) had null username (`usernameac`) and password (`passwordac`) fields, causing API authentication (`401 Unauthorized`) and connection failures when fetching `getUsedIPs` or adding peers.
- **Resolution**: Updated admin credentials and auto-login handshake mechanisms in the bot database. Tested live peer creation and retrieval successfully.

### 2. New Server HUDSON (`ID=2` / `codm.vipvirtualnet.eu` / `85.9.99.250`)
- **Root Cause Resolved**: Replaced previous slow/unresponsive endpoint with clean, systemd-managed Gunicorn backend (`wg-dashboard.service`).
- **Resolution**: Updated the bot database to point to the secure HTTPS domain (`https://codm.vipvirtualnet.eu:10086/`) and verified instant peer creation/deletion and live IP tracking.

### 3. Core Engine & API Reliability (`WGDashboard.php` & `request.php`)
- Optimized cURL timeout handling and exponential retry backoff.
- Enhanced `getUsedIPs` logic to robustly parse all subnet configurations (`/24` to `/16`).
- Normalized timestamp parsing and activity status calculation (`online_at`).
