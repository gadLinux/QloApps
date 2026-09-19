<?php
/**
 * Guard: prevent directory listing of brand assets.
 * @copyright GF Experiences 2026
 */
header('Expires: -1');
header('Cache-Control: no-store');
header('Pragma: no-cache');
exit;
