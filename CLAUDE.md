# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Travium is a Travian T4.5 browser-based strategy game server written in PHP 7.3 (requires >=7.3 <8.0). It features PvP combat, resource management, alliances, hero systems, and a marketplace. Uses MySQL/MariaDB for storage and Redis for caching.

## Development Setup

### Prerequisites
- PHP 7.3.x (not PHP 8+)
- MySQL/MariaDB (utf8mb4)
- Redis with `php-redis` extension
- Required PHP extensions: `mysqli`, `redis`, `gd`, `json`, `filter`, `session`, `curl`, `intl`

### Install Dependencies
```bash
composer install
```

### Configuration
Copy `config.sample.php` to `config.php` and fill in database credentials, domain URLs, and API keys. Game-specific settings live in `src/config/` subdirectory (`config.custom.php`, `config.dev.php`, timers in `src/config/timers/`).

### Database
Import the schema: `mysql -u <user> -p <database> < maindb.sql`

### DNS Subdomains Required
The game expects subdomains: `server1.`, `api.`, `cdn.`, `install.`, `voting.`, `payment.` — all pointed at the same server.

## Architecture

### MVC with Custom Dispatcher
- **Entry point:** `src/mainInclude.php` — parses the URI, maps page names to controller classes via a `$pages` array, then calls `Core\Dispatcher::dispatch()`
- **Controllers:** `src/Controller/` — 60+ controllers. All extend `Controller\AbstractCtrl`. AJAX handlers live in `src/Controller/Ajax/`, building-specific in `src/Controller/Build/`
- **Models:** `src/Model/` — data access layer, 60+ classes (e.g., `BattleModel`, `AllianceModel`, `FarmListModel`)
- **Views/Templates:** Twig 1.0 templates in `src/resources/Templates/`, view classes in `src/resources/View/`
- **Translation:** `T()` function, locale files in `src/resources/Translation/`

### Core Singletons (`src/Core/`)
Global state is managed through singletons accessed via `::getInstance()`:
- `Config` — game configuration (loaded from DB `config` table + PHP config files)
- `Session` — user session state, village data (large class ~36KB)
- `DB` / `GlobalDB` / `ServerDB` — MySQLi database wrappers
- `Caching` / `GlobalCaching` — Redis caching layer
- `Village` — current player's village data object (~60KB)

### Game Logic (`src/Game/`)
- `Formulas.php` — core game calculations (162KB, the largest file)
- `BattleCalculator.php` — combat resolution
- `ResourcesHelper.php` — resource production/consumption
- `GoldHelper.php` — premium currency
- `Buildings/`, `Hero/`, `Map/`, `AllianceBonus/` — subsystems

### Page Routing (in `src/mainInclude.php`)
Key mappings: `index.php` → `LoginCtrl`, `dorf1.php` → `Dorf1Ctrl` (village overview), `dorf2.php` → `Dorf2Ctrl` (village map), `dorf3.php` → `Dorf3Ctrl` (marketplace), `build.php` → `BuildCtrl`, `ajax.php` → `AjaxCtrl`, `karte.php` → `KarteCtrl` (world map), `allianz.php` → `AllianceCtrl`, `hero.php` → `HeroDivider`, `admin.php` → separate admin bootstrap

### Integrations (`integrations/`)
- **REST API:** `integrations/api/` — FastRoute-based, endpoints at `/v1/{section}/{action}`
- **Payments:** `integrations/payment/` — PayPal, Zarinpal, etc.
- **Installer:** `integrations/install/` — one-time setup wizard
- **Voting/CDN:** `integrations/voting/`, `integrations/cdn/`

### Admin Panel
Separate MVC system in `src/admin/` with its own bootstrap (`src/admin/include/bootstrap.php`) and dispatcher.

### Database Pattern
- MySQLi wrapper with auto-reconnect and connection pooling for CLI (`p:hostname`)
- Multi-world support: tables use `worldId` field; `GlobalDB` for cross-world meta data, `DB` for game world data
- Schema defined in `maindb.sql`

### Caching
- Redis is the primary cache (required — bootstrap dies without `redis` extension)
- Config references memcached on `127.0.0.1:11211` but Redis is the actual backend
- 300s TTL for world config, custom per use case

### Key Conventions
- Global helper functions in `src/functions.general.php` (e.g., `getWorldId()`, `getGame()`, `logError()`)
- `src/bootstrap.php` initializes sessions, constants (`ROOT_PATH`, `INCLUDE_PATH`, `RESOURCES_PATH`, `LOCALE_PATH`, `TEMPLATES_PATH`), autoloader, config, DB, and cache
- Autoloading via custom `Core\Autoloader` (PSR-4 style) plus Composer autoload
- Input sanitization through MySQLi `real_escape_string`; reCAPTCHA for registration
- Graphics packs (T4.4, T4.5, T4.6) selectable via `gpacks` config
