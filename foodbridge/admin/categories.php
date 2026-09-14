<?php
// CATEGORY MANAGEMENT (protected: require_role('admin'))
// - Add / edit / delete food categories
// - A category referenced by existing donations cannot be deleted
//   (donations.category_id has ON DELETE RESTRICT) - caught and shown
//   as a friendly error instead of a raw DB failure
require_once __DIR__ . '/../includes/role_check.php';
require_role('admin');

$errors = [];
$success = null;

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'add') {
        $name = sanitize_input($_POST['category_name'] ?? '');
        if ($name === '' || mb_strlen($name) > 50) {
            $errors[] = 'Category name is required (max 50 characters).';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO categories (category_name) VALUES (:name)');
                $stmt->execute([':name' => $name]);
                $success = 'Category added.';
            } catch (PDOException $ex) {
                $errors[] = ($ex->getCode() === '23000')
                    ? 'A category with that name already exists.'
                    : 'Could not add the category.';
                error_log('FoodBridge category add failed: ' . $ex->getMessage());
            }
        }

    } elseif ($action === 'edit') {
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        $name = sanitize_input($_POST['category_name'] ?? '');
        if (!$category_id) {
            $errors[] = 'Invalid category.';
        } elseif ($name === '' || mb_strlen($name) > 50) {
            $errors[] = 'Category name is required (max 50 characters).';
        } else {
            try {
                $stmt = $pdo->prepare('UPDATE categories SET category_name = :name WHERE category_id = :id');
                $stmt->execute([':name' => $name, ':id' => $category_id]);
                $success = 'Category updated.';
            } catch (PDOException $ex) {
                $errors[] = ($ex->getCode() === '23000')
                    ? 'A category with that name already exists.'
                    : 'Could not update the category.';
                error_log('FoodBridge category edit failed: ' . $ex->getMessage());
            }
        }

    } elseif ($action === 'delete') {
        $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
        if ($category_id) {
            try {
                $stmt = $pdo->prepare('DELETE FROM categories WHERE category_id = :id');
                $stmt->execute([':id' => $category_id]);
                $success = 'Category deleted.';
            } catch (PDOException $ex) {
                $errors[] = ($ex->getCode() === '23000')
                    ? 'Cannot delete this category - it is used by existing donations.'
                    : 'Could not delete the category.';
                error_log('FoodBridge category delete failed: ' . $ex->getMessage());
            }
        }
    }
}

$stmt = $pdo->query(
    'SELECT c.category_id, c.category_name,
            (SELECT COUNT(*) FROM donations WHERE category_id = c.category_id) AS donation_count
     FROM categories c
     ORDER BY c.category_name'
);
$categories = $stmt->fetchAll();

$current_page = 'categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Category Management - <?= e(SITE_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php require __DIR__ . '/_nav.php'; ?>

<div class="container pb-5">
    <h3 class="mb-3">Category Management</h3>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <h5 class="card-title">Add Category</h5>
        <form method="post" action="categories.php" class="row g-2">
          <input type="hidden" name="action" value="add">
          <div class="col-sm-8">
            <input type="text" class="form-control" name="category_name" maxlength="50" placeholder="Category name" required>
          </div>
          <div class="col-sm-4">
            <button type="submit" class="btn btn-success w-100">Add</button>
          </div>
        </form>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered bg-white align-middle">
        <thead class="table-light">
          <tr><th>Category Name</th><th>Donations Using It</th><th style="width: 140px;">Delete</th></tr>
        </thead>
        <tbody>
          <?php if (!$categories): ?>
            <tr><td colspan="3" class="text-center text-muted">No categories yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td>
                <form method="post" action="categories.php" class="d-flex gap-2">
                  <input type="hidden" name="action" value="edit">
                  <input type="hidden" name="category_id" value="<?= (int) $cat['category_id'] ?>">
                  <input type="text" class="form-control form-control-sm" name="category_name" maxlength="50"
                         value="<?= e($cat['category_name']) ?>">
                  <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Save</button>
                </form>
              </td>
              <td><?= (int) $cat['donation_count'] ?></td>
              <td>
                <form method="post" action="categories.php"
                      onsubmit="return confirm('Delete this category? This cannot be undone.');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="category_id" value="<?= (int) $cat['category_id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
</div>
</body>
</html>
