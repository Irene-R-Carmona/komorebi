<?php
require "/app/vendor/autoload.php";
error_reporting(E_ALL);
$before = get_declared_classes();
try {
    require "/app/tests/Unit/Services/ContextServiceTest.php";
} catch (Throwable $e) {
    echo "ERROR: " . get_class($e) . ": " . $e->getMessage() . "\n";
    exit(1);
}
$after = get_declared_classes();
$new = array_diff($after, $before);
echo "New classes: " . implode(", ", $new) . "\n";
