# Token Prefix Update - Breaking Change

## Summary

The KindeTokenAuthenticator now requires tokens to be prefixed with `kinde_` instead of checking for `app_` prefix to skip. This allows better coexistence with multiple authentication methods.

## What Changed

### 1. Token Format (BREAKING CHANGE)

**Before:**
```http
Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
```

**After:**
```http
Authorization: Bearer kinde_eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
```

### 2. Authenticator Behavior

**Before:**
- Accepted all Bearer tokens except those starting with `app_`
- `app_` prefix was project-specific assumption

**After:**
- Only accepts tokens with `kinde_` prefix
- Prefix is automatically removed before JWT validation
- Better separation between authentication methods

### 3. Debug Logging

The authenticator now logs support decisions:
- `[DEBUG] KindeTokenAuthenticator: Supporting request - valid Kinde token detected`
- `[DEBUG] KindeTokenAuthenticator: Not supporting request - token does not start with kinde_ prefix`
- `[DEBUG] KindeTokenAuthenticator: Not supporting request - no Bearer token found`
- `[DEBUG] KindeTokenAuthenticator: Not supporting request - test environment`

## Migration Guide

### Frontend Changes Required

#### JavaScript/TypeScript
```javascript
// Before
const token = await kinde.getToken();
fetch('/api/endpoint', {
    headers: {
        'Authorization': `Bearer ${token}`
    }
});

// After - Add kinde_ prefix
const token = await kinde.getToken();
fetch('/api/endpoint', {
    headers: {
        'Authorization': `Bearer kinde_${token}`
    }
});
```

#### PHP/Symfony HttpClient
```php
// Before
$response = $httpClient->request('GET', '/api/endpoint', [
    'headers' => [
        'Authorization' => 'Bearer ' . $kindeToken,
    ],
]);

// After - Add kinde_ prefix
$response = $httpClient->request('GET', '/api/endpoint', [
    'headers' => [
        'Authorization' => 'Bearer kinde_' . $kindeToken,
    ],
]);
```

#### cURL
```bash
# Before
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
     https://your-api.com/api/endpoint

# After - Add kinde_ prefix
curl -H "Authorization: Bearer kinde_YOUR_JWT_TOKEN" \
     https://your-api.com/api/endpoint
```

### No Backend Changes Required

The bundle automatically:
1. Detects tokens with `kinde_` prefix in `supports()`
2. Removes the prefix in `authenticate()` before validation
3. Validates the pure JWT with Kinde JWKS

## Testing

### 1. Update Your Tests

If you have integration tests that send tokens:

```php
// Before
$client->request('GET', '/api/endpoint', [], [], [
    'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
]);

// After
$client->request('GET', '/api/endpoint', [], [], [
    'HTTP_AUTHORIZATION' => 'Bearer kinde_' . $token,
]);
```

### 2. Verify Logging

Enable debug logging to verify behavior:

```yaml
# config/packages/dev/monolog.yaml
monolog:
    handlers:
        security:
            type: stream
            path: "%kernel.logs_dir%/security.log"
            level: debug
            channels: [security]
```

Then check logs:
```bash
tail -f var/log/security.log | grep KindeTokenAuthenticator
```

### 3. Test Without Prefix

Try sending a request without the `kinde_` prefix to verify it's rejected:

```bash
curl -H "Authorization: Bearer eyJhbGciOi..." \
     https://your-api.com/api/endpoint
```

Expected: 401 Unauthorized
Log: `[DEBUG] KindeTokenAuthenticator: Not supporting request - token does not start with kinde_ prefix`

## Multiple Authenticators

If you're using multiple authenticators, update your custom authenticators:

```php
class CustomTokenAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        $authHeader = $request->headers->get('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return false;
        }
        
        $token = substr($authHeader, 7);
        
        // Handle tokens that are NOT Kinde tokens
        // Example: app_ prefix for API keys
        return str_starts_with($token, 'app_');
    }
}
```

## Debug Command

The `kinde:debug-token` command now accepts tokens with the prefix:

```bash
# All these work:
php bin/console kinde:debug-token eyJhbGciOi...
php bin/console kinde:debug-token kinde_eyJhbGciOi...
php bin/console kinde:debug-token "Bearer kinde_eyJhbGciOi..."
```

The command automatically strips prefixes and will show a note when it removes `kinde_`.

## Troubleshooting

### Error: "Token validation failed: Malformed UTF-8 characters"

**Cause:** Token includes the `kinde_` prefix but the authenticator isn't removing it before validation.

**Solution:** Update to the latest version of the bundle. The authenticator now properly removes the prefix in `authenticate()`.

### Error: 401 Unauthorized with log "Not supporting request - token does not start with kinde_ prefix"

**Cause:** You forgot to add the `kinde_` prefix in your frontend.

**Solution:** Update your frontend code to prepend `kinde_` to tokens:
```javascript
'Authorization': `Bearer kinde_${token}`
```

### No logs appearing

**Cause:** Logger not configured or log level too high.

**Solution:** Check that:
1. Monolog is installed
2. Security channel is configured
3. Log level is set to `debug` for development

## Files Changed

### Source Code
- `src/Security/KindeTokenAuthenticator.php`
  - Updated `supports()` to check for `kinde_` prefix
  - Updated `authenticate()` to remove `kinde_` prefix before validation
  - Added debug logging throughout
  
- `src/Command/DebugKindeTokenCommand.php`
  - Added `kinde_` prefix removal support

### Documentation
- `README.md` - Added token format section and examples
- `INSTALL.md` - Updated test examples with `kinde_` prefix
- `docs/ADVANCED.md` - Updated "Multiple Authenticators" section
- `docs/SERVICES.md` - Updated authenticator behavior documentation
- `docs/KINDE_SETUP.md` - Added token format requirements and examples

## Benefits

1. **Explicit Token Identification** - Clear distinction between Kinde and other tokens
2. **Better Coexistence** - Multiple authentication methods work seamlessly
3. **Improved Debugging** - Debug logs show exactly why tokens are accepted/rejected
4. **Framework Agnostic** - Not tied to project-specific conventions like `app_` prefix
5. **Self-Documenting** - Code clearly shows intent to handle Kinde tokens

## Questions?

If you have issues migrating, please:
1. Check debug logs for support decision messages
2. Verify token format with `kinde:debug-token` command
3. Open an issue with log output and token format used

