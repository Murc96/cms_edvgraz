<?php
require '../includes/functions.php';
require '../includes/db-connect.php';
require '../includes/validate.php';

$path_to_img = '/uploads';
$allowed_types = [ 'image/jpeg', 'image/png' ];
$allowed_ext = [ 'jpg', 'jpeg', 'png' ];
$max_size = 1080 * 1920 * 2;

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? '';
$tmp_path= $_FILES['image_file']['tmp_name'] ?? '';
$save_to = '';

$article = [
    'id'        => $id,
    'title'     => '',
    'summary'   => '',
    'content'   => '',
    'published' => false,
    'category_id' => 0,
    'user_id'   => 0,
    'images_id' => null,
    'filename'  => '',
    'alttext'   => '',
    'image_file' => '',
    'image_alt' => ''
];

$errors = [
    'issue'     => '',
    'title'     => '',
    'summary'   => '',
    'content'   => '',
    'user'      => '',
    'category'  => '',
    'filename'  => '',
    'alttext'   => '',
    'image_file' => '',
    'image_alt' => ''
];

$sql = "SELECT id, name FROM category";
$categories = pdo_execute($pdo, $sql)->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT id, forename, surname FROM user";
$users = pdo_execute($pdo, $sql)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_FILES['image_file'])) {
        $image = $_FILES['image_file'];
        
        if ($image['error'] === 1) {
            $errors['filename'] = $image['error'] == 1 ? 'The image is too large ' : '';
        }
        
        if ($image && $image['error'] == UPLOAD_ERR_OK) {
            
            $article['alttext'] = filter_input(INPUT_POST, var_name: 'image_alt');
            
            $errors['alttext'] = is_text($article['alttext'], min: 1, max: 254) ? '' : 'Alt text must be between 1 and 254 characters';
            
            $tmp_path = stream_get_meta_data(tmpfile())['uri'];
            move_uploaded_file($image['tmp_name'], $tmp_path);
            $type = mime_content_type($tmp_path);
            $extension = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            $errors['filename'] .= (!in_array($type, $allowed_types) ? 'The file type is not allowed; ' : '');
            $errors['filename'] .= (!in_array($extension, $allowed_ext) ? 'The file extension is not allowed; ' : '');
            $errors['filename'] .= ($image['size'] > $max_size ? 'The image exceeds the maximum upload size ' : '');
            
            if (!$errors['filename'] && !$errors['alttext']) {
                $article['filename'] = $image['name'];
                $save_to = get_file_path($image['name'], $path_to_img);
            }
        }
    }

    
    $article['title'] = filter_input(INPUT_POST, var_name: 'title');
    $article['summary'] = filter_input(INPUT_POST, var_name: 'summary');
    $article['content'] = filter_input(INPUT_POST, var_name: 'content');
    $article['user_id'] = filter_input(INPUT_POST, var_name: 'user', filter: FILTER_VALIDATE_INT);
    $article['category_id'] = filter_input(INPUT_POST, var_name: 'category', filter: FILTER_VALIDATE_INT);
    $article['published'] = filter_input(INPUT_POST, var_name: 'published', filter: FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

    
    $errors['title'] = is_text($article['title'], min: 1, max: 100) ? '' : 'Title must be between 1 and 100 characters';
    $errors['summary'] = is_text($article['summary'], min: 1, max: 200) ? '' : 'Summary must be between 1 and 200 characters';
    $errors['content'] = is_text($article['content'], min: 1, max: 10000) ? '' : 'Content must be between 1 and 10,000 characters';
    $errors['user'] = is_user_id($article['user_id'], $users) ? '' : 'User not found';
    $errors['category'] = is_category_id($article['category_id'], $categories) ? '' : 'Category not found';

    $problems = implode($errors);


    if (!$problems) {
        
    
        try {
           
            $pdo->beginTransaction();
    
            
            if ($save_to) {
                scale_and_copy($tmp_path, $save_to);
    
                $sql = "INSERT INTO images (filename, alttext) VALUES (:filename, :alttext)";
                $stmt = pdo_execute($pdo, $sql, ['filename' => $article['filename'], 'alttext' => $article['alttext']]);
                $bindings['images_id'] = $pdo->lastInsertId();
            }
    
            
            if (!$id) {
                unset($bindings['filename'], $bindings['alttext'], $bindings['images_id']);
                $sql = "INSERT INTO articles (title, summary, content, category_id, user_id, published, images_id)
                        VALUES (:title, :summary, :content, :category_id, :user_id, :published, :images_id)";
            } else {
                
                $sql = "UPDATE articles SET title = :title, summary = :summary, content = :content, 
                        category_id = :category_id, user_id = :user_id, published = :published, images_id = :images_id WHERE id = :id";
                $bindings['id'] = $id;
            }
    
            $stmt = pdo_execute($pdo, $sql, $bindings);
    
            
            $pdo->commit();
    
            redirect('articles.php', ['success' => 'Article successfully saved']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['issue'] = $e->getMessage();
        }
    }
    
}
?>

<?php include '../includes/header-admin.php'; ?>
<main class="p-10">
    <h2 class="text-3xl text-blue-500 mb-8 text-center"><?= $article['id'] ? 'Edit ' : 'New ' ?>Article</h2>
    <?php if ($errors['issue']): ?>
        <p class="error text-red-500 bg-red-200 p-5 rounded-md"><?= $errors['issue'] ?></p>
    <?php endif ?>
    <form action="article.php?id=<?= e($id) ?>" method="POST" enctype="multipart/form-data" class="grid gap-6 mb-6 md:grid-cols-2 md:w-full">
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 pt-2" for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e($article['title']) ?>"
                   class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
            <span class="text-red-500"><?= $errors['title'] ?></span>
            <label class="block mb-2 text-sm font-medium text-gray-900 pt-2" for="summary">Summary</label>
            <textarea id="summary" name="summary"
                      class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"><?= e($article['summary']) ?></textarea>
            <span class="text-red-500"><?= $errors['summary'] ?></span>
            <label class="block mb-2 text-sm font-medium text-gray-900 pt-2" for="content">Content</label>
            <textarea id="content" rows="10" name="content"
                      class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500"><?= e($article['content']) ?></textarea>
            <span class="text-red-500"><?= $errors['content'] ?></span>
        </div>
        <div>
            <label class="block mb-2 text-sm font-medium text-gray-900 pt-2" for="category">Category</label>
            <select id="category" name="category"
                    class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                <option>select category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['id'] ?>" <?= $category['id'] === $article['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="text-red-500"><?= $errors['category'] ?></span>
            <label class="block mb-2 text-sm font-medium text-gray-900 pt-2" for="user_id">User</label>
            <select id="user_id" name="user"
                    class="block p-2.5 w-full text-sm text-gray-900 bg-gray-50 rounded-lg border border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                <option>select user</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= $user['id'] === $article['user_id'] ? 'selected' : '' ?>><?= e($user['forename']) ?> <?= e($user['surname']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="text-red-500"><?= $errors['user'] ?></span>
            <?php if (!$article['image_file']): ?>
                <label class="block mb-2 text-sm font-medium text-gray-900 pt-2" for="image_file">Image</label>
                <input type="file" id="image_file" accept="image/jpeg, image/png" name="image_file"
                       class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                <span class="text-red-500"><?= $errors['image_file'] ?></span>
            <?php else: ?>
                <img src="../uploads/<?= e($article['image_file']) ?>" alt="<?= e($article['image_alt']) ?>" class="w-full h-auto"/>
                <span>Alt Text: <?= e($article['image_alt']) ?></span>
                <a href="alt-text-edit.php?id=<?= e($article['id']) ?>" class="text-blue-500">Edit Alt Text</a>
                <a href="img-delete.php?id=<?= e($article['id']) ?>" class="text-red-500">Delete Image</a>
            <?php endif; ?>
            <label class="block mb-2 text-sm font-medium text-gray-900 pt-2" for="image_alt">Image Alt</label>
            <input type="text" id="image_alt" name="image_alt" value="<?= e($article['alttext'] ?? '') ?>"
                   class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
            <span class="text-red-500"><?= $errors['image_alt'] ?></span>
            <label class="block mb-2 text-sm font-medium text-gray-900 pt-2" for="published">Published</label>
            <input type="checkbox" id="published" name="published" <?= $article['published'] ? 'checked' : '' ?>
                   class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500 dark:focus:ring-blue-600">
        </div>
        <button type="submit" class="text-white bg-blue-500 p-3 rounded-md hover:bg-pink-600">Save</button>
    </form>
</main>
<?php include '../includes/footer-admin.php'; ?>
