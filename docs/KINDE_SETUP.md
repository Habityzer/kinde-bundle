# Kinde Dashboard Setup Guide

This guide walks you through configuring your Kinde application to work with the Habityzer Kinde Bundle.

## Prerequisites

- A Kinde account ([Sign up at kinde.com](https://kinde.com))
- A Symfony application with the bundle installed

---

## Step 1: Create a Kinde Business

1. Go to [Kinde Dashboard](https://app.kinde.com)
2. Click **Create business** if you don't have one
3. Choose your business name and region
4. Complete the setup wizard

---

## Step 2: Create an Application

1. Navigate to **Settings** → **Applications**
2. Click **Add application**
3. Choose application type:
   - **Single Page Application (SPA)** - For React, Vue, Nuxt, etc.
   - **Back-end web** - For server-side applications
   - **Machine to Machine (M2M)** - For service-to-service communication
4. Name your application and click **Save**

---

## Step 3: Get Your Credentials

After creating the application, you'll see your credentials:

### From Applications Settings

Navigate to **Settings** → **Applications** → **[Your App]**:

| Field | Environment Variable | Description |
|-------|---------------------|-------------|
| **Domain** | `KINDE_DOMAIN` | Your Kinde domain (e.g., `https://your-business.kinde.com`) |
| **Client ID** | `KINDE_CLIENT_ID` | Application client ID |
| **Client secret** | `KINDE_CLIENT_SECRET` | Application client secret (keep this safe!) |

### Example .env Configuration

```env
KINDE_DOMAIN=https://your-business.kinde.com
KINDE_CLIENT_ID=a1b2c3d4e5f6g7h8i9j0
KINDE_CLIENT_SECRET=your-super-secret-client-secret
KINDE_WEBHOOK_SECRET=your-webhook-signing-secret
```

---

## Step 4: Configure OAuth Scopes

The bundle requires specific scopes to function correctly.

### Required Scopes

| Scope | Purpose |
|-------|---------|
| `openid` | Required for OpenID Connect |
| `profile` | Access to user profile (name, picture) |
| `email` | Access to user email |

### Configure in Kinde

1. Go to **Settings** → **Applications** → **[Your App]**
2. Scroll to **Authentication** section
3. Ensure the following scopes are enabled:
   - `openid`
   - `profile`
   - `email`
   - `offline` (optional, for refresh tokens)

### Configure in Your Frontend

When initializing Kinde SDK in your frontend application:

```javascript
// Nuxt/Vue example
const kinde = createKindeClient({
  clientId: 'your-client-id',
  domain: 'https://your-business.kinde.com',
  redirectUri: 'http://localhost:3000/callback',
  scope: 'openid profile email offline', // Include all required scopes
});
```

```javascript
// React example
<KindeProvider
  clientId="your-client-id"
  domain="https://your-business.kinde.com"
  redirectUri="http://localhost:3000/callback"
  scope="openid profile email offline"
>
```

> **Important:** If `email` scope is not requested, the token won't contain the email claim. The bundle will fall back to fetching from the UserInfo endpoint, but including the scope is more efficient.

---

## Step 5: Configure Callback URLs

Set up allowed URLs for your application:

1. Go to **Settings** → **Applications** → **[Your App]**
2. Configure the following URLs:

### Allowed callback URLs

URLs where users can be redirected after login:

```
http://localhost:3000/callback
http://localhost:3000/api/auth/kinde_callback
https://your-production-domain.com/callback
```

### Allowed logout redirect URLs

URLs where users can be redirected after logout:

```
http://localhost:3000
https://your-production-domain.com
```

### Allowed origins (CORS)

Origins allowed to make requests:

```
http://localhost:3000
https://your-production-domain.com
```

---

## Step 6: Set Up Webhooks

Webhooks allow Kinde to notify your Symfony application of events.

### Create Webhook Endpoint

1. Go to **Settings** → **Webhooks**
2. Click **Add webhook**
3. Configure:

| Field | Value |
|-------|-------|
| **Name** | Your webhook name (e.g., "Symfony Backend") |
| **Endpoint URL** | `https://your-api.com/api/webhooks/kinde` |
| **Description** | Optional description |

### Get Webhook Secret

1. After creating the webhook, click on it
2. Copy the **Signing secret**
3. Add to your `.env`:

```env
KINDE_WEBHOOK_SECRET=whsec_your_signing_secret_here
```

### Select Events

Choose which events to receive:

#### User Events
- ✅ `user.updated` - User information changed
- ✅ `user.deleted` - User deleted from Kinde
- ✅ `user.authenticated` - User logged in (optional)

#### Subscription Events (if using Kinde billing)
- ✅ `subscription.created` - New subscription
- ✅ `subscription.updated` - Subscription plan changed
- ✅ `subscription.cancelled` - Subscription cancelled
- ✅ `subscription.reactivated` - Subscription reactivated

### Webhook Security

The bundle automatically verifies webhook signatures using HMAC SHA256. Ensure your webhook secret is:
- Kept secret (never commit to version control)
- Stored in environment variables
- Rotated if compromised

---

## Step 7: Configure Token Settings

### Access Token Configuration

1. Go to **Settings** → **Applications** → **[Your App]** → **Tokens**
2. Configure access token settings:

| Setting | Recommended Value | Description |
|---------|-------------------|-------------|
| **Token lifetime** | 3600 (1 hour) | How long access tokens are valid |
| **Include email in token** | ✅ Enabled | Ensures email is in the JWT |

### ID Token vs Access Token

- **ID Token**: Contains user identity claims (email, name, etc.)
- **Access Token**: Used to authorize API requests

The bundle works with access tokens. Ensure your frontend sends the access token (not ID token) in API requests.

**Important:** The bundle requires tokens to be prefixed with `kinde_` to identify them as Kinde tokens and allow coexistence with other authentication methods.

```javascript
// Frontend: Get access token from Kinde
const accessToken = await kinde.getToken();

// Add kinde_ prefix before sending to your API
fetch('https://your-api.com/api/endpoint', {
  headers: {
    'Authorization': `Bearer kinde_${accessToken}`,
  },
});
```

**Why the prefix?** 
- Allows the authenticator to identify Kinde tokens
- Enables multiple authentication methods (Kinde + API keys, etc.)
- The authenticator automatically strips the prefix before validating the JWT

---

## Step 8: Configure Organizations (Optional)

If using Kinde organizations:

1. Go to **Organizations**
2. Create or configure your organization
3. The `org_code` will be included in tokens

Access organization in your code:

```php
$payload = $this->tokenValidator->validateToken($token);
$orgCode = $payload['org_code'] ?? null;
```

---

## Step 9: Test Configuration

### Test JWT Token

Use the debug command to verify your token contains expected claims:

```bash
# Get a token from your frontend app, then:
php bin/console kinde:debug-token YOUR_JWT_TOKEN
```

Expected output should include:
- `sub` (Kinde user ID)
- `email` (user email)
- `iss` (your Kinde domain)
- `aud` or `azp` (your client ID)

### Test API Authentication

**Important:** Remember to add the `kinde_` prefix to your token:

```bash
# Add kinde_ prefix to your JWT token
curl -H "Authorization: Bearer kinde_YOUR_JWT_TOKEN" \
     https://your-api.com/api/protected-endpoint
```

If you forget the prefix, you'll see in the logs:

```
[DEBUG] KindeTokenAuthenticator: Not supporting request - token does not start with kinde_ prefix
```

### Test Webhook

1. In Kinde Dashboard, go to your webhook
2. Click **Send test event**
3. Check your Symfony logs for the received event

---

## Troubleshooting

### "Invalid token audience"

**Cause:** Token's `aud` or `azp` claim doesn't match your `KINDE_CLIENT_ID`

**Solutions:**
1. Verify `KINDE_CLIENT_ID` in your `.env` matches Kinde dashboard
2. Ensure frontend is using the same client ID
3. Clear Symfony cache: `php bin/console cache:clear`

### "Email missing from token"

**Cause:** Email scope not requested or not enabled

**Solutions:**
1. Add `email` to scope in frontend SDK
2. Enable "Include email in token" in Kinde dashboard
3. The bundle will fall back to UserInfo endpoint, but this is less efficient

### "Invalid token issuer"

**Cause:** Token's `iss` claim doesn't match `KINDE_DOMAIN`

**Solutions:**
1. Verify `KINDE_DOMAIN` includes `https://`
2. Ensure no trailing slash in domain
3. Example: `https://your-business.kinde.com` (not `your-business.kinde.com`)

### "Webhook signature verification failed"

**Cause:** Webhook secret mismatch

**Solutions:**
1. Copy the exact signing secret from Kinde webhook settings
2. Ensure no extra spaces or newlines in `.env`
3. Check that `KINDE_WEBHOOK_SECRET` is set in production

### "Failed to fetch JWKS"

**Cause:** Network issue or incorrect domain

**Solutions:**
1. Verify `KINDE_DOMAIN` is correct
2. Check network connectivity from your server
3. Try: `curl https://your-business.kinde.com/.well-known/jwks.json`

---

## Security Best Practices

1. **Never expose secrets**
   - Keep `KINDE_CLIENT_SECRET` and `KINDE_WEBHOOK_SECRET` in environment variables
   - Never commit secrets to version control

2. **Use HTTPS in production**
   - Webhook endpoints must use HTTPS
   - All Kinde communication is over HTTPS

3. **Rotate secrets periodically**
   - Regenerate webhook secrets if compromised
   - Update client secrets as needed

4. **Validate webhooks**
   - The bundle automatically verifies signatures
   - Never disable signature verification in production

5. **Secure your firewall**
   - Ensure webhook endpoint is publicly accessible but properly configured
   - API endpoints should require authentication

---

## Additional Resources

- [Kinde Documentation](https://docs.kinde.com/)
- [Kinde API Reference](https://docs.kinde.com/api/)
- [JWT.io Debugger](https://jwt.io/) - Inspect token contents
- [Bundle README](../README.md) - Quick start guide


