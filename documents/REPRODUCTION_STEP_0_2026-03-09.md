# Reproduction Step 0: Clean-Room Initialization
**Date: 2026-03-09**

This document outlines the refined "Step 0" protocol for initializing the `pl-opensocial-rework` project. It incorporates fixes for the Private File System requirement, DDEV port separation, and Playwright execution reliability.

## Goal
Establish a clean, functional Open Social 13.0.0 environment in `~/Sites/pl-opensocial-rework` that serves as the baseline for following the `BUILD_LOG.md` reproduction.

## Environment & Safety Protocol

> [!IMPORTANT]
> **Private File System**: Open Social 13 requires a private file path. This MUST be configured before the site installation.
> **Port Pinning**: The rework project is pinned to ports `8580` (HTTP) and `8543` (HTTPS) to prevent conflicts with the original project.
> **Terminal Safety**: Execute commands individually (no `&&` chains) and avoid pipes to prevent terminal hangs.

## Execution Steps (Clean Start)

1. **Scaffold Project**:
   ```bash
   # Ensure directory is empty, then run from ~/Sites/pl-opensocial-rework
   composer create-project goalgorilla/social_template:13.0.0 .
   ```
2. **Configure DDEV**:
   ```bash
   ddev config --project-name=pl-opensocial-rework --project-type=drupal10 --docroot=web --php-version=8.3 --mariadb-version=11.8 --router-http-port=8580 --router-https-port=8543
   ```
3. **Environment Setup**:
   ```bash
   ddev start
   mkdir private
   ```
4. **Configure Private Path**:
   Append the following to `web/sites/default/settings.php`:
   ```php
   $settings['file_private_path'] = '/var/www/html/private';
   ```
5. **Site Install**:
   ```bash
   ddev drush site:install social --account-name=admin --account-pass=admin --site-name="Open Social Rework" -y
   ```

## Playwright Execution Protocol

To ensure reliability during the reproduction phases:
1. **Background Execution**: Always run with `WaitMsBeforeAsync: 500`.
2. **Monitoring**: Poll with `command_status` every 30-60 seconds.
3. **Halt on Failure**: Append `--timeout=30000` to every test command to catch hangs instantly.
