<?php
session_start();
if (!empty($_SESSION['admin_user'])) {
    echo "pong";
} else {
    http_response_code(401);
    echo "unauthorized";
}
