# Publishing Version 1.0.1

## Summary

This version fixes the installation error where required configuration parameters were not set, causing `cache:clear` to fail during initial `composer require`.

## Pre-Release Checklist

- [x] Version bumped to 1.0.1 in `composer.json`
- [x] CHANGELOG.md updated with version 1.0.1 notes
- [x] Symfony Flex recipe created (`manifest.json`)
- [x] Configuration file template created (`config/packages/habityzer_kinde.yaml`)
- [x] Documentation updated (README.md, INSTALL.md)
- [x] No linter errors

## Files Changed

1. **composer.json** - Added version 1.0.1 and Symfony Flex extra configuration
2. **manifest.json** - New: Symfony Flex recipe manifest
3. **config/packages/habityzer_kinde.yaml** - New: Configuration template
4. **CHANGELOG.md** - Added v1.0.1 release notes
5. **README.md** - Added environment variable setup instructions
6. **INSTALL.md** - Added troubleshooting section for configuration error
7. **docs/INSTALLATION_FIX.md** - New: Detailed explanation of the fix

## Publishing Steps

### 1. Commit Changes

```bash
git add .
git commit -m "Release v1.0.1: Add Symfony Flex recipe to fix installation error"
```

### 2. Create Git Tag

```bash
git tag -a v1.0.1 -m "Version 1.0.1

- Added Symfony Flex recipe for automatic configuration
- Fixed installation error with missing required configuration
- Updated documentation with troubleshooting steps"
```

### 3. Push to Repository

```bash
git push origin master
git push origin v1.0.1
```

### 4. Publish to Packagist (if using Packagist)

If you haven't already, submit your package to Packagist:
1. Go to https://packagist.org/packages/submit
2. Enter your repository URL: https://github.com/habityzer/kinde-bundle
3. Packagist will automatically track new tags

If already published, Packagist will automatically detect the new tag within a few minutes.

### 5. Verify Installation

Test in a fresh Symfony project:

```bash
# Create new Symfony project
symfony new test-project --version=7.1
cd test-project

# Add your bundle
composer require habityzer/kinde-bundle

# Verify files were created
ls -la config/packages/habityzer_kinde.yaml
cat .env | grep KINDE_

# Should complete without errors
php bin/console cache:clear
```

## Notes for Private Repository (if not on Packagist)

If you're using this bundle privately (not on Packagist), users need to add your repository to their `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/habityzer/kinde-bundle"
        }
    ]
}
```

Then they can require it:
```bash
composer require habityzer/kinde-bundle:^1.0.1
```

## What's Next?

After publishing v1.0.1:

1. **Test in Production** - Verify the installation works in a real project
2. **Monitor Issues** - Watch for any bug reports related to installation
3. **Consider Future Improvements**:
   - Add more configuration options
   - Create official Symfony Flex recipe in symfony/recipes-contrib
   - Add integration tests for the Flex recipe

## Rollback Plan

If issues are discovered with v1.0.1:

1. Delete the tag:
   ```bash
   git tag -d v1.0.1
   git push origin :refs/tags/v1.0.1
   ```

2. Fix the issues and create v1.0.2

3. Document the issue in CHANGELOG.md

## Support

If users still encounter installation issues:

1. Direct them to the troubleshooting section in INSTALL.md
2. Ask them to verify their Symfony version (must be 6.4+ or 7.x)
3. Check if they're using Symfony Flex (should be enabled by default)
4. Verify their .env file has the required variables

## Official Symfony Flex Recipe (Optional Future Step)

To make this bundle even more discoverable, consider submitting to the official Symfony Flex repository:

1. Fork https://github.com/symfony/recipes-contrib
2. Create a directory: `habityzer/kinde-bundle/1.0/`
3. Copy `manifest.json` and `config/` directory there
4. Submit a Pull Request

Benefits:
- Better integration with Symfony ecosystem
- More visibility for the bundle
- Community validation of the recipe

