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

$redirect =  $_GET['dest'] ?? '/core/db-config.php';
$page_title = $_SESSION['page_title'] ?? 'Installing Your Application';
unset($_SESSION['install']);
unset($_SESSION['install_redirect']);
unset($_SESSION['page_title']);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: sans-serif;
            background: #f4f4f4;
            padding: 40px;
            text-align: center;
        }
        #installer {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px #ccc;
        }
        .step {
            padding: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .status {
            float: right;
            font-weight: bold;
        }
        .status.running {
            color: #f0ad4e;
        }
        .status.done {
            color: #28a745;
        }
        .status.failed {
            color: #dc3545;
        }
    </style>
</head>
<body>

<div id="installer">
    <h2><?php echo $page_title ?></h2>

    <div class="step" data-step="directories">Moving directories <span class="status">Pending</span></div>
    <div class="step" data-step="modules">Installing modules <span class="status">Pending</span></div>
    <div class="step" data-step="finalize">Finalizing installation <span class="status">Pending</span></div>

    <div id="finish" style="display:none; margin-top: 20px;">
        ✅ Installation complete! Redirecting to configuration...
    </div>
</div>

<script>
    $(function () {
        const steps = [
            {
                key: 'directories',
                task: () => {
                    return $.post('install_tasks.php', { action: 'directories' });
                }
            },
            {
                key: 'modules',
                task: () => {
                    return $.post('install_tasks.php', { action: 'modules' });
                }
            },
            {
                key: 'finalize',
                task: () => {
                    return $.post('install_tasks.php', { action: 'finalize' });
                }
            }
        ];

        const runStep = (index) => {
            if (index >= steps.length) {
                $('#finish').fadeIn();
                setTimeout(() => {
                    window.location.href = '<?php echo $redirect; ?>'; // Change if needed
                }, 3000);
                return;
            }

            const step = steps[index];
            const $el = $('.step[data-step="' + step.key + '"] .status');
            $el.removeClass().addClass('status running').text('Running...');

            step.task().done(response => {
                $el.removeClass().addClass('status done').text('Done');
                setTimeout(() => runStep(index + 1), 500);
            }).fail(() => {
                $el.removeClass().addClass('status failed').text('Failed');
            });
        };

        runStep(0);
    });
</script>

</body>
</html>
