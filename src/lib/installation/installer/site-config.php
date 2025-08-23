<?php
@session_start();
// install.php
require_once __DIR__ . "/../../vendor/autoload.php";

if (!isset($_SESSION['install'])) {
    echo "Access denied";
    exit;
}

if ($_SESSION['install'] !== true) {
    echo "Access denied";
    exit;
}

$site = \Simp\Core\components\form\FormDefinitionBuilder::factory()->getForm('site.form');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Website Installer</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="/core/assets/milligram-1.4.1/dist/milligram.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            padding: 30px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        button {
            padding: 12px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #0056b3;
        }

        .success-message {
            display: none;
            color: green;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Site Form</h2>
    <?= $site ?>
</div>
<script src="/core/assets/default/password.js"></script>
</body>
</html>
