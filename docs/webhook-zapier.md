# Incremental Course Sync — Quickbase Webhook + Zapier Setup

When a Course Catalog record is saved in Quickbase, this pipeline automatically syncs that record into WordPress within seconds, creating or updating the corresponding `course` post.

**Flow:** QB record save → QB Webhook → Zapier Catch Hook → Zapier POST → WP REST endpoint → upsert course post

> **Note:** The old `/bust-cache` endpoint no longer exists as of v2.2.0. Any existing Zaps pointing to that endpoint must be updated to use `/sync-course` as described below.

---

## Step 1 — Generate a Sync Token

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

## Step 2 — Create the Quickbase Webhook

1. In the Quickbase app, open the **Course Catalog** table.
2. Go to **Settings → Webhooks** (or the table automation/webhook panel).
3. Click **New Webhook** (or **New Notification**).
4. **Trigger:** Record Changed (or Record Saved — any save event).
5. **URL to notify:** paste the Zapier webhook URL from Step 3 (complete Step 3 first to get this URL).
6. **Method:** POST
7. **Content type:** `application/json`
8. **Fields to send:** include at minimum **Record ID#** (field 3). QB will include field values in the webhook payload, and Zapier will be able to map `{{3}}` (Record ID#) from the incoming data.
9. Save the webhook.

---

## Step 3 — Create the Zapier Zap

### Trigger: Webhooks by Zapier — Catch Hook

1. In Zapier, create a new Zap.
2. Choose **Trigger:** Webhooks by Zapier → **Catch Hook**.
3. Copy the generated webhook URL and paste it into the Quickbase webhook from Step 2.
4. Trigger a test record save in QB to send a sample payload to Zapier.
5. Confirm that Zapier received the payload and that `{{3}}` (Record ID#) is available in the data mapper. If the QB payload does not include field values directly, add an intermediate **Quickbase → Find Record** step to look up the record by ID and expose its fields.
6. Click **Continue**.

### Action: Webhooks by Zapier — POST

1. Choose **Action:** Webhooks by Zapier → **POST**.
2. **URL:** `https://thearcoregon.org/wp-json/arc-qb-sync/v1/sync-course`
3. **Payload Type:** `json`
4. **Data:**
   ```json
   {"record_id": "{{3}}"}
   ```
   Map `{{3}}` from the QB webhook trigger (or the intermediate lookup step) to `record_id`.
5. **Headers:**
   - Key: `Authorization`
   - Value: `Bearer paste-your-token-here`
6. Click **Continue** and **Test** the action (see Step 4 below).
7. **Name and publish** the Zap.

---

## What the Endpoint Does

`POST /wp-json/arc-qb-sync/v1/sync-course`

When called, the endpoint:

1. Reads `record_id` from the JSON request body.
2. Fetches the full course record from Quickbase by that ID.
3. Upserts the corresponding WordPress `course` post (creates it if new, updates it if it already exists).
4. If **Public Listing** (FID 36) is `false`, demotes the existing post to draft rather than creating or updating it.

This is an incremental sync — only the one record is fetched and processed.

---

## Step 4 — Test the Pipeline

1. In Quickbase, open any Course Catalog record, make a minor change, and save it.
2. In Zapier, check **Task History** for the Zap — the POST action should show a `200` response.
3. In WordPress, verify the corresponding `course` post was created or updated with the latest field values.
4. Alternatively, test the WP endpoint directly:

```bash
curl -X POST https://thearcoregon.org/wp-json/arc-qb-sync/v1/sync-course \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"record_id": 123}'
```

Replace `123` with a real QB Record ID#.

---

## Troubleshooting

| Symptom | Check |
|---|---|
| Zapier task fails with 401 | Verify the Bearer token in the Zapier header matches `ARC_QB_CACHE_BUST_TOKEN` in wp-config.php exactly |
| Zapier task fails with 404 | Confirm the plugin is active; visit `/wp-json/arc-qb-sync/v1/sync-course` in a browser (expect a 405 Method Not Allowed, which confirms the route is registered) |
| WP post not updated after sync | Check that `record_id` is correctly mapped in the Zapier action body; check QB credentials in wp-config.php |
| `{{3}}` not available in Zapier | The QB webhook payload may not include field values — add an intermediate Quickbase Find Record step to look up the record and expose its fields |
| QB webhook not firing | Check QB table → Settings → Webhooks and confirm the webhook is active; QB may require a specific user role to set webhooks |
