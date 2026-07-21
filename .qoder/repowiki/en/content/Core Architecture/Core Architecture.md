# Core Architecture

<cite>
**Referenced Files in This Document**
- [index.php](file://index.php)
- [botapi.php](file://botapi.php)
- [config.php](file://config.php)
- [function.php](file://function.php)
- [panels.php](file://panels.php)
- [request.php](file://request.php)
- [admin.php](file://admin.php)
- [panel/index.php](file://panel/index.php)
- [panel/inc/config.php](file://panel/inc/config.php)
- [api/users.php](file://api/users.php)
- [api/payment.php](file://api/payment.php)
- [api/service.php](file://api/service.php)
- [api/panels.php](file://api/panels.php)
- [cronbot/index.php](file://cronbot/index.php)
- [vpnbot/Default/botapi.php](file://vpnbot/Default/botapi.php)
- [vpnbot/Default/admin.php](file://vpnbot/Default/admin.php)
- [vpnbot/Default/config.php](file://vpnbot/Default/config.php)
- [vpnbot/Default/func.php](file://vpnbot/Default/func.php)
- [vpnbot/Default/index.php](file://vpnbot/Default/index.php)
- [vpnbot/Default/keyboard.php](file://vpnbot/Default/keyboard.php)
- [vpnbot/Default/text.json](file://vpnbot/Default/text.json)
- [Marzban.php](file://Marzban.php)
- [WGDashboard.php](file://WGDashboard.php)
- [hiddify.php](file://hiddify.php)
- [s_ui.php](file://s_ui.php)
- [x-ui_single.php](file://x-ui_single.php)
- [mikrotik.php](file://mikrotik.php)
- [ibsng.php](file://ibsng.php)
</cite>

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)
10. [Appendices](#appendices)

## Introduction
This document describes MirzaBot’s core system architecture with a focus on its modular monolith structure, plugin-based VPN panel integration, and event-driven processing via cron jobs. It explains the main entry points (index.php, botapi.php), request routing flow, session management, and database abstraction layer. It also details the separation between the Telegram bot interface, web admin panel, and REST API layers, and maps interactions among user management, service provisioning, payment processing, and notification systems. Finally, it provides system context diagrams, scalability considerations, security boundaries, and extensibility points for custom integrations.

## Project Structure
MirzaBot is organized as a PHP-based modular monolith:
- Root-level entry points handle Telegram updates and general requests.
- The api/ directory exposes REST endpoints for internal and external consumers.
- The panel/ directory implements the web admin interface.
- The vpnbot/ directory contains pluggable VPN panel adapters.
- The cronbot/ directory hosts scheduled tasks and background workers.
- Payment gateways are implemented under payment/.
- Shared configuration and utilities reside at the root level.

```mermaid
graph TB
subgraph "Entry Points"
IDX["index.php"]
BOTAPI["botapi.php"]
end
subgraph "Web Admin Panel"
PANEL_IDX["panel/index.php"]
PANEL_CFG["panel/inc/config.php"]
end
subgraph "REST API"
API_USERS["api/users.php"]
API_PAYMENT["api/payment.php"]
API_SERVICE["api/service.php"]
API_PANELS["api/panels.php"]
end
subgraph "Cron Jobs"
CRON_IDX["cronbot/index.php"]
end
subgraph "VPN Plugins"
VDBOT_API["vpnbot/Default/botapi.php"]
VDBOT_ADMIN["vpnbot/Default/admin.php"]
VDBOT_CFG["vpnbot/Default/config.php"]
VDBOT_FUNC["vpnbot/Default/func.php"]
VDBOT_INDEX["vpnbot/Default/index.php"]
VDBOT_KB["vpnbot/Default/keyboard.php"]
VDBOT_TXT["vpnbot/Default/text.json"]
end
subgraph "Panel Adapters"
MARZBAN["Marzban.php"]
WG["WGDashboard.php"]
HIDDIFY["hiddify.php"]
SUI["s_ui.php"]
XUI["x-ui_single.php"]
MIKRO["mikrotik.php"]
IBSNG["ibsng.php"]
end
IDX --> BOTAPI
BOTAPI --> API_USERS
BOTAPI --> API_SERVICE
BOTAPI --> API_PAYMENT
BOTAPI --> API_PANELS
PANEL_IDX --> PANEL_CFG
CRON_IDX --> API_PANELS
CRON_IDX --> API_SERVICE
CRON_IDX --> API_PAYMENT
BOTAPI --> VDBOT_API
VDBOT_API --> VDBOT_FUNC
VDBOT_API --> VDBOT_CFG
VDBOT_API --> VDBOT_KB
VDBOT_API --> VDBOT_TXT
API_PANELS --> MARZBAN
API_PANELS --> WG
API_PANELS --> HIDDIFY
API_PANELS --> SUI
API_PANELS --> XUI
API_PANELS --> MIKRO
API_PANELS --> IBSNG
```

**Diagram sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [panel/index.php:1-200](file://panel/index.php#L1-L200)
- [panel/inc/config.php:1-200](file://panel/inc/config.php#L1-L200)
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)
- [vpnbot/Default/botapi.php:1-200](file://vpnbot/Default/botapi.php#L1-L200)
- [vpnbot/Default/admin.php:1-200](file://vpnbot/Default/admin.php#L1-L200)
- [vpnbot/Default/config.php:1-200](file://vpnbot/Default/config.php#L1-L200)
- [vpnbot/Default/func.php:1-200](file://vpnbot/Default/func.php#L1-L200)
- [vpnbot/Default/index.php:1-200](file://vpnbot/Default/index.php#L1-L200)
- [vpnbot/Default/keyboard.php:1-200](file://vpnbot/Default/keyboard.php#L1-L200)
- [vpnbot/Default/text.json:1-200](file://vpnbot/Default/text.json#L1-L200)
- [Marzban.php:1-200](file://Marzban.php#L1-L200)
- [WGDashboard.php:1-200](file://WGDashboard.php#L1-L200)
- [hiddify.php:1-200](file://hiddify.php#L1-L200)
- [s_ui.php:1-200](file://s_ui.php#L1-L200)
- [x-ui_single.php:1-200](file://x-ui_single.php#L1-L200)
- [mikrotik.php:1-200](file://mikrotik.php#L1-L200)
- [ibsng.php:1-200](file://ibsng.php#L1-L200)

**Section sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [panel/index.php:1-200](file://panel/index.php#L1-L200)
- [panel/inc/config.php:1-200](file://panel/inc/config.php#L1-L200)
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)
- [vpnbot/Default/botapi.php:1-200](file://vpnbot/Default/botapi.php#L1-L200)
- [vpnbot/Default/admin.php:1-200](file://vpnbot/Default/admin.php#L1-L200)
- [vpnbot/Default/config.php:1-200](file://vpnbot/Default/config.php#L1-L200)
- [vpnbot/Default/func.php:1-200](file://vpnbot/Default/func.php#L1-L200)
- [vpnbot/Default/index.php:1-200](file://vpnbot/Default/index.php#L1-L200)
- [vpnbot/Default/keyboard.php:1-200](file://vpnbot/Default/keyboard.php#L1-L200)
- [vpnbot/Default/text.json:1-200](file://vpnbot/Default/text.json#L1-L200)
- [Marzban.php:1-200](file://Marzban.php#L1-L200)
- [WGDashboard.php:1-200](file://WGDashboard.php#L1-L200)
- [hiddify.php:1-200](file://hiddify.php#L1-L200)
- [s_ui.php:1-200](file://s_ui.php#L1-L200)
- [x-ui_single.php:1-200](file://x-ui_single.php#L1-L200)
- [mikrotik.php:1-200](file://mikrotik.php#L1-L200)
- [ibsng.php:1-200](file://ibsng.php#L1-L200)

## Core Components
- Telegram Bot Interface
  - Entry point index.php receives incoming updates and routes them to botapi.php for processing.
  - botapi.php orchestrates message handling, command dispatch, and interaction flows.
- Web Admin Panel
  - panel/index.php serves the admin UI; panel/inc/config.php centralizes panel configuration and shared includes.
  - Authentication and session management are handled within the panel scope.
- REST API Layer
  - api/users.php, api/payment.php, api/service.php, and api/panels.php expose structured endpoints for internal services and external clients.
- Cron Jobs and Background Processing
  - cronbot/index.php coordinates periodic tasks such as expiration checks, status monitoring, and notifications.
- VPN Plugin Architecture
  - vpnbot/Default/* provides a standard plugin contract for panel-specific logic (bot commands, admin actions, keyboard layouts, texts).
  - Root-level adapter files (e.g., Marzban.php, WGDashboard.php, hiddify.php, s_ui.php, x-ui_single.php, mikrotik.php, ibsng.php) implement protocol-specific integrations invoked by api/panels.php.

Key responsibilities:
- Request routing and orchestration: index.php, botapi.php
- Session and configuration management: panel/inc/config.php, config.php
- Domain operations: api/* modules
- Scheduled events: cronbot/*
- External integrations: vpnbot/* and adapter files

**Section sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [panel/index.php:1-200](file://panel/index.php#L1-L200)
- [panel/inc/config.php:1-200](file://panel/inc/config.php#L1-L200)
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)
- [vpnbot/Default/botapi.php:1-200](file://vpnbot/Default/botapi.php#L1-L200)
- [vpnbot/Default/admin.php:1-200](file://vpnbot/Default/admin.php#L1-L200)
- [vpnbot/Default/config.php:1-200](file://vpnbot/Default/config.php#L1-L200)
- [vpnbot/Default/func.php:1-200](file://vpnbot/Default/func.php#L1-L200)
- [vpnbot/Default/index.php:1-200](file://vpnbot/Default/index.php#L1-L200)
- [vpnbot/Default/keyboard.php:1-200](file://vpnbot/Default/keyboard.php#L1-L200)
- [vpnbot/Default/text.json:1-200](file://vpnbot/Default/text.json#L1-L200)
- [Marzban.php:1-200](file://Marzban.php#L1-L200)
- [WGDashboard.php:1-200](file://WGDashboard.php#L1-L200)
- [hiddify.php:1-200](file://hiddify.php#L1-L200)
- [s_ui.php:1-200](file://s_ui.php#L1-L200)
- [x-ui_single.php:1-200](file://x-ui_single.php#L1-L200)
- [mikrotik.php:1-200](file://mikrotik.php#L1-L200)
- [ibsng.php:1-200](file://ibsng.php#L1-L200)

## Architecture Overview
The system follows a modular monolith design:
- Single deployment unit with clear module boundaries.
- Separation of concerns across interfaces (Telegram, Web Admin, REST API).
- Pluggable VPN panel adapters abstracting heterogeneous backend systems.
- Event-driven processing through cron jobs for asynchronous tasks.

```mermaid
graph TB
TG["Telegram Users"]
WEB["Admin Web Panel"]
API["REST API"]
CORE["Core Orchestrator<br/>index.php / botapi.php"]
DB[(Database)]
CFG["Configuration<br/>config.php"]
SESSION["Session Store"]
PAY["Payment Gateways"]
VPN["VPN Panels<br/>Adapters"]
CRON["Cron Jobs<br/>cronbot/index.php"]
TG --> CORE
WEB --> CORE
API --> CORE
CORE --> DB
CORE --> CFG
CORE --> SESSION
CORE --> PAY
CORE --> VPN
CRON --> CORE
CRON --> DB
CRON --> VPN
```

**Diagram sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [config.php:1-200](file://config.php#L1-L200)
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)
- [Marzban.php:1-200](file://Marzban.php#L1-L200)
- [WGDashboard.php:1-200](file://WGDashboard.php#L1-L200)
- [hiddify.php:1-200](file://hiddify.php#L1-L200)
- [s_ui.php:1-200](file://s_ui.php#L1-L200)
- [x-ui_single.php:1-200](file://x-ui_single.php#L1-L200)
- [mikrotik.php:1-200](file://mikrotik.php#L1-L200)
- [ibsng.php:1-200](file://ibsng.php#L1-L200)

## Detailed Component Analysis

### Telegram Bot Interface
- index.php receives updates from Telegram and forwards them to botapi.php.
- botapi.php parses payloads, validates tokens, resolves commands, and delegates to domain handlers.
- Keyboard and text resources are provided by the Default plugin set for consistent UX.

```mermaid
sequenceDiagram
participant U as "Telegram User"
participant T as "Telegram API"
participant I as "index.php"
participant B as "botapi.php"
participant P as "vpnbot/Default/botapi.php"
participant A as "api/*"
participant D as "Database"
U->>T : "Send message/command"
T-->>I : "Update webhook/polling"
I->>B : "Dispatch update"
B->>P : "Load plugin context"
B->>A : "Call domain APIs (users, service, payment)"
A->>D : "Read/write state"
B-->>U : "Reply via Telegram Bot API"
```

**Diagram sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [vpnbot/Default/botapi.php:1-200](file://vpnbot/Default/botapi.php#L1-L200)
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)

**Section sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [vpnbot/Default/botapi.php:1-200](file://vpnbot/Default/botapi.php#L1-L200)
- [vpnbot/Default/keyboard.php:1-200](file://vpnbot/Default/keyboard.php#L1-L200)
- [vpnbot/Default/text.json:1-200](file://vpnbot/Default/text.json#L1-L200)

### Web Admin Panel
- panel/index.php renders the admin UI and routes administrative actions.
- panel/inc/config.php centralizes configuration, layout includes, and shared helpers.
- Authentication and session management are scoped to the panel namespace.

```mermaid
flowchart TD
Start(["Admin Login"]) --> Auth["Authenticate Credentials"]
Auth --> Valid{"Valid?"}
Valid --> |No| Error["Show Error"]
Valid --> |Yes| Dashboard["Render Dashboard"]
Dashboard --> Actions["Perform Admin Actions"]
Actions --> Persist["Persist Changes to Database"]
Persist --> End(["Done"])
Error --> End
```

**Diagram sources**
- [panel/index.php:1-200](file://panel/index.php#L1-L200)
- [panel/inc/config.php:1-200](file://panel/inc/config.php#L1-L200)

**Section sources**
- [panel/index.php:1-200](file://panel/index.php#L1-L200)
- [panel/inc/config.php:1-200](file://panel/inc/config.php#L1-L200)

### REST API Layer
- api/users.php manages user lifecycle and profile data.
- api/payment.php handles payment initiation, verification, and callbacks.
- api/service.php provisions and manages services.
- api/panels.php orchestrates calls to VPN panel adapters.

```mermaid
classDiagram
class UsersAPI {
+listUsers()
+getUser(id)
+updateUser(id, data)
+deleteUser(id)
}
class PaymentAPI {
+createOrder(data)
+verifyPayment(token)
+refund(orderId)
}
class ServiceAPI {
+getServices()
+provisionService(userId, planId)
+deactivateService(serviceId)
}
class PanelsAPI {
+listPanels()
+addPeer(panelId, peerData)
+removePeer(panelId, peerId)
}
UsersAPI --> Database : "reads/writes"
PaymentAPI --> Database : "reads/writes"
ServiceAPI --> Database : "reads/writes"
PanelsAPI --> VPN_Adapters : "delegates"
```

**Diagram sources**
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)

**Section sources**
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)

### VPN Plugin Architecture
- The Default plugin set defines a common contract for bot commands, admin actions, keyboard layouts, and localized texts.
- Root-level adapter files implement specific protocols for each VPN panel provider.

```mermaid
classDiagram
class DefaultPlugin {
+botapi()
+admin()
+config()
+func()
+index()
+keyboard()
+text.json
}
class MarzbanAdapter {
+connect()
+createClient()
+updateClient()
+deleteClient()
}
class WGDashboardAdapter {
+connect()
+createClient()
+updateClient()
+deleteClient()
}
class HiddifyAdapter {
+connect()
+createClient()
+updateClient()
+deleteClient()
}
class SUiAdapter {
+connect()
+createClient()
+updateClient()
+deleteClient()
}
class XUIAdapter {
+connect()
+createClient()
+updateClient()
+deleteClient()
}
class MikroTikAdapter {
+connect()
+createClient()
+updateClient()
+deleteClient()
}
class IBSngAdapter {
+connect()
+createClient()
+updateClient()
+deleteClient()
}
DefaultPlugin <.. MarzbanAdapter : "invokes"
DefaultPlugin <.. WGDashboardAdapter : "invokes"
DefaultPlugin <.. HiddifyAdapter : "invokes"
DefaultPlugin <.. SUiAdapter : "invokes"
DefaultPlugin <.. XUIAdapter : "invokes"
DefaultPlugin <.. MikroTikAdapter : "invokes"
DefaultPlugin <.. IBSngAdapter : "invokes"
```

**Diagram sources**
- [vpnbot/Default/botapi.php:1-200](file://vpnbot/Default/botapi.php#L1-L200)
- [vpnbot/Default/admin.php:1-200](file://vpnbot/Default/admin.php#L1-L200)
- [vpnbot/Default/config.php:1-200](file://vpnbot/Default/config.php#L1-L200)
- [vpnbot/Default/func.php:1-200](file://vpnbot/Default/func.php#L1-L200)
- [vpnbot/Default/index.php:1-200](file://vpnbot/Default/index.php#L1-L200)
- [vpnbot/Default/keyboard.php:1-200](file://vpnbot/Default/keyboard.php#L1-L200)
- [vpnbot/Default/text.json:1-200](file://vpnbot/Default/text.json#L1-L200)
- [Marzban.php:1-200](file://Marzban.php#L1-L200)
- [WGDashboard.php:1-200](file://WGDashboard.php#L1-L200)
- [hiddify.php:1-200](file://hiddify.php#L1-L200)
- [s_ui.php:1-200](file://s_ui.php#L1-L200)
- [x-ui_single.php:1-200](file://x-ui_single.php#L1-L200)
- [mikrotik.php:1-200](file://mikrotik.php#L1-L200)
- [ibsng.php:1-200](file://ibsng.php#L1-L200)

**Section sources**
- [vpnbot/Default/botapi.php:1-200](file://vpnbot/Default/botapi.php#L1-L200)
- [vpnbot/Default/admin.php:1-200](file://vpnbot/Default/admin.php#L1-L200)
- [vpnbot/Default/config.php:1-200](file://vpnbot/Default/config.php#L1-L200)
- [vpnbot/Default/func.php:1-200](file://vpnbot/Default/func.php#L1-L200)
- [vpnbot/Default/index.php:1-200](file://vpnbot/Default/index.php#L1-L200)
- [vpnbot/Default/keyboard.php:1-200](file://vpnbot/Default/keyboard.php#L1-L200)
- [vpnbot/Default/text.json:1-200](file://vpnbot/Default/text.json#L1-L200)
- [Marzban.php:1-200](file://Marzban.php#L1-L200)
- [WGDashboard.php:1-200](file://WGDashboard.php#L1-L200)
- [hiddify.php:1-200](file://hiddify.php#L1-L200)
- [s_ui.php:1-200](file://s_ui.php#L1-L200)
- [x-ui_single.php:1-200](file://x-ui_single.php#L1-L200)
- [mikrotik.php:1-200](file://mikrotik.php#L1-L200)
- [ibsng.php:1-200](file://ibsng.php#L1-L200)

### Cron Jobs and Event-Driven Processing
- cronbot/index.php schedules recurring tasks such as expiration checks, uptime monitoring, and notifications.
- It interacts with the database and invokes panel adapters to reconcile state.

```mermaid
flowchart TD
Start(["Cron Trigger"]) --> LoadTasks["Load Scheduled Tasks"]
LoadTasks --> Iterate{"Task Type"}
Iterate --> |Expiration| Expire["Check Expirations"]
Iterate --> |Uptime| Uptime["Probe Nodes/Panels"]
Iterate --> |Notifications| Notify["Send Notifications"]
Expire --> UpdateDB["Update Status in DB"]
Uptime --> UpdateDB
Notify --> UpdateDB
UpdateDB --> End(["Complete"])
```

**Diagram sources**
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)

**Section sources**
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)

### Configuration and Database Abstraction
- config.php centralizes application-wide settings and environment variables.
- function.php provides shared utilities used across modules.
- The panel configuration panel/inc/config.php isolates admin panel settings and includes.

```mermaid
graph TB
CFG["config.php"]
FUNC["function.php"]
PCFG["panel/inc/config.php"]
DB[(Database)]
CFG --> DB
FUNC --> DB
PCFG --> DB
```

**Diagram sources**
- [config.php:1-200](file://config.php#L1-L200)
- [function.php:1-200](file://function.php#L1-L200)
- [panel/inc/config.php:1-200](file://panel/inc/config.php#L1-L200)

**Section sources**
- [config.php:1-200](file://config.php#L1-L200)
- [function.php:1-200](file://function.php#L1-L200)
- [panel/inc/config.php:1-200](file://panel/inc/config.php#L1-L200)

## Dependency Analysis
High-level dependencies:
- index.php and botapi.php depend on configuration and utility modules.
- API modules depend on database access and VPN adapters.
- Cron jobs depend on API modules and adapters for reconciliation.
- VPN adapters encapsulate external panel SDKs or HTTP APIs.

```mermaid
graph TB
IDX["index.php"] --> BOTAPI["botapi.php"]
BOTAPI --> API_USERS["api/users.php"]
BOTAPI --> API_SERVICE["api/service.php"]
BOTAPI --> API_PAYMENT["api/payment.php"]
BOTAPI --> API_PANELS["api/panels.php"]
API_PANELS --> MARZBAN["Marzban.php"]
API_PANELS --> WG["WGDashboard.php"]
API_PANELS --> HIDDIFY["hiddify.php"]
API_PANELS --> SUI["s_ui.php"]
API_PANELS --> XUI["x-ui_single.php"]
API_PANELS --> MIKRO["mikrotik.php"]
API_PANELS --> IBSNG["ibsng.php"]
CRON["cronbot/index.php"] --> API_PANELS
CRON --> API_SERVICE
CRON --> API_PAYMENT
BOTAPI --> CFG["config.php"]
BOTAPI --> FUNC["function.php"]
```

**Diagram sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)
- [config.php:1-200](file://config.php#L1-L200)
- [function.php:1-200](file://function.php#L1-L200)
- [Marzban.php:1-200](file://Marzban.php#L1-L200)
- [WGDashboard.php:1-200](file://WGDashboard.php#L1-L200)
- [hiddify.php:1-200](file://hiddify.php#L1-L200)
- [s_ui.php:1-200](file://s_ui.php#L1-L200)
- [x-ui_single.php:1-200](file://x-ui_single.php#L1-L200)
- [mikrotik.php:1-200](file://mikrotik.php#L1-L200)
- [ibsng.php:1-200](file://ibsng.php#L1-L200)

**Section sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)
- [config.php:1-200](file://config.php#L1-L200)
- [function.php:1-200](file://function.php#L1-L200)
- [Marzban.php:1-200](file://Marzban.php#L1-L200)
- [WGDashboard.php:1-200](file://WGDashboard.php#L1-L200)
- [hiddify.php:1-200](file://hiddify.php#L1-L200)
- [s_ui.php:1-200](file://s_ui.php#L1-L200)
- [x-ui_single.php:1-200](file://x-ui_single.php#L1-L200)
- [mikrotik.php:1-200](file://mikrotik.php#L1-L200)
- [ibsng.php:1-200](file://ibsng.php#L1-L200)

## Performance Considerations
- Use connection pooling for database access to reduce overhead under high concurrency.
- Cache frequently accessed configuration and static resources where safe.
- Offload long-running operations (e.g., bulk provisioning) to background workers triggered by cronbot.
- Implement rate limiting and request validation at the API boundary to protect downstream adapters.
- Prefer batch operations when interacting with VPN panels to minimize network round-trips.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
Common areas to inspect:
- Telegram update delivery and token validation in index.php and botapi.php.
- API error responses and payload structures in api/*.
- VPN adapter connectivity and authentication in adapter files.
- Cron job execution logs and scheduling in cronbot/index.php.
- Panel configuration and session state in panel/inc/config.php.

Recommended steps:
- Verify webhook/polling configuration and bot token validity.
- Check database connectivity and query performance.
- Validate VPN panel credentials and endpoint reachability.
- Review cron logs for failed tasks and retry policies.
- Inspect admin panel sessions and permissions.

**Section sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)
- [panel/inc/config.php:1-200](file://panel/inc/config.php#L1-L200)

## Conclusion
MirzaBot’s architecture combines a modular monolith with clear separation of concerns across Telegram, web admin, and REST API layers. The plugin-based VPN panel integration enables flexible support for multiple providers, while cron-driven tasks ensure reliable background processing. With proper configuration, caching, and robust error handling, the system can scale horizontally at the application layer and integrate additional panels and payment gateways through well-defined extension points.

[No sources needed since this section summarizes without analyzing specific files]

## Appendices

### System Context Diagram
```mermaid
graph TB
subgraph "External Systems"
TG["Telegram Platform"]
PG["Payment Gateways"]
VPNS["VPN Panels (Marzban, WgDashboard, etc.)"]
end
subgraph "MirzaBot"
ENTRY["index.php / botapi.php"]
API["REST API (api/*)"]
ADMIN["Admin Panel (panel/*)"]
CRON["Cron Jobs (cronbot/*)"]
ADAPTERS["VPN Adapters (Marzban.php, WGDashboard.php, ...)"]
DB[(Database)]
end
TG --> ENTRY
ENTRY --> API
ENTRY --> ADMIN
ENTRY --> CRON
API --> ADAPTERS
CRON --> ADAPTERS
API --> DB
CRON --> DB
ENTRY --> DB
API --> PG
```

**Diagram sources**
- [index.php:1-200](file://index.php#L1-L200)
- [botapi.php:1-200](file://botapi.php#L1-L200)
- [api/users.php:1-200](file://api/users.php#L1-L200)
- [api/payment.php:1-200](file://api/payment.php#L1-L200)
- [api/service.php:1-200](file://api/service.php#L1-L200)
- [api/panels.php:1-200](file://api/panels.php#L1-L200)
- [cronbot/index.php:1-200](file://cronbot/index.php#L1-L200)
- [panel/index.php:1-200](file://panel/index.php#L1-L200)
- [Marzban.php:1-200](file://Marzban.php#L1-L200)
- [WGDashboard.php:1-200](file://WGDashboard.php#L1-L200)
- [hiddify.php:1-200](file://hiddify.php#L1-L200)
- [s_ui.php:1-200](file://s_ui.php#L1-L200)
- [x-ui_single.php:1-200](file://x-ui_single.php#L1-L200)
- [mikrotik.php:1-200](file://mikrotik.php#L1-L200)
- [ibsng.php:1-200](file://ibsng.php#L1-L200)