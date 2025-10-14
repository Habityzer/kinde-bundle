# Installation Error Fix - Version 1.0.1

## Problem

When installing the bundle via `composer require habityzer/kinde-bundle`, users encountered this error:

```
In ArrayNode.php line 213:
  The child config "domain" under "habityzer_kinde" must be configured: Kinde
   domain (e.g., your-business.kinde.com)
```

This occurred because:
1. The bundle's configuration had required parameters (`domain` and `client_id`)
2. No Symfony Flex recipe was provided to automatically create the configuration file
3. The `cache:clear` command ran before the user had a chance to configure the bundle

## Solution

We added a complete Symfony Flex recipe structure that automatically:

1. **Creates configuration file** - Generates `config/packages/habityzer_kinde.yaml` with proper structure
2. **Sets environment variables** - Adds placeholder values to `.env` file
3. **Enables the bundle** - Automatically registers the bundle in `config/bundles.php`

## Changes Made

### 1. Created `manifest.json`

This is the Symfony Flex recipe manifest that tells Symfony how to install the bundle:

```json
{
    "bundles": {
        "Habityzer\\KindeBundle\\HabityzerKindeBundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/"
    },
    "env": {
        "KINDE_DOMAIN": "your-business.kinde.com",
        "KINDE_CLIENT_ID": "your-client-id",
        "KINDE_CLIENT_SECRET": "",
        "KINDE_WEBHOOK_SECRET": ""
    }
}
```

### 2. Created `config/packages/habityzer_kinde.yaml`

This configuration file is automatically copied to the user's project:

```yaml
habityzer_kinde:
    domain: '%env(KINDE_DOMAIN)%'
    client_id: '%env(KINDE_CLIENT_ID)%'
    client_secret: '%env(KINDE_CLIENT_SECRET)%'
    webhook_secret: '%env(KINDE_WEBHOOK_SECRET)%'
    jwks_cache_ttl: 3600
    enable_webhook_route: true
```

### 3. Updated `composer.json`

Added Symfony Flex configuration:

```json
"extra": {
    "symfony": {
        "allow-contrib": false,
        "require": "^6.4|^7.0"
    }
}
```

### 4. Updated Documentation

- Added installation troubleshooting to `INSTALL.md`
- Updated `README.md` with environment variable setup instructions
- Added version 1.0.1 to `CHANGELOG.md`

## How It Works Now

When a user runs:
```bash
composer require habityzer/kinde-bundle
```

Symfony Flex will automatically:

1. Copy `config/packages/habityzer_kinde.yaml` to their project
2. Add the environment variables to their `.env` file with placeholder values
3. Register the bundle in `config/bundles.php`
4. The `cache:clear` will succeed because the configuration file exists with valid (placeholder) values

## User Action Required

After installation, users only need to:

1. Update their `.env` file with real Kinde credentials:
```env
KINDE_DOMAIN=your-actual-domain.kinde.com
KINDE_CLIENT_ID=your-actual-client-id
KINDE_CLIENT_SECRET=your-actual-secret
KINDE_WEBHOOK_SECRET=your-webhook-secret
```

2. Implement the `KindeUserProviderInterface` (as documented in the README)

## Testing

To test this fix, create a new Symfony project and run:

```bash
composer require habityzer/kinde-bundle
```

The installation should complete without errors, and you should find:
- `config/packages/habityzer_kinde.yaml` created
- Environment variables added to `.env`
- Bundle registered in `config/bundles.php`

## Release Notes

This fix will be released as version 1.0.1 and includes:
- Symfony Flex recipe for automatic configuration
- Updated documentation with troubleshooting
- Improved installation experience

## References

- [Symfony Flex Documentation](https://symfony.com/doc/current/setup/flex.html)
- [Creating Symfony Recipes](https://symfony.com/doc/current/bundles/best_practices.html#creating-a-symfony-flex-recipe)

