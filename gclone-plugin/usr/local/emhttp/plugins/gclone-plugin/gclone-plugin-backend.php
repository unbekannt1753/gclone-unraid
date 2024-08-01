<?php
// gclone-plugin-backend.php

// Define constants
define('PLUGIN_DIR', '/boot/config/plugins/gclone-plugin');
define('CONFIG_DIR', PLUGIN_DIR . '/configs');
define('MOUNT_SCRIPT', PLUGIN_DIR . '/mount.sh');

// Ensure the config directory exists
if (!file_exists(CONFIG_DIR)) {
    mkdir(CONFIG_DIR, 0755, true);
}

// Function to sanitize input
function sanitize_input($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Function to validate the form data
function validate_form_data($data) {
    $required_fields = ['driveName', 'clientId', 'clientSecret', 'refreshToken', 'mountPoint'];
    $errors = [];

    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "The field '$field' is required.";
        }
    }

    if (!empty($data['saFolder']) && !is_dir($data['saFolder'])) {
        $errors[] = "The specified Service Account Folder does not exist.";
    }

    if (!empty($data['saFile']) && !file_exists($data['saFile'])) {
        $errors[] = "The specified Service Account File does not exist.";
    }

    return $errors;
}

// Function to create gclone configuration file
function create_gclone_config($data) {
    $config_content = "[" . sanitize_input($data['driveName']) . "]\n";
    $config_content .= "type = drive\n";
    $config_content .= "client_id = " . sanitize_input($data['clientId']) . "\n";
    $config_content .= "client_secret = " . sanitize_input($data['clientSecret']) . "\n";
    $config_content .= "token = " . sanitize_input($data['refreshToken']) . "\n";
    
    if (!empty($data['teamDriveId'])) {
        $config_content .= "team_drive = " . sanitize_input($data['teamDriveId']) . "\n";
    }
    
    if (!empty($data['saFolder'])) {
        $config_content .= "service_account_file_path = " . sanitize_input($data['saFolder']) . "\n";
    } elseif (!empty($data['saFile'])) {
        $config_content .= "service_account_file = " . sanitize_input($data['saFile']) . "\n";
    }

    $config_file = CONFIG_DIR . '/' . sanitize_input($data['driveName']) . '.conf';
    file_put_contents($config_file, $config_content);

    return $config_file;
}

// Function to create or update mount script
function update_mount_script($data) {
    $mount_command = "gclone mount " . sanitize_input($data['driveName']) . ": " . sanitize_input($data['mountPoint']) . " --daemon\n";
    
    if (file_exists(MOUNT_SCRIPT)) {
        $current_content = file_get_contents(MOUNT_SCRIPT);
        if (strpos($current_content, $mount_command) === false) {
            file_put_contents(MOUNT_SCRIPT, $mount_command, FILE_APPEND);
        }
    } else {
        $script_content = "#!/bin/bash\n\n" . $mount_command;
        file_put_contents(MOUNT_SCRIPT, $script_content);
        chmod(MOUNT_SCRIPT, 0755);
    }
}

// Main logic to handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = validate_form_data($_POST);

    if (empty($errors)) {
        $config_file = create_gclone_config($_POST);
        update_mount_script($_POST);

        // Execute the mount script
        exec(MOUNT_SCRIPT . " 2>&1", $output, $return_var);

        if ($return_var !== 0) {
            $errors[] = "Failed to mount the drive. Error: " . implode("\n", $output);
        } else {
            $success_message = "Drive mounted successfully!";
        }
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => empty($errors),
        'errors' => $errors,
        'message' => $success_message ?? ''
    ]);
    exit;
}

// If it's not a POST request, return an error
header('HTTP/1.1 405 Method Not Allowed');
echo "Only POST requests are allowed.";
?>