# Upload Flow Documentation

**Plugin:** iDrivee2 Media Upload
**Version:** 1.0.1
**Date:** 2026-02-03

---

## Complete Upload Process: Step by Step

### Context: User uploads 1 or more images from Media → Add New

---

## Phase 1: WordPress Core Processing

### Step 1: User Action
```
User: Drag & drop / selects image(s) in Media → Add New
```

### Step 2: WordPress Receives Upload
```php
// WordPress core file: wp-admin/async-upload.php
1. Validates file upload ($_FILES)
2. Checks file type, size, security
3. Moves uploaded file to temp location
4. Assigns unique filename if needed
```

### Step 3: WordPress Creates Attachment Post
```php
// Function: wp_handle_upload()
1. Moves file to: /wp-content/uploads/YYYY/MM/filename.ext
2. Creates attachment post in wp_posts table
3. Returns attachment ID

// Example:
$file_path = '/wp-content/uploads/2026/02/Bandit.png'
$attachment_id = 123
```

### Step 4: WordPress Generates Image Sizes
```php
// Function: wp_generate_attachment_metadata()
1. Reads original image
2. Generates all registered image sizes:
   - thumbnail (150x150)
   - medium (300x300)
   - medium_large (768x0)
   - large (1024x1024)
   - Custom sizes (if theme registers them)
3. Saves all generated images to same directory as original

// Example files created:
/uploads/2026/02/Bandit.png             (original - 2000x3000)
/uploads/2026/02/Bandit-150x150.png     (thumbnail)
/uploads/2026/02/Bandit-300x450.png     (medium)
/uploads/2026/02/Bandit-768x1152.png    (medium_large)
/uploads/2026/02/Bandit-1024x1536.png   (large)
/uploads/2026/02/Bandit-657x1024.png    (custom size)
/uploads/2026/02/Bandit-986x1536.png    (custom size)
// etc...
```

### Step 5: WordPress Builds Metadata Array
```php
$metadata = array(
    'width'  => 2000,
    'height' => 3000,
    'file'   => '2026/02/Bandit.png',  // Relative path
    'sizes'  => array(
        'thumbnail' => array(
            'file'      => 'Bandit-150x150.png',
            'width'     => 150,
            'height'    => 150,
            'mime-type' => 'image/png'
        ),
        'medium' => array(
            'file'      => 'Bandit-300x450.png',
            'width'     => 300,
            'height'    => 450,
            'mime-type' => 'image/png'
        ),
        // ... more sizes ...
    ),
    'image_meta' => array( /* EXIF data */ )
);
```

---

## Phase 2: Plugin Hooks Execute

### Step 6: Hook `wp_generate_attachment_metadata` Fires (Priority 10)
```php
// WordPress calls:
apply_filters( 'wp_generate_attachment_metadata', $metadata, $attachment_id );

// Our plugin method executes:
Media_Uploader::upload_attachment_to_idrivee2( $metadata, $attachment_id )
```

**IMPORTANT:** This hook fires **ONCE** per upload, **AFTER** all image sizes are generated.

---

## Phase 3: Our Plugin Processing

### Step 7: Check if Already Processed
```php
// Prevent duplicate uploads if hook fires multiple times
$processed = get_post_meta( $attachment_id, '_idrivee2_processed', true );
if ( $processed ) {
    return $meta; // Exit early - already uploaded
}
```

**Why this is needed:**
- WordPress sometimes calls hooks multiple times
- `wp_update_attachment_metadata` also fires and can cause duplicate uploads
- This flag prevents re-uploading the same files

### Step 8: Initialize S3 Client
```php
// Check configuration
if ( ! $this->config->is_configured() ) {
    return $meta; // Exit if not configured
}

// Create S3 client with credentials
$client = $this->client_factory->create();
```

### Step 9: Build File List
```php
$upload_dir = wp_upload_dir();
$base_path  = path_join( $upload_dir['basedir'], $meta['file'] );

$files = array(
    'original' => '/full/path/to/uploads/2026/02/Bandit.png',
);

// Add all generated sizes
foreach ( $meta['sizes'] as $size ) {
    $files[ $size['file'] ] = '/full/path/to/uploads/2026/02/' . $size['file'];
}

// Result:
$files = array(
    'original'            => '/...uploads/2026/02/Bandit.png',
    'Bandit-150x150.png'  => '/...uploads/2026/02/Bandit-150x150.png',
    'Bandit-300x450.png'  => '/...uploads/2026/02/Bandit-300x450.png',
    // ... all other sizes ...
);
```

### Step 10: Upload Each File to S3
```php
foreach ( $files as $key => $local_path ) {
    // 1. Check file exists
    if ( ! $wp_filesystem->exists( $local_path ) ) {
        continue; // Skip missing files
    }

    // 2. Determine S3 object key (path in bucket)
    $object_key = ( 'original' === $key )
        ? '2026/02/Bandit.png'              // Original uses meta['file']
        : '2026/02/Bandit-150x150.png';     // Sizes use filename

    // 3. Read file contents
    $content = $wp_filesystem->get_contents( $local_path );

    // 4. Upload to S3
    $result = $client->putObject(
        array(
            'Bucket' => 'my-bucket',
            'Key'    => '2026/02/Bandit.png',
            'Body'   => $content,
            'ACL'    => 'public-read',
        )
    );

    // 5. Log success
    $this->logger->s3_operation( 'putObject', true, 'Bandit.png' );

    // 6. Capture S3 URL on original file
    if ( 'original' === $key ) {
        // Build public URL based on configuration
        if ( $this->config->has_domain() ) {
            // Use CDN domain
            $s3_base_url = 'https://cdn.yourdomain.com/2026/02';
        } else {
            // Use S3 ObjectURL
            $object_url = $result['ObjectURL'];
            // Example: https://my-bucket.s3.amazonaws.com/2026/02/Bandit.png
            $s3_base_url = dirname( $object_url );
        }
    }
}
```

**S3 Upload Details:**
- Each file uploaded separately
- Files uploaded in order: original first, then sizes
- Uses `public-read` ACL (files are publicly accessible)
- Content loaded in memory (not streamed)
- No local files deleted

### Step 11: Mark as Processed
```php
// Prevent duplicate uploads on subsequent hook calls
update_post_meta( $attachment_id, '_idrivee2_processed', true );
update_post_meta( $attachment_id, '_idrivee2_s3_base_url', $s3_base_url );
```

### Step 12: Update WordPress Metadata
```php
// Preserve relative path in database
update_post_meta( $attachment_id, '_wp_attached_file', '2026/02/Bandit.png' );
```

### Step 13: Update GUID to Public URL
```php
// Build public URL
if ( $this->config->has_domain() ) {
    // With CDN domain
    $public_url = 'https://cdn.yourdomain.com/2026/02/Bandit.png';
} else {
    // With S3 URL
    $public_url = 'https://my-bucket.s3.amazonaws.com/2026/02/Bandit.png';
}

// Update GUID in wp_posts table
wp_update_post(
    array(
        'ID'   => 123,
        'guid' => $public_url,
    )
);
```

**GUID Explanation:**
- GUID is the "permanent" URL for the attachment
- WordPress uses GUID to generate all other URLs
- By setting GUID to S3/CDN URL, all image references use S3

### Step 14: Return Metadata (Unchanged)
```php
return $meta; // WordPress continues normal processing
```

---

## Phase 4: WordPress Finalizes

### Step 15: WordPress Saves Metadata
```php
// WordPress core saves $metadata to database
update_post_meta( $attachment_id, '_wp_attachment_metadata', $metadata );
```

### Step 16: WordPress Admin Shows Preview
```javascript
// Media Library JavaScript displays uploaded image
// Uses GUID from database
// Files still exist locally, so preview works immediately
```

---

## Phase 5: URL Rewriting (Frontend)

### Step 17: URL Rewriter Hook
```php
// When image URL is requested (frontend)
add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_attachment_url' ), 10, 2 );

// Our plugin rewrites:
// FROM: /wp-content/uploads/2026/02/Bandit.png
// TO:   https://cdn.yourdomain.com/2026/02/Bandit.png
```

**How it works:**
```php
public function rewrite_attachment_url( string $url, int $attachment_id ): string {
    // Get relative path from metadata
    $file = get_post_meta( $attachment_id, '_wp_attached_file', true );

    // Build CDN URL
    if ( $this->config->has_domain() ) {
        return trailingslashit( $this->config->get_domain() ) . $file;
    }

    // Or return GUID (which is already S3 URL)
    return get_the_guid( $attachment_id );
}
```

---

## File Status: Before vs After

### Before Upload:
```
Local:  NO FILES
S3:     NO FILES
```

### After WordPress Processing (Step 4):
```
Local:  /uploads/2026/02/Bandit.png         (original)
        /uploads/2026/02/Bandit-150x150.png  (thumbnail)
        /uploads/2026/02/Bandit-300x450.png  (medium)
        ... (all other sizes)
S3:     NO FILES
```

### After Our Plugin (Step 10):
```
Local:  /uploads/2026/02/Bandit.png         (KEPT - not deleted)
        /uploads/2026/02/Bandit-150x150.png  (KEPT)
        /uploads/2026/02/Bandit-300x450.png  (KEPT)
        ... (all other sizes - KEPT)

S3:     s3://bucket/2026/02/Bandit.png      (uploaded)
        s3://bucket/2026/02/Bandit-150x150.png (uploaded)
        s3://bucket/2026/02/Bandit-300x450.png (uploaded)
        ... (all other sizes - uploaded)
```

**IMPORTANT:** Local files are **NOT deleted** to ensure:
1. WordPress admin can display thumbnails immediately
2. Backup copy exists on server
3. No risk of data loss if S3 fails

---

## Hook Execution Order

```
1. add_attachment (WordPress)
2. wp_generate_attachment_metadata (Priority 10 - WordPress generates sizes)
3. wp_generate_attachment_metadata (Priority 10 - OUR PLUGIN uploads to S3)
4. wp_update_attachment_metadata (WordPress saves metadata)
5. wp_update_attachment_metadata (Priority 10 - OUR PLUGIN - SKIPPED due to _idrivee2_processed flag)
6. edit_attachment (Not called during initial upload)

Frontend:
7. wp_get_attachment_url (Priority 10 - OUR URL_Rewriter rewrites to CDN)
```

---

## Multiple Images Upload

When user uploads multiple images simultaneously:

```
Image 1:
  1. WordPress processes Image 1 (steps 1-5)
  2. Our plugin uploads Image 1 to S3 (steps 6-14)
  3. WordPress finalizes Image 1 (steps 15-16)

Image 2:
  1. WordPress processes Image 2 (steps 1-5)
  2. Our plugin uploads Image 2 to S3 (steps 6-14)
  3. WordPress finalizes Image 2 (steps 15-16)

Image 3:
  ...
```

**Each image processed sequentially, not in parallel.**

---

## Database State After Upload

### wp_posts table:
```sql
ID: 123
guid: 'https://cdn.yourdomain.com/2026/02/Bandit.png'
post_type: 'attachment'
post_mime_type: 'image/png'
```

### wp_postmeta table:
```sql
post_id: 123, meta_key: '_wp_attached_file', meta_value: '2026/02/Bandit.png'
post_id: 123, meta_key: '_wp_attachment_metadata', meta_value: [serialized metadata array]
post_id: 123, meta_key: '_idrivee2_processed', meta_value: '1'
post_id: 123, meta_key: '_idrivee2_s3_base_url', meta_value: 'https://cdn.yourdomain.com/2026/02'
```

---

## Potential Issues & Solutions

### Issue 1: Hook Fires Multiple Times
**Problem:** WordPress calls `wp_generate_attachment_metadata` and `wp_update_attachment_metadata`
**Solution:** `_idrivee2_processed` flag prevents duplicate uploads

### Issue 2: Thumbnails Not Showing Immediately
**Problem:** If local files deleted, admin can't show preview
**Solution:** Keep local files (don't delete them)

### Issue 3: Wrong URL Used (S3 endpoint instead of CDN)
**Problem:** Using S3 ObjectURL instead of CDN domain
**Solution:** Check `has_domain()` first, construct CDN URL correctly

### Issue 4: Error 500 on Upload
**Possible causes:**
- S3 credentials invalid
- Bucket doesn't exist
- Network timeout
- Memory limit exceeded
**Solution:** Check debug.log for specific error

---

## Current Implementation Status

✅ **Working:**
- Files upload to S3 correctly
- Multiple sizes generated and uploaded
- Duplicate uploads prevented
- Logging working

⚠️ **Issues:**
- Need to verify CDN URLs used correctly
- Need to test with multiple images
- Need to verify thumbnails show immediately in admin

---

## Testing Checklist

- [ ] Upload single image from Media → Add New
- [ ] Verify thumbnail shows immediately in Media Library
- [ ] Verify no error 500
- [ ] Check debug.log for errors
- [ ] Verify only 1 set of uploads (not duplicates)
- [ ] Upload multiple images (2-3 at once)
- [ ] Verify all thumbnails show
- [ ] Check S3 bucket - verify all files present
- [ ] Check local files - verify they exist
- [ ] View image on frontend
- [ ] Verify URL uses CDN domain (if configured)
- [ ] Verify URL works (returns image)

---

**Last Updated:** 2026-02-03
**Status:** Testing Required
