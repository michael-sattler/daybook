*////////////// Architecture and Deployment Standards //////////////*

*STACK*
- See /docs/specs-platform.md

*ADMIN*
- In addition to user-facing tools, we have an admin area with tools only staffers can use << Not needed for Daybook>>

*STANDARDS*
* FOR TERMINAL COMMANDS: We use a docker instance on a windows machine for development. DO NOT USE POWERSHELL COMMANDS

*CONFIG*
- Centralized platform-aware config files for easy deployment
- /public/config/config.php pulls in platform-specific files named development.config.php, staging.config.php, and production.config.php that contain DB credentials, paths, API keys, and other information unique to each platform. This will allow the system to be deployed on different platforms by changing one file. 
- platform-specific config files should not be in the git repo
- /public/config/config.php also pulls in /public/config/auth.php and /public/config/database.php so they don't need to be pulled in by individual files
* Because the project root may change on production and development, at the top of every file in /public, we need to load the central config file using a relative path: require_once(__DIR__ . '/../config/config.php'); 
* every subsequent include can use the PROJECT_ROOT constant once it's defined in config.php

- Changes to vhost files should be minimized
- All error logging should be done by using the custom debug_log() function defined in config.php, the log destination should be defined by the [platform].config.php. Do not redefine logging paths elsewhere or use the platform default
- **LOG_PATH REQUIREMENT:** LOG_PATH MUST always be defined as `PUBLIC_ROOT . '/logs'` to ensure logs stay within the application directory. This is critical for shared hosting deployments where you cannot write outside the public directory. Never route logs to PROJECT_ROOT or any path outside PUBLIC_ROOT.

*////////////// Database Standards //////////////*
- database access should use the global $mysqli connection variable that should be established by the config system
- database connections should use direct mysqli queries - no PDO [This is to accommodate a production deployment which may or may not use PDO]
- database queries should use regular $mysqli->query() calls with proper escaping using mysqli_real_escape_string($mysqli, $value) and intval(). Consider using prepared statements for better security where possible, but mysqli_real_escape_string is acceptable for production compatibility
- all timestamps should be stored in the database as unix timestamps and converted to readable dates in the front end

*////////////// Back End Standards //////////////*

*FUNCTIONS*
- All functions used in user-facing and API tools functions should be sequestered in dedicated include files
  - Universal settings, constants, and access credentials that apply to all PHP code should live in /public/config/config.php, /public/config/auth.php, and /public/config/database.php
  - General-purpose functions used in multiple files should live in /public/app/includes/functions-universal.php
  - Single-purpose functions used in specific files should live in /public/app/includes/functions-[type].php and be included as needed by the appropriate pages
- adminonly(). If this appears at the top of the page, redirect if $_SESSION['adminuser_id'] doesn't exist
- userloginonly(). If this appears at the top of the page, redirect if $_SESSION['user_id'] doesn't exist

*////////////// Major Subsystems //////////////*
- See /docs/scope-coresaas.md for detailed subsystem specifications including database schemas
- Key subsystems: Registration, Auth, ContentLibrary, EmailLibrary, Event Logging, ImageGallery, SupportMessaging, PromptLibrary, FeatureFlags

*FUNCTION USAGE NOTES*
- featureflagRequired([featureflag_id], [redirect_url]) - If this appears at the top of the page, redirect if user doesn't have that feature flag. Use $_SESSION['featureflags'][featureflag_id] if $_SESSION['featureflags'] exists; otherwise query DB
- hasFeatureFlag([featureflag_id]) - Returns true if user has the feature flag, false otherwise. Use in conditionals to display page elements that are only visible to those with the flag
- Regenerate $_SESSION['featureflags'] on login by querying user_featureflags and adminuser_featureflags tables


  *////////////// Docker //////////////*
- use separate web/db/phpmyadmin containers via docker-compose.yml ... each service restarts/upgrades/resets independently, logs don't intermingle

  *////////////// Deployment //////////////*
  All files should be checked into dedicated git repo
