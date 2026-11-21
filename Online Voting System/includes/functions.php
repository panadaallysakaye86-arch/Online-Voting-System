<?php
/**
 * includes/functions.php
 * Core Helper Functions: Sanitization, CSRF, Flash Messages, Settings & Uploads
 */

// ----------------------------------------------------
// 1. Output Sanitization & Base URL Helper
// ----------------------------------------------------

/**
 * Escape strings for safe HTML output (XSS defense).
 */
function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a dynamic URL relative to the project root directory.
 * Ensures all internal links work smoothly across local XAMPP and InfinityFree.
 */
function url(string $path = ''): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = dirname($script);
    // Strip subdirectories if executed inside /admin, /voter, or /includes
    $cleanDir = preg_replace('#/(admin|voter|includes)$#i', '', $dir);
    $base = rtrim($cleanDir, '/\\');
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
}

// ----------------------------------------------------
// 2. CSRF (Cross-Site Request Forgery) Defense
// ----------------------------------------------------

/**
 * Generate or retrieve the current session's CSRF token.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output an HTML hidden input containing the CSRF token.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate a submitted CSRF token using constant-time string comparison.
 */
function validate_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ----------------------------------------------------
// 3. Flash Messaging System
// ----------------------------------------------------

/**
 * Store a one-time flash message in the session.
 * @param string $type success | danger | warning | info
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear the flash message.
 */
function get_flash(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Render flash messages as a dismissible Bootstrap 5 alert.
 */
function display_flash(): void {
    $flash = get_flash();
    if ($flash) {
        $type = e($flash['type']);
        $message = e($flash['message']);
        echo <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show my-3 shadow-sm" role="alert">
            {$message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
HTML;
    }
}

// ----------------------------------------------------
// 4. Election Settings & Schedule Helpers
// ----------------------------------------------------

/**
 * Fetch a value from the `settings` table.
 */
function get_setting(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row !== false && $row['setting_value'] !== null ? $row['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Insert or update a key-value setting.
 */
function set_setting(PDO $pdo, string $key, string $value): bool {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Determine the current status of the election schedule.
 * Returns: ['status' => 'upcoming'|'active'|'ended', 'label' => string, 'start' => string, 'end' => string]
 */
function get_election_schedule(PDO $pdo): array {
    $title = get_setting($pdo, 'election_title', 'General Online Voting');
    $startStr = get_setting($pdo, 'election_start', '2026-01-01 08:00:00');
    $endStr = get_setting($pdo, 'election_end', '2026-12-31 17:00:00');

    $now = time();
    $startTime = strtotime($startStr) ?: 0;
    $endTime = strtotime($endStr) ?: 0;

    if ($now < $startTime) {
        $status = 'upcoming';
        $badgeClass = 'bg-secondary';
        $label = 'Election Not Started';
    } elseif ($now > $endTime) {
        $status = 'ended';
        $badgeClass = 'bg-dark';
        $label = 'Election Ended';
    } else {
        $status = 'active';
        $badgeClass = 'bg-success';
        $label = 'Voting Active';
    }

    return [
        'title'      => $title,
        'status'     => $status,
        'label'      => $label,
        'badgeClass' => $badgeClass,
        'start'      => $startStr,
        'end'        => $endStr,
        'is_open'    => ($status === 'active')
    ];
}

// ----------------------------------------------------
// 5. Secure Photo Upload Helpers
// ----------------------------------------------------

/**
 * Validate and upload a candidate photo.
 * Allowed: JPG, PNG, WEBP up to 2MB. Uses getimagesize() for image verification.
 *
 * @param array $file The $_FILES['photo'] array
 * @param string $uploadDir Absolute path to uploads directory
 * @return array ['success' => bool, 'filename' => ?string, 'error' => ?string]
 */
function upload_candidate_photo(array $file, string $uploadDir): array {
    // Check if a file was provided
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filename' => null, 'error' => 'File upload error code: ' . $file['error']];
    }

    // Size limit: 2MB (2 * 1024 * 1024 bytes)
    $maxSize = 2 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'filename' => null, 'error' => 'Photo exceeds the maximum allowed size of 2MB.'];
    }

    // Verify real image via getimagesize()
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['success' => false, 'filename' => null, 'error' => 'Uploaded file is not a valid image.'];
    }

    // Check MIME type and extension
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $mime = $imageInfo['mime'];
    if (!array_key_exists($mime, $allowedMimes)) {
        return ['success' => false, 'filename' => null, 'error' => 'Only JPG, PNG, and WEBP images are allowed.'];
    }

    $extension = $allowedMimes[$mime];
    // Generate safe, unique filename
    $newFilename = uniqid('cand_', true) . '.' . $extension;
    $targetPath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'filename' => null, 'error' => 'Failed to save the uploaded image to the server.'];
    }

    return ['success' => true, 'filename' => $newFilename, 'error' => null];
}

/**
 * Remove an old candidate photo file from disk if it exists.
 */
function delete_candidate_photo(?string $filename, string $uploadDir): void {
    if (empty($filename)) {
        return;
    }
    $filePath = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . basename($filename);
    if (file_exists($filePath) && is_file($filePath)) {
        @unlink($filePath);
    }
}

