# Quick Recipe Setup Guide

## Yes, You Can Get Automatic Configuration! 🎉

To get automatic config file creation and .env updates (like Doctrine, Mailer, etc.), you need to submit your recipe to **symfony/recipes-contrib**.

## I've Prepared Everything For You

Check the `recipe-for-contrib/` directory - I've created the complete recipe ready to submit!

```
recipe-for-contrib/
├── manifest.json              # Tells Flex what to do
├── config/
│   └── packages/
│       └── habityzer_kinde.yaml  # Auto-created config file
└── post-install.txt           # Friendly "What's next?" message
```

## Quick Start: 3 Steps

### 1. Fork the Recipes Repository

Go to https://github.com/symfony/recipes-contrib and click **Fork**.

### 2. Add Your Recipe

In your fork, create this structure:

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/recipes-contrib
cd recipes-contrib

# Create directory for your recipe (1.0 = works for versions >=1.0.0)
mkdir -p habityzer/kinde-bundle/1.0

# Copy the prepared recipe
cp -r /path/to/kinde-bundle/recipe-for-contrib/* habityzer/kinde-bundle/1.0/
```

### 3. Submit Pull Request

```bash
git checkout -b recipe-habityzer-kinde-bundle
git add habityzer/kinde-bundle/
git commit -m "Add recipe for habityzer/kinde-bundle"
git push origin recipe-habityzer-kinde-bundle
```

Then create a PR on GitHub to `symfony/recipes-contrib:main`.

## What Users Will See After Approval

```bash
$ composer require habityzer/kinde-bundle

Symfony operations: 1 recipe
  - Configuring habityzer/kinde-bundle (>=1.0.0): From github.com/symfony/recipes-contrib

✅ Configuration file created: config/packages/habityzer_kinde.yaml
✅ Environment variables added to .env:
   - KINDE_DOMAIN
   - KINDE_CLIENT_ID
   - KINDE_CLIENT_SECRET
   - KINDE_WEBHOOK_SECRET

              
 What's next? 
              
  * Configure your Kinde credentials in .env
  * Implement the KindeUserProviderInterface
  * Enable the authenticator in security.yaml
```

**Perfect installation experience! 🎉**

## Timeline

- **Submit PR**: Today
- **Review**: 1-7 days (usually quick for simple recipes)
- **Approved & Merged**: Available immediately after merge
- **Everyone benefits**: From next `composer require`

## Don't Want to Submit?

That's okay! Your current approach works fine:
- ✅ Installation succeeds
- ✅ Runtime validation gives helpful errors
- ⚠️ Users manually create config (documented in README)

Many bundles work this way!

## More Details

See `HOW_TO_SUBMIT_RECIPE.md` for complete instructions, PR template, and guidelines.

## Need Help?

- Symfony Slack: https://symfony.com/slack (#recipes channel)
- PR comments: The community is very helpful!

