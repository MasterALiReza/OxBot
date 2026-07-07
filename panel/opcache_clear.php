<?php
// Force webhook trigger comment
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPCache Cleared Successfully!";
} else {
    echo "OPCache extension not available.";
}
