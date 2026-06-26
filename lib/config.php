<?php
declare(strict_types=1);

const ALLOWED_LISTING_TABLES = [
    'managed'      => 'managed_offices',
    'commercial'   => 'office_spaces',
    'furnished'    => 'furnished_offices',
    'unfurnished'  => 'unfurnished_offices',
];

const ALLOWED_LISTING_STATUSES = ['draft', 'published', 'archived'];

const ALLOWED_CONTACT_STATUSES = ['new', 'contacted', 'closed'];

const ALLOWED_LISTING_COLUMNS = [
    'id', 'title', 'slug', 'listing_type', 'description',
    'city', 'area', 'address', 'price', 'price_label',
    'total_seats', 'total_area_sqft', 'amenities', 'images',
    'featured', 'status', 'seo_text', 'created_at', 'updated_at',
];

function get_listing_table(string $type): ?string {
    return ALLOWED_LISTING_TABLES[$type] ?? null;
}

function validate_listing_table(string $table): bool {
    return in_array($table, ALLOWED_LISTING_TABLES, true);
}

if (!function_exists('e')) {
    function e($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('format_number')) {
    function format_number($val, $decimals = 0) {
        if (is_null($val) || $val === '') {
            return number_format(0, $decimals);
        }
        if (is_numeric($val)) {
            return number_format((float)$val, $decimals);
        }
        $clean = str_replace(',', '', (string)$val);
        $clean = preg_replace('/[^0-9.]/', '', $clean);
        if ($clean === '') {
            return e($val);
        }
        return number_format((float)$clean, $decimals);
    }
}