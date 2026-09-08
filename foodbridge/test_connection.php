<?php
/**
 * FoodBridge - Database Connection Test
 * ======================================
 * A throwaway page to confirm PHP can reach MySQL through
 * config/database.php. Visit it at:
 *     http://localhost/Project/foodbridge/test_connection.php
 *
 * Delete this file once you've confirmed the connection works -
 * it is not part of the application itself.
 */
require_once __DIR__ . '/config/database.php'; // gives us $pdo
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FoodBridge - DB Connection Test</title>
</head>
<body style="font-family: sans-serif; padding: 2rem;">

<h2>FoodBridge Database Connection Test</h2>

<?php
try {
    // 1. Basic connectivity check - proves $pdo is live.
    $stmt = $pdo->query('SELECT DATABASE() AS db_name, NOW() AS server_time');
    $info = $stmt->fetch();

    echo '<p style="color:green;"><strong>Connected successfully.</strong></p>';
    echo '<p>Database: ' . htmlspecialchars($info['db_name']) . '</p>';
    echo '<p>Server time: ' . htmlspecialchars($info['server_time']) . '</p>';

    // 2. Prepared statement WITHOUT parameters.
    $stmt = $pdo->prepare('SELECT category_id, category_name FROM categories ORDER BY category_name');
    $stmt->execute();
    $categories = $stmt->fetchAll();

    if ($categories) {
        echo '<h3>Categories currently in the database:</h3><ul>';
        foreach ($categories as $category) {
            echo '<li>' . htmlspecialchars($category['category_name']) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>No categories found yet. Run database/schema.sql then database/sample_data.sql in phpMyAdmin.</p>';
    }

    // 3. Prepared statement WITH a bound parameter - demonstrates safe,
    //    injection-proof query usage (never concatenate user input into SQL).
    $stmt = $pdo->prepare('SELECT category_name FROM categories WHERE category_id = :id');
    $stmt->execute([':id' => 1]);
    $first = $stmt->fetch();

    if ($first) {
        echo '<p>Lookup by ID using a bound parameter &rarr; category #1 is "' .
             htmlspecialchars($first['category_name']) . '".</p>';
    }

} catch (PDOException $e) {
    // Log the real error, show only a generic message on screen.
    error_log('FoodBridge test query failed: ' . $e->getMessage());
    echo '<p style="color:red;">A query failed. Check the PHP error log for details.</p>';
}
?>

</body>
</html>
