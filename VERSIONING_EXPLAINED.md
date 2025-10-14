# Versioning Explained

## The "v" Prefix Confusion

You may notice that git tags have a "v" prefix (like `v1.0.1`) but Packagist shows versions without it (like `1.0.1`). **This is normal and expected!**

## How It Works

### Git Tags (with "v")
```bash
git tag -a v1.0.0 -m "Release 1.0.0"
git tag -a v1.0.1 -m "Release 1.0.1"
git tag -a v1.0.2 -m "Release 1.0.2"
```

Git tags use the "v" prefix by convention. This is standard practice in the PHP ecosystem.

### Composer.json (NO version field)

```json
{
    "name": "habityzer/kinde-bundle",
    "description": "...",
    "type": "symfony-bundle"
}
```

**DO NOT** include a `"version"` field in `composer.json`. Composer automatically:
1. Reads git tags from your repository
2. Normalizes them by removing the "v" prefix
3. Uses them as package versions

### Packagist (without "v")

On Packagist, you'll see:
- ✅ `1.0.0`
- ✅ `1.0.1`
- ✅ `1.0.2`

This is Composer's normalized version format (without the "v").

## Why This Matters

### ❌ Wrong Approach (Don't Do This)

```json
{
    "name": "habityzer/kinde-bundle",
    "version": "1.0.1",  ← DON'T DO THIS
    "description": "..."
}
```

Problems:
- Version in `composer.json` overrides git tags
- You have to manually update it with every release
- Can cause version conflicts
- Not the recommended practice

### ✅ Correct Approach (Do This)

**composer.json:**
```json
{
    "name": "habityzer/kinde-bundle",
    "description": "..."
    // No "version" field - let git tags handle it
}
```

**Git tags:**
```bash
git tag -a v1.0.1 -m "Release 1.0.1"
git push origin v1.0.1
```

**Result:**
- Composer reads tag `v1.0.1`
- Normalizes to `1.0.1`
- Packagist shows `1.0.1`
- Everything works perfectly ✓

## Summary

| Location | Format | Example |
|----------|--------|---------|
| Git Tags | `v{major}.{minor}.{patch}` | `v1.0.1` |
| Packagist | `{major}.{minor}.{patch}` | `1.0.1` |
| composer.json | NO version field | N/A |
| CHANGELOG.md | Use whatever you prefer | `[1.0.1]` or `[v1.0.1]` |

## What You See on Packagist

Looking at your package on Packagist:
- `dev-master` ← The master branch
- `1.0.1` ← From git tag `v1.0.1` (v was removed automatically)
- `v1.0.0` ← From git tag `v1.0.0`

**Wait, why does v1.0.0 still have the "v"?**

This can happen if:
1. The old version had `"version": "v1.0.0"` in composer.json (incorrect)
2. Or it's just Packagist's display inconsistency

After you remove the version field from composer.json and push, all future tags will be consistently displayed without "v".

## References

- [Composer Versions Documentation](https://getcomposer.org/doc/articles/versions.md)
- [Semantic Versioning](https://semver.org/)
- [Composer Best Practices](https://getcomposer.org/doc/04-schema.md#version)

## TL;DR

1. ✅ Use git tags with "v": `v1.0.1`
2. ✅ DON'T put version in `composer.json`
3. ✅ Packagist will show: `1.0.1` (without v)
4. ✅ This is normal and correct!

