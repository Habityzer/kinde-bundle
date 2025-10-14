# How to Submit Your Recipe to Symfony Recipes

To get automatic configuration (like popular bundles do), you need to submit your recipe to the official Symfony Flex recipes repository.

## What This Enables

Once your recipe is in the official repository, when users run:
```bash
composer require habityzer/kinde-bundle
```

Symfony Flex will automatically:
- ✅ Create `config/packages/habityzer_kinde.yaml` with proper configuration
- ✅ Add environment variables to `.env` file
- ✅ Show a helpful "What's next?" message
- ✅ Register the bundle in `config/bundles.php`

**Just like popular bundles like Doctrine, Twig, Mailer, etc.**

## Steps to Submit

### 1. Choose the Right Repository

There are two official recipe repositories:

- **symfony/recipes** - For recipes that are very stable and widely used
- **symfony/recipes-contrib** - For community contributions (start here)

Start with `symfony/recipes-contrib`.

### 2. Fork the Repository

1. Go to https://github.com/symfony/recipes-contrib
2. Click "Fork" to create your own copy

### 3. Create Your Recipe

In your fork, create this directory structure:

```
symfony/recipes-contrib/
└── habityzer/
    └── kinde-bundle/
        └── 1.0/
            ├── manifest.json
            ├── config/
            │   └── packages/
            │       └── habityzer_kinde.yaml
            └── post-install.txt (optional but recommended)
```

I've already created this structure for you in the `recipe-for-contrib/` directory!

### 4. Copy Your Recipe Files

```bash
# In your fork of symfony/recipes-contrib:
mkdir -p habityzer/kinde-bundle/1.0
cp -r /path/to/kinde-bundle/recipe-for-contrib/* habityzer/kinde-bundle/1.0/
```

The directory name `1.0` means this recipe will be used for versions `>=1.0`.

### 5. Test Your Recipe Locally (Optional but Recommended)

Before submitting, test it locally:

```bash
# In your test Symfony project
composer config extra.symfony.endpoint '["https://api.github.com/repos/YOUR_USERNAME/recipes-contrib/contents/index.json","flex://defaults"]'
composer require habityzer/kinde-bundle
```

### 6. Commit and Create Pull Request

```bash
# In your fork of symfony/recipes-contrib
git checkout -b recipe-habityzer-kinde-bundle
git add habityzer/kinde-bundle/
git commit -m "Add recipe for habityzer/kinde-bundle"
git push origin recipe-habityzer-kinde-bundle
```

Then go to GitHub and create a Pull Request to `symfony/recipes-contrib:main`.

### 7. Pull Request Description Template

```markdown
## Package Information

- Package name: habityzer/kinde-bundle
- Package URL: https://packagist.org/packages/habityzer/kinde-bundle
- GitHub repository: https://github.com/habityzer/kinde-bundle
- Minimum version: 1.0.0

## Description

Symfony bundle for Kinde authentication integration with JWT validation, webhooks, and user synchronization.

## Recipe Actions

This recipe:
- Registers the bundle
- Creates configuration file in config/packages/
- Adds required environment variables to .env
- Shows helpful post-install message with setup instructions

## Testing

Tested with:
- Symfony 6.4
- Symfony 7.0
- PHP 8.2

## Checklist

- [x] I have read the [contribution guidelines](https://github.com/symfony/recipes-contrib/blob/main/CONTRIBUTING.md)
- [x] The recipe works with all supported Symfony versions
- [x] Environment variables have sensible defaults
- [x] Post-install message is helpful and concise
```

## What Happens Next?

1. **Review** - Symfony core team and community will review your recipe
2. **Feedback** - They may ask for changes (usually minor tweaks)
3. **Approval** - Once approved, your recipe is merged
4. **Activation** - Within hours, it becomes available to everyone

From that point on, everyone who installs your bundle will get automatic configuration!

## Important Notes

### Recipe Updates

If you need to update your recipe later:
- Minor changes to existing version: Create a new PR updating `1.0/`
- Breaking changes: Create a new version directory like `2.0/`

### Version Requirements

The recipe version (e.g., `1.0/`) is the **minimum package version** that recipe supports. So:
- `1.0/` recipe works for versions `>=1.0.0`
- If you create `2.0/` later, it will be used for `>=2.0.0`

### Recipe Guidelines

Symfony recipes should:
- ✅ Be minimal (only essential configuration)
- ✅ Use environment variables for sensitive data
- ✅ Have sensible defaults
- ✅ Include helpful comments
- ✅ Show a concise post-install message
- ❌ Not include too much opinionated configuration
- ❌ Not require complex manual steps

## Current State vs. With Recipe

### Current State (Without Official Recipe)

```bash
composer require habityzer/kinde-bundle
# ✅ Installs
# ⚠️ User must manually create config/packages/habityzer_kinde.yaml
# ⚠️ User must manually add env vars to .env
# ⚠️ No guidance shown
```

### With Official Recipe

```bash
composer require habityzer/kinde-bundle
# ✅ Installs
# ✅ Automatically creates config/packages/habityzer_kinde.yaml
# ✅ Automatically adds env vars to .env
# ✅ Shows helpful "What's next?" message
# ✅ Just like Doctrine, Mailer, etc.
```

## Alternative: Keep Current Approach

If you don't want to submit to the official repository, your current approach is fine:
- ✅ Bundle installs successfully
- ✅ Runtime validation provides helpful errors
- ⚠️ Users must manually create config file (documented in README)

Many bundles work this way successfully!

## Resources

- Symfony Flex Recipes: https://github.com/symfony/recipes-contrib
- Contributing Guide: https://github.com/symfony/recipes-contrib/blob/main/CONTRIBUTING.md
- Recipe Format: https://symfony.com/doc/current/setup/flex.html

## Questions?

If you have questions during submission:
- Ask in the PR comments
- Join Symfony Slack: https://symfony.com/slack
- Check #recipes channel

The Symfony community is very helpful! 🎉

