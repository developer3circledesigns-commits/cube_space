<?php
// PATCH for office_detail.php - fixes mysqli_stmt_bind_param error at line 216
// Replace lines 103-115 with this corrected version

// Original buggy code was:
// if ($typeParam && in_array($typeParam, ['managed', 'furnished', 'unfurnished'])) {
//     $tableMap = ['managed' => 'managed_offices', 'furnished' => 'furnished_offices', 'unfurnished' => 'unfurnished_offices'];
//     $table = $tableMap[$typeParam];
//     $stmt = mysqli_prepare($conn, "SELECT * FROM $table WHERE slug = ? AND status = 'active'");
//     mysqli_stmt_bind_param($stmt, 's', $slug);  // ERROR: $stmt is FALSE because $table in SQL is invalid

// CORRECTED VERSION:
if ($typeParam && in_array($typeParam, ['managed', 'furnished', 'unfurnished'])) {
    if ($typeParam === 'managed') {
        $stmt = @mysqli_prepare($conn, "SELECT * FROM managed_offices WHERE slug = ? AND status = 'active'");
    } elseif ($typeParam === 'furnished') {
        $stmt = @mysqli_prepare($conn, "SELECT * FROM furnished_offices WHERE slug = ? AND status = 'active'");
    } else {
        $stmt = @mysqli_prepare($conn, "SELECT * FROM unfurnished_offices WHERE slug = ? AND status = 'active'");
    }
    if ($stmt) {
        @mysqli_stmt_bind_param($stmt, 's', $slug);
        @mysqli_stmt_execute($stmt);
        $office = @mysqli_fetch_assoc(@mysqli_stmt_get_result($stmt));
        @mysqli_stmt_close($stmt);
        if ($office) { $listingTypeDb = $typeParam; }
    }
}

// The key fix: Don't put variable $table in the SQL query before prepare()
// Use if/elseif to hardcode each table name in the SQL, then bind parameters
?>
