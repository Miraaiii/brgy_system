<?php
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/connection.php';
require_once __DIR__ . '/public_nav.php';
require_once __DIR__ . '/public_footer.php';

if (!function_exists('pub_e')) {
    function pub_e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('pub_bind_params')) {
    function pub_bind_params($stmt, $types, array $params) {
        if ($types === '') {
            return true;
        }

        $refs = [];
        foreach ($params as $key => $value) {
            $refs[$key] = &$params[$key];
        }

        return $stmt->bind_param($types, ...$refs);
    }
}

if (!function_exists('pub_table_exists')) {
    function pub_table_exists($conn, $table) {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $safe_table = $conn->real_escape_string($table);
        $result = $conn->query("SHOW TABLES LIKE '{$safe_table}'");
        $cache[$table] = $result && $result->num_rows > 0;

        return $cache[$table];
    }
}

if (!function_exists('pub_fetch_one')) {
    function pub_fetch_one($conn, $sql, $types = '', array $params = []) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }

        pub_bind_params($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }
}

if (!function_exists('pub_fetch_all')) {
    function pub_fetch_all($conn, $sql, $types = '', array $params = []) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }

        pub_bind_params($stmt, $types, $params);
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
}

if (!function_exists('pub_scalar')) {
    function pub_scalar($conn, $sql, $types = '', array $params = []) {
        $row = pub_fetch_one($conn, $sql, $types, $params);
        if (!$row) {
            return 0;
        }

        return (int)reset($row);
    }
}

if (!function_exists('pub_date')) {
    function pub_date($value) {
        $time = strtotime((string)$value);
        return $time ? date('F j, Y', $time) : 'Not set';
    }
}

if (!function_exists('pub_date_short')) {
    function pub_date_short($value) {
        $time = strtotime((string)$value);
        return $time ? date('M j, Y', $time) : 'Not set';
    }
}

if (!function_exists('pub_excerpt')) {
    function pub_excerpt($value, $limit = 170) {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'))));
        if (strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(substr($text, 0, $limit - 3)) . '...';
    }
}

if (!function_exists('pub_money')) {
    function pub_money($amount) {
        $value = (float)$amount;
        if ($value <= 0.009) {
            return 'Free';
        }

        return 'PHP ' . number_format($value, 2);
    }
}

if (!function_exists('pub_processing_time')) {
    function pub_processing_time($days) {
        $count = max(1, (int)$days);
        return $count . ' business day' . ($count === 1 ? '' : 's');
    }
}

if (!function_exists('pub_requirements_list')) {
    function pub_requirements_list($value) {
        $parts = preg_split('/[;\r\n]+/', (string)$value);
        $items = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $items[] = $part;
            }
        }

        return $items ?: ['Valid government ID', 'Proof of residency'];
    }
}

if (!function_exists('pub_category_meta')) {
    function pub_category_meta($category) {
        $key = strtolower(trim((string)$category));
        $map = [
            'health' => ['label' => 'Health', 'class' => 'health', 'icon' => 'bi-heart-pulse-fill'],
            'events' => ['label' => 'Events', 'class' => 'events', 'icon' => 'bi-calendar-event-fill'],
            'ordinance' => ['label' => 'Ordinance', 'class' => 'ordinance', 'icon' => 'bi-journal-check'],
            'emergency' => ['label' => 'Emergency', 'class' => 'emergency', 'icon' => 'bi-exclamation-triangle-fill'],
            'notice' => ['label' => 'Notice', 'class' => 'notice', 'icon' => 'bi-megaphone-fill'],
            'programs' => ['label' => 'Programs', 'class' => 'programs', 'icon' => 'bi-people-fill'],
            'general' => ['label' => 'General', 'class' => 'general', 'icon' => 'bi-info-circle-fill'],
        ];

        return $map[$key] ?? $map['general'];
    }
}

if (!function_exists('pub_doc_meta')) {
    function pub_doc_meta($slug, $name = '') {
        $key = strtolower(trim((string)$slug));
        $map = [
            'barangay-clearance' => [
                'icon' => 'bi-patch-check-fill',
                'eligibility' => 'Verified residents who need clearance for employment, travel, or general legal use.',
                'required_ids' => 'Valid government ID and proof of residency',
                'description' => 'For employment, travel, legal, or general purpose requirements.',
            ],
            'certificate-residency' => [
                'icon' => 'bi-house-heart-fill',
                'eligibility' => 'Residents with a current address within Barangay Sta. Rosa 1.',
                'required_ids' => 'Valid government ID and proof of address',
                'description' => 'Proof that the requester resides in the barangay.',
            ],
            'certificate-indigency' => [
                'icon' => 'bi-hand-heart-fill',
                'eligibility' => 'Residents requesting support for medical, scholarship, or assistance programs.',
                'required_ids' => 'Valid government ID and supporting assistance document if available',
                'description' => 'Used for medical assistance, scholarships, and social welfare programs.',
            ],
            'business-clearance' => [
                'icon' => 'bi-shop-window',
                'eligibility' => 'Business owners operating within Barangay Sta. Rosa 1.',
                'required_ids' => 'Valid government ID, business address proof, and registration document if available',
                'description' => 'Required for business operation and permit processing.',
            ],
            'barangay-certification' => [
                'icon' => 'bi-file-earmark-text-fill',
                'eligibility' => 'Residents needing a specific barangay certification such as solo parent or good standing.',
                'required_ids' => 'Valid government ID and supporting document for the certification type',
                'description' => 'For resident-specific certifications issued by the barangay.',
            ],
            'blotter-certificate' => [
                'icon' => 'bi-journal-text',
                'eligibility' => 'Complainants or involved parties with an existing blotter case reference.',
                'required_ids' => 'Valid government ID and blotter case number',
                'description' => 'Official extract or certification for a barangay blotter record.',
            ],
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        return [
            'icon' => 'bi-file-earmark-richtext-fill',
            'eligibility' => 'Verified residents with complete request details.',
            'required_ids' => 'Valid government ID and supporting documents',
            'description' => $name !== '' ? 'Official barangay document request.' : 'Barangay document request.',
        ];
    }
}

if (!function_exists('pub_initials')) {
    function pub_initials($name) {
        $initials = '';
        foreach (preg_split('/\s+/', trim((string)$name)) as $part) {
            if ($part !== '' && $part[0] !== '[') {
                $initials .= strtoupper(substr($part, 0, 1));
            }
        }

        return substr($initials ?: 'SR', 0, 2);
    }
}

if (!function_exists('pub_position_label')) {
    function pub_position_label($position) {
        $map = [
            'captain' => 'Punong Barangay',
            'secretary' => 'Barangay Secretary',
            'treasurer' => 'Barangay Treasurer',
            'kagawad' => 'Barangay Kagawad',
            'sk_chair' => 'SK Chairperson',
            'sk_kagawad' => 'SK Kagawad',
        ];

        $key = strtolower(trim((string)$position));
        return $map[$key] ?? ucwords(str_replace('_', ' ', $key ?: 'Official'));
    }
}

if (!function_exists('pub_term_years')) {
    function pub_term_years($start, $end) {
        $start_year = $start ? date('Y', strtotime((string)$start)) : '2023';
        $end_year = $end ? date('Y', strtotime((string)$end)) : '2025';
        return $start_year . '-' . $end_year;
    }
}
