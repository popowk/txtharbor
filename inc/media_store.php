<?php
/** Future external cloud media contract. No provider is enabled in v1.5. */
require_once __DIR__.'/entries.php';
/**
 * A future provider supplies prepareUpload / completeUpload / authorizeRead
 * / delete semantics. Values must be object IDs, never permanent public URLs.
 * This abstraction deliberately has no networking or file upload behavior.
 */
interface ThExternalMediaProvider {
    public function prepareUpload($context, $mime, $size);
    public function completeUpload($ticket);
    public function authorizeRead($objectId, $ttlSeconds);
    public function delete($objectId);
}
/** Future media endpoint must check access BEFORE resolving cloud object IDs. */
function th_media_access($entry, $descriptor, $provider = null) {
    if (!is_array($descriptor) || !th_entry_public_content($entry)) return false;
    // Explicitly disabled until an actual provider, CSRF flow, and signed URLs exist.
    return false;
}
