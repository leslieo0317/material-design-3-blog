<?php
declare(strict_types=1);

session_start();

const APP_NAME = 'MD3 Personal Blog';
const DATA_DIR = __DIR__ . '/../data';
const DB_PATH = DATA_DIR . '/blog.sqlite';
const UPLOAD_DIR = __DIR__ . '/../uploads';

if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(UPLOAD_DIR . '/covers')) {
    mkdir(UPLOAD_DIR . '/covers', 0755, true);
}
if (!is_dir(UPLOAD_DIR . '/avatars')) {
    mkdir(UPLOAD_DIR . '/avatars', 0755, true);
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            display_name TEXT NOT NULL,
            email TEXT,
            avatar TEXT NOT NULL DEFAULT '',
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            banned INTEGER NOT NULL DEFAULT 0,
            previous_role TEXT NOT NULL DEFAULT 'user',
            ban_until TEXT NOT NULL DEFAULT '',
            ban_reason TEXT NOT NULL DEFAULT '',
            oidc_email TEXT NOT NULL DEFAULT '',
            email_verified INTEGER NOT NULL DEFAULT 0,
            email_verify_token TEXT NOT NULL DEFAULT '',
            password_reset_token TEXT NOT NULL DEFAULT '',
            password_reset_expires TEXT NOT NULL DEFAULT '',
            bio TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category_id INTEGER,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            excerpt TEXT NOT NULL,
            content TEXT NOT NULL,
            cover TEXT NOT NULL DEFAULT '',
            featured_home INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'published',
            review_status TEXT NOT NULL DEFAULT 'approved',
            review_note TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE,
            description TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS post_tags (
            post_id INTEGER NOT NULL,
            tag_id INTEGER NOT NULL,
            PRIMARY KEY (post_id, tag_id),
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            parent_id INTEGER,
            body TEXT NOT NULL,
            review_status TEXT NOT NULL DEFAULT 'approved',
            review_note TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(post_id, user_id),
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS comment_likes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            comment_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(comment_id, user_id),
            FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS favorites (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(post_id, user_id),
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS follows (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            follower_id INTEGER NOT NULL,
            following_id INTEGER NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(follower_id, following_id),
            FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            actor_id INTEGER,
            type TEXT NOT NULL,
            body TEXT NOT NULL,
            url TEXT NOT NULL DEFAULT '',
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
        );

        CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    ");

    ensure_column($pdo, 'posts', 'category_id', 'INTEGER');
    ensure_column($pdo, 'posts', 'featured_home', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'posts', 'review_status', "TEXT NOT NULL DEFAULT 'approved'");
    ensure_column($pdo, 'posts', 'review_note', "TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'users', 'banned', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'previous_role', "TEXT NOT NULL DEFAULT 'user'");
    ensure_column($pdo, 'users', 'ban_until', "TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'users', 'ban_reason', "TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'users', 'oidc_email', "TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'users', 'email_verified', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'users', 'email_verify_token', "TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'users', 'password_reset_token', "TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'users', 'password_reset_expires', "TEXT NOT NULL DEFAULT ''");
    ensure_column($pdo, 'comments', 'parent_id', 'INTEGER');
    ensure_column($pdo, 'comments', 'review_status', "TEXT NOT NULL DEFAULT 'approved'");
    ensure_column($pdo, 'comments', 'review_note', "TEXT NOT NULL DEFAULT ''");
    $pdo->exec("UPDATE posts SET review_status = 'approved' WHERE review_status IS NULL OR review_status = ''");
    $pdo->exec("UPDATE comments SET review_status = 'approved' WHERE review_status IS NULL OR review_status = ''");
    $pdo->exec("UPDATE users SET role = 'banned', previous_role = CASE WHEN previous_role = '' OR previous_role = 'banned' THEN 'user' ELSE previous_role END WHERE banned = 1 AND role != 'banned'");
    $pdo->exec("UPDATE users SET role = 'user', banned = 0, previous_role = 'user', ban_until = '', ban_reason = '' WHERE role = 'banned' AND ban_until != '' AND datetime(ban_until) <= datetime('now')");

    $categoryCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($categoryCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO categories (name, slug, description, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute(['技术笔记', 'tech-notes', '服务器、开发、工具和折腾记录', 1]);
        $stmt->execute(['生活随笔', 'life', '日常、想法和灵感碎片', 2]);
    }

    $tagCount = (int)$pdo->query('SELECT COUNT(*) FROM tags')->fetchColumn();
    if ($tagCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
        $stmt->execute(['MD3', 'md3']);
        $stmt->execute(['Oracle', 'oracle']);
    }

    $adminExists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminExists === 0) {
        $stmt = $pdo->prepare('INSERT INTO users (username, display_name, email, avatar, password_hash, role, bio) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'admin',
            '站长',
            'admin@example.com',
            'https://api.dicebear.com/8.x/adventurer/svg?seed=md3',
            password_hash('admin123456', PASSWORD_DEFAULT),
            'admin',
            '热爱 Android Material Design 3、服务器和写作。'
        ]);
    }

    $linkCount = (int)$pdo->query('SELECT COUNT(*) FROM links')->fetchColumn();
    if ($linkCount === 0) {
        $stmt = $pdo->prepare('INSERT INTO links (title, url, description, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute(['我的主站', 'https://example.com', '个人主页、作品和服务入口', 1]);
        $stmt->execute(['项目仓库', 'https://github.com/', '代码、实验和开源记录', 2]);
        $stmt->execute(['导航页', 'https://example.org', '常用工具与资源站点', 3]);
    }

    $settingCount = (int)$pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    if ($settingCount === 0) {
        seed_default_settings();
    }

    $postCount = (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
    if ($postCount === 0) {
        $adminId = (int)$pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, slug, excerpt, content, cover) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $adminId,
            '博客上线第一天',
            'hello-md3-blog',
            '一个使用 Android Material Design 3 风格设计的轻量个人博客。',
            "## 欢迎来到我的博客\n\n这里支持多人登录、评论、点赞、文章发布和后台管理。你可以在后台修改首页头像、站点介绍和友情链接。\n\n部署时只需要 **PHP 8+** 和 SQLite 扩展，非常适合 Oracle 服务器配合宝塔面板使用。",
            'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=1400&q=80'
        ]);
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && ($user['role'] ?? '') === 'banned' && ($user['ban_until'] ?? '') !== '' && strtotime($user['ban_until']) <= time()) {
        $update = db()->prepare("UPDATE users SET role = ?, banned = 0, previous_role = 'user', ban_until = '', ban_reason = '' WHERE id = ?");
        $update->execute(['user', $user['id']]);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('请先登录。');
        redirect('?page=login');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function is_banned_user(?array $user): bool
{
    return (bool)$user && (($user['role'] ?? '') === 'banned' || !empty($user['banned']));
}

function can_publish_post(?array $user): bool
{
    if (!$user || is_banned_user($user)) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    return setting('posts_enabled', '1') === '1' && ($user['role'] ?? '') === 'blogger';
}

function can_comment(?array $user): bool
{
    if (!$user || is_banned_user($user)) {
        return false;
    }
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }
    return setting('comments_enabled', '1') === '1';
}

function user_group_label(string $role): string
{
    return [
        'admin' => '管理员',
        'blogger' => '博主',
        'user' => '普通用户',
        'banned' => '封禁用户',
    ][$role] ?? '普通用户';
}

function review_status_label(string $status): string
{
    return [
        'approved' => '已通过',
        'pending' => '待审核',
        'rejected' => '未通过',
    ][$status] ?? '待审核';
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $message = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $message;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('CSRF token mismatch');
    }
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([$key, $value]);
}

function default_settings(): array
{
    return [
        'site_brand' => 'MD3 Blog',
        'nav_write_label' => '发布博客',
        'nav_login_label' => '登录',
        'nav_register_label' => '注册',
        'hero_eyebrow' => 'Material Design 3 Personal Blog',
        'hero_title' => '你好，我是站长',
        'hero_subtitle' => '这里记录技术、生活、服务器部署和灵感碎片。',
        'hero_avatar' => 'https://api.dicebear.com/8.x/adventurer/svg?seed=md3',
        'hero_primary_button' => '发布博客',
        'hero_secondary_button' => '浏览文章',
        'hero_badge_title' => '站点由你掌控',
        'hero_badge_subtitle' => '头像、简介、链接都可在后台修改',
        'links_section_title' => '我的其他网站',
        'links_section_subtitle' => '把主站、项目、导航页或服务入口集中展示。',
        'posts_section_title' => '最新博客',
        'posts_section_subtitle' => '登录后可以点赞、评论，也可以进入个人主页管理自己的资料。',
        'post_read_more_label' => '阅读全文',
        'empty_posts_text' => '还没有公开文章。',
        'home_latest_limit' => '6',
        'browser_site_title' => 'MD3 Blog',
        'browser_site_subtitle' => '个人博客',
        'site_favicon' => '',
        'seo_description' => '一个使用 Android Material Design 3 风格设计的个人博客。',
        'seo_keywords' => '博客,MD3,Material Design,个人网站',
        'seo_author' => '站长',
        'announcement_enabled' => '0',
        'announcement_version' => '1',
        'announcement_title' => '公告',
        'announcement_body' => '欢迎访问我的博客。',
        'register_tip' => '用户名用于登录，昵称用于公开展示；注册时头像只支持 URL。',
        'profile_tip' => '你可以选择 URL 或上传图片来更新头像，上传后会弹出裁剪窗口。',
        'write_tip' => '发布前建议补充标签和封面图，方便读者浏览。',
        'footer_text' => 'All rights reserved © 2025 | The Venetian® Macao',
        'logo_mode' => 'icon',
        'logo_icon' => 'auto_stories',
        'logo_url' => '',
        'color_scheme' => 'violet',
        'hero_buttons_json' => '[{"icon":"draw","text":"发布博客","url":"?page=write","style":"filled","sort":1},{"icon":"south","text":"浏览文章","url":"#posts","style":"tonal","sort":2}]',
        'registration_enabled' => '1',
        'posts_enabled' => '1',
        'comments_enabled' => '1',
        'review_posts_enabled' => '0',
        'review_comments_enabled' => '0',
        'oidc_enabled' => '0',
        'oidc_login_label' => '使用 OIDC 登录',
        'oidc_issuer' => '',
        'oidc_client_id' => '',
        'oidc_client_secret' => '',
        'oidc_redirect_uri' => '',
        'redis_enabled' => '0',
        'redis_host' => '127.0.0.1',
        'redis_port' => '6379',
        'redis_password' => '',
        'hcaptcha_enabled' => '0',
        'hcaptcha_site_key' => '',
        'hcaptcha_secret' => '',
        'hcaptcha_login' => '0',
        'hcaptcha_register' => '0',
        'hcaptcha_post' => '0',
        'hcaptcha_comment' => '0',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => 'MD3 Blog',
    ];
}

function seed_default_settings(): void
{
    foreach (default_settings() as $key => $value) {
        set_setting($key, $value);
    }
}

function setting(string $key, string $fallback = ''): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    if ($value !== false) {
        return (string)$value;
    }
    $defaults = default_settings();
    return $fallback !== '' ? $fallback : ($defaults[$key] ?? '');
}

function slugify(string $title): string
{
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $title), '-'));
    if ($slug === '') {
        $slug = 'post-' . date('YmdHis');
    }
    $base = $slug;
    $i = 2;
    while (true) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM posts WHERE slug = ?');
        $stmt->execute([$slug]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i++;
    }
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    foreach ($columns as $item) {
        if (($item['name'] ?? '') === $column) {
            return;
        }
    }
    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function upload_file(string $field, string $kind): string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }
    $tmp = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) {
        return '';
    }
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $mime = $info['mime'] ?? '';
    if (!isset($extensions[$mime])) {
        return '';
    }
    $dir = UPLOAD_DIR . '/' . $kind;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
    $target = $dir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        return '';
    }
    return 'uploads/' . $kind . '/' . $name;
}

function save_data_image(string $dataUrl, string $kind): string
{
    if (!preg_match('/^data:image\/(png|jpeg|webp);base64,/', $dataUrl, $match)) {
        return '';
    }
    $extension = $match[1] === 'jpeg' ? 'jpg' : $match[1];
    $data = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
    if ($data === false || strlen($data) > 5 * 1024 * 1024) {
        return '';
    }
    $dir = UPLOAD_DIR . '/' . $kind;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    file_put_contents($dir . '/' . $name, $data);
    return 'uploads/' . $kind . '/' . $name;
}

function fetch_post_tags(int $postId): array
{
    $stmt = db()->prepare('SELECT tags.* FROM tags JOIN post_tags ON post_tags.tag_id = tags.id WHERE post_tags.post_id = ? ORDER BY tags.name');
    $stmt->execute([$postId]);
    return $stmt->fetchAll();
}

function sync_post_tags(int $postId, array $tagIds): void
{
    $delete = db()->prepare('DELETE FROM post_tags WHERE post_id = ?');
    $delete->execute([$postId]);
    $insert = db()->prepare('INSERT OR IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)');
    foreach (array_unique(array_map('intval', $tagIds)) as $tagId) {
        if ($tagId > 0) {
            $insert->execute([$postId, $tagId]);
        }
    }
}

function hero_buttons(): array
{
    $buttons = json_decode(setting('hero_buttons_json'), true);
    if (!is_array($buttons)) {
        $buttons = [];
    }
    usort($buttons, fn($a, $b) => (int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0));
    return array_slice($buttons, 0, 6);
}

function color_schemes(): array
{
    return [
        'violet' => '经典紫',
        'green' => '自然绿',
        'blue' => '科技蓝',
        'rose' => '柔和粉',
        'amber' => '暖琥珀',
    ];
}

function text_excerpt(string $text, int $length = 120): string
{
    $text = trim($text);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $length);
    }
    return substr($text, 0, $length);
}

function sanitize_html(string $html): string
{
    $allowed = '<p><br><strong><b><em><i><u><s><h2><h3><h4><blockquote><pre><code><ul><ol><li><a><img><figure><figcaption><hr>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/on\w+="[^"]*"/i', '', $html);
    $html = preg_replace("/on\w+='[^']*'/i", '', $html);
    $html = preg_replace('/javascript:/i', '', $html);
    return $html;
}

function markdown_to_html(string $markdown): string
{
    $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
    if ($markdown === '') {
        return '';
    }

    $html = '';
    $paragraph = [];
    $inList = false;
    $inCode = false;
    $codeLines = [];
    $lines = explode("\n", $markdown);

    $flushParagraph = function () use (&$html, &$paragraph): void {
        if (!$paragraph) {
            return;
        }
        $html .= '<p>' . markdown_inline(implode(' ', $paragraph)) . '</p>';
        $paragraph = [];
    };
    $closeList = function () use (&$html, &$inList): void {
        if ($inList) {
            $html .= '</ul>';
            $inList = false;
        }
    };

    foreach ($lines as $line) {
        $trim = trim($line);

        if (str_starts_with($trim, '```')) {
            if ($inCode) {
                $html .= '<pre><code>' . e(implode("\n", $codeLines)) . '</code></pre>';
                $codeLines = [];
                $inCode = false;
            } else {
                $flushParagraph();
                $closeList();
                $inCode = true;
            }
            continue;
        }

        if ($inCode) {
            $codeLines[] = $line;
            continue;
        }

        if ($trim === '') {
            $flushParagraph();
            $closeList();
            continue;
        }

        if (preg_match('/^(#{1,4})\s+(.+)$/', $trim, $match)) {
            $flushParagraph();
            $closeList();
            $level = min(strlen($match[1]) + 1, 4);
            $html .= '<h' . $level . '>' . markdown_inline($match[2]) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^>\s?(.+)$/', $trim, $match)) {
            $flushParagraph();
            $closeList();
            $html .= '<blockquote>' . markdown_inline($match[1]) . '</blockquote>';
            continue;
        }

        if (preg_match('/^[-*]\s+(.+)$/', $trim, $match)) {
            $flushParagraph();
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $html .= '<li>' . markdown_inline($match[1]) . '</li>';
            continue;
        }

        $paragraph[] = $trim;
    }

    if ($inCode) {
        $html .= '<pre><code>' . e(implode("\n", $codeLines)) . '</code></pre>';
    }
    $flushParagraph();
    $closeList();

    return $html;
}

function render_post_content(string $content): string
{
    if (preg_match('/<\/?(p|h2|h3|h4|blockquote|pre|code|ul|ol|li|strong|em|img|a)\b/i', $content)) {
        return sanitize_html($content);
    }
    return markdown_to_html($content);
}

function markdown_inline(string $text): string
{
    $escaped = e($text);
    $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped);
    $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped);
    $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped);
    $escaped = preg_replace('/!\[([^\]]*)\]\((https?:\/\/[^)\s]+)\)/', '<img src="$2" alt="$1">', $escaped);
    $escaped = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $escaped);
    return $escaped;
}

function markdown_plain_text(string $markdown): string
{
    $text = preg_replace('/```.*?```/s', '', $markdown);
    $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '$1', (string)$text);
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', (string)$text);
    $text = preg_replace('/[#>*_`-]+/', ' ', (string)$text);
    return trim(preg_replace('/\s+/', ' ', (string)$text));
}

function post_stats(int $postId, ?int $userId = null): array
{
    $likes = db()->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
    $likes->execute([$postId]);
    $comments = db()->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ? AND review_status = 'approved'");
    $comments->execute([$postId]);
    $liked = false;
    if ($userId) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ? AND user_id = ?');
        $stmt->execute([$postId, $userId]);
        $liked = (int)$stmt->fetchColumn() > 0;
    }
    return [
        'likes' => (int)$likes->fetchColumn(),
        'comments' => (int)$comments->fetchColumn(),
        'liked' => $liked,
    ];
}

function comment_stats(int $commentId, ?int $userId = null): array
{
    $likes = db()->prepare('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?');
    $likes->execute([$commentId]);
    $liked = false;
    if ($userId) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ? AND user_id = ?');
        $stmt->execute([$commentId, $userId]);
        $liked = (int)$stmt->fetchColumn() > 0;
    }
    return [
        'likes' => (int)$likes->fetchColumn(),
        'liked' => $liked,
    ];
}

function post_user_flags(int $postId, ?int $userId): array
{
    $favorite = false;
    if ($userId) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM favorites WHERE post_id = ? AND user_id = ?');
        $stmt->execute([$postId, $userId]);
        $favorite = (int)$stmt->fetchColumn() > 0;
    }
    return ['favorite' => $favorite];
}

function unread_notification_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function notify_user(int $userId, ?int $actorId, string $type, string $body, string $url = ''): void
{
    if ($actorId && $actorId === $userId) {
        return;
    }
    $stmt = db()->prepare('INSERT INTO notifications (user_id, actor_id, type, body, url) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $actorId, $type, $body, $url]);
}

function notify_mentions(string $text, int $actorId, string $url = ''): void
{
    if (!preg_match_all('/@([A-Za-z0-9_\-]+)/u', $text, $matches)) {
        return;
    }
    $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
    foreach (array_unique($matches[1]) as $username) {
        $stmt->execute([$username]);
        $targetId = (int)$stmt->fetchColumn();
        if ($targetId > 0) {
            notify_user($targetId, $actorId, 'mention', '有人在内容中提到了你。', $url);
        }
    }
}

function paginate(int $total, int $page, int $perPage = 15): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return [$page, $perPage, ($page - 1) * $perPage, $pages];
}

function render_pagination(string $baseUrl, int $page, int $pages): void
{
    if ($pages <= 1) {
        return;
    }
    echo '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $fragment = '';
        $url = $baseUrl;
        if (str_contains($baseUrl, '#')) {
            [$url, $fragment] = explode('#', $baseUrl, 2);
            $fragment = '#' . $fragment;
        }
        $sep = str_contains($url, '?') ? '&' : '?';
        echo '<a class="' . ($i === $page ? 'active' : '') . '" href="' . e($url . $sep . 'p=' . $i . $fragment) . '">' . $i . '</a>';
    }
    echo '</div>';
}

function hcaptcha_widget(string $context): string
{
    if (setting('hcaptcha_enabled', '0') !== '1' || setting('hcaptcha_' . $context, '0') !== '1' || setting('hcaptcha_site_key') === '') {
        return '';
    }
    return '<div class="h-captcha" data-sitekey="' . e(setting('hcaptcha_site_key')) . '"></div><script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
}

function verify_hcaptcha(string $context): bool
{
    if (setting('hcaptcha_enabled', '0') !== '1' || setting('hcaptcha_' . $context, '0') !== '1') {
        return true;
    }
    $token = $_POST['h-captcha-response'] ?? '';
    if ($token === '' || setting('hcaptcha_secret') === '') {
        return false;
    }
    $payload = http_build_query(['secret' => setting('hcaptcha_secret'), 'response' => $token]);
    $response = @file_get_contents('https://hcaptcha.com/siteverify', false, stream_context_create([
        'http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $payload, 'timeout' => 6],
    ]));
    $json = $response ? json_decode($response, true) : [];
    return !empty($json['success']);
}

function send_site_mail(string $to, string $subject, string $body): bool
{
    $from = setting('smtp_from_email') ?: 'no-reply@example.com';
    $name = setting('smtp_from_name', 'MD3 Blog');
    return @mail($to, $subject, $body, 'From: ' . $name . ' <' . $from . '>');
}

function site_url(string $path = ''): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = ($https ? 'https://' : 'http://') . $host . strtok($_SERVER['SCRIPT_NAME'] ?? '/index.php', '?');
    return $base . $path;
}

function redis_client(): ?object
{
    static $redis = false;
    if ($redis !== false) {
        return $redis;
    }
    if (setting('redis_enabled', '0') !== '1' || !class_exists('Redis')) {
        $redis = null;
        return null;
    }
    try {
        $client = new Redis();
        $client->connect(setting('redis_host', '127.0.0.1'), (int)setting('redis_port', '6379'), 1.5);
        if (setting('redis_password') !== '') {
            $client->auth(setting('redis_password'));
        }
        $redis = $client;
    } catch (Throwable) {
        $redis = null;
    }
    return $redis;
}

function cache_get(string $key): mixed
{
    $redis = redis_client();
    if (!$redis) {
        return null;
    }
    $value = $redis->get('md3blog:' . $key);
    return $value === false ? null : json_decode($value, true);
}

function cache_set(string $key, mixed $value, int $ttl = 120): void
{
    $redis = redis_client();
    if ($redis) {
        $redis->setex('md3blog:' . $key, $ttl, json_encode($value, JSON_UNESCAPED_UNICODE));
    }
}
