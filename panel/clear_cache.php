<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPCache flushed successfully.";
} else {
    echo "OPCache is not enabled or function not exists.";
}
?>
