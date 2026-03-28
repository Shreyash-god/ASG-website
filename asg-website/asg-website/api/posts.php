<?php
/**
 * ASG — Posts API (Blog, Code, Docs)
 */
define('ASG_BOOT', 1);
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!feature('blog_enabled')) json_err('Blog is currently disabled', 503);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

match($action) {
    'list'    => listPosts(),
    'get'     => getPost(),
    'create'  => createPost(),
    'update'  => updatePost(),
    'delete'  => deletePost(),
    'upload'  => uploadMedia(),
    default   => json_err('Unknown action', 404)
};

function listPosts(): never {
    $type   = sanitize($_GET['type'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(20, max(1, (int)($_GET['limit'] ?? 9)));
    $offset = ($page - 1) * $limit;
    $search = sanitize($_GET['q'] ?? '');

    $where  = ["p.status='published'"];
    $params = [];

    if ($type && in_array($type, ['blog','code','documentation','news'])) {
        $where[] = "p.type=?";
        $params[] = $type;
    }
    if ($search) {
        $where[] = "MATCH(p.title, p.content) AGAINST(? IN BOOLEAN MODE)";
        $params[] = $search . '*';
    }

    $cond = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = DB::count("SELECT COUNT(*) FROM posts p $cond", $params);
    $posts = DB::all(
        "SELECT p.id, p.uuid, p.title, p.slug, p.excerpt, p.cover_image,
                p.type, p.views, p.tags, p.published_at,
                u.display_name AS author_name, u.avatar_url AS author_avatar
         FROM posts p
         JOIN users u ON u.id = p.author_id
         $cond
         ORDER BY p.is_featured DESC, p.published_at DESC
         LIMIT $limit OFFSET $offset",
        $params
    );

    foreach ($posts as &$p) {
        $p['tags'] = json_decode($p['tags'] ?? '[]', true);
    }

    json_ok(['posts' => $posts, 'total' => $total, 'page' => $page, 'pages' => ceil($total / $limit)]);
}

function getPost(): never {
    $slug = sanitize($_GET['slug'] ?? '');
    if (!$slug) json_err('Slug required');

    $post = DB::one(
        "SELECT p.*, u.display_name AS author_name, u.avatar_url AS author_avatar
         FROM posts p JOIN users u ON u.id=p.author_id
         WHERE p.slug=? AND p.status='published'",
        [$slug]
    );
    if (!$post) json_err('Post not found', 404);

    DB::query("UPDATE posts SET views=views+1 WHERE id=?", [$post['id']]);
    $post['tags'] = json_decode($post['tags'] ?? '[]', true);

    $comments = feature('comments_enabled') ? DB::all(
        "SELECT c.*, u.display_name, u.avatar_url FROM comments c
         JOIN users u ON u.id=c.user_id
         WHERE c.post_id=? AND c.is_approved=1 ORDER BY c.created_at ASC",
        [$post['id']]
    ) : [];

    json_ok(['post' => $post, 'comments' => $comments]);
}

function createPost(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    Auth::requireAdmin();
    CSRF::verifyOrFail();

    $title   = sanitize($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $type    = sanitize($_POST['type'] ?? 'blog');
    $excerpt = sanitize($_POST['excerpt'] ?? '');
    $status  = sanitize($_POST['status'] ?? 'draft');
    $tags    = json_encode(array_map('trim', explode(',', $_POST['tags'] ?? '')));
    $featured = isset($_POST['featured']) ? 1 : 0;

    if (!$title || !$content) json_err('Title and content required');

    $slug = slugify($title);
    $exists = DB::count("SELECT COUNT(*) FROM posts WHERE slug=?", [$slug]);
    if ($exists) $slug .= '-' . time();

    $cover = null;
    if (!empty($_FILES['cover']['name'])) {
        $up    = upload_file($_FILES['cover'], 'uploads/posts');
        $cover = $up['url'];
    }

    $published = $status === 'published' ? date('Y-m-d H:i:s') : null;
    $id = DB::insert(
        "INSERT INTO posts (author_id, title, slug, excerpt, content, cover_image, type, status, is_featured, tags, published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
        [Auth::id(), $title, $slug, $excerpt, $content, $cover, $type, $status, $featured, $tags, $published]
    );

    audit('post.create', "post:$id");
    json_ok(['post_id' => $id, 'slug' => $slug, 'message' => 'Post created']);
}

function updatePost(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    Auth::requireAdmin();
    CSRF::verifyOrFail();

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) json_err('Post ID required');

    $post = DB::one("SELECT * FROM posts WHERE id=?", [$id]);
    if (!$post) json_err('Post not found', 404);

    $title   = sanitize($_POST['title'] ?? $post['title']);
    $content = $_POST['content'] ?? $post['content'];
    $status  = sanitize($_POST['status'] ?? $post['status']);
    $type    = sanitize($_POST['type'] ?? $post['type']);
    $excerpt = sanitize($_POST['excerpt'] ?? $post['excerpt']);
    $tags    = json_encode(array_map('trim', explode(',', $_POST['tags'] ?? '')));
    $featured = isset($_POST['featured']) ? 1 : 0;
    $cover   = $post['cover_image'];

    if (!empty($_FILES['cover']['name'])) {
        $up    = upload_file($_FILES['cover'], 'uploads/posts');
        $cover = $up['url'];
    }

    $published = $post['published_at'];
    if ($status === 'published' && !$published) $published = date('Y-m-d H:i:s');

    DB::query(
        "UPDATE posts SET title=?, content=?, excerpt=?, cover_image=?, type=?, status=?, is_featured=?, tags=?, published_at=? WHERE id=?",
        [$title, $content, $excerpt, $cover, $type, $status, $featured, $tags, $published, $id]
    );

    audit('post.update', "post:$id");
    json_ok(['message' => 'Post updated']);
}

function deletePost(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    Auth::requireAdmin();
    CSRF::verifyOrFail();

    $id = (int)($_POST['id'] ?? 0);
    if (!$id) json_err('ID required');
    DB::query("DELETE FROM posts WHERE id=?", [$id]);
    audit('post.delete', "post:$id");
    json_ok(['message' => 'Post deleted']);
}

function uploadMedia(): never {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('Method not allowed', 405);
    Auth::requireAdmin();
    if (empty($_FILES['file'])) json_err('No file uploaded');
    $up = upload_file($_FILES['file'], 'uploads/media');
    DB::insert(
        "INSERT INTO media (uploader_id, filename, filepath, mime_type, file_size) VALUES (?,?,?,?,?)",
        [Auth::id(), $up['filename'], $up['path'], $up['mime'], $_FILES['file']['size']]
    );
    json_ok(['url' => $up['url'], 'filename' => $up['filename']]);
}
