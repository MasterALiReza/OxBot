<?php
if (function_exists('opcache_reset')) {
    echo opcache_reset() ? 'OPCache cleared successfully' : 'Failed to clear OPCache';
} else {
    echo 'OPCache extension is not enabled';
}
unlink(__FILE__);
