# Cache Invalidation — Quickbase Webhook + Zapier Setup

When a Course Catalog record is saved in Quickbase, this pipeline automatically clears the WordPress course catalog cache so visitors see fresh data within seconds instead of waiting up to 15 minutes.

**Flow:** QB record save → QB Webhook → Zapier Catch Hook → Zapier POST → WP REST endpoint → delete transient

---

## Step 1 — Generate a Cache Bust Token

On the WordPress server, generate a strong random secret:

```bash
openssl rand -hex 32
```

Copy the output. You will use this value in both wp-config.php and the Zapier Zap.

Add to `wp-config.php`:

```php
define( 'ARC_QB_CACHE_BUST_TOKEN', 'paste-your-token-here' );
```

---

## Step 2 — Create the Zapier Zap

### Trigger: Webhooks by Zapier — Catch Hook

1. In Zapier, create a new Zap.
2. Choose **Trigger:** Webhooks by Zapier → **Catch Hook**.
3. Copy the generated webhook URL (you will paste it into Quickbase).
4. Click **Continue**.

### Action: Webhooks by Zapier — POST

1. Choose **Action:** Webhooks by Zapier → **POST**.
2. **URL:** `https://thearcoregon.org/wp-json/arc-qb-sync/v1/bust-cache`
3. **Payload Type:** `json`
4. **Data:** leave empty (no body required)
5. **Headers:**
   - Key: `Authorization`
   - Value: `Bearer paste-your-token-here`
6. Click **Continue** and **Test** the action (see Step 4 below).
7. **Name and publish** the Zap.

---

## Step 3 — Create the Quickbase Webhook

1. In the Quickbase app, open the **Course Catalog** table.
2. Go to **Settings → Webhooks** (or the table automation/webhook panel).
3. Click **New Webhook** (or **New Notification**).
4. **Trigger:** Record Changed (or Record Saved — any save event).
5. **URL to notify:** paste the Zapier webhook URL from Step 2.
6. **Method:** POST
7. **Content type:** `application/json`
8. **Fields to send:** you can send any field or leave as minimal payload — Zapier only needs to receive the trigger; it does not use the QB payload.
9. Save the webhook.

---

## Step 4 — Test the Pipeline

1. In Quickbase, open any Course Catalog record and save it (no changes needed).
2. In Zapier, check **Task History** for the Zap — the POST action should show a 200 response with body `{"success":true,"message":"Cache cleared."}`.
3. Alternatively, test the WP endpoint directly:

```bash
curl -X POST https://thearcoregon.org/wp-json/arc-qb-sync/v1/bust-cache \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

Expected response:

```json
{"success":true,"message":"Cache cleared."}
```

4. Load the `/training` page — the catalog should now show the latest data from Quickbase.

---

## Troubleshooting

| Symptom | Check |
|---|---|
| Zapier task fails with 401 | Verify the Bearer token in the Zapier header matches `ARC_QB_CACHE_BUST_TOKEN` in wp-config.php exactly |
| Zapier task fails with 404 | Confirm the plugin is active; visit `/wp-json/arc-qb-sync/v1/bust-cache` in a browser (expect a 405 Method Not Allowed, which confirms the route is registered) |
| Cache bust succeeds but catalog shows stale data | The page may be cached by a page caching plugin (e.g. WP Rocket, LiteSpeed). Purge the page cache for `/training` as well |
| QB webhook not firing | Check QB table → Settings → Webhooks and confirm the webhook is active; QB may require a specific user role to set webhooks |
