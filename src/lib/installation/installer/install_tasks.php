<?php
@session_start();
// install.php
require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . '/InstallTasks.php';

if (!isset($_SESSION['install'])) {
    echo "Access denied";
    exit;
}

if ($_SESSION['install'] !== true) {
    echo "Access denied";
    exit;
}



$action = $_POST['action'] ?? null;

switch ($action) {
    case 'directories':
        InstallTasks::moveDirectories();
        (new \Simp\Core\lib\installation\InstallerValidator())->bootStorage();
        break;

    case 'modules':
        InstallTasks::moveModules();
        break;

    case 'finalize':
        // Finalize install (e.g., create lock file)
        break;

    default:
        http_response_code(400);
        echo "Invalid action.";
}

echo "OK";
