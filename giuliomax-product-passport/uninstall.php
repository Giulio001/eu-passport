<?php

/** Passport identities and documents are intentionally retained on uninstall. */

defined('WP_UNINSTALL_PLUGIN') || exit;
// No automatic deletion: printed identifiers may still be in use.
