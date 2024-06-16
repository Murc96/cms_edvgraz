<?php
require '../includes/validate.php';
require '../includes/db-connect.php';
require '../includes/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? null;

if (!$id) {
    redirect('categories.php', ['error' => 'Invalid category ID']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = filter_input(INPUT_POST, 'confirm', FILTER_VALIDATE_BOOLEAN);

    if ($confirm) {
        // Check if category has any associated articles
        $sql = "SELECT COUNT(*) as count FROM articles WHERE category_id = :id";
        $result = pdo_execute($pdo, $sql, ['id' => $id])->fetch();

        if ($result['count'] > 0) {
            redirect('categories.php', ['error' => 'Category cannot be deleted because it has associated articles']);
        } else {
            $sql = "DELETE FROM category WHERE id = :id";
            try {
                pdo_execute($pdo, $sql, ['id' => $id]);
                redirect('categories.php', ['success' => 'Category successfully deleted']);
            } catch (PDOException $e) {
                redirect('categories.php', ['error' => 'There was an issue deleting the category']);
            }
        }
    } else {
        redirect('categories.php');
    }
}
?>

<?php include '../includes/header-admin.php' ?>
<main class="container w-auto mx-auto md:w-1/2 flex justify-center flex-col items-center p-5">
    <h2 class="text-3xl text-blue-500 mb-8">Delete Category</h2>
    <form class="w-full grid" action="category-delete.php?id=<?= $id ?>" method="POST">
        <p>Are you sure you want to delete this category?</p>
        <div class="flex space-x-4 mt-4">
            <button type="submit" name="confirm" value="1" class="text-white  bg-red-500 p-3 rounded-md hover:bg-red-600 m-2">Yes</button>
            <button type="submit" name="confirm" value="0" class="text-white bg-blue-500 p-3 rounded-md hover:bg-gray-600 m-2">No</button>
        </div>
    </form>
</main>
<?php include '../includes/footer-admin.php' ?>