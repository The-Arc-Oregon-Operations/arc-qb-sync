---
status: shipped
started: 2026-04-25
owner: Alan Lytle
target_version: 3.7.0
notes: FID Log confirmed from snapshot-2026-04-26-2048.md. QB_IMAGE_ASSETS_TABLE_ID defined in wp-config.php. All FID constants below are real values — no placeholders remain.
shipped: 2026-04-25
shipped_version: 3.7.0
---

# arc-qb-sync v3.7.0 — Option A Image Pipeline

## 1. What You Are Doing and Why

You are upgrading arc-qb-sync's image handling from a URL-resolution-only pattern to the Option A pipeline model already used by summit-qb-sync.

Currently, the plugin reads an image URL from a QB lookup field and calls `arc_qb_sync_set_featured_image($post_id, $url)`, which runs `attachment_url_to_postid()` on every sync. This requires the image to already exist in WordPress and re-resolves on every sync pass. It provides no path to upload images from QB and gives no pipeline state visibility.

Under Option A: the plugin checks `i_attachment_id` first (fast path — no HTTP, no lookup). On a miss, it checks the review status gate (`i_review_status = Approved`), then attempts sideload — first from a QB file attachment download, then from a URL fallback. On successful sideload, it writes the WP attachment ID back to the Image Assets record in QB so every subsequent sync is a fast read-hit.

This also adds `arc_qb_upsert_record()` — a general-purpose QB write function that arc-qb-sync currently lacks. This function is needed for the writeback and will be the foundation for any future QB write operations from this plugin.

---

## 2. Prerequisites — Confirm Before Starting

- [ ] Current plugin version reads `3.6.2` in `arc-qb-sync.php` header and `ARC_QB_SYNC_VERSION` constant
- [x] QB build spec complete: Image Assets table (`bvx88yiv2`) has all `i_*` pipeline fields added
- [x] FID Log fully populated — confirmed from `snapshot-2026-04-26-2048.md` (2026-04-26)
- [ ] `QB_IMAGE_ASSETS_TABLE_ID` is defined in wp-config.php as `'bvx88yiv2'`
- [ ] `QB_REALM_HOST` and `QB_USER_TOKEN` are defined in wp-config.php (pre-existing)

---

## 3. Read These Files First

1. `arc-qb-sync/includes/qb-api.php` — existing request function and `arc_qb_sync_set_featured_image()`
2. `arc-qb-sync/includes/sync-courses.php` — current image FID constants and upsert logic
3. `arc-qb-sync/includes/sync-events.php` — current image FID constants and upsert logic
4. `arc-qb-sync/includes/sync-instructors.php` — current headshot handling
5. `event-management/artifacts/2026-04-25_unified-image-assets/qb-build-spec.md` — QB schema and FID Log (the source of truth for all new FID values)
6. *(reference only — do not modify)* `summit-qb-sync/plugin/summit-qb-sync/includes/sync-presenters.php` — Option A implementation to model
7. *(reference only — do not modify)* `summit-qb-sync/plugin/summit-qb-sync/includes/qb-api.php` — `summit_qb_upsert_record()` to model

---

## 4. Target File Structure Changes

```
arc-qb-sync/
  arc-qb-sync.php                       ← update: version 3.6.2 → 3.7.0
  includes/
    qb-api.php                          ← update: add arc_qb_upsert_record(),
                                                   arc_qb_download_image_file(),
                                                   arc_qb_write_image_attachment_id(),
                                                   update arc_qb_sync_set_featured_image() → Option A
    sync-courses.php                    ← update: new FID constants, Option A upsert calls
    sync-events.php                     ← update: new FID constants, Option A upsert calls
    sync-instructors.php                ← update: new FID constants, Option A upsert call
  CHANGELOG.md                          ← update: add [3.7.0] entry
```

No new files. All logic lives in existing files.

---

## 5. Numbered Tasks

---

### Task 1: Add `arc_qb_upsert_record()` to `qb-api.php`

This is a new general-purpose write function. arc-qb-sync currently has no way to write to QB — add this capability modeled on `summit_qb_upsert_record()` in the summit plugin.

Add after the closing of `arc_qb_sync_set_featured_image()`:

```php
/**
 * Upsert one or more records to a Quickbase table.
 *
 * Posts to /v1/records. Include FID 3 (Record ID#) in each record's data
 * array to update an existing record; omit it to create a new one.
 *
 * @param string $table_id  Quickbase table ID (e.g. 'bvx88yiv2').
 * @param array  $data      Array of record data arrays. Each inner array is
 *                          keyed by FID (int) → array( 'value' => $value ).
 * @return array|\WP_Error  QB API response body on success, WP_Error on failure.
 */
function arc_qb_upsert_record( $table_id, array $data ) {

    if ( ! defined( 'QB_REALM_HOST' ) || ! defined( 'QB_USER_TOKEN' ) ) {
        return new WP_Error(
            'arc_qb_missing_config',
            'Quickbase configuration missing: QB_REALM_HOST and QB_USER_TOKEN must be defined in wp-config.php.'
        );
    }

    $url  = 'https://api.quickbase.com/v1/records';
    $body = array(
        'to'   => $table_id,
        'data' => $data,
    );

    $args = array(
        'headers' => array(
            'QB-Realm-Hostname' => QB_REALM_HOST,
            'User-Agent'        => 'WordPress-ArcOregon-QBSync',
            'Authorization'     => 'QB-USER-TOKEN ' . QB_USER_TOKEN,
            'Content-Type'      => 'application/json',
        ),
        'body'        => wp_json_encode( $body ),
        'method'      => 'POST',
        'timeout'     => 15,
        'data_format' => 'body',
    );

    $response = wp_remote_post( $url, $args );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status      = wp_remote_retrieve_response_code( $response );
    $parsed_body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status < 200 || $status >= 300 ) {
        return new WP_Error(
            'arc_qb_upsert_failed',
            sprintf( 'QB upsert failed (HTTP %d).', $status )
        );
    }

    return $parsed_body;
}
```

---

### Task 2: Add `arc_qb_download_image_file()` to `qb-api.php`

This function downloads a file from a QB File Attachment field and saves it to the WordPress upload temp directory. Returns a file array suitable for `wp_handle_sideload()`, or a WP_Error.

Add after `arc_qb_upsert_record()`:

```php
/**
 * Download a file from a Quickbase File Attachment field.
 *
 * Uses the QB Files API: GET /v1/files/{tableId}/{recordId}/{fieldId}/{version}
 * Version 0 is the original upload. If the file has been replaced in QB,
 * clear i_attachment_id and re-approve to trigger a fresh sideload.
 *
 * @param string $table_id  QB table ID containing the file attachment field.
 * @param int    $record_id QB Record ID# of the record with the attachment.
 * @param int    $field_id  FID of the File Attachment field.
 * @param string $filename  Suggested filename for the temp file.
 * @return array|\WP_Error  Array with keys 'tmp_name', 'name', 'size', 'type'
 *                          on success (compatible with wp_handle_sideload()).
 *                          WP_Error on failure.
 */
function arc_qb_download_image_file( $table_id, $record_id, $field_id, $filename = 'image' ) {

    if ( ! defined( 'QB_REALM_HOST' ) || ! defined( 'QB_USER_TOKEN' ) ) {
        return new WP_Error( 'arc_qb_missing_config', 'QB_REALM_HOST and QB_USER_TOKEN must be defined.' );
    }

    $url = sprintf(
        'https://api.quickbase.com/v1/files/%s/%d/%d/0',
        rawurlencode( $table_id ),
        intval( $record_id ),
        intval( $field_id )
    );

    $response = wp_remote_get( $url, array(
        'headers' => array(
            'QB-Realm-Hostname' => QB_REALM_HOST,
            'Authorization'     => 'QB-USER-TOKEN ' . QB_USER_TOKEN,
            'User-Agent'        => 'WordPress-ArcOregon-QBSync',
        ),
        'timeout' => 30,
    ) );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $status = wp_remote_retrieve_response_code( $response );
    if ( 200 !== $status ) {
        return new WP_Error(
            'arc_qb_file_download_failed',
            sprintf( 'QB file download returned HTTP %d for record %d field %d.', $status, $record_id, $field_id )
        );
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return new WP_Error( 'arc_qb_file_empty', 'QB file download returned empty body.' );
    }

    // Detect content type from response headers.
    $content_type = wp_remote_retrieve_header( $response, 'content-type' );
    $content_type = $content_type ?: 'application/octet-stream';

    // Write to a temp file in WP's upload tmp dir.
    $tmp_dir  = get_temp_dir();
    $tmp_file = tempnam( $tmp_dir, 'arc_qb_img_' );
    if ( false === $tmp_file ) {
        return new WP_Error( 'arc_qb_tmpfile_failed', 'Could not create temp file for QB image download.' );
    }

    file_put_contents( $tmp_file, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

    return array(
        'tmp_name' => $tmp_file,
        'name'     => sanitize_file_name( $filename ),
        'size'     => strlen( $body ),
        'type'     => $content_type,
    );
}
```

---

### Task 3: Add `arc_qb_write_image_attachment_id()` to `qb-api.php`

Writeback helper — called once per Image Asset record after a successful first-touch sideload. Writes `i_attachment_id` (always), and optionally `i_processing_status = Processed`.

Add after `arc_qb_download_image_file()`:

```php
/**
 * Write a WP attachment ID back to the Image Assets record in QB (Option A).
 *
 * Called only on successful first-touch sideload — not on every sync.
 * On subsequent syncs, i_attachment_id will be non-zero and the sideload
 * path is skipped entirely.
 *
 * @param int    $ia_record_id  Image Assets Record ID# (from the FK field on
 *                              the parent — e.g. Courses, Events, Instructors).
 * @param int    $attachment_id WP Media Library attachment ID to write.
 * @param string $public_url    Optional. WP public media URL to write to i_url (FID 6).
 *                              Pass empty string to skip.
 * @return array|\WP_Error
 */
function arc_qb_write_image_attachment_id( $ia_record_id, $attachment_id, $public_url = '' ) {

    if ( ! defined( 'QB_IMAGE_ASSETS_TABLE_ID' ) || '' === QB_IMAGE_ASSETS_TABLE_ID ) {
        return new WP_Error(
            'arc_qb_missing_config',
            'QB_IMAGE_ASSETS_TABLE_ID is not defined in wp-config.php.'
        );
    }

    // FID 3 = Record ID# (upsert target). FID values from FID Log.
    $record = array(
        3                             => array( 'value' => intval( $ia_record_id ) ),
        ARC_QB_IA_FID_ATTACHMENT_ID   => array( 'value' => intval( $attachment_id ) ),
        ARC_QB_IA_FID_PROC_STATUS     => array( 'value' => 'Processed' ),
    );

    if ( $public_url ) {
        $record[ ARC_QB_IA_FID_URL ] = array( 'value' => esc_url_raw( $public_url ) );
    }

    return arc_qb_upsert_record( QB_IMAGE_ASSETS_TABLE_ID, array( $record ) );
}
```

---

### Task 4: Replace `arc_qb_sync_set_featured_image()` with Option A version in `qb-api.php`

Replace the existing `arc_qb_sync_set_featured_image()` function entirely. The new version accepts a structured `$args` array. The old single-URL signature is gone — all callers (sync-courses, sync-events, sync-instructors) are updated in Tasks 5–7.

```php
/**
 * Set the WP featured image for a CPT post — Option A pipeline hybrid.
 *
 * Fast path: if $args['attachment_id'] is non-zero, call set_post_thumbnail()
 * directly. No HTTP, no lookup.
 *
 * Miss path: if attachment_id is empty and review_status is 'Approved':
 *   1. Try QB file download (via i_file attachment field on Image Assets record).
 *   2. If no file: fall back to sideload from $args['image_url'].
 *   3. On success: write attachment ID back to QB via arc_qb_write_image_attachment_id().
 *
 * If review_status is not 'Approved', skip sideload and log gate reason.
 * If all paths fail, clear any existing thumbnail (stale prevention).
 *
 * @param int   $post_id   WP post ID.
 * @param array $args {
 *   @type int    $attachment_id  Stored WP attachment ID (0 if not yet sideloaded).
 *   @type string $review_status  Value of i_review_status on Image Assets record.
 *   @type string $image_url      Fallback public URL (i_url lookup, FID 6).
 *   @type int    $ia_record_id   Image Assets Record ID# (FK from child record).
 *                                Required for QB file download and writeback.
 *   @type string $ia_filename    Optional. Filename hint for QB file download.
 *   @type string $context_label  Log label — e.g. 'Course 123 featured'.
 * }
 * @return void
 */
function arc_qb_sync_set_featured_image( $post_id, array $args ) {

    $attachment_id = intval( $args['attachment_id'] ?? 0 );
    $review_status = sanitize_text_field( $args['review_status'] ?? '' );
    $image_url     = esc_url_raw( $args['image_url'] ?? '' );
    $ia_record_id  = intval( $args['ia_record_id'] ?? 0 );
    $ia_filename   = sanitize_file_name( $args['ia_filename'] ?? 'image' );
    $label         = sanitize_text_field( $args['context_label'] ?? 'unknown' );

    // ── Fast path: attachment ID already stored ───────────────────────────────
    if ( $attachment_id > 0 ) {
        set_post_thumbnail( $post_id, $attachment_id );
        return;
    }

    // ── Miss path: need to sideload ───────────────────────────────────────────
    if ( 'Approved' !== $review_status ) {
        if ( $review_status ) {
            error_log( "[arc-qb-sync] {$label}: review gate ({$review_status}) — sideload skipped." );
        }
        delete_post_thumbnail( $post_id );
        return;
    }

    if ( ! function_exists( 'media_sideload_image' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $new_attachment_id = 0;
    $public_url        = '';

    // ── Attempt 1: QB file attachment download ────────────────────────────────
    if ( $ia_record_id > 0 && defined( 'ARC_QB_IA_FID_FILE' ) && ARC_QB_IA_FID_FILE > 0 ) {
        $file = arc_qb_download_image_file(
            QB_IMAGE_ASSETS_TABLE_ID,
            $ia_record_id,
            ARC_QB_IA_FID_FILE,
            $ia_filename
        );

        if ( ! is_wp_error( $file ) ) {
            $overrides = array( 'test_form' => false );
            $moved     = wp_handle_sideload( $file, $overrides );

            if ( empty( $moved['error'] ) ) {
                $attachment = array(
                    'post_mime_type' => $moved['type'],
                    'post_title'     => sanitize_file_name( pathinfo( $moved['file'], PATHINFO_FILENAME ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                );
                $att_id = wp_insert_attachment( $attachment, $moved['file'], $post_id );
                if ( ! is_wp_error( $att_id ) ) {
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    $metadata = wp_generate_attachment_metadata( $att_id, $moved['file'] );
                    wp_update_attachment_metadata( $att_id, $metadata );
                    $new_attachment_id = $att_id;
                    $public_url        = wp_get_attachment_url( $att_id );
                }
            } else {
                error_log( "[arc-qb-sync] {$label}: QB file sideload failed — {$moved['error']}" );
            }

            // Clean up temp file if it still exists.
            if ( file_exists( $file['tmp_name'] ) ) {
                @unlink( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
        } else {
            error_log( "[arc-qb-sync] {$label}: QB file download error — " . $file->get_error_message() );
        }
    }

    // ── Attempt 2: URL sideload fallback ──────────────────────────────────────
    if ( 0 === $new_attachment_id && $image_url ) {
        $result = media_sideload_image( $image_url, $post_id, null, 'id' );
        if ( ! is_wp_error( $result ) ) {
            $new_attachment_id = $result;
            $public_url        = wp_get_attachment_url( $new_attachment_id );
        } else {
            error_log( "[arc-qb-sync] {$label}: URL sideload failed — " . $result->get_error_message() );
        }
    }

    // ── Set thumbnail and write back to QB ────────────────────────────────────
    if ( $new_attachment_id > 0 ) {
        set_post_thumbnail( $post_id, $new_attachment_id );

        if ( $ia_record_id > 0 ) {
            $wb = arc_qb_write_image_attachment_id( $ia_record_id, $new_attachment_id, $public_url );
            if ( is_wp_error( $wb ) ) {
                error_log( "[arc-qb-sync] {$label}: writeback failed — " . $wb->get_error_message() );
            } else {
                error_log( "[arc-qb-sync] {$label}: sideloaded and written back to QB (att ID {$new_attachment_id})." );
            }
        }
    } else {
        // All paths exhausted — clear any stale thumbnail.
        delete_post_thumbnail( $post_id );
        error_log( "[arc-qb-sync] {$label}: no image source available; featured image cleared." );
    }
}
```

---

### Task 5: Update `sync-courses.php` — New FID Constants + Option A Calls

**5a. Add new FID constants** below the existing image FID block (after line defining `ARC_QB_COURSE_HERO_IMAGE_FID`).

Fill in all `0` placeholders with real FIDs from the FID Log before running:

```php
// ── Image Assets pipeline FID constants (Image Assets table bvx88yiv2) ────────
// Shared across Courses, Events, Instructors. Defined here once; guarded with
// defined() so later sync files don't redefine.
// FIDs confirmed from snapshot-2026-04-26-2048.md.
if ( ! defined( 'ARC_QB_IA_FID_ATTACHMENT_ID' ) ) define( 'ARC_QB_IA_FID_ATTACHMENT_ID', 24 ); // i_attachment_id
if ( ! defined( 'ARC_QB_IA_FID_URL' ) )           define( 'ARC_QB_IA_FID_URL',            6 ); // i_url — existing FID 6
if ( ! defined( 'ARC_QB_IA_FID_PROC_STATUS' ) )   define( 'ARC_QB_IA_FID_PROC_STATUS',   28 ); // i_processing_status
if ( ! defined( 'ARC_QB_IA_FID_FILE' ) )          define( 'ARC_QB_IA_FID_FILE',          30 ); // i_file (File Attachment)

// ── Courses: pipeline lookup FIDs ─────────────────────────────────────────────
if ( ! defined( 'ARC_QB_COURSE_FEATURED_IMAGE_FK_FID' ) )         define( 'ARC_QB_COURSE_FEATURED_IMAGE_FK_FID',          93 ); // Featured Image [Ref] — FK holding IA Record ID#
if ( ! defined( 'ARC_QB_COURSE_FEATURED_IMAGE_ATTACHMENT_FID' ) ) define( 'ARC_QB_COURSE_FEATURED_IMAGE_ATTACHMENT_FID', 111 ); // Featured Image - Attachment ID [lookup]
if ( ! defined( 'ARC_QB_COURSE_FEATURED_IMAGE_REVIEW_FID' ) )     define( 'ARC_QB_COURSE_FEATURED_IMAGE_REVIEW_FID',     112 ); // Featured Image - Review Status [lookup]
if ( ! defined( 'ARC_QB_COURSE_HERO_IMAGE_FK_FID' ) )             define( 'ARC_QB_COURSE_HERO_IMAGE_FK_FID',              95 ); // Hero Image [Ref] — FK holding IA Record ID#
if ( ! defined( 'ARC_QB_COURSE_HERO_IMAGE_ATTACHMENT_FID' ) )     define( 'ARC_QB_COURSE_HERO_IMAGE_ATTACHMENT_FID',     113 ); // Hero Image - Attachment ID [lookup]
if ( ! defined( 'ARC_QB_COURSE_HERO_IMAGE_REVIEW_FID' ) )         define( 'ARC_QB_COURSE_HERO_IMAGE_REVIEW_FID',         114 ); // Hero Image - Review Status [lookup]
```

**5b. Add new pipeline FIDs to the `select` array** in `arc_qb_fetch_all_course_records()` and `arc_qb_fetch_course_record()`. After the existing image FIDs (94, 96), add:

```php
ARC_QB_COURSE_FEATURED_IMAGE_FK_FID,
ARC_QB_COURSE_FEATURED_IMAGE_ATTACHMENT_FID,
ARC_QB_COURSE_FEATURED_IMAGE_REVIEW_FID,
ARC_QB_COURSE_HERO_IMAGE_FK_FID,
ARC_QB_COURSE_HERO_IMAGE_ATTACHMENT_FID,
ARC_QB_COURSE_HERO_IMAGE_REVIEW_FID,
```

Guard each with a check: `if ( ARC_QB_COURSE_FEATURED_IMAGE_FK_FID > 0 )` — add to the select array only when the constant is non-zero. This makes the code safe to deploy before the FID log is complete. Model this on the existing 0-skip pattern used in the plugin.

**5c. Update the featured image call in `arc_qb_upsert_course()`.**

Replace the existing:
```php
arc_qb_sync_set_featured_image( $post_id, $course_featured_image_url );
```

With:
```php
// ── Featured image — Option A ─────────────────────────────────────────────────
arc_qb_sync_set_featured_image( $post_id, array(
    'attachment_id' => intval( arc_qb_get_course_field( $record, ARC_QB_COURSE_FEATURED_IMAGE_ATTACHMENT_FID ) ),
    'review_status' => sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_COURSE_FEATURED_IMAGE_REVIEW_FID ) ),
    'image_url'     => $course_featured_image_url, // existing FID 94 URL lookup (fallback)
    'ia_record_id'  => intval( arc_qb_get_course_field( $record, ARC_QB_COURSE_FEATURED_IMAGE_FK_FID ) ),
    'ia_filename'   => sanitize_file_name( get_post_meta( $post_id, '_arc_course_slug', true ) . '-featured' ),
    'context_label' => 'Course ' . intval( arc_qb_get_course_field( $record, 3 ) ) . ' featured',
) );

// ── Hero image — Option A writeback only (not set_post_thumbnail) ─────────────
// Hero URL is stored as post meta and displayed via shortcode. We still run Option A
// so that QB file uploads are sideloaded and the attachment ID is written back.
$hero_attachment_id = intval( arc_qb_get_course_field( $record, ARC_QB_COURSE_HERO_IMAGE_ATTACHMENT_FID ) );
$hero_review_status = sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_COURSE_HERO_IMAGE_REVIEW_FID ) );
$hero_ia_record_id  = intval( arc_qb_get_course_field( $record, ARC_QB_COURSE_HERO_IMAGE_FK_FID ) );
$hero_image_url     = esc_url_raw( arc_qb_get_course_field( $record, ARC_QB_COURSE_HERO_IMAGE_FID ) );

if ( 0 === $hero_attachment_id && 'Approved' === $hero_review_status && $hero_ia_record_id > 0 ) {
    // Miss: attempt sideload for writeback only — do not set as WP featured image.
    // Reuse the Option A helper but discard the set_post_thumbnail call by passing
    // a throwaway post ID of 0 and checking the result manually.
    // ↳ Simpler: inline the file-download + writeback here without set_post_thumbnail.
    if ( ! function_exists( 'media_sideload_image' ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }
    $hero_att_id  = 0;
    $hero_pub_url = '';

    // Try QB file first.
    if ( defined( 'ARC_QB_IA_FID_FILE' ) && ARC_QB_IA_FID_FILE > 0 ) {
        $hero_slug = sanitize_file_name( get_post_meta( $post_id, '_arc_course_slug', true ) . '-hero' );
        $file = arc_qb_download_image_file( QB_IMAGE_ASSETS_TABLE_ID, $hero_ia_record_id, ARC_QB_IA_FID_FILE, $hero_slug );
        if ( ! is_wp_error( $file ) ) {
            $moved = wp_handle_sideload( $file, array( 'test_form' => false ) );
            if ( empty( $moved['error'] ) ) {
                $att = wp_insert_attachment( array(
                    'post_mime_type' => $moved['type'],
                    'post_title'     => sanitize_file_name( pathinfo( $moved['file'], PATHINFO_FILENAME ) ),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                ), $moved['file'], $post_id );
                if ( ! is_wp_error( $att ) ) {
                    wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $moved['file'] ) );
                    $hero_att_id  = $att;
                    $hero_pub_url = wp_get_attachment_url( $att );
                }
            }
            if ( file_exists( $file['tmp_name'] ) ) { @unlink( $file['tmp_name'] ); } // phpcs:ignore
        }
    }
    // URL fallback.
    if ( 0 === $hero_att_id && $hero_image_url ) {
        $result = media_sideload_image( $hero_image_url, $post_id, null, 'id' );
        if ( ! is_wp_error( $result ) ) {
            $hero_att_id  = $result;
            $hero_pub_url = wp_get_attachment_url( $hero_att_id );
        }
    }
    // Writeback.
    if ( $hero_att_id > 0 ) {
        arc_qb_write_image_attachment_id( $hero_ia_record_id, $hero_att_id, $hero_pub_url );
    }
}
```

---

### Task 6: Update `sync-events.php` — New FID Constants + Option A Calls

**6a. Add new FID constants** below the existing Events image FID block. The shared `ARC_QB_IA_FID_*` constants are already defined in sync-courses.php (loaded first) and guarded with `defined()` — do not redefine them. Add only the Events-specific pipeline FIDs:

```php
// ── Events: pipeline lookup FIDs ──────────────────────────────────────────────
if ( ! defined( 'ARC_QB_EVENT_FEATURED_IMAGE_FK_FID' ) )         define( 'ARC_QB_EVENT_FEATURED_IMAGE_FK_FID',         463 ); // Featured Image [Ref] — FK
if ( ! defined( 'ARC_QB_EVENT_FEATURED_IMAGE_ATTACHMENT_FID' ) ) define( 'ARC_QB_EVENT_FEATURED_IMAGE_ATTACHMENT_FID', 495 ); // Featured Image - Attachment ID [lookup]
if ( ! defined( 'ARC_QB_EVENT_FEATURED_IMAGE_REVIEW_FID' ) )     define( 'ARC_QB_EVENT_FEATURED_IMAGE_REVIEW_FID',     496 ); // Featured Image - Review Status [lookup]
if ( ! defined( 'ARC_QB_EVENT_HERO_IMAGE_FK_FID' ) )             define( 'ARC_QB_EVENT_HERO_IMAGE_FK_FID',             465 ); // Hero Image [Ref] — FK
if ( ! defined( 'ARC_QB_EVENT_HERO_IMAGE_ATTACHMENT_FID' ) )     define( 'ARC_QB_EVENT_HERO_IMAGE_ATTACHMENT_FID',     497 ); // Hero Image - Attachment ID [lookup]
if ( ! defined( 'ARC_QB_EVENT_HERO_IMAGE_REVIEW_FID' ) )         define( 'ARC_QB_EVENT_HERO_IMAGE_REVIEW_FID',         498 ); // Hero Image - Review Status [lookup]
```

**6b.** Add new FIDs to `select` array (same 0-skip guard pattern as Courses).

**6c.** Replace the featured image call in `arc_qb_upsert_event()` — same pattern as Courses Task 5c, using Events-specific FID constants and label prefix `'Event ' . $qb_id . ' featured'`.

Include the hero image writeback block exactly as in Task 5c, substituting Events FID constants and slug meta key `_arc_event_slug`.

---

### Task 7: Update `sync-instructors.php` — New FID Constants + Option A Call

**7a. Add new FID constants** below the existing headshot FID comment:

```php
// ── Instructors: Image Asset pipeline FIDs ────────────────────────────────────
if ( ! defined( 'ARC_QB_INSTRUCTOR_HEADSHOT_FK_FID' ) )         define( 'ARC_QB_INSTRUCTOR_HEADSHOT_FK_FID',         14 ); // Headshot [Ref] — FK
if ( ! defined( 'ARC_QB_INSTRUCTOR_HEADSHOT_ATTACHMENT_FID' ) ) define( 'ARC_QB_INSTRUCTOR_HEADSHOT_ATTACHMENT_FID', 32 ); // Headshot - Attachment ID [lookup]
if ( ! defined( 'ARC_QB_INSTRUCTOR_HEADSHOT_REVIEW_FID' ) )     define( 'ARC_QB_INSTRUCTOR_HEADSHOT_REVIEW_FID',     33 ); // Headshot - Review Status [lookup]
```

**7b.** Add new FIDs to the Instructors `select` array (same 0-skip guard).

**7c.** Replace the headshot call in `arc_qb_upsert_instructor()`:

Replace:
```php
arc_qb_sync_set_featured_image( $post_id, $headshot_url );
```

With:
```php
// ── Headshot — Option A ───────────────────────────────────────────────────────
$instructor_slug = sanitize_file_name( get_post_meta( $post_id, '_arc_instructor_slug', true ) );
arc_qb_sync_set_featured_image( $post_id, array(
    'attachment_id' => intval( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_HEADSHOT_ATTACHMENT_FID ) ),
    'review_status' => sanitize_text_field( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_HEADSHOT_REVIEW_FID ) ),
    'image_url'     => $headshot_url, // existing FID 15 URL lookup (fallback)
    'ia_record_id'  => intval( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_HEADSHOT_FK_FID ) ),
    'ia_filename'   => $instructor_slug . '-headshot',
    'context_label' => 'Instructor ' . intval( arc_qb_get_course_field( $record, ARC_QB_INSTRUCTOR_FID_RECORD_ID ) ) . ' headshot',
) );
```

---

### Task 8: Version Bump and CHANGELOG

**`arc-qb-sync.php`:** Update `Version:` header and `ARC_QB_SYNC_VERSION` constant from `3.6.2` to `3.7.0`.

**`CHANGELOG.md`:** Add entry at top:

```
## [3.7.0] — YYYY-MM-DD

### Added
- `qb-api.php`: `arc_qb_upsert_record()` — general-purpose QB write function (POST to /v1/records); first write capability in arc-qb-sync
- `qb-api.php`: `arc_qb_download_image_file()` — downloads a file from a QB File Attachment field via the QB Files API
- `qb-api.php`: `arc_qb_write_image_attachment_id()` — writes WP attachment ID (and optionally public URL + Processing Status = Processed) back to the Image Assets record in QB after first-touch sideload
- `sync-courses.php`, `sync-events.php`, `sync-instructors.php`: new pipeline FID constants for Image Assets Attachment ID, Review Status, and relationship FK fields on each child table

### Changed
- `qb-api.php`: `arc_qb_sync_set_featured_image()` upgraded from URL-only to Option A pipeline. New signature: `arc_qb_sync_set_featured_image( $post_id, array $args )`. Fast path reads stored `i_attachment_id`; miss path gates on `i_review_status = Approved`, then downloads QB file attachment or falls back to URL sideload; writes attachment ID back to QB on success.
- Courses, Events, Instructors: featured image sync now uses Option A; hero/headshot slots also run Option A writeback (not set_post_thumbnail for hero)

### Notes
- All new FID constants default to 0 when not yet populated; the plugin skips gracefully when FIDs are 0. Safe to deploy before the FID Log is finalized, but Option A will not activate until constants are filled.
- Requires `QB_IMAGE_ASSETS_TABLE_ID` defined in wp-config.php as `'bvx88yiv2'`
```

---

## 6. Verification Checklist

- [ ] `ARC_QB_SYNC_VERSION` constant and plugin header both read `3.7.0`
- [ ] `arc_qb_upsert_record()` exists in `qb-api.php` and accepts `($table_id, array $data)`
- [ ] `arc_qb_download_image_file()` exists in `qb-api.php`
- [ ] `arc_qb_write_image_attachment_id()` references `QB_IMAGE_ASSETS_TABLE_ID` and `ARC_QB_IA_FID_ATTACHMENT_ID`
- [ ] `arc_qb_sync_set_featured_image()` signature is now `($post_id, array $args)` — not `($post_id, $url)`
- [ ] No remaining calls to the old `arc_qb_sync_set_featured_image( $post_id, $url )` two-argument string form
- [ ] `ARC_QB_IA_FID_*` constants defined once in `sync-courses.php` and guarded with `defined()`
- [ ] `ARC_QB_COURSE_*`, `ARC_QB_EVENT_*`, `ARC_QB_INSTRUCTOR_HEADSHOT_*` pipeline FID constants present in their respective sync files
- [ ] All new FID constants default to `0` (not undefined) — plugin skips gracefully when 0
- [ ] CHANGELOG entry present and dated
- [ ] No changes to `shortcodes-courses.php`, `shortcodes-events-cpt.php`, `shortcodes-instructors.php`, `elementor-dynamic-tags.php`, or any file not listed in the target structure

---

## 7. What NOT to Change

- **Shortcode files.** `shortcodes-courses.php`, `shortcodes-events-cpt.php`, `shortcodes-instructors.php` — image URL meta keys (`_arc_course_featured_image_url`, `_arc_event_featured_image_url`, `_arc_instructor_headshot_url`) are unchanged; shortcodes continue to read them.
- **Elementor dynamic tags.** `elementor-dynamic-tags.php` is unchanged.
- **Existing FID constants.** `ARC_QB_COURSE_FEATURED_IMAGE_FID` (94), `ARC_QB_COURSE_HERO_IMAGE_FID` (96), `ARC_QB_EVENT_FEATURED_IMAGE_FID` (464), `ARC_QB_EVENT_HERO_IMAGE_FID` (466), `ARC_QB_INSTRUCTOR_PROFILE_FID` (15) — all kept; used as URL fallback in Option A.
- **Legacy URL meta writes.** Lines storing `_arc_course_image_url` (FID 88), `_arc_event_image_url` (FID 461) — leave in place.
- **summit-qb-sync.** Do not touch any summit-qb-sync files.
- **`courses.php` or `events.php`** (the single-record detail page fetchers) — not in scope.

---

## 8. Manual Tests (Post-Merge)

| Scenario | Expected |
|---|---|
| Sync a Course with `i_review_status = Approved` and a QB `File` attachment | Image appears in WP Media Library; Course CPT has WP featured image set; `Attachment ID` field in QB Image Assets record is populated after sync |
| Sync same Course again immediately | Featured image set via fast path (no sideload log entry); sync completes without HTTP to QB files API |
| Sync a Course with `i_review_status = Unreviewed` | Log entry: `review gate (Unreviewed) — sideload skipped`; no featured image set |
| Sync a Course with no Image Asset linked | Featured image cleared (no error) |
| Sync an Instructor with QB file attachment + `Approved` | WP featured image set; QB `Attachment ID` written back |
| Check CHANGELOG | `[3.7.0]` entry present at top with today's date |
