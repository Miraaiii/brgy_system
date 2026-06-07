<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/connection.php';
require_once __DIR__ . '/../../config/auth_helpers.php';

function adm_e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function adm_bind_params($stmt, $types, array $params) {
    if ($types === '') {
        return true;
    }

    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }

    return $stmt->bind_param($types, ...$refs);
}

function adm_table_exists($conn, $table) {
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS table_count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
          AND table_name = ?
    ");
    if (!$stmt) {
        $cache[$table] = false;
        return false;
    }

    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cache[$table] = isset($row['table_count']) && (int)$row['table_count'] > 0;
    return $cache[$table];
}

function adm_column_exists($conn, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $stmt = $conn->prepare("
        SELECT COUNT(*) AS column_count
        FROM information_schema.columns
        WHERE table_schema = DATABASE()
          AND table_name = ?
          AND column_name = ?
    ");
    if (!$stmt) {
        $cache[$key] = false;
        return false;
    }

    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $cache[$key] = isset($row['column_count']) && (int)$row['column_count'] > 0;
    return $cache[$key];
}

function adm_fetch_one($conn, $sql, $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    adm_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ?: null;
}

function adm_fetch_all($conn, $sql, $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }

    adm_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();

    return $rows;
}

function adm_scalar($conn, $sql, $types = '', array $params = []) {
    $row = adm_fetch_one($conn, $sql, $types, $params);
    if (!$row) {
        return 0;
    }

    return (int)reset($row);
}

function adm_set_flash($type, $message) {
    $_SESSION['admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function adm_pull_flash() {
    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    return $flash;
}

function adm_admin_roles() {
    return ['captain', 'secretary', 'treasurer', 'kagawad', 'sk_chair', 'sk_kagawad'];
}

function adm_role_label($role) {
    $role = strtolower(trim((string)$role));
    $labels = [
        'captain' => 'Punong Barangay',
        'secretary' => 'Barangay Secretary',
        'treasurer' => 'Barangay Treasurer',
        'kagawad' => 'Barangay Kagawad',
        'sk_chair' => 'SK Chairperson',
        'sk_kagawad' => 'SK Kagawad',
        'resident' => 'Resident',
    ];

    return $labels[$role] ?? ucwords(str_replace('_', ' ', $role ?: 'Official'));
}

function adm_role_badge_class($role) {
    $role = strtolower(trim((string)$role));
    $classes = [
        'captain' => 'warning',
        'secretary' => 'processing',
        'treasurer' => 'approved',
        'kagawad' => 'approval',
        'sk_chair' => 'approval',
        'sk_kagawad' => 'approval',
    ];

    return $classes[$role] ?? 'neutral';
}

function adm_is_captain($user) {
    return strtolower(trim((string)($user['role'] ?? ''))) === 'captain';
}

function adm_require_admin($conn, array $allowed_roles = []) {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    $user = adm_fetch_one(
        $conn,
        'SELECT id, username, fullname, email, role, status, contact, purok
         FROM users
         WHERE id = ?
         LIMIT 1',
        'i',
        [(int)$_SESSION['user_id']]
    );

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit();
    }

    $role = strtolower(trim((string)($user['role'] ?? '')));
    $status = strtolower(trim((string)($user['status'] ?? 'active')));
    $admin_roles = adm_admin_roles();
    if (!in_array($role, $admin_roles, true) || $status !== 'active') {
        header('Location: ../logout.php');
        exit();
    }

    $allowed_roles = $allowed_roles ?: $admin_roles;
    $allowed_roles = array_map(fn($item) => strtolower(trim((string)$item)), $allowed_roles);
    if (!in_array($role, $allowed_roles, true)) {
        adm_set_flash('danger', 'Access denied. Your official role cannot open that page.');
        header('Location: dashboard.php');
        exit();
    }

    $_SESSION['role'] = $role;
    $_SESSION['email'] = $user['email'];

    return $user;
}

function adm_require_secretary($conn) {
    return adm_require_admin($conn, ['secretary']);
}

function adm_require_captain($conn) {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }

    $user = adm_require_admin($conn);
    if (!adm_is_captain($user)) {
        adm_set_flash('danger', 'Access denied. This page is reserved for the Punong Barangay.');
        header('Location: dashboard.php');
        exit();
    }

    return $user;
}

function adm_initials($name) {
    $initials = '';
    foreach (preg_split('/\s+/', trim((string)$name)) as $part) {
        if ($part !== '') {
            $initials .= strtoupper(substr($part, 0, 1));
        }
    }

    return substr($initials ?: 'BS', 0, 2);
}

function adm_first_name($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    return $parts && $parts[0] !== '' ? $parts[0] : 'Secretary';
}

function adm_date($value) {
    $time = strtotime((string)$value);
    return $time ? date('M j, Y', $time) : 'Not set';
}

function adm_date_long($value) {
    $time = strtotime((string)$value);
    return $time ? date('F j, Y', $time) : 'Not set';
}

function adm_datetime($value) {
    $time = strtotime((string)$value);
    return $time ? date('F j, Y, g:i A', $time) : 'Not set';
}

function adm_relative_time($value) {
    $time = strtotime((string)$value);
    if (!$time) {
        return 'Recently';
    }

    $diff = time() - $time;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $minutes = (int)floor($diff / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int)floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 604800) {
        $days = (int)floor($diff / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }

    return adm_date_long($value);
}

function adm_age($birth_date) {
    $time = strtotime((string)$birth_date);
    if (!$time) {
        return '';
    }

    $birth = new DateTime(date('Y-m-d', $time));
    return (string)$birth->diff(new DateTime('today'))->y;
}

function adm_file_size($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }
    return $bytes . ' bytes';
}

function adm_status_label($status) {
    $status = strtolower(trim((string)$status));
    $labels = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'for_approval' => 'For Approval',
        'approved' => 'Ready for Pick-up',
        'released' => 'Released',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
        'active' => 'Active',
        'suspended' => 'Suspended',
        'deceased' => 'Deceased',
        'transferred' => 'Transferred',
        'open' => 'Open',
        'under_mediation' => 'Under Mediation',
        'settled' => 'Settled',
        'escalated' => 'Escalated',
        'closed' => 'Closed',
        'published' => 'Published',
        'draft' => 'Draft',
        'planning' => 'Planning',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'scheduled' => 'Scheduled',
        'held' => 'Held',
    ];

    return $labels[$status] ?? ucwords(str_replace('_', ' ', $status ?: 'Unknown'));
}

function adm_status_class($status) {
    $status = strtolower(trim((string)$status));
    $classes = [
        'pending' => 'pending',
        'processing' => 'processing',
        'for_approval' => 'approval',
        'approved' => 'approved',
        'released' => 'released',
        'cancelled' => 'neutral',
        'rejected' => 'danger',
        'active' => 'approved',
        'suspended' => 'danger',
        'deceased' => 'neutral',
        'transferred' => 'neutral',
        'open' => 'danger',
        'under_mediation' => 'processing',
        'settled' => 'approved',
        'closed' => 'neutral',
        'escalated' => 'danger',
        'planning' => 'pending',
        'ongoing' => 'processing',
        'completed' => 'approved',
        'on_hold' => 'neutral',
        'scheduled' => 'pending',
        'held' => 'approved',
    ];

    return $classes[$status] ?? 'neutral';
}

function adm_request_is_overdue($created_at, $status) {
    $status = strtolower(trim((string)$status));
    if (in_array($status, ['approved', 'released', 'rejected', 'cancelled'], true)) {
        return false;
    }

    $time = strtotime((string)$created_at);
    return $time && $time < strtotime('-3 days');
}

function adm_action_token() {
    return bms_csrf_token('admin_action_csrf');
}

function adm_verify_action_token($token) {
    return bms_verify_csrf_token($token, 'admin_action_csrf');
}

function adm_log_activity($conn, $user_id, $action, $table_name = null, $record_id = null, array $new_values = []) {
    if (!adm_table_exists($conn, 'audit_logs')) {
        return;
    }

    $json = $new_values ? json_encode($new_values, JSON_UNESCAPED_SLASHES) : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null;
    $stmt = $conn->prepare(
        'INSERT INTO audit_logs (user_id, action, table_name, record_id, new_values, ip_address, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return;
    }

    $record_id = $record_id !== null ? (int)$record_id : null;
    $stmt->bind_param('ississs', $user_id, $action, $table_name, $record_id, $json, $ip, $agent);
    $stmt->execute();
    $stmt->close();
}

function adm_send_email($to, $subject, $html_body, $text_body = '') {
    $to = trim((string)$to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    if ((string)getenv('MAIL_USERNAME') === '' || (string)getenv('MAIL_PASSWORD') === '') {
        return false;
    }

    $mailer_path = __DIR__ . '/../../config/mailer.php';
    if (!file_exists($mailer_path)) {
        return false;
    }

    try {
        require_once $mailer_path;
        if (!function_exists('createMailer')) {
            return false;
        }

        $mail = createMailer();
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $html_body;
        $mail->AltBody = $text_body !== '' ? $text_body : strip_tags($html_body);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function adm_create_notification($conn, $user_id, $type, $title, $message, $link = null, $email = null, $email_subject = null) {
    if ($user_id && adm_table_exists($conn, 'notifications')) {
        $stmt = $conn->prepare(
            'INSERT INTO notifications (user_id, type, title, message, link)
             VALUES (?, ?, ?, ?, ?)'
        );
        if ($stmt) {
            $stmt->bind_param('issss', $user_id, $type, $title, $message, $link);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($email !== null) {
        adm_send_email($email, $email_subject ?: $title, '<p>' . nl2br(adm_e($message)) . '</p>', $message);
    }
}

function adm_notify_request_status($conn, $request_id, $status, $reason = '') {
    $request = adm_fetch_one(
        $conn,
        'SELECT dr.reference_no, dr.remarks, dt.name AS document_name,
                u.id AS user_id, u.email
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         INNER JOIN residents r ON r.id = dr.resident_id
         LEFT JOIN users u ON u.id = r.user_id
         WHERE dr.id = ?
         LIMIT 1',
        'i',
        [(int)$request_id]
    );

    if (!$request || empty($request['user_id'])) {
        return;
    }

    $status = strtolower(trim((string)$status));
    $reference_no = $request['reference_no'];
    $document_name = $request['document_name'] ?: 'document';
    $reason = trim($reason !== '' ? (string)$reason : (string)($request['remarks'] ?? ''));
    $messages = [
        'approved' => 'Your ' . $document_name . ' is ready for pickup at the barangay hall.',
        'released' => 'Your request ' . $reference_no . ' has been released.',
        'rejected' => 'Your request ' . $reference_no . ' was rejected. Reason: ' . ($reason !== '' ? $reason : 'Please contact the barangay office.'),
        'for_approval' => 'Your request ' . $reference_no . ' is awaiting Barangay Captain approval.',
    ];

    $message = $messages[$status] ?? ('Your request ' . $reference_no . ' is now ' . adm_status_label($status) . '.');
    adm_create_notification(
        $conn,
        (int)$request['user_id'],
        'request_status',
        'Request status updated',
        $message,
        'portal/request-detail.php?id=' . (int)$request_id,
        $request['email'] ?? null
    );
}

function adm_notify_captains_for_approval($conn, $request_id) {
    $request = adm_fetch_one(
        $conn,
        'SELECT dr.reference_no, dt.name AS document_name
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         WHERE dr.id = ?
         LIMIT 1',
        'i',
        [(int)$request_id]
    );
    if (!$request) {
        return;
    }

    $captains = adm_fetch_all(
        $conn,
        "SELECT id, email FROM users WHERE role = 'captain' AND status = 'active'"
    );
    foreach ($captains as $captain) {
        adm_create_notification(
            $conn,
            (int)$captain['id'],
            'captain_approval',
            'Request awaiting approval',
            $request['reference_no'] . ' (' . $request['document_name'] . ') is ready for Captain approval.',
            'dashboard.php',
            $captain['email'] ?? null
        );
    }
}

function adm_generate_doc_number($conn) {
    for ($i = 0; $i < 12; $i++) {
        $candidate = 'DOC-' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $exists = adm_table_exists($conn, 'issued_documents')
            ? adm_scalar($conn, 'SELECT COUNT(*) FROM issued_documents WHERE doc_number = ?', 's', [$candidate])
            : 0;
        if ($exists === 0) {
            return $candidate;
        }
    }

    return 'DOC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function adm_create_issued_document($conn, $request_id, $issued_by) {
    if (!adm_table_exists($conn, 'issued_documents')) {
        return false;
    }

    $existing = adm_fetch_one(
        $conn,
        'SELECT id FROM issued_documents WHERE request_id = ? LIMIT 1',
        'i',
        [(int)$request_id]
    );
    if ($existing) {
        return true;
    }

    $doc_number = adm_generate_doc_number($conn);
    $qr_token = bin2hex(random_bytes(32));
    $stmt = $conn->prepare(
        'INSERT INTO issued_documents (request_id, doc_number, qr_token, issued_by)
         VALUES (?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }

    $request_id = (int)$request_id;
    $issued_by = (int)$issued_by;
    $stmt->bind_param('issi', $request_id, $doc_number, $qr_token, $issued_by);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function adm_process_document_request($conn, $request_id, $secretary_id) {
    $request = adm_fetch_one($conn, 'SELECT id, status FROM document_requests WHERE id = ? LIMIT 1', 'i', [(int)$request_id]);
    if (!$request) {
        return [false, 'Request not found.'];
    }
    if (strtolower((string)$request['status']) !== 'pending') {
        return [false, 'Only pending requests can be moved to processing.'];
    }

    $stmt = $conn->prepare(
        "UPDATE document_requests
         SET status = 'processing', processed_by = ?, processed_at = NOW(), updated_at = NOW()
         WHERE id = ? AND status = 'pending'"
    );
    if (!$stmt) {
        return [false, 'Unable to prepare request update.'];
    }

    $request_id = (int)$request_id;
    $secretary_id = (int)$secretary_id;
    $stmt->bind_param('ii', $secretary_id, $request_id);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        return [false, 'Request was already updated.'];
    }

    adm_log_activity($conn, $secretary_id, 'Processed document request', 'document_requests', $request_id, ['status' => 'processing']);
    return [true, 'Request moved to processing.'];
}

function adm_send_request_for_approval($conn, $request_id, $secretary_id) {
    $request = adm_fetch_one(
        $conn,
        'SELECT id, status FROM document_requests WHERE id = ? LIMIT 1',
        'i',
        [(int)$request_id]
    );
    if (!$request) {
        return [false, 'Request not found.'];
    }
    if (strtolower((string)$request['status']) !== 'processing') {
        return [false, 'Only processing requests can be sent for Captain approval.'];
    }

    $stmt = $conn->prepare(
        "UPDATE document_requests
         SET status = 'for_approval',
             processed_by = COALESCE(processed_by, ?),
             processed_at = COALESCE(processed_at, NOW()),
             updated_at = NOW()
         WHERE id = ? AND status = 'processing'"
    );
    if (!$stmt) {
        return [false, 'Unable to prepare request update.'];
    }

    $request_id = (int)$request_id;
    $secretary_id = (int)$secretary_id;
    $stmt->bind_param('ii', $secretary_id, $request_id);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        return [false, 'Request was already updated.'];
    }

    adm_notify_captains_for_approval($conn, $request_id);
    adm_notify_request_status($conn, $request_id, 'for_approval');
    adm_log_activity($conn, $secretary_id, 'Sent request for Captain approval', 'document_requests', $request_id, ['status' => 'for_approval']);
    return [true, 'Request sent for Captain approval.'];
}

function adm_approve_and_issue_request($conn, $request_id, $secretary_id) {
    $request = adm_fetch_one(
        $conn,
        'SELECT dr.id, dr.status, dt.requires_approval
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         WHERE dr.id = ?
         LIMIT 1',
        'i',
        [(int)$request_id]
    );
    if (!$request) {
        return [false, 'Request not found.'];
    }
    if (strtolower((string)$request['status']) !== 'processing') {
        return [false, 'Only processing requests can be approved by the Secretary.'];
    }
    if ((int)$request['requires_approval'] === 1) {
        return [false, 'This document type requires Barangay Captain approval.'];
    }
    if (!adm_table_exists($conn, 'issued_documents')) {
        return [false, 'Issued documents table is not installed.'];
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "UPDATE document_requests
             SET status = 'approved', approved_by = ?, approved_at = NOW(), updated_at = NOW()
             WHERE id = ? AND status = 'processing'"
        );
        if (!$stmt) {
            throw new Exception('Unable to prepare request update.');
        }
        $request_id = (int)$request_id;
        $secretary_id = (int)$secretary_id;
        $stmt->bind_param('ii', $secretary_id, $request_id);
        $stmt->execute();
        $updated = $stmt->affected_rows > 0;
        $stmt->close();
        if (!$updated) {
            throw new Exception('Request was already updated.');
        }
        if (!adm_create_issued_document($conn, $request_id, $secretary_id)) {
            throw new Exception('Unable to create issued document record.');
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return [false, $e->getMessage()];
    }

    adm_notify_request_status($conn, $request_id, 'approved');
    adm_log_activity($conn, $secretary_id, 'Approved and issued document request', 'document_requests', $request_id, ['status' => 'approved']);
    return [true, 'Request approved and issued.'];
}

function adm_reject_document_request($conn, $request_id, $secretary_id, $reason) {
    $reason = trim((string)$reason);
    if ($reason === '') {
        return [false, 'Rejection reason is required.'];
    }

    $request = adm_fetch_one($conn, 'SELECT id, status FROM document_requests WHERE id = ? LIMIT 1', 'i', [(int)$request_id]);
    if (!$request) {
        return [false, 'Request not found.'];
    }
    if (!in_array(strtolower((string)$request['status']), ['pending', 'processing'], true)) {
        return [false, 'Only pending or processing requests can be rejected by the Secretary.'];
    }

    $stmt = $conn->prepare(
        "UPDATE document_requests
         SET status = 'rejected',
             remarks = ?,
             processed_by = COALESCE(processed_by, ?),
             processed_at = COALESCE(processed_at, NOW()),
             updated_at = NOW()
         WHERE id = ? AND status IN ('pending', 'processing')"
    );
    if (!$stmt) {
        return [false, 'Unable to prepare request update.'];
    }

    $request_id = (int)$request_id;
    $secretary_id = (int)$secretary_id;
    $stmt->bind_param('sii', $reason, $secretary_id, $request_id);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        return [false, 'Request was already updated.'];
    }

    adm_notify_request_status($conn, $request_id, 'rejected', $reason);
    adm_log_activity($conn, $secretary_id, 'Rejected document request', 'document_requests', $request_id, ['status' => 'rejected', 'reason' => $reason]);
    return [true, 'Request rejected and resident notified.'];
}

function adm_release_document_request($conn, $request_id, $secretary_id) {
    $request = adm_fetch_one($conn, 'SELECT id, status FROM document_requests WHERE id = ? LIMIT 1', 'i', [(int)$request_id]);
    if (!$request) {
        return [false, 'Request not found.'];
    }
    if (strtolower((string)$request['status']) !== 'approved') {
        return [false, 'Only approved requests can be marked as released.'];
    }

    $stmt = $conn->prepare(
        "UPDATE document_requests
         SET status = 'released', released_by = ?, released_at = NOW(), updated_at = NOW()
         WHERE id = ? AND status = 'approved'"
    );
    if (!$stmt) {
        return [false, 'Unable to prepare release update.'];
    }

    $request_id = (int)$request_id;
    $secretary_id = (int)$secretary_id;
    $stmt->bind_param('ii', $secretary_id, $request_id);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        return [false, 'Request was already updated.'];
    }

    adm_notify_request_status($conn, $request_id, 'released');
    adm_log_activity($conn, $secretary_id, 'Released document request', 'document_requests', $request_id, ['status' => 'released']);
    return [true, 'Request marked as released.'];
}

function adm_find_or_create_household($conn, $house_number, $street, $purok) {
    $house_number = trim((string)$house_number);
    $street = trim((string)$street);
    $purok = trim((string)$purok);
    if ($street === '') {
        $street = 'Unspecified street';
    }
    if ($purok === '') {
        $purok = 'Unassigned';
    }

    if (!adm_table_exists($conn, 'households')) {
        return null;
    }

    $existing = adm_fetch_one(
        $conn,
        'SELECT id FROM households
         WHERE COALESCE(house_number, "") = ?
           AND street = ?
           AND purok = ?
         LIMIT 1',
        'sss',
        [$house_number, $street, $purok]
    );
    if ($existing) {
        return (int)$existing['id'];
    }

    $stmt = $conn->prepare('INSERT INTO households (house_number, street, purok) VALUES (?, ?, ?)');
    if (!$stmt) {
        return null;
    }
    $house_value = $house_number !== '' ? $house_number : null;
    $stmt->bind_param('sss', $house_value, $street, $purok);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return $id > 0 ? $id : null;
}

function adm_approve_resident_registration($conn, $registration_id, $secretary_id) {
    if (!adm_table_exists($conn, 'pending_resident_registrations') || !adm_table_exists($conn, 'residents')) {
        return [false, 'Resident registration tables are not installed.'];
    }

    $registration = adm_fetch_one(
        $conn,
        "SELECT pr.*, u.status AS user_status
         FROM pending_resident_registrations pr
         INNER JOIN users u ON u.id = pr.user_id
         WHERE pr.id = ? AND pr.status = 'pending'
         LIMIT 1",
        'i',
        [(int)$registration_id]
    );
    if (!$registration) {
        return [false, 'Pending registration not found.'];
    }

    $conn->begin_transaction();
    try {
        $household_id = adm_find_or_create_household(
            $conn,
            $registration['house_number'] ?? '',
            $registration['street_name'] ?? '',
            $registration['purok_zone'] ?? ''
        );
        $fullname = trim($registration['first_name'] . ' ' . ($registration['middle_name'] ? $registration['middle_name'] . ' ' : '') . $registration['last_name']);
        $resident = adm_fetch_one($conn, 'SELECT id FROM residents WHERE user_id = ? LIMIT 1', 'i', [(int)$registration['user_id']]);

        if ($resident) {
            $stmt = $conn->prepare(
                "UPDATE residents
                 SET household_id = ?, last_name = ?, first_name = ?, middle_name = ?,
                     birth_date = ?, birth_place = ?, sex = ?, civil_status = ?,
                     nationality = ?, occupation = ?, contact_number = ?, email = ?,
                     valid_id_path = ?, status = 'active', verified_by = ?, verified_at = NOW()
                 WHERE id = ?"
            );
            if (!$stmt) {
                throw new Exception('Unable to prepare resident update.');
            }
            $middle_name = $registration['middle_name'] ?: null;
            $occupation = $registration['occupation'] ?: null;
            $stmt->bind_param(
                'issssssssssssii',
                $household_id,
                $registration['last_name'],
                $registration['first_name'],
                $middle_name,
                $registration['birth_date'],
                $registration['birth_place'],
                $registration['sex'],
                $registration['civil_status'],
                $registration['nationality'],
                $occupation,
                $registration['mobile_number'],
                $registration['email'],
                $registration['valid_id_path'],
                $secretary_id,
                $resident['id']
            );
            $stmt->execute();
            $resident_id = (int)$resident['id'];
            $stmt->close();
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO residents (
                    user_id, household_id, last_name, first_name, middle_name,
                    birth_date, birth_place, sex, civil_status, nationality,
                    occupation, contact_number, email, valid_id_path,
                    status, verified_by, verified_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())"
            );
            if (!$stmt) {
                throw new Exception('Unable to prepare resident record.');
            }
            $middle_name = $registration['middle_name'] ?: null;
            $occupation = $registration['occupation'] ?: null;
            $user_id = (int)$registration['user_id'];
            $stmt->bind_param(
                'iissssssssssssi',
                $user_id,
                $household_id,
                $registration['last_name'],
                $registration['first_name'],
                $middle_name,
                $registration['birth_date'],
                $registration['birth_place'],
                $registration['sex'],
                $registration['civil_status'],
                $registration['nationality'],
                $occupation,
                $registration['mobile_number'],
                $registration['email'],
                $registration['valid_id_path'],
                $secretary_id
            );
            $stmt->execute();
            $resident_id = (int)$stmt->insert_id;
            $stmt->close();
        }

        $stmt_user = $conn->prepare(
            "UPDATE users
             SET status = 'active', fullname = ?, contact = ?, purok = ?
             WHERE id = ?"
        );
        if (!$stmt_user) {
            throw new Exception('Unable to update resident account.');
        }
        $purok = $registration['purok_zone'] ?: null;
        $user_id = (int)$registration['user_id'];
        $stmt_user->bind_param('sssi', $fullname, $registration['mobile_number'], $purok, $user_id);
        $stmt_user->execute();
        $stmt_user->close();

        $stmt_reg = $conn->prepare(
            "UPDATE pending_resident_registrations
             SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?"
        );
        if (!$stmt_reg) {
            throw new Exception('Unable to update registration queue.');
        }
        $registration_id = (int)$registration_id;
        $secretary_id = (int)$secretary_id;
        $stmt_reg->bind_param('ii', $secretary_id, $registration_id);
        $stmt_reg->execute();
        $stmt_reg->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return [false, $e->getMessage()];
    }

    adm_create_notification(
        $conn,
        (int)$registration['user_id'],
        'account_approved',
        'Resident account approved',
        'Your resident account has been approved. You can now request documents and use the resident portal.',
        'portal/resident_dashboard.php',
        $registration['email'],
        'Your resident account has been approved'
    );
    adm_log_activity($conn, $secretary_id, 'Approved resident registration', 'residents', $resident_id, ['registration_id' => $registration_id]);

    return [true, 'Resident registration approved.'];
}

function adm_reject_resident_registration($conn, $registration_id, $secretary_id, $reason) {
    $reason = trim((string)$reason);
    if ($reason === '') {
        return [false, 'Rejection reason is required.'];
    }

    if (!adm_table_exists($conn, 'pending_resident_registrations')) {
        return [false, 'Registration queue is not installed.'];
    }

    $registration = adm_fetch_one(
        $conn,
        "SELECT * FROM pending_resident_registrations WHERE id = ? AND status = 'pending' LIMIT 1",
        'i',
        [(int)$registration_id]
    );
    if (!$registration) {
        return [false, 'Pending registration not found.'];
    }

    $conn->begin_transaction();
    try {
        $stmt_reg = $conn->prepare(
            "UPDATE pending_resident_registrations
             SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?"
        );
        if (!$stmt_reg) {
            throw new Exception('Unable to update registration queue.');
        }
        $registration_id = (int)$registration_id;
        $secretary_id = (int)$secretary_id;
        $stmt_reg->bind_param('ii', $secretary_id, $registration_id);
        $stmt_reg->execute();
        $stmt_reg->close();

        $stmt_user = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
        if (!$stmt_user) {
            throw new Exception('Unable to update resident account.');
        }
        $user_id = (int)$registration['user_id'];
        $stmt_user->bind_param('i', $user_id);
        $stmt_user->execute();
        $stmt_user->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return [false, $e->getMessage()];
    }

    adm_create_notification(
        $conn,
        (int)$registration['user_id'],
        'account_rejected',
        'Resident account rejected',
        'Your resident account registration was rejected. Reason: ' . $reason,
        null,
        $registration['email'],
        'Your resident account registration was rejected'
    );
    adm_log_activity($conn, $secretary_id, 'Rejected resident registration', 'pending_resident_registrations', $registration_id, ['reason' => $reason]);

    return [true, 'Resident registration rejected.'];
}

function adm_archive_resident($conn, $resident_id, $secretary_id, $status) {
    $status = strtolower(trim((string)$status));
    if (!in_array($status, ['active', 'deceased', 'transferred'], true)) {
        return [false, 'Invalid resident status.'];
    }

    $stmt = $conn->prepare('UPDATE residents SET status = ?, updated_at = NOW() WHERE id = ?');
    if (!$stmt) {
        return [false, 'Unable to update resident.'];
    }
    $resident_id = (int)$resident_id;
    $stmt->bind_param('si', $status, $resident_id);
    $stmt->execute();
    $updated = $stmt->affected_rows >= 0;
    $stmt->close();

    if (!$updated) {
        return [false, 'Unable to update resident.'];
    }

    adm_log_activity($conn, $secretary_id, 'Updated resident status', 'residents', $resident_id, ['status' => $status]);
    return [true, 'Resident status updated.'];
}

function adm_days_waiting($value) {
    $time = strtotime((string)$value);
    if (!$time) {
        return 0;
    }

    return max(0, (int)floor((time() - $time) / 86400));
}

function adm_generate_or_number($conn) {
    for ($i = 0; $i < 12; $i++) {
        $candidate = 'OR-' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $exists = adm_table_exists($conn, 'collections')
            ? adm_scalar($conn, 'SELECT COUNT(*) FROM collections WHERE or_number = ?', 's', [$candidate])
            : 0;
        if ($exists === 0) {
            return $candidate;
        }
    }

    return 'OR-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function adm_create_collection_for_request($conn, $request_id, $collected_by) {
    if (!adm_table_exists($conn, 'collections')) {
        return true;
    }

    $request = adm_fetch_one(
        $conn,
        'SELECT dr.id, dr.resident_id, dr.reference_no, dt.name AS document_name, dt.fee
         FROM document_requests dr
         INNER JOIN document_types dt ON dt.id = dr.doc_type_id
         WHERE dr.id = ?
         LIMIT 1',
        'i',
        [(int)$request_id]
    );
    if (!$request || (float)$request['fee'] <= 0) {
        return true;
    }

    $existing = adm_fetch_one(
        $conn,
        'SELECT id FROM collections WHERE request_id = ? LIMIT 1',
        'i',
        [(int)$request_id]
    );
    if ($existing) {
        return true;
    }

    $or_number = adm_generate_or_number($conn);
    $description = $request['document_name'] . ' fee for ' . $request['reference_no'];
    $source_type = 'document_fee';
    $amount = (float)$request['fee'];
    $resident_id = (int)$request['resident_id'];
    $request_id = (int)$request_id;
    $collected_by = (int)$collected_by;

    $stmt = $conn->prepare(
        'INSERT INTO collections (or_number, request_id, resident_id, source_type, amount, description, collected_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('siisdsi', $or_number, $request_id, $resident_id, $source_type, $amount, $description, $collected_by);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function adm_notify_secretaries_request_returned($conn, $request_id, $reason) {
    $request = adm_fetch_one(
        $conn,
        'SELECT reference_no FROM document_requests WHERE id = ? LIMIT 1',
        'i',
        [(int)$request_id]
    );
    if (!$request) {
        return;
    }

    $secretaries = adm_fetch_all($conn, "SELECT id, email FROM users WHERE role = 'secretary' AND status = 'active'");
    foreach ($secretaries as $secretary) {
        adm_create_notification(
            $conn,
            (int)$secretary['id'],
            'request_returned',
            'Request returned by Captain',
            $request['reference_no'] . ' was sent back for processing. Reason: ' . $reason,
            'admin/request-detail.php?id=' . (int)$request_id,
            $secretary['email'] ?? null
        );
    }
}

function adm_captain_approve_request($conn, $request_id, $captain_id) {
    $request = adm_fetch_one(
        $conn,
        'SELECT id, status FROM document_requests WHERE id = ? LIMIT 1',
        'i',
        [(int)$request_id]
    );
    if (!$request) {
        return [false, 'Request not found.'];
    }
    if (strtolower((string)$request['status']) !== 'for_approval') {
        return [false, 'Only requests awaiting Captain approval can be approved.'];
    }
    if (!adm_table_exists($conn, 'issued_documents')) {
        return [false, 'Issued documents table is not installed.'];
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "UPDATE document_requests
             SET status = 'approved',
                 approved_by = ?,
                 approved_at = NOW(),
                 updated_at = NOW()
             WHERE id = ? AND status = 'for_approval'"
        );
        if (!$stmt) {
            throw new Exception('Unable to prepare approval update.');
        }
        $request_id = (int)$request_id;
        $captain_id = (int)$captain_id;
        $stmt->bind_param('ii', $captain_id, $request_id);
        $stmt->execute();
        $updated = $stmt->affected_rows > 0;
        $stmt->close();
        if (!$updated) {
            throw new Exception('Request was already updated.');
        }
        if (!adm_create_issued_document($conn, $request_id, $captain_id)) {
            throw new Exception('Unable to create issued document record.');
        }
        if (!adm_create_collection_for_request($conn, $request_id, $captain_id)) {
            throw new Exception('Unable to create collection record.');
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        return [false, $e->getMessage()];
    }

    adm_notify_request_status($conn, $request_id, 'approved');
    adm_log_activity($conn, $captain_id, 'doc_approved', 'document_requests', $request_id, ['status' => 'approved']);
    return [true, 'Request approved, signed, and issued.'];
}

function adm_captain_return_request($conn, $request_id, $captain_id, $reason) {
    $reason = trim((string)$reason);
    if ($reason === '') {
        return [false, 'A send-back reason is required.'];
    }

    $request = adm_fetch_one(
        $conn,
        'SELECT id, status FROM document_requests WHERE id = ? LIMIT 1',
        'i',
        [(int)$request_id]
    );
    if (!$request) {
        return [false, 'Request not found.'];
    }
    if (strtolower((string)$request['status']) !== 'for_approval') {
        return [false, 'Only requests awaiting Captain approval can be sent back.'];
    }

    $stmt = $conn->prepare(
        "UPDATE document_requests
         SET status = 'processing',
             remarks = ?,
             updated_at = NOW()
         WHERE id = ? AND status = 'for_approval'"
    );
    if (!$stmt) {
        return [false, 'Unable to prepare send-back update.'];
    }

    $request_id = (int)$request_id;
    $captain_id = (int)$captain_id;
    $stmt->bind_param('si', $reason, $request_id);
    $stmt->execute();
    $updated = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$updated) {
        return [false, 'Request was already updated.'];
    }

    adm_notify_secretaries_request_returned($conn, $request_id, $reason);
    adm_log_activity($conn, $captain_id, 'doc_sent_back', 'document_requests', $request_id, ['status' => 'processing', 'reason' => $reason]);
    return [true, 'Request sent back to the Secretary.'];
}

function adm_ensure_expenditure_approval_columns($conn) {
    if (!adm_table_exists($conn, 'expenditures')) {
        return false;
    }

    if (!adm_column_exists($conn, 'expenditures', 'approval_status')) {
        @$conn->query("ALTER TABLE expenditures ADD COLUMN approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER supporting_doc_path");
    }
    if (!adm_column_exists($conn, 'expenditures', 'approval_notes')) {
        @$conn->query("ALTER TABLE expenditures ADD COLUMN approval_notes TEXT NULL DEFAULT NULL AFTER approval_status");
    }
    if (!adm_column_exists($conn, 'expenditures', 'approved_at')) {
        @$conn->query("ALTER TABLE expenditures ADD COLUMN approved_at TIMESTAMP NULL DEFAULT NULL AFTER approved_by");
    }

    return true;
}

function adm_notify_expenditure_recorder($conn, $expenditure_id, $status, $reason = '') {
    $row = adm_fetch_one(
        $conn,
        'SELECT e.recorded_by, e.amount, e.category, u.email
         FROM expenditures e
         LEFT JOIN users u ON u.id = e.recorded_by
         WHERE e.id = ?
         LIMIT 1',
        'i',
        [(int)$expenditure_id]
    );
    if (!$row || empty($row['recorded_by'])) {
        return;
    }

    $approved = $status === 'approved';
    $message = 'Expenditure for ' . $row['category'] . ' amounting to PHP ' . number_format((float)$row['amount'], 2)
        . ' was ' . ($approved ? 'approved.' : 'rejected. Reason: ' . ($reason !== '' ? $reason : 'No reason provided.'));

    adm_create_notification(
        $conn,
        (int)$row['recorded_by'],
        'expenditure_' . $status,
        'Expenditure ' . adm_status_label($status),
        $message,
        'admin/finance.php?tab=expenditures',
        $row['email'] ?? null
    );
}

function adm_approve_expenditure($conn, $expenditure_id, $captain_id) {
    adm_ensure_expenditure_approval_columns($conn);

    $expenditure = adm_fetch_one(
        $conn,
        'SELECT id, approval_status FROM expenditures WHERE id = ? LIMIT 1',
        'i',
        [(int)$expenditure_id]
    );
    if (!$expenditure) {
        return [false, 'Expenditure not found.'];
    }
    if (strtolower((string)$expenditure['approval_status']) === 'approved') {
        return [false, 'This expenditure is already approved.'];
    }

    $stmt = $conn->prepare(
        "UPDATE expenditures
         SET approval_status = 'approved',
             approval_notes = NULL,
             approved_by = ?,
             approved_at = NOW()
         WHERE id = ?"
    );
    if (!$stmt) {
        return [false, 'Unable to prepare expenditure approval.'];
    }

    $expenditure_id = (int)$expenditure_id;
    $captain_id = (int)$captain_id;
    $stmt->bind_param('ii', $captain_id, $expenditure_id);
    $stmt->execute();
    $ok = $stmt->affected_rows >= 0;
    $stmt->close();

    if (!$ok) {
        return [false, 'Unable to approve expenditure.'];
    }

    adm_notify_expenditure_recorder($conn, $expenditure_id, 'approved');
    adm_log_activity($conn, $captain_id, 'expenditure_approved', 'expenditures', $expenditure_id, ['approval_status' => 'approved']);
    return [true, 'Expenditure approved.'];
}

function adm_reject_expenditure($conn, $expenditure_id, $captain_id, $reason) {
    adm_ensure_expenditure_approval_columns($conn);
    $reason = trim((string)$reason);
    if ($reason === '') {
        return [false, 'A rejection reason is required.'];
    }

    $expenditure = adm_fetch_one(
        $conn,
        'SELECT id, approval_status FROM expenditures WHERE id = ? LIMIT 1',
        'i',
        [(int)$expenditure_id]
    );
    if (!$expenditure) {
        return [false, 'Expenditure not found.'];
    }

    $stmt = $conn->prepare(
        "UPDATE expenditures
         SET approval_status = 'rejected',
             approval_notes = ?,
             approved_by = ?,
             approved_at = NOW()
         WHERE id = ?"
    );
    if (!$stmt) {
        return [false, 'Unable to prepare expenditure rejection.'];
    }

    $expenditure_id = (int)$expenditure_id;
    $captain_id = (int)$captain_id;
    $stmt->bind_param('sii', $reason, $captain_id, $expenditure_id);
    $stmt->execute();
    $ok = $stmt->affected_rows >= 0;
    $stmt->close();

    if (!$ok) {
        return [false, 'Unable to reject expenditure.'];
    }

    adm_notify_expenditure_recorder($conn, $expenditure_id, 'rejected', $reason);
    adm_log_activity($conn, $captain_id, 'expenditure_rejected', 'expenditures', $expenditure_id, ['approval_status' => 'rejected', 'reason' => $reason]);
    return [true, 'Expenditure rejected and returned to the Treasurer.'];
}

function adm_ensure_settings_tables($conn) {
    @$conn->query(
        "CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(80) NOT NULL,
            setting_value TEXT NULL DEFAULT NULL,
            updated_by INT UNSIGNED NULL DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    @$conn->query(
        "CREATE TABLE IF NOT EXISTS role_permissions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            role VARCHAR(40) NOT NULL,
            module VARCHAR(80) NOT NULL,
            can_read TINYINT(1) NOT NULL DEFAULT 0,
            can_write TINYINT(1) NOT NULL DEFAULT 0,
            can_delete TINYINT(1) NOT NULL DEFAULT 0,
            updated_by INT UNSIGNED NULL DEFAULT NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_role_module (role, module)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function adm_get_settings($conn, array $keys) {
    if (!$keys) {
        return array_fill_keys($keys, '');
    }

    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $types = str_repeat('s', count($keys));
    $rows = adm_fetch_all(
        $conn,
        "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ({$placeholders})",
        $types,
        $keys
    );
    $settings = array_fill_keys($keys, '');
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = (string)($row['setting_value'] ?? '');
    }

    return $settings;
}

function adm_save_setting($conn, $key, $value, $user_id) {
    adm_ensure_settings_tables($conn);
    $stmt = $conn->prepare(
        'INSERT INTO system_settings (setting_key, setting_value, updated_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
    );
    if (!$stmt) {
        return false;
    }

    $key = (string)$key;
    $value = (string)$value;
    $user_id = (int)$user_id;
    $stmt->bind_param('ssi', $key, $value, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function adm_ensure_project_tables($conn) {
    @$conn->query(
        "CREATE TABLE IF NOT EXISTS projects (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(160) NOT NULL,
            committee VARCHAR(100) NOT NULL,
            assigned_user_id INT UNSIGNED NULL DEFAULT NULL,
            category VARCHAR(60) NOT NULL,
            description TEXT NOT NULL,
            status ENUM('planning','ongoing','completed','on_hold') NOT NULL DEFAULT 'planning',
            start_date DATE NOT NULL,
            target_end_date DATE NOT NULL,
            estimated_budget DECIMAL(12,2) NULL DEFAULT NULL,
            progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
            archived_at TIMESTAMP NULL DEFAULT NULL,
            created_by INT UNSIGNED NOT NULL,
            updated_by INT UNSIGNED NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_projects_committee (committee),
            KEY idx_projects_status (status),
            KEY idx_projects_assigned (assigned_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    @$conn->query(
        "CREATE TABLE IF NOT EXISTS project_photos (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            project_id INT UNSIGNED NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            original_name VARCHAR(200) NOT NULL,
            uploaded_by INT UNSIGNED NULL DEFAULT NULL,
            uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_project_photos_project (project_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function adm_user_role_values($conn) {
    $row = adm_fetch_one($conn, "SHOW COLUMNS FROM users LIKE 'role'");
    if (!$row || empty($row['Type'])) {
        return ['captain', 'secretary', 'treasurer', 'kagawad'];
    }

    preg_match_all("/'([^']+)'/", (string)$row['Type'], $matches);
    $roles = $matches[1] ?? [];
    $roles = array_values(array_filter($roles, fn($role) => $role !== 'resident'));
    return $roles ?: ['captain', 'secretary', 'treasurer', 'kagawad'];
}

function adm_handle_request_action($conn, $action, $request_id, $user_id, $reason = '', $role = null) {
    $role = strtolower(trim((string)($role ?? ($_SESSION['role'] ?? ''))));
    switch ($action) {
        case 'process_request':
            return adm_process_document_request($conn, $request_id, $user_id);
        case 'approve_issue_request':
            return adm_approve_and_issue_request($conn, $request_id, $user_id);
        case 'send_for_approval':
            return adm_send_request_for_approval($conn, $request_id, $user_id);
        case 'reject_request':
            return adm_reject_document_request($conn, $request_id, $user_id, $reason);
        case 'release_request':
            return adm_release_document_request($conn, $request_id, $user_id);
        case 'captain_approve_request':
            if ($role !== 'captain') {
                return [false, 'Only the Punong Barangay can approve and sign this request.'];
            }
            return adm_captain_approve_request($conn, $request_id, $user_id);
        case 'captain_return_request':
            if ($role !== 'captain') {
                return [false, 'Only the Punong Barangay can send this request back.'];
            }
            return adm_captain_return_request($conn, $request_id, $user_id, $reason);
    }

    return [false, 'Unknown action.'];
}
