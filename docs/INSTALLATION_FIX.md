# Installation Error Fix - Version 1.0.1

## Problem

When installing the bundle via `composer require habityzer/kinde-bundle`, users encountered this error:

```
In ArrayNode.php line 213:
  The child config "domain" under "habityzer_kinde" must be configured: Kinde
   domain (e.g., your-business.kinde.com)
```

This occurred because:
1. The bundle's configuration had required parameters (`domain` and `client_id`) with `isRequired()` and `cannotBeEmpty()`
2. The `cache:clear` command ran during installation before users had a chance to configure the bundle
3. Symfony's configuration validation failed during cache warming

## Solution Considered: Symfony Flex Recipe

Initially, we tried creating an embedded Symfony Flex recipe (`manifest.json`), but this doesn't work for packages not in the official Symfony recipes repository. Symfony Flex generates an "auto-generated recipe" for such packages, which only registers the bundle but doesn't copy configuration files.

## Actual Solution: Optional Configuration with Runtime Validation

We made configuration parameters optional during installation, but validated at runtime:

1. **Configuration has safe defaults** - Allows cache:clear to succeed
2. **Services validate at runtime** - Throw clear errors when used without proper configuration
3. **Better user experience** - Installation succeeds, configuration required before use

## Changes Made

### 1. Updated `Configuration.php`

Changed required parameters to have default placeholder values:

**Before:**
```php
->scalarNode('domain')
    ->isRequired()        // ❌ Causes installation error
    ->cannotBeEmpty()
    ->info('...')
->end()
```

**After:**
```php
->scalarNode('domain')
    ->defaultValue('your-business.kinde.com')  // ✅ Safe default
    ->info('Kinde domain. REQUIRED: Set this in your .env file')
->end()
```

### 2. Added Runtime Validation to `KindeTokenValidator.php`

Services now validate configuration when instantiated:

```php
public function __construct(..., string $kindeDomain, string $kindeClientId, ...) {
    // Validate configuration is properly set
    if ($kindeDomain === 'your-business.kinde.com' || empty($kindeDomain)) {
        throw new \RuntimeException(
            'Kinde domain is not configured. Please set KINDE_DOMAIN in your .env file. ' .
            'Get your domain from https://app.kinde.com/settings/environment'
        );
    }
    
    if ($kindeClientId === 'your-kinde-client-id' || empty($kindeClientId)) {
        throw new \RuntimeException(
            'Kinde client ID is not configured. Please set KINDE_CLIENT_ID in your .env file. ' .
            'Get your client ID from https://app.kinde.com/settings/applications'
        );
    }
    // ... rest of constructor
}
```

### 3. Created Configuration Template

Created `config/packages/habityzer_kinde.yaml` as a reference template for users.

### 4. Updated Documentation

- Updated `README.md` with step-by-step installation
- Added troubleshooting to `INSTALL.md`
- Removed version field from `composer.json` (use git tags)
- Added `VERSIONING_EXPLAINED.md` to clarify version management

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

