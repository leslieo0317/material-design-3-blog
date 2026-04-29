<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/actions.php';

$action = $_GET['action'] ?? '';
if ($action !== '') {
    handle_action($action);
}

$page = $_GET['page'] ?? 'home';
$user = current_user();
$flash = flash();

function render_header(?array $user, ?string $flash): void
{
    $titleParts = array_filter([setting('browser_site_title'), setting('browser_site_subtitle')]);
    $browserTitle = implode(' - ', $titleParts) ?: APP_NAME;
    ?>
    <!doctype html>
    <html lang="zh-CN" data-color-scheme="<?= e(setting('color_scheme', 'violet')) ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($browserTitle) ?></title>
        <meta name="description" content="<?= e(setting('seo_description')) ?>">
        <meta name="keywords" content="<?= e(setting('seo_keywords')) ?>">
        <meta name="author" content="<?= e(setting('seo_author')) ?>">
        <meta property="og:title" content="<?= e($browserTitle) ?>">
        <meta property="og:description" content="<?= e(setting('seo_description')) ?>">
        <?php if (setting('site_favicon') !== ''): ?>
            <link rel="icon" href="<?= e(setting('site_favicon')) ?>">
        <?php endif; ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700;800&family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,500,0,0" rel="stylesheet">
        <link rel="stylesheet" href="assets/vendor/vditor/dist/index.css">
        <link rel="stylesheet" href="assets/style.css?v=20260430-2">
    </head>
    <body>
    <header class="top-app-bar">
        <a class="brand" href="index.php" aria-label="返回首页">
            <?php if (setting('logo_mode') === 'url' && setting('logo_url') !== ''): ?>
                <span class="brand-mark image"><img src="<?= e(setting('logo_url')) ?>" alt=""></span>
            <?php else: ?>
                <span class="brand-mark material-symbols-rounded"><?= e(setting('logo_icon', 'auto_stories')) ?></span>
            <?php endif; ?>
            <span><?= e(setting('site_brand')) ?></span>
        </a>
        <nav class="nav-actions">
            <button class="icon-btn theme-toggle" type="button" title="切换明暗主题" aria-label="切换明暗主题" data-theme-toggle>
                <span class="material-symbols-rounded" data-theme-icon>dark_mode</span>
            </button>
            <?php if ($user): ?><a class="icon-btn" href="?page=search" title="搜索"><span class="material-symbols-rounded">search</span></a><?php endif; ?>
            <a class="icon-btn" href="?page=blog" title="博客"><span class="material-symbols-rounded">view_list</span></a>
            <?php if (can_publish_post($user)): ?>
                <a class="icon-btn" href="?page=write" title="<?= e(setting('nav_write_label')) ?>"><span class="material-symbols-rounded">edit_square</span></a>
            <?php endif; ?>
            <?php if ($user): ?>
                <a class="profile-chip" href="?page=profile">
                    <img src="<?= e($user['avatar'] ?: setting('hero_avatar')) ?>" alt="">
                    <?php if (unread_notification_count((int)$user['id']) > 0): ?><span class="notify-dot"></span><?php endif; ?>
                    <span><?= e($user['display_name']) ?></span>
                </a>
            <?php else: ?>
                <a class="text-btn" href="?page=login"><?= e(setting('nav_login_label')) ?></a>
                <?php if (setting('registration_enabled', '1') === '1'): ?><a class="filled-btn" href="?page=register"><?= e(setting('nav_register_label')) ?></a><?php endif; ?>
            <?php endif; ?>
        </nav>
    </header>
    <?php if ($flash): ?>
        <div class="snackbar" data-snackbar><?= e($flash) ?></div>
    <?php endif; ?>
    <?php if (setting('announcement_enabled') === '1'): ?>
        <div class="announcement-modal" data-announcement data-announcement-version="<?= e(setting('announcement_version', '1')) ?>" hidden>
            <div class="announcement-card">
                <button class="icon-btn announcement-close" type="button" aria-label="关闭公告" data-announcement-close>
                    <span class="material-symbols-rounded">close</span>
                </button>
                <h2><?= e(setting('announcement_title')) ?></h2>
                <p><?= nl2br(e(setting('announcement_body'))) ?></p>
                <div class="announcement-actions">
                    <button class="tonal-btn" type="button" data-announcement-read>已阅读，不再提醒</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <dialog class="user-modal email-code-dialog" data-email-code-dialog>
        <form method="dialog" class="modal-head">
            <h2>邮箱验证码</h2>
            <button class="icon-btn" value="cancel" aria-label="关闭"><span class="material-symbols-rounded">close</span></button>
        </form>
        <div class="md-form">
            <p data-email-code-message>验证码已发送，请输入邮箱验证码。</p>
            <label>验证码<input inputmode="numeric" autocomplete="one-time-code" data-email-code-input></label>
            <button class="filled-btn" type="button" data-email-code-confirm>确认提交</button>
        </div>
    </dialog>
    <main class="page-shell">
    <?php
}

function render_footer(): void
{
    ?>
    </main>
    <footer class="site-footer"><?= e(setting('footer_text')) ?></footer>
    <script src="assets/vendor/vditor/dist/index.min.js"></script>
    <script src="assets/app.js?v=20260430-2"></script>
    </body>
    </html>
    <?php
}

function render_home(?array $user): void
{
    $links = db()->query('SELECT * FROM links ORDER BY sort_order, id')->fetchAll();
    $limit = max(1, (int)setting('home_latest_limit', '6'));
    $featured = db()->query("
        SELECT posts.*, users.display_name, users.avatar,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.review_status = 'approved') AS comment_count
        FROM posts
        JOIN users ON users.id = posts.user_id
        WHERE posts.status = 'published' AND posts.review_status = 'approved' AND posts.featured_home = 1
        ORDER BY posts.created_at DESC
    ")->fetchAll();
    $featuredIds = array_map(fn($item) => (int)$item['id'], $featured);
    $whereExtra = $featuredIds ? 'AND posts.id NOT IN (' . implode(',', $featuredIds) . ')' : '';
    $latest = db()->query("
        SELECT posts.*, users.display_name, users.avatar,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) AS like_count,
        (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id AND comments.review_status = 'approved') AS comment_count
        FROM posts
        JOIN users ON users.id = posts.user_id
        WHERE posts.status = 'published' AND posts.review_status = 'approved' $whereExtra
        ORDER BY posts.created_at DESC
        LIMIT $limit
    ")->fetchAll();
    $posts = array_merge($featured, $latest);
    ?>
    <section class="hero surface-enter">
        <div class="hero-copy">
            <span class="eyebrow"><?= e(setting('hero_eyebrow')) ?></span>
            <h1><?= e(setting('hero_title')) ?></h1>
            <p><?= e(setting('hero_subtitle')) ?></p>
            <div class="hero-actions">
                <?php foreach (hero_buttons() as $button): ?>
                    <?php $buttonIcon = $button['icon'] ?? 'open_in_new'; ?>
                    <a class="<?= ($button['style'] ?? 'tonal') === 'filled' ? 'filled-btn' : 'tonal-btn' ?> large" href="<?= e($button['url'] ?? '#') ?>">
                        <span class="<?= preg_match('/^[a-z0-9_]+$/', $buttonIcon) ? 'material-symbols-rounded' : 'emoji-icon' ?>"><?= e($buttonIcon) ?></span><?= e($button['text'] ?? '') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="hero-avatar-wrap">
            <img class="hero-avatar" src="<?= e(setting('hero_avatar')) ?>" alt="站长头像">
            <div class="avatar-card">
                <span class="material-symbols-rounded">verified</span>
                <strong><?= e(setting('hero_badge_title')) ?></strong>
                <small><?= e(setting('hero_badge_subtitle')) ?></small>
            </div>
        </div>
    </section>

    <section class="section-block surface-enter">
        <div class="section-heading">
            <span class="material-symbols-rounded">travel_explore</span>
            <div>
                <h2><?= e(setting('links_section_title')) ?></h2>
                <p><?= e(setting('links_section_subtitle')) ?></p>
            </div>
        </div>
        <div class="link-grid">
            <?php foreach ($links as $link): ?>
                <a class="site-card" href="<?= e($link['url']) ?>" target="_blank" rel="noopener">
                    <span class="material-symbols-rounded">open_in_new</span>
                    <h3><?= e($link['title']) ?></h3>
                    <p><?= e($link['description']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="section-block surface-enter" id="posts">
        <div class="section-heading">
            <span class="material-symbols-rounded">article</span>
            <div>
                <h2><?= e(setting('posts_section_title')) ?></h2>
                <p><?= e(setting('posts_section_subtitle')) ?></p>
            </div>
        </div>
        <div class="post-grid">
            <?php foreach ($posts as $post): ?>
                <article class="post-card">
                    <?php if ($post['cover']): ?>
                        <img src="<?= e($post['cover']) ?>" alt="">
                    <?php endif; ?>
                    <div class="post-body">
                        <div class="author-line">
                            <img src="<?= e($post['avatar'] ?: setting('hero_avatar')) ?>" alt="">
                            <a href="?page=user&id=<?= (int)$post['user_id'] ?>"><?= e($post['display_name']) ?></a>
                            <time><?= e(date('Y-m-d', strtotime($post['created_at']))) ?></time>
                        </div>
                        <h3><a href="?page=post&slug=<?= e($post['slug']) ?>"><?= markdown_inline($post['title']) ?></a></h3>
                        <div class="post-excerpt"><?= markdown_inline($post['excerpt']) ?></div>
                        <div class="meta-row">
                            <span><span class="material-symbols-rounded">favorite</span><?= (int)$post['like_count'] ?></span>
                            <span><span class="material-symbols-rounded">chat_bubble</span><?= (int)$post['comment_count'] ?></span>
                            <a href="?page=post&slug=<?= e($post['slug']) ?>"><?= e(setting('post_read_more_label')) ?></a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if (!$posts): ?>
                <div class="empty-state"><?= e(setting('empty_posts_text')) ?></div>
            <?php endif; ?>
        </div>
        <div class="section-footer-action">
            <a class="tonal-btn" href="?page=blog"><span class="material-symbols-rounded">view_list</span>进入博客页</a>
        </div>
    </section>
    <?php
}

function render_search(array $user): void
{
    $q = trim($_GET['q'] ?? '');
    $posts = [];
    if ($q !== '') {
        $stmt = db()->prepare("
            SELECT posts.*, users.display_name, users.avatar
            FROM posts
            JOIN users ON users.id = posts.user_id
            WHERE posts.status = 'published' AND posts.review_status = 'approved'
              AND (posts.title LIKE ? OR users.display_name LIKE ? OR users.username LIKE ?)
            ORDER BY posts.created_at DESC
            LIMIT 30
        ");
        $like = '%' . $q . '%';
        $stmt->execute([$like, $like, $like]);
        $posts = $stmt->fetchAll();
    }
    ?>
    <section class="section-block surface-enter">
        <div class="section-heading">
            <span class="material-symbols-rounded">search</span>
            <div><h1>搜索</h1><p>搜索文章标题和作者。</p></div>
        </div>
        <form class="search-form" method="get">
            <input type="hidden" name="page" value="search">
            <input name="q" value="<?= e($q) ?>" placeholder="输入标题或作者">
            <button class="filled-btn" type="submit">搜索</button>
        </form>
        <div class="post-grid compact">
            <?php foreach ($posts as $post): ?><?php render_post_card($post); ?><?php endforeach; ?>
            <?php if ($q !== '' && !$posts): ?><div class="empty-state">没有找到相关博客。</div><?php endif; ?>
        </div>
    </section>
    <?php
}

function render_blog(): void
{
    $tags = db()->query('SELECT * FROM tags ORDER BY name')->fetchAll();
    $activeTag = trim($_GET['tag'] ?? '');
    $countSql = "SELECT COUNT(*) FROM posts JOIN users ON users.id = posts.user_id WHERE posts.status = 'published' AND posts.review_status = 'approved'";
    $countParams = [];
    if ($activeTag !== '') {
        $tagStmt = db()->prepare('SELECT * FROM tags WHERE slug = ?');
        $tagStmt->execute([$activeTag]);
        $tag = $tagStmt->fetch();
        if ($tag) {
            $countSql = "SELECT COUNT(*) FROM posts JOIN post_tags ON post_tags.post_id = posts.id JOIN users ON users.id = posts.user_id WHERE posts.status = 'published' AND posts.review_status = 'approved' AND post_tags.tag_id = ?";
            $countParams[] = $tag['id'];
        }
    }
    $countStmt = db()->prepare($countSql);
    $countStmt->execute($countParams);
    [$blogPage, $perPage, $offset, $pages] = paginate((int)$countStmt->fetchColumn(), (int)($_GET['p'] ?? 1), 15);
    ?>
    <section class="section-block surface-enter">
        <div class="section-heading">
            <span class="material-symbols-rounded">library_books</span>
            <div>
                <h1>博客</h1>
                <p>按照标签浏览全部公开文章。</p>
            </div>
        </div>
        <div class="blog-layout">
            <aside class="blog-sidebar">
                <h2>标签</h2>
                <div class="tag-cloud">
                    <a class="<?= $activeTag === '' ? 'active' : '' ?>" href="?page=blog">全部</a>
                    <?php foreach ($tags as $tag): ?>
                        <a class="<?= $activeTag === $tag['slug'] ? 'active' : '' ?>" href="?page=blog&tag=<?= e($tag['slug']) ?>">#<?= e($tag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </aside>
            <div class="blog-main">
                <?php if ($activeTag !== ''): ?>
                    <?php
                    $stmt = db()->prepare('SELECT * FROM tags WHERE slug = ?');
                    $stmt->execute([$activeTag]);
                    $tag = $stmt->fetch();
                    $posts = [];
                    if ($tag) {
                        $stmt = db()->prepare("
                            SELECT posts.*, users.display_name, users.avatar
                            FROM posts
                            JOIN users ON users.id = posts.user_id
                            JOIN post_tags ON post_tags.post_id = posts.id
                            WHERE posts.status = 'published' AND posts.review_status = 'approved' AND post_tags.tag_id = ?
                            ORDER BY posts.created_at DESC
                            LIMIT 15 OFFSET $offset
                        ");
                        $stmt->execute([$tag['id']]);
                        $posts = $stmt->fetchAll();
                    }
                    ?>
                    <section class="blog-group">
                        <h2><?= $tag ? '#' . e($tag['name']) : '标签不存在' ?></h2>
                        <div class="post-grid compact">
                            <?php foreach ($posts as $post): ?>
                                <?php render_post_card($post); ?>
                            <?php endforeach; ?>
                        </div>
                        <?php render_pagination('?page=blog&tag=' . urlencode($activeTag), $blogPage, $pages); ?>
                    </section>
                <?php else: ?>
                    <?php
                    $allPosts = db()->query("
                        SELECT posts.*, users.display_name, users.avatar
                        FROM posts
                        JOIN users ON users.id = posts.user_id
                        WHERE posts.status = 'published' AND posts.review_status = 'approved'
                        ORDER BY posts.created_at DESC
                        LIMIT 15 OFFSET $offset
                    ")->fetchAll();
                    ?>
                    <section class="blog-group">
                        <h2>全部博客</h2>
                        <div class="post-grid compact">
                            <?php foreach ($allPosts as $post): ?>
                                <?php render_post_card($post); ?>
                            <?php endforeach; ?>
                        </div>
                        <?php render_pagination('?page=blog', $blogPage, $pages); ?>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <?php
}

function render_post_card(array $post): void
{
    ?>
    <article class="post-card">
        <?php if ($post['cover']): ?><img src="<?= e($post['cover']) ?>" alt=""><?php endif; ?>
        <div class="post-body">
            <div class="author-line">
                <a href="?page=user&id=<?= (int)$post['user_id'] ?>"><img src="<?= e($post['avatar'] ?: setting('hero_avatar')) ?>" alt=""></a>
                <a href="?page=user&id=<?= (int)$post['user_id'] ?>"><?= e($post['display_name']) ?></a>
                <time><?= e(date('Y-m-d', strtotime($post['created_at']))) ?></time>
            </div>
            <h3><a href="?page=post&slug=<?= e($post['slug']) ?>"><?= markdown_inline($post['title']) ?></a></h3>
            <div class="post-excerpt"><?= markdown_inline($post['excerpt']) ?></div>
            <div class="tag-row">
                <?php foreach (fetch_post_tags((int)$post['id']) as $tag): ?>
                    <a href="?page=blog&tag=<?= e($tag['slug']) ?>">#<?= e($tag['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </article>
    <?php
}

function render_post(?array $user): void
{
    $slug = $_GET['slug'] ?? '';
    $stmt = db()->prepare('SELECT posts.*, users.display_name, users.avatar FROM posts JOIN users ON users.id = posts.user_id WHERE slug = ?');
    $stmt->execute([$slug]);
    $post = $stmt->fetch();
    if (!$post) {
        http_response_code(404);
        echo '<section class="empty-state">文章不存在。</section>';
        return;
    }
    if ($post['status'] !== 'published' || $post['review_status'] !== 'approved') {
        if (!$user || ((int)$post['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
            http_response_code(404);
            echo '<section class="empty-state">文章不存在。</section>';
            return;
        }
    }
    $stats = post_stats((int)$post['id'], $user['id'] ?? null);
    $commentWhere = $user && $user['role'] === 'admin' ? 'post_id = ?' : "post_id = ? AND comments.review_status = 'approved'";
    $comments = db()->prepare("SELECT comments.*, users.display_name, users.avatar FROM comments JOIN users ON users.id = comments.user_id WHERE $commentWhere ORDER BY comments.created_at ASC");
    $comments->execute([$post['id']]);
    $commentTree = build_comment_tree($comments->fetchAll());
    ?>
    <article class="reader surface-enter">
        <?php if ($post['cover']): ?><img class="reader-cover" src="<?= e($post['cover']) ?>" alt=""><?php endif; ?>
        <div class="reader-head">
            <div class="author-line">
                <img src="<?= e($post['avatar'] ?: setting('hero_avatar')) ?>" alt="">
                <span><?= e($post['display_name']) ?></span>
                <time><?= e(date('Y-m-d H:i', strtotime($post['created_at']))) ?></time>
            </div>
            <h1><?= markdown_inline($post['title']) ?></h1>
            <div class="reader-excerpt"><?= markdown_inline($post['excerpt']) ?></div>
            <div class="tag-row selected-tags">
                <?php foreach (fetch_post_tags((int)$post['id']) as $tag): ?>
                    <a href="?page=blog&tag=<?= e($tag['slug']) ?>">#<?= e($tag['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="content-prose vditor-rendered" data-vditor-preview>
            <textarea hidden><?= e($post['content']) ?></textarea>
        </div>
        <div class="reader-actions">
            <form method="post" action="?action=like" class="inline-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                <input type="hidden" name="back" value="?page=post&slug=<?= e($post['slug']) ?>">
                <button class="<?= $stats['liked'] ? 'filled-btn' : 'tonal-btn' ?>" type="submit">
                    <span class="material-symbols-rounded">favorite</span><?= $stats['likes'] ?> 点赞
                </button>
            </form>
            <?php if ($user): ?>
                <?php $flags = post_user_flags((int)$post['id'], (int)$user['id']); ?>
                <form method="post" action="?action=favorite_post" class="inline-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                    <input type="hidden" name="back" value="?page=post&slug=<?= e($post['slug']) ?>">
                    <button class="<?= $flags['favorite'] ? 'filled-btn' : 'tonal-btn' ?>" type="submit">
                        <span class="material-symbols-rounded">bookmark</span><?= $flags['favorite'] ? '已收藏' : '收藏' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <button class="comment-fab" type="button" data-comments-toggle aria-controls="comments-panel" aria-expanded="false">
        <span class="material-symbols-rounded">forum</span>
    </button>
    <section class="section-block surface-enter comments-panel" id="comments-panel" data-comments-panel hidden>
        <div class="section-heading">
            <span class="material-symbols-rounded">forum</span>
            <div><h2>评论</h2><p><?= $stats['comments'] ?> 条讨论</p></div>
        </div>
        <?php if (can_comment($user)): ?>
                <form method="post" action="?action=comment" class="comment-box vditor-comment-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                <input type="hidden" name="parent_id" value="0">
                <input type="hidden" name="back" value="?page=post&slug=<?= e($post['slug']) ?>">
                <div class="comment-editor" data-comment-editor></div>
                <textarea name="body" hidden data-comment-output></textarea>
                <?= hcaptcha_widget('comment') ?>
                <button class="filled-btn" type="submit"><span class="material-symbols-rounded">send</span>发布评论</button>
            </form>
        <?php elseif ($user): ?>
            <p class="login-hint"><?= is_banned_user($user) ? '账号封禁期间不能评论。' : '当前暂未开放评论。' ?></p>
        <?php else: ?>
            <p class="login-hint">登录后可以评论和点赞。<a href="?page=login">去登录</a></p>
        <?php endif; ?>
        <div class="comment-list">
            <?php render_comments($commentTree, $post, $user); ?>
        </div>
    </section>
    <?php
}

function build_comment_tree(array $comments): array
{
    $items = [];
    foreach ($comments as $comment) {
        $comment['children'] = [];
        $items[(int)$comment['id']] = $comment;
    }
    $tree = [];
    foreach ($items as $id => &$comment) {
        $parent = (int)($comment['parent_id'] ?? 0);
        if ($parent > 0 && isset($items[$parent])) {
            $items[$parent]['children'][] = &$comment;
        } else {
            $tree[] = &$comment;
        }
    }
    return $tree;
}

function render_comments(array $comments, array $post, ?array $user, int $depth = 0): void
{
    foreach ($comments as $comment): ?>
        <div class="comment-item depth-<?= min($depth, 3) ?>">
            <a href="?page=user&id=<?= (int)$comment['user_id'] ?>"><img src="<?= e($comment['avatar'] ?: setting('hero_avatar')) ?>" alt=""></a>
            <div class="comment-content">
                <strong><a href="?page=user&id=<?= (int)$comment['user_id'] ?>"><?= e($comment['display_name']) ?></a></strong>
                <time><?= e(date('Y-m-d H:i', strtotime($comment['created_at']))) ?><?= ($comment['review_status'] ?? 'approved') !== 'approved' ? ' · ' . review_status_label($comment['review_status']) : '' ?></time>
                <div class="comment-markdown vditor-rendered" data-vditor-preview><textarea hidden><?= e($comment['body']) ?></textarea></div>
                <div class="comment-actions">
                    <?php $commentStats = comment_stats((int)$comment['id'], $user['id'] ?? null); ?>
                    <?php if ($user && !is_banned_user($user)): ?>
                        <form method="post" action="?action=comment_like" class="inline-form ajax-like-form" data-ajax-like>
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                            <input type="hidden" name="ajax" value="1">
                            <input type="hidden" name="back" value="?page=post&slug=<?= e($post['slug']) ?>">
                            <button class="<?= $commentStats['liked'] ? 'filled-btn' : 'tonal-btn' ?>" type="submit"><span class="material-symbols-rounded">thumb_up</span><span data-like-count><?= $commentStats['likes'] ?></span></button>
                        </form>
                    <?php else: ?>
                        <span class="pill"><span class="material-symbols-rounded">thumb_up</span><?= $commentStats['likes'] ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($user && $user['role'] === 'admin'): ?>
                    <button class="icon-btn comment-manage-btn" type="button" data-open-user-modal="comment-modal-<?= (int)$comment['id'] ?>" title="管理评论"><span class="material-symbols-rounded">more_vert</span></button>
                <?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?>
                    <dialog class="user-modal" id="comment-modal-<?= (int)$comment['id'] ?>">
                        <form method="dialog" class="modal-head">
                            <h2>管理评论</h2>
                            <button class="icon-btn" value="cancel" aria-label="关闭"><span class="material-symbols-rounded">close</span></button>
                        </form>
                        <form method="post" action="?action=admin_comment_update" class="md-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                            <input type="hidden" name="back" value="?page=post&slug=<?= e($post['slug']) ?>">
                            <label>审核状态<select name="review_status">
                                <?php foreach (['approved' => '已通过', 'pending' => '待审核', 'rejected' => '未通过'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($comment['review_status'] ?? 'approved') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                            <label>评论内容<textarea name="body" rows="6"><?= e($comment['body']) ?></textarea></label>
                            <button class="filled-btn" type="submit">保存评论</button>
                        </form>
                        <form method="post" action="?action=delete_comment" class="danger-zone" onsubmit="return confirm('确认删除这条评论？')">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                            <input type="hidden" name="back" value="?page=post&slug=<?= e($post['slug']) ?>">
                            <button class="text-btn danger" type="submit">删除评论</button>
                        </form>
                    </dialog>
                <?php endif; ?>
                <?php if (can_comment($user)): ?>
                    <details class="reply-box">
                        <summary>回复</summary>
                        <form method="post" action="?action=comment" class="vditor-comment-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                            <input type="hidden" name="parent_id" value="<?= (int)$comment['id'] ?>">
                            <input type="hidden" name="back" value="?page=post&slug=<?= e($post['slug']) ?>">
                            <div class="comment-editor small" data-comment-editor></div>
                            <textarea name="body" hidden data-comment-output></textarea>
                            <?= hcaptcha_widget('comment') ?>
                            <button class="tonal-btn" type="submit">回复</button>
                        </form>
                    </details>
                <?php endif; ?>
                <?php if (!empty($comment['children'])): ?>
                    <div class="comment-children"><?php render_comments($comment['children'], $post, $user, $depth + 1); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach;
}

function render_auth(string $type): void
{
    $isRegister = $type === 'register';
    ?>
    <section class="auth-panel surface-enter">
        <h1><?= $isRegister ? '创建账号' : '欢迎回来' ?></h1>
        <?php if ($isRegister && setting('registration_enabled', '1') !== '1'): ?>
            <div class="empty-state">当前暂未开放注册。</div>
        <?php else: ?>
        <?php if ($isRegister && setting('register_tip') !== ''): ?>
            <div class="inline-tip" data-dismissible-tip="register_tip">
                <span class="material-symbols-rounded">info</span>
                <p><?= e(setting('register_tip')) ?></p>
                <button class="icon-btn" type="button" data-tip-close aria-label="关闭提示"><span class="material-symbols-rounded">close</span></button>
            </div>
        <?php endif; ?>
        <form method="post" action="?action=<?= $isRegister ? 'register' : 'login' ?>" class="md-form" <?= $isRegister ? 'data-email-verify-form data-email-context="register"' : '' ?>>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <?php if ($isRegister): ?><input type="hidden" name="email_code" data-email-code><?php endif; ?>
            <label>用户名<input name="username" required autocomplete="username"></label>
            <?php if ($isRegister): ?>
                <label>昵称<input name="display_name" required></label>
                <label>邮箱
                    <div class="email-verify-row">
                        <input type="email" name="email" data-email-address required>
                        <button class="tonal-btn" type="button" data-email-send-code disabled>发送验证码</button>
                    </div>
                </label>
                <label>头像 URL<input name="avatar" placeholder="https://..."></label>
            <?php endif; ?>
            <label>密码<input type="password" name="password" required minlength="8" autocomplete="<?= $isRegister ? 'new-password' : 'current-password' ?>"></label>
            <?= hcaptcha_widget($isRegister ? 'register' : 'login') ?>
            <button class="filled-btn large" type="submit"><?= $isRegister ? '注册' : '登录' ?></button>
        </form>
        <?php if (!$isRegister && setting('oidc_enabled', '0') === '1'): ?>
            <div class="oidc-login">
                <a class="tonal-btn large" href="?action=oidc_start"><span class="material-symbols-rounded">verified_user</span><?= e(setting('oidc_login_label', '使用 OIDC 登录')) ?></a>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        <div class="auth-links">
            <?php if ($isRegister || setting('registration_enabled', '1') === '1'): ?>
                <a class="text-link" href="?page=<?= $isRegister ? 'login' : 'register' ?>"><?= $isRegister ? '已有账号，去登录' : '没有账号，去注册' ?></a>
            <?php endif; ?>
            <?php if (!$isRegister): ?><a class="text-link" href="?page=forgot_password">忘记密码</a><?php endif; ?>
        </div>
    </section>
    <?php
}

function render_forgot_password(): void
{
    ?>
    <section class="auth-panel surface-enter">
        <h1>找回密码</h1>
        <form method="post" action="?action=forgot_password" class="md-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>已验证邮箱<input type="email" name="email" required></label>
            <button class="filled-btn large" type="submit">发送重置邮件</button>
        </form>
    </section>
    <?php
}

function render_reset_password(): void
{
    $token = trim($_GET['token'] ?? '');
    ?>
    <section class="auth-panel surface-enter">
        <h1>重置密码</h1>
        <form method="post" action="?action=reset_password" class="md-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label>新密码<input type="password" name="password" required minlength="8"></label>
            <button class="filled-btn large" type="submit">重置密码</button>
        </form>
    </section>
    <?php
}

function render_profile(array $user): void
{
    $posts = db()->prepare('SELECT * FROM posts WHERE user_id = ? ORDER BY created_at DESC');
    $posts->execute([$user['id']]);
    $favorites = db()->prepare("SELECT posts.* FROM favorites JOIN posts ON posts.id = favorites.post_id WHERE favorites.user_id = ? ORDER BY favorites.created_at DESC");
    $favorites->execute([$user['id']]);
    $followers = db()->prepare('SELECT users.* FROM follows JOIN users ON users.id = follows.follower_id WHERE follows.following_id = ? ORDER BY follows.created_at DESC LIMIT 10');
    $followers->execute([$user['id']]);
    $followerRows = $followers->fetchAll();
    $following = db()->prepare('SELECT users.* FROM follows JOIN users ON users.id = follows.following_id WHERE follows.follower_id = ? ORDER BY follows.created_at DESC LIMIT 10');
    $following->execute([$user['id']]);
    $followingRows = $following->fetchAll();
    $followerCountStmt = db()->prepare('SELECT COUNT(*) FROM follows WHERE following_id = ?');
    $followerCountStmt->execute([$user['id']]);
    $followingCountStmt = db()->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ?');
    $followingCountStmt->execute([$user['id']]);
    $notifications = db()->prepare('SELECT notifications.*, users.display_name AS actor_name FROM notifications LEFT JOIN users ON users.id = notifications.actor_id WHERE notifications.user_id = ? ORDER BY notifications.created_at DESC LIMIT 20');
    $notifications->execute([$user['id']]);
    $notificationRows = $notifications->fetchAll();
    ?>
    <section class="profile-layout surface-enter">
        <aside class="profile-panel">
            <img src="<?= e($user['avatar'] ?: setting('hero_avatar')) ?>" alt="">
            <h1><?= e($user['display_name']) ?></h1>
            <p>@<?= e($user['username']) ?></p>
            <p><?= e($user['bio']) ?></p>
            <div class="profile-actions">
                <a class="tonal-btn" href="?page=profile_edit"><span class="material-symbols-rounded">manage_accounts</span>修改资料</a>
                <button class="tonal-btn" type="button" data-open-user-modal="messages-modal"><span class="material-symbols-rounded">notifications</span>消息提醒</button>
                <button class="tonal-btn" type="button" data-open-user-modal="favorites-modal"><span class="material-symbols-rounded">bookmark</span>我的收藏</button>
                <?php if ($user['role'] === 'admin'): ?>
                    <a class="tonal-btn" href="?page=admin"><span class="material-symbols-rounded">admin_panel_settings</span>后台管理</a>
                <?php endif; ?>
                <form method="post" action="?action=logout">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button class="text-btn danger" type="submit">退出登录</button>
                </form>
            </div>
        </aside>
        <div class="profile-main">
            <div class="profile-info-card">
                <h2>个人信息</h2>
                <?php if (is_banned_user($user)): ?>
                    <div class="ban-notice">
                        <strong>账号已被封禁</strong>
                        <span>到期时间：<?= e($user['ban_until'] ?: '永久封禁') ?></span>
                        <span>原因：<?= e($user['ban_reason'] ?: '未填写') ?></span>
                    </div>
                <?php endif; ?>
                <dl>
                    <div><dt>用户名</dt><dd><?= e($user['username']) ?></dd></div>
                    <div><dt>昵称</dt><dd><?= e($user['display_name']) ?></dd></div>
                    <div><dt>邮箱</dt><dd><?= e($user['email'] ?: '未填写') ?></dd></div>
                    <div><dt>邮箱验证</dt><dd><?= !empty($user['email_verified']) ? '已验证' : '未验证' ?></dd></div>
                    <div><dt>OIDC 绑定</dt><dd><?= $user['oidc_email'] ? e($user['oidc_email']) : '未绑定' ?></dd></div>
                    <div><dt>用户组</dt><dd><?= e(user_group_label($user['role'])) ?></dd></div>
                    <div><dt>关注者</dt><dd><button class="text-btn" type="button" data-open-user-modal="followers-modal"><?= (int)$followerCountStmt->fetchColumn() ?> 人</button></dd></div>
                    <div><dt>正在关注</dt><dd><button class="text-btn" type="button" data-open-user-modal="following-modal"><?= (int)$followingCountStmt->fetchColumn() ?> 人</button></dd></div>
                    <div><dt>注册时间</dt><dd><?= e(date('Y-m-d', strtotime($user['created_at']))) ?></dd></div>
                </dl>
                <div class="row-actions">
                    <form method="post" action="?action=bind_oidc"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="tonal-btn" type="submit">绑定 OIDC</button></form>
                    <?php if ($user['oidc_email']): ?><form method="post" action="?action=unbind_oidc"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="text-btn danger" type="submit">解绑 OIDC</button></form><?php endif; ?>
                </div>
            </div>
            <dialog class="user-modal" id="followers-modal">
                <form method="dialog" class="modal-head"><h2>关注者</h2><button class="icon-btn" value="cancel" aria-label="关闭"><span class="material-symbols-rounded">close</span></button></form>
                <div class="modal-list">
                    <?php foreach ($followerRows as $item): ?><a href="?page=user&id=<?= (int)$item['id'] ?>"><?= e($item['display_name']) ?><small>@<?= e($item['username']) ?></small></a><?php endforeach; ?>
                    <?php if (!$followerRows): ?><span>暂无关注者</span><?php endif; ?>
                </div>
            </dialog>
            <dialog class="user-modal" id="following-modal">
                <form method="dialog" class="modal-head"><h2>正在关注</h2><button class="icon-btn" value="cancel" aria-label="关闭"><span class="material-symbols-rounded">close</span></button></form>
                <div class="modal-list">
                    <?php foreach ($followingRows as $item): ?><a href="?page=user&id=<?= (int)$item['id'] ?>"><?= e($item['display_name']) ?><small>@<?= e($item['username']) ?></small></a><?php endforeach; ?>
                    <?php if (!$followingRows): ?><span>暂无关注</span><?php endif; ?>
                </div>
            </dialog>
            <dialog class="user-modal" id="messages-modal">
                <form method="dialog" class="modal-head"><h2>消息提醒</h2><button class="icon-btn" value="cancel" aria-label="关闭"><span class="material-symbols-rounded">close</span></button></form>
                <div class="modal-list">
                <?php foreach ($notificationRows as $notice): ?>
                    <a href="<?= e($notice['url'] ?: '?page=profile') ?>"><span><?= e($notice['body']) ?></span><small><?= e($notice['actor_name'] ?: '系统') ?> · <?= e($notice['created_at']) ?></small></a>
                <?php endforeach; ?>
                    <?php if (!$notificationRows): ?><span>暂无消息</span><?php endif; ?>
                </div>
                <form method="post" action="?action=mark_notifications_read"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button class="tonal-btn" type="submit">标记全部已读</button></form>
            </dialog>
            <dialog class="user-modal" id="favorites-modal">
                <form method="dialog" class="modal-head"><h2>我的收藏</h2><button class="icon-btn" value="cancel" aria-label="关闭"><span class="material-symbols-rounded">close</span></button></form>
                <div class="modal-list">
                <?php foreach ($favorites as $post): ?><a href="?page=post&slug=<?= e($post['slug']) ?>"><span><?= markdown_inline($post['title']) ?></span><small><?= e($post['created_at']) ?></small></a><?php endforeach; ?>
                </div>
            </dialog>
            <div class="mini-list">
                <h2>我的文章</h2>
                <?php foreach ($posts as $post): ?>
                    <a href="?page=write&id=<?= (int)$post['id'] ?>">
                        <span><?= e($post['title']) ?></span>
                        <small><?= e($post['status']) ?> · <?= review_status_label($post['review_status'] ?? 'approved') ?><?= ($post['review_status'] ?? '') === 'rejected' && ($post['review_note'] ?? '') !== '' ? ' · ' . e($post['review_note']) : '' ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

function render_profile_edit(array $user): void
{
    ?>
    <section class="auth-panel surface-enter profile-edit-panel">
        <h1>修改资料</h1>
        <?php if (setting('profile_tip') !== ''): ?>
            <div class="inline-tip" data-dismissible-tip="profile_tip">
                <span class="material-symbols-rounded">info</span>
                <p><?= e(setting('profile_tip')) ?></p>
                <button class="icon-btn" type="button" data-tip-close aria-label="关闭提示"><span class="material-symbols-rounded">close</span></button>
            </div>
        <?php endif; ?>
        <form method="post" action="?action=profile" class="md-form" enctype="multipart/form-data" data-email-verify-form data-email-context="profile" data-current-email="<?= e($user['email']) ?>">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="email_code" data-email-code>
            <label>用户名<input class="readonly-field" value="<?= e($user['username']) ?>" readonly></label>
            <label>昵称<input name="display_name" value="<?= e($user['display_name']) ?>" required></label>
            <label>邮箱<input class="readonly-field" type="email" name="email" value="<?= e($user['email']) ?>" readonly></label>
            <label>头像方式<select name="avatar_mode" data-avatar-mode>
                <option value="url">使用 URL</option>
                <option value="upload">上传图片并裁剪</option>
            </select></label>
            <label data-avatar-url-field>头像 URL<input name="avatar" value="<?= e($user['avatar']) ?>"></label>
            <label data-avatar-upload-field hidden>上传头像<input type="file" name="avatar_file" accept="image/*" data-avatar-file></label>
            <div class="avatar-cropper-modal" data-avatar-cropper hidden>
                <div class="avatar-cropper-dialog">
                    <h2>裁剪头像</h2>
                    <canvas width="320" height="320" data-avatar-canvas></canvas>
                    <label>缩放<input type="range" min="1" max="3" step="0.05" value="1" data-avatar-zoom></label>
                    <button class="filled-btn" type="button" data-avatar-crop-done>使用裁剪头像</button>
                </div>
            </div>
            <input type="hidden" name="avatar_cropped" data-avatar-cropped>
            <label>简介<textarea name="bio" rows="4"><?= e($user['bio']) ?></textarea></label>
            <h2>修改密码</h2>
            <label>当前密码<input type="password" name="current_password" autocomplete="current-password"></label>
            <label>新密码<input type="password" name="new_password" minlength="8" autocomplete="new-password"></label>
            <button class="filled-btn large" type="submit">保存资料</button>
        </form>
    </section>
    <?php
}

function render_public_user(array $viewer): void
{
    $id = (int)($_GET['id'] ?? 0);
    $stmt = db()->prepare('SELECT id, username, display_name, avatar, bio, role, created_at FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $profile = $stmt->fetch();
    if (!$profile) {
        echo '<section class="empty-state">用户不存在。</section>';
        return;
    }
    $isFollowing = false;
    if ((int)$viewer['id'] !== (int)$profile['id']) {
        $follow = db()->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = ?');
        $follow->execute([$viewer['id'], $profile['id']]);
        $isFollowing = (int)$follow->fetchColumn() > 0;
    }
    $posts = db()->prepare("SELECT * FROM posts WHERE user_id = ? AND status = 'published' AND review_status = 'approved' ORDER BY created_at DESC LIMIT 15");
    $posts->execute([$profile['id']]);
    ?>
    <section class="profile-layout surface-enter">
        <aside class="profile-panel">
            <img src="<?= e($profile['avatar'] ?: setting('hero_avatar')) ?>" alt="">
            <h1><?= e($profile['display_name']) ?></h1>
            <p>@<?= e($profile['username']) ?> · <?= e(user_group_label($profile['role'])) ?></p>
            <p><?= e($profile['bio']) ?></p>
            <?php if ((int)$viewer['id'] !== (int)$profile['id']): ?>
                <form method="post" action="?action=follow_user">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="target_id" value="<?= (int)$profile['id'] ?>">
                    <input type="hidden" name="back" value="?page=user&id=<?= (int)$profile['id'] ?>">
                    <button class="<?= $isFollowing ? 'filled-btn' : 'tonal-btn' ?>" type="submit"><?= $isFollowing ? '已关注' : '关注' ?></button>
                </form>
            <?php endif; ?>
        </aside>
        <div class="profile-main">
            <div class="mini-list">
                <h2>公开博客</h2>
                <?php foreach ($posts as $post): ?><a href="?page=post&slug=<?= e($post['slug']) ?>"><span><?= markdown_inline($post['title']) ?></span><small><?= e($post['created_at']) ?></small></a><?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

function render_write(array $user): void
{
    if (!can_publish_post($user)) {
        echo '<section class="empty-state">当前账号没有发布博客权限。</section>';
        return;
    }
    $post = null;
    $tags = db()->query('SELECT * FROM tags ORDER BY name')->fetchAll();
    $selectedTags = [];
    if (!empty($_GET['id'])) {
        $post = fetch_post_by_id((int)$_GET['id']);
        if (!$post || ((int)$post['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
            http_response_code(403);
            exit('Forbidden');
        }
        $selectedTags = array_map(fn($item) => (int)$item['id'], fetch_post_tags((int)$post['id']));
    }
    ?>
    <section class="editor-page surface-enter">
        <div class="section-heading">
            <span class="material-symbols-rounded">edit_note</span>
            <div><h1><?= $post ? '编辑博客' : '发布博客' ?></h1><p>Markdown 编辑器支持标题、摘要、正文、引用、列表、代码、图片和链接。</p></div>
        </div>
        <form method="post" action="?action=save_post" class="editor-form" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="post_id" value="<?= (int)($post['id'] ?? 0) ?>">
            <div class="editor-meta">
                <label>标题 Markdown<input name="title" value="<?= e($post['title'] ?? '') ?>" required placeholder="例如：我的 **第一篇** 博客"></label>
                <label>摘要 Markdown<textarea name="excerpt" rows="3" placeholder="支持 **加粗**、[链接](https://example.com)"><?= e($post['excerpt'] ?? '') ?></textarea></label>
                <label>封面图 URL<input name="cover" value="<?= e($post['cover'] ?? '') ?>" placeholder="https://... 或 /uploads/covers/..."></label>
                <label>手动上传封面图<input type="file" name="cover_file" accept="image/*"></label>
                <label>状态<select name="status">
                    <option value="published">公开发布</option>
                    <option value="hidden" <?= ($post['status'] ?? '') === 'hidden' ? 'selected' : '' ?>>隐藏</option>
                    <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>草稿</option>
                </select></label>
                <label class="checkbox-field"><input type="checkbox" name="featured_home" value="1" <?= !empty($post['featured_home']) ? 'checked' : '' ?>>固定显示在首页最新博客</label>
                <div class="tag-picker">
                    <span>标签 <?php if (in_array($user['role'], ['admin', 'blogger'], true)): ?><button class="text-btn" type="button" data-open-user-modal="tag-create-modal">新增标签</button><?php endif; ?></span>
                    <div>
                        <?php foreach ($tags as $tag): ?>
                            <label><input type="checkbox" name="tag_ids[]" value="<?= (int)$tag['id'] ?>" <?= in_array((int)$tag['id'], $selectedTags, true) ? 'checked' : '' ?>>#<?= e($tag['name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="vditor-field">
                <div class="field-title">主体内容 Markdown</div>
                <?php if (setting('write_tip') !== ''): ?><div class="inline-tip"><span class="material-symbols-rounded">info</span><p><?= e(setting('write_tip')) ?></p></div><?php endif; ?>
                <div id="vditor-editor" data-vditor-editor></div>
                <textarea name="content" data-vditor-output hidden><?= e($post['content'] ?? '') ?></textarea>
                <?= hcaptcha_widget('post') ?>
            </div>
            <div class="form-actions">
                <button class="filled-btn large" type="submit"><span class="material-symbols-rounded">publish</span>保存文章</button>
            </div>
        </form>
        <?php if (in_array($user['role'], ['admin', 'blogger'], true)): ?>
            <dialog class="user-modal" id="tag-create-modal">
                <form method="dialog" class="modal-head"><h2>新增标签</h2><button class="icon-btn" value="cancel"><span class="material-symbols-rounded">close</span></button></form>
                <form method="post" action="?action=admin_tag" class="md-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>名称<input name="name" required></label>
                    <label>Slug<input name="slug"></label>
                    <button class="filled-btn" type="submit">创建标签</button>
                </form>
            </dialog>
        <?php endif; ?>
    </section>
    <?php
}

function render_admin(): void
{
    $adminPage = max(1, (int)($_GET['p'] ?? 1));
    $posts = db()->query('SELECT posts.*, users.display_name FROM posts JOIN users ON users.id = posts.user_id ORDER BY posts.created_at DESC')->fetchAll();
    $draftPosts = db()->query("SELECT posts.*, users.display_name FROM posts JOIN users ON users.id = posts.user_id WHERE posts.status = 'draft' ORDER BY posts.updated_at DESC")->fetchAll();
    $pendingPosts = db()->query("SELECT posts.*, users.display_name FROM posts JOIN users ON users.id = posts.user_id WHERE posts.review_status = 'pending' ORDER BY posts.updated_at DESC")->fetchAll();
    $comments = db()->query('SELECT comments.*, posts.title AS post_title, posts.slug AS post_slug, users.display_name FROM comments JOIN posts ON posts.id = comments.post_id JOIN users ON users.id = comments.user_id ORDER BY comments.created_at DESC')->fetchAll();
    $pendingComments = array_values(array_filter($comments, fn($comment) => ($comment['review_status'] ?? 'approved') === 'pending'));
    $users = db()->query('SELECT id, username, display_name, email, avatar, bio, role, banned, previous_role, ban_until, ban_reason, created_at FROM users ORDER BY created_at DESC')->fetchAll();
    $userFilter = $_GET['group'] ?? '';
    if (in_array($userFilter, ['admin', 'blogger', 'user', 'banned'], true)) {
        $users = array_values(array_filter($users, fn($item) => $item['role'] === $userFilter));
    }
    [$postPage, $postPer, $postOffset, $postPages] = paginate(count($posts), $adminPage, 15);
    [$commentPage, $commentPer, $commentOffset, $commentPages] = paginate(count($comments), $adminPage, 15);
    [$userPage, $userPer, $userOffset, $userPages] = paginate(count($users), $adminPage, 15);
    $postsPageRows = array_slice($posts, $postOffset, $postPer);
    $commentsPageRows = array_slice($comments, $commentOffset, $commentPer);
    $usersPageRows = array_slice($users, $userOffset, $userPer);
    $links = db()->query('SELECT * FROM links ORDER BY sort_order, id')->fetchAll();
    $tags = db()->query('SELECT * FROM tags ORDER BY name')->fetchAll();
    ?>
    <section class="admin-shell surface-enter">
        <div class="admin-hero">
            <div>
                <span class="eyebrow">Admin Console</span>
                <h1>后台管理</h1>
                <p>分项维护首页、网站链接、文章和用户信息。</p>
            </div>
            <button class="icon-btn admin-drawer-toggle" type="button" data-admin-drawer-toggle aria-label="打开后台导航"><span class="material-symbols-rounded">menu</span></button>
            <a class="filled-btn" href="?page=write"><span class="material-symbols-rounded">edit_square</span>发布博客</a>
        </div>

        <div class="admin-stats" data-admin-drawer>
            <details class="admin-nav-section" open>
                <summary><span class="material-symbols-rounded">menu_open</span><strong>后台导航</strong><span class="material-symbols-rounded nav-arrow">expand_more</span></summary>
            <button class="stat-card active" type="button" data-admin-tab="overview">
                <span class="material-symbols-rounded">dashboard</span>
                <strong>概览</strong>
                <small><?= count($posts) ?> 篇文章 · <?= count($users) ?> 个用户</small>
            </button>
            <button class="stat-card" type="button" data-admin-tab="home">
                <span class="material-symbols-rounded">home</span>
                <strong>首页文案</strong>
                <small>品牌、Hero、区块标题</small>
            </button>
            <button class="stat-card" type="button" data-admin-tab="seo">
                <span class="material-symbols-rounded">travel_explore</span>
                <strong>站点 SEO</strong>
                <small>浏览器标题、favicon、meta</small>
            </button>
            <button class="stat-card" type="button" data-admin-tab="notice">
                <span class="material-symbols-rounded">campaign</span>
                <strong>公告提示</strong>
                <small>首页公告和表单提示</small>
            </button>
            <button class="stat-card" type="button" data-admin-tab="links">
                <span class="material-symbols-rounded">link</span>
                <strong>网站链接</strong>
                <small><?= count($links) ?> 个首页链接</small>
            </button>
            <button class="stat-card" type="button" data-admin-tab="taxonomy">
                <span class="material-symbols-rounded">sell</span>
                <strong>标签</strong>
                <small><?= count($tags) ?> 个标签</small>
            </button>
            <button class="stat-card" type="button" data-admin-tab="posts">
                <span class="material-symbols-rounded">article</span>
                <strong>文章管理</strong>
                <small>编辑、删除、查看状态</small>
            </button>
            <button class="stat-card" type="button" data-admin-tab="comments">
                <span class="material-symbols-rounded">forum</span>
                <strong>评论</strong>
                <small><?= count($comments) ?> 条评论 · <?= count($pendingComments) ?> 待审核</small>
            </button>
            <button class="stat-card" type="button" data-admin-tab="access">
                <span class="material-symbols-rounded">security</span>
                <strong>访问控制</strong>
                <small>注册、评论、发布、审核、OIDC</small>
            </button>
            <button class="stat-card" type="button" data-admin-tab="users">
                <span class="material-symbols-rounded">group</span>
                <strong>用户</strong>
                <small>账号和角色列表</small>
            </button>
            </details>
        </div>

        <div class="admin-panel active" data-admin-panel="overview">
            <div class="admin-card">
                <h2>站点状态</h2>
                <div class="overview-grid">
                    <div><strong><?= count($posts) ?></strong><span>文章总数</span></div>
                    <div><strong><?= count($links) ?></strong><span>网站链接</span></div>
                    <div><strong><?= count($users) ?></strong><span>注册用户</span></div>
                    <div><strong><?= count($tags) ?></strong><span>标签数量</span></div>
                </div>
            </div>
            <div class="admin-card version-card">
                <h2>版本信息</h2>
                <div class="version-badge">
                    <span class="material-symbols-rounded">deployed_code</span>
                    <div>
                        <strong>0.1 beta</strong>
                        <small>当前运行版本</small>
                    </div>
                </div>
                <p>该版本包含 MD3 首页、多人登录、Vditor 编辑器、评论点赞、分项后台和本地化编辑器资源。</p>
            </div>
            <div class="admin-card">
                <h2>快捷操作</h2>
                <div class="quick-actions">
                    <a class="tonal-btn" href="?page=write"><span class="material-symbols-rounded">edit_square</span>写新文章</a>
                    <button class="tonal-btn" type="button" data-admin-tab="home"><span class="material-symbols-rounded">tune</span>修改首页</button>
                    <button class="tonal-btn" type="button" data-admin-tab="seo"><span class="material-symbols-rounded">travel_explore</span>SEO 设置</button>
                    <button class="tonal-btn" type="button" data-admin-tab="notice"><span class="material-symbols-rounded">campaign</span>公告提示</button>
                    <button class="tonal-btn" type="button" data-admin-tab="links"><span class="material-symbols-rounded">add_link</span>管理链接</button>
                    <button class="tonal-btn" type="button" data-admin-tab="taxonomy"><span class="material-symbols-rounded">sell</span>管理标签</button>
                    <button class="tonal-btn" type="button" data-admin-tab="access"><span class="material-symbols-rounded">security</span>访问控制</button>
                    <button class="tonal-btn" type="button" data-admin-tab="comments"><span class="material-symbols-rounded">forum</span>评论管理</button>
                </div>
            </div>
        </div>

        <div class="admin-panel" data-admin-panel="home">
            <div class="admin-card wide">
                <h2>首页文案与首屏展示</h2>
                <form method="post" action="?action=admin_settings" class="md-form admin-form-grid">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>站点品牌名<input name="site_brand" value="<?= e(setting('site_brand')) ?>"></label>
                    <label>导航发布按钮提示<input name="nav_write_label" value="<?= e(setting('nav_write_label')) ?>"></label>
                    <label>导航登录文字<input name="nav_login_label" value="<?= e(setting('nav_login_label')) ?>"></label>
                    <label>导航注册文字<input name="nav_register_label" value="<?= e(setting('nav_register_label')) ?>"></label>
                    <label>Hero 顶部小字<input name="hero_eyebrow" value="<?= e(setting('hero_eyebrow')) ?>"></label>
                    <label>Hero 标题<input name="hero_title" value="<?= e(setting('hero_title')) ?>"></label>
                    <label class="span-2">Hero 简介<textarea name="hero_subtitle" rows="3"><?= e(setting('hero_subtitle')) ?></textarea></label>
                    <label>首页头像 URL<input name="hero_avatar" value="<?= e(setting('hero_avatar')) ?>"></label>
                    <label>Hero 主按钮文字<input name="hero_primary_button" value="<?= e(setting('hero_primary_button')) ?>"></label>
                    <label>Hero 次按钮文字<input name="hero_secondary_button" value="<?= e(setting('hero_secondary_button')) ?>"></label>
                    <label>头像卡片标题<input name="hero_badge_title" value="<?= e(setting('hero_badge_title')) ?>"></label>
                    <label>头像卡片说明<input name="hero_badge_subtitle" value="<?= e(setting('hero_badge_subtitle')) ?>"></label>
                    <label>网站链接区标题<input name="links_section_title" value="<?= e(setting('links_section_title')) ?>"></label>
                    <label class="span-2">网站链接区说明<textarea name="links_section_subtitle" rows="2"><?= e(setting('links_section_subtitle')) ?></textarea></label>
                    <label>博客区标题<input name="posts_section_title" value="<?= e(setting('posts_section_title')) ?>"></label>
                    <label class="span-2">博客区说明<textarea name="posts_section_subtitle" rows="2"><?= e(setting('posts_section_subtitle')) ?></textarea></label>
                    <label>文章卡片入口文字<input name="post_read_more_label" value="<?= e(setting('post_read_more_label')) ?>"></label>
                    <label>无文章提示文字<input name="empty_posts_text" value="<?= e(setting('empty_posts_text')) ?>"></label>
                    <label>首页最新博客数量<input type="number" min="1" max="24" name="home_latest_limit" value="<?= e(setting('home_latest_limit', '6')) ?>"></label>
                    <label class="span-2">全局底部版权文字<input name="footer_text" value="<?= e(setting('footer_text')) ?>"></label>
                    <div class="span-2"><button class="filled-btn" type="submit">保存首页</button></div>
                </form>
            </div>
            <div class="admin-card wide">
                <h2>首页首屏按钮</h2>
                <p class="muted-text">最多 6 个按钮，每行最多 3 个。可设置图标、文本、链接和排序。</p>
                <div class="hero-button-admin-list">
                    <?php foreach (hero_buttons() as $index => $button): ?>
                        <form method="post" action="?action=admin_hero_button" class="hero-button-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="button_index" value="<?= (int)$index ?>">
                            <input name="icon" value="<?= e($button['icon'] ?? '') ?>" placeholder="icon">
                            <input name="text" value="<?= e($button['text'] ?? '') ?>" placeholder="文本" required>
                            <input name="url" value="<?= e($button['url'] ?? '') ?>" placeholder="链接" required>
                            <select name="style"><option value="tonal">Tonal</option><option value="filled" <?= ($button['style'] ?? '') === 'filled' ? 'selected' : '' ?>>Filled</option></select>
                            <input type="number" name="sort" value="<?= (int)($button['sort'] ?? 10) ?>">
                            <button class="tonal-btn" type="submit">保存</button>
                        </form>
                        <form method="post" action="?action=delete_hero_button">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="button_index" value="<?= (int)$index ?>">
                            <button class="text-btn danger" type="submit">删除</button>
                        </form>
                    <?php endforeach; ?>
                </div>
                <?php if (count(hero_buttons()) < 6): ?>
                    <form method="post" action="?action=admin_hero_button" class="hero-button-form add">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input name="icon" placeholder="open_in_new">
                        <input name="text" placeholder="按钮文本" required>
                        <input name="url" placeholder="?page=blog" required>
                        <select name="style"><option value="tonal">Tonal</option><option value="filled">Filled</option></select>
                        <input type="number" name="sort" value="10">
                        <button class="filled-btn" type="submit">添加按钮</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="admin-panel" data-admin-panel="seo">
            <div class="admin-card wide">
                <h2>站点 SEO 与浏览器显示</h2>
                <form method="post" action="?action=admin_settings" class="md-form admin-form-grid">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <?php foreach (array_keys(default_settings()) as $key): ?>
                        <?php if (!in_array($key, ['browser_site_title','browser_site_subtitle','site_favicon','seo_description','seo_keywords','seo_author'], true)): ?>
                            <input type="hidden" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label>浏览器站名<input name="browser_site_title" value="<?= e(setting('browser_site_title')) ?>"></label>
                    <label>副标题<input name="browser_site_subtitle" value="<?= e(setting('browser_site_subtitle')) ?>"></label>
                    <label class="span-2">Favicon URL<input name="site_favicon" value="<?= e(setting('site_favicon')) ?>" placeholder="https://.../favicon.ico 或 uploads/..."></label>
                    <label>Logo 模式<select name="logo_mode"><option value="icon">Material 图标</option><option value="url" <?= setting('logo_mode') === 'url' ? 'selected' : '' ?>>图片 URL</option></select></label>
                    <label>Logo 图标名<input name="logo_icon" value="<?= e(setting('logo_icon', 'auto_stories')) ?>" placeholder="auto_stories"></label>
                    <label class="span-2">Logo 图片 URL<input name="logo_url" value="<?= e(setting('logo_url')) ?>" placeholder="https://.../logo.png"></label>
                    <label>MD3 配色方案<select name="color_scheme">
                        <?php foreach (color_schemes() as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= setting('color_scheme', 'violet') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select></label>
                    <label class="span-2">SEO 描述<textarea name="seo_description" rows="3"><?= e(setting('seo_description')) ?></textarea></label>
                    <label class="span-2">SEO 关键词<input name="seo_keywords" value="<?= e(setting('seo_keywords')) ?>"></label>
                    <label>作者<input name="seo_author" value="<?= e(setting('seo_author')) ?>"></label>
                    <div class="span-2"><button class="filled-btn" type="submit">保存 SEO</button></div>
                </form>
            </div>
        </div>

        <div class="admin-panel" data-admin-panel="notice">
            <div class="admin-card wide">
                <h2>公告与提示语</h2>
                <form method="post" action="?action=admin_settings" class="md-form admin-form-grid">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <?php foreach (array_keys(default_settings()) as $key): ?>
                        <?php if (!in_array($key, ['announcement_enabled','announcement_version','announcement_title','announcement_body','register_tip','profile_tip','write_tip'], true)): ?>
                            <input type="hidden" name="<?= e($key) ?>" value="<?= e(setting($key)) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label class="checkbox-field"><input type="checkbox" name="announcement_enabled" value="1" <?= setting('announcement_enabled') === '1' ? 'checked' : '' ?>>启用首页公告</label>
                    <label>公告版本<input name="announcement_version" value="<?= e(setting('announcement_version', '1')) ?>"></label>
                    <label>公告标题<input name="announcement_title" value="<?= e(setting('announcement_title')) ?>"></label>
                    <label class="span-2">公告内容<textarea name="announcement_body" rows="5"><?= e(setting('announcement_body')) ?></textarea></label>
                    <label class="span-2">注册页面提示语<textarea name="register_tip" rows="3"><?= e(setting('register_tip')) ?></textarea></label>
                    <label class="span-2">修改资料提示语<textarea name="profile_tip" rows="3"><?= e(setting('profile_tip')) ?></textarea></label>
                    <label class="span-2">发布/编辑博客提示语<textarea name="write_tip" rows="3"><?= e(setting('write_tip')) ?></textarea></label>
                    <div class="span-2"><button class="filled-btn" type="submit">保存公告提示</button></div>
                </form>
            </div>
        </div>

        <div class="admin-panel" data-admin-panel="access">
            <div class="admin-card wide">
                <h2>访问控制与审核</h2>
                <form method="post" action="?action=admin_settings" class="md-form admin-form-grid">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>开放注册<select name="registration_enabled"><option value="1" <?= setting('registration_enabled', '1') === '1' ? 'selected' : '' ?>>开启</option><option value="0" <?= setting('registration_enabled', '1') === '0' ? 'selected' : '' ?>>关闭</option></select></label>
                    <label>开放博客发布<select name="posts_enabled"><option value="1" <?= setting('posts_enabled', '1') === '1' ? 'selected' : '' ?>>开启</option><option value="0" <?= setting('posts_enabled', '1') === '0' ? 'selected' : '' ?>>关闭</option></select></label>
                    <label>开放全局评论<select name="comments_enabled"><option value="1" <?= setting('comments_enabled', '1') === '1' ? 'selected' : '' ?>>开启</option><option value="0" <?= setting('comments_enabled', '1') === '0' ? 'selected' : '' ?>>关闭</option></select></label>
                    <label>文章发布审核<select name="review_posts_enabled"><option value="0" <?= setting('review_posts_enabled', '0') === '0' ? 'selected' : '' ?>>关闭</option><option value="1" <?= setting('review_posts_enabled', '0') === '1' ? 'selected' : '' ?>>开启</option></select></label>
                    <label>评论审核<select name="review_comments_enabled"><option value="0" <?= setting('review_comments_enabled', '0') === '0' ? 'selected' : '' ?>>关闭</option><option value="1" <?= setting('review_comments_enabled', '0') === '1' ? 'selected' : '' ?>>开启</option></select></label>
                    <div class="span-2"><button class="filled-btn" type="submit">保存访问控制</button></div>
                </form>
            </div>
            <div class="admin-card wide">
                <h2>OIDC 登录</h2>
                <form method="post" action="?action=admin_settings" class="md-form admin-form-grid">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>OIDC 登录<select name="oidc_enabled"><option value="0" <?= setting('oidc_enabled', '0') === '0' ? 'selected' : '' ?>>关闭</option><option value="1" <?= setting('oidc_enabled', '0') === '1' ? 'selected' : '' ?>>开启</option></select></label>
                    <label>登录按钮名称<input name="oidc_login_label" value="<?= e(setting('oidc_login_label')) ?>"></label>
                    <label class="span-2">Issuer<input name="oidc_issuer" value="<?= e(setting('oidc_issuer')) ?>" placeholder="https://accounts.example.com"></label>
                    <label>Client ID<input name="oidc_client_id" value="<?= e(setting('oidc_client_id')) ?>"></label>
                    <label>Client Secret<input name="oidc_client_secret" value="<?= e(setting('oidc_client_secret')) ?>"></label>
                    <label class="span-2">Redirect URI<input name="oidc_redirect_uri" value="<?= e(setting('oidc_redirect_uri')) ?>" placeholder="https://你的域名/index.php?action=oidc_callback"></label>
                    <div class="span-2"><button class="filled-btn" type="submit">保存 OIDC</button></div>
                </form>
            </div>
            <div class="admin-card wide">
                <h2>hCaptcha 验证码</h2>
                <form method="post" action="?action=admin_settings" class="md-form admin-form-grid">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>启用 hCaptcha<select name="hcaptcha_enabled"><option value="0" <?= setting('hcaptcha_enabled') === '0' ? 'selected' : '' ?>>关闭</option><option value="1" <?= setting('hcaptcha_enabled') === '1' ? 'selected' : '' ?>>开启</option></select></label>
                    <label>Site Key<input name="hcaptcha_site_key" value="<?= e(setting('hcaptcha_site_key')) ?>"></label>
                    <label class="span-2">Secret<input name="hcaptcha_secret" value="<?= e(setting('hcaptcha_secret')) ?>"></label>
                    <?php foreach (['login' => '登录', 'register' => '注册', 'post' => '发布博客', 'comment' => '评论'] as $key => $label): ?>
                        <label>用于<?= e($label) ?><select name="hcaptcha_<?= e($key) ?>"><option value="0" <?= setting('hcaptcha_' . $key) === '0' ? 'selected' : '' ?>>关闭</option><option value="1" <?= setting('hcaptcha_' . $key) === '1' ? 'selected' : '' ?>>开启</option></select></label>
                    <?php endforeach; ?>
                    <div class="span-2"><button class="filled-btn" type="submit">保存 hCaptcha</button></div>
                </form>
            </div>
            <div class="admin-card wide">
                <h2>Redis 与 SMTP</h2>
                <form method="post" action="?action=admin_settings" class="md-form admin-form-grid">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>Redis 缓存<select name="redis_enabled"><option value="0" <?= setting('redis_enabled') === '0' ? 'selected' : '' ?>>关闭</option><option value="1" <?= setting('redis_enabled') === '1' ? 'selected' : '' ?>>开启</option></select></label>
                    <label>Redis Host<input name="redis_host" value="<?= e(setting('redis_host')) ?>"></label>
                    <label>Redis Port<input name="redis_port" value="<?= e(setting('redis_port')) ?>"></label>
                    <label>Redis Password<input name="redis_password" value="<?= e(setting('redis_password')) ?>"></label>
                    <label>SMTP Host<input name="smtp_host" value="<?= e(setting('smtp_host')) ?>"></label>
                    <label>SMTP Port<input name="smtp_port" value="<?= e(setting('smtp_port')) ?>"></label>
                    <label>SMTP 用户名<input name="smtp_username" value="<?= e(setting('smtp_username')) ?>"></label>
                    <label>SMTP 密码<input name="smtp_password" value="<?= e(setting('smtp_password')) ?>"></label>
                    <label>发件邮箱<input name="smtp_from_email" value="<?= e(setting('smtp_from_email')) ?>"></label>
                    <label>发件名称<input name="smtp_from_name" value="<?= e(setting('smtp_from_name')) ?>"></label>
                    <div class="span-2"><button class="filled-btn" type="submit">保存服务配置</button></div>
                </form>
            </div>
        </div>

        <div class="admin-panel" data-admin-panel="taxonomy">
            <div class="admin-card">
                <h2>添加标签</h2>
                <form method="post" action="?action=admin_tag" class="md-form compact">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>名称<input name="name" required></label>
                    <label>Slug<input name="slug" placeholder="md3"></label>
                    <button class="tonal-btn" type="submit">添加标签</button>
                </form>
            </div>
            <div class="admin-card wide">
                <h2>标签管理</h2>
                <div class="taxonomy-list">
                    <?php foreach ($tags as $tag): ?>
                        <form method="post" action="?action=admin_tag" class="tag-edit-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="tag_id" value="<?= (int)$tag['id'] ?>">
                            <input name="name" value="<?= e($tag['name']) ?>" required>
                            <input name="slug" value="<?= e($tag['slug']) ?>" required>
                            <button class="tonal-btn" type="submit">保存</button>
                        </form>
                        <form method="post" action="?action=delete_tag" onsubmit="return confirm('确认删除这个标签？')">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="tag_id" value="<?= (int)$tag['id'] ?>">
                            <button class="text-btn danger" type="submit">删除</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="admin-panel" data-admin-panel="links">
            <div class="admin-card">
                <h2>添加网站链接</h2>
                <form method="post" action="?action=admin_link" class="md-form compact">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label>标题<input name="title" required></label>
                    <label>URL<input name="url" required></label>
                    <label>描述<input name="description"></label>
                    <label>排序<input type="number" name="sort_order" value="10"></label>
                    <button class="tonal-btn" type="submit">添加链接</button>
                </form>
            </div>
            <div class="admin-card wide">
                <h2>首页网站链接</h2>
                <div class="link-admin-list">
                    <?php foreach ($links as $link): ?>
                        <form method="post" action="?action=admin_link" class="link-edit-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="link_id" value="<?= (int)$link['id'] ?>">
                            <input name="title" value="<?= e($link['title']) ?>" aria-label="标题" required>
                            <input name="url" value="<?= e($link['url']) ?>" aria-label="URL" required>
                            <input name="description" value="<?= e($link['description']) ?>" aria-label="描述">
                            <input type="number" name="sort_order" value="<?= (int)$link['sort_order'] ?>" aria-label="排序">
                            <button class="tonal-btn" type="submit">保存</button>
                        </form>
                        <form method="post" action="?action=delete_link" class="delete-link-form" onsubmit="return confirm('确认删除这个网站链接？')">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="link_id" value="<?= (int)$link['id'] ?>">
                            <button class="text-btn danger" type="submit">删除</button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="admin-panel" data-admin-panel="posts">
            <div class="admin-card wide">
                <h2>待审核文章</h2>
                <div class="table-list">
                    <?php foreach ($pendingPosts as $post): ?>
                        <div>
                            <span><?= markdown_inline($post['title']) ?><small><?= e($post['display_name']) ?> · <?= review_status_label($post['review_status']) ?></small></span>
                            <div class="row-actions">
                                <a class="tonal-btn" href="?page=write&id=<?= (int)$post['id'] ?>">查看</a>
                                <form method="post" action="?action=review_post">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                    <input type="hidden" name="decision" value="approved">
                                    <button class="filled-btn" type="submit">通过</button>
                                </form>
                                <form method="post" action="?action=review_post" class="inline-review-form">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                    <input type="hidden" name="decision" value="rejected">
                                    <input name="review_note" placeholder="失败原因">
                                    <button class="text-btn danger" type="submit">不通过</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$pendingPosts): ?><div><span>暂无待审核文章</span></div><?php endif; ?>
                </div>
            </div>
            <div class="admin-card wide">
                <h2>文章管理</h2>
                <div class="table-list">
                    <?php foreach ($postsPageRows as $post): ?>
                        <div>
                            <span><?= markdown_inline($post['title']) ?><small><?= e($post['display_name']) ?> · <?= e($post['status']) ?> · <?= review_status_label($post['review_status'] ?? 'approved') ?></small></span>
                            <div class="row-actions">
                                <a class="icon-btn" href="?page=write&id=<?= (int)$post['id'] ?>" title="编辑"><span class="material-symbols-rounded">edit</span></a>
                                <form method="post" action="?action=delete_post" onsubmit="return confirm('确认删除这篇文章？')">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="post_id" value="<?= (int)$post['id'] ?>">
                                    <button class="icon-btn danger" type="submit" title="删除"><span class="material-symbols-rounded">delete</span></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php render_pagination('?page=admin#posts', $postPage, $postPages); ?>
            </div>
            <div class="admin-card wide">
                <h2>草稿箱</h2>
                <div class="table-list">
                    <?php foreach ($draftPosts as $post): ?>
                        <div>
                            <span><?= markdown_inline($post['title']) ?><small><?= e($post['display_name']) ?> · <?= e($post['updated_at']) ?></small></span>
                            <a class="tonal-btn" href="?page=write&id=<?= (int)$post['id'] ?>">继续编辑</a>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$draftPosts): ?><div><span>暂无草稿</span></div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="admin-panel" data-admin-panel="comments">
            <div class="admin-card wide">
                <h2>评论管理</h2>
                <form method="post" action="?action=delete_comment" class="table-list batch-comment-form" onsubmit="return confirm('确认删除选中的评论？')">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="back" value="?page=admin">
                    <?php foreach ($commentsPageRows as $comment): ?>
                        <div>
                            <label class="checkbox-field"><input type="checkbox" name="comment_ids[]" value="<?= (int)$comment['id'] ?>"></label>
                            <span><?= e(text_excerpt(markdown_plain_text($comment['body']), 70)) ?><small><?= e($comment['display_name']) ?> · <?= markdown_inline($comment['post_title']) ?> · <?= review_status_label($comment['review_status'] ?? 'approved') ?></small></span>
                            <div class="row-actions">
                                <a class="tonal-btn" href="?page=post&slug=<?= e($comment['post_slug']) ?>">查看</a>
                                <button class="tonal-btn" type="button" data-open-user-modal="admin-comment-modal-<?= (int)$comment['id'] ?>">修改</button>
                                <?php if (($comment['review_status'] ?? 'approved') === 'pending'): ?>
                                    <button class="filled-btn" type="submit" formaction="?action=review_comment" name="decision" value="approved" onclick="this.form.comment_id.value='<?= (int)$comment['id'] ?>'">通过</button>
                                    <button class="text-btn danger" type="submit" formaction="?action=review_comment" name="decision" value="rejected" onclick="this.form.comment_id.value='<?= (int)$comment['id'] ?>'">不通过</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$commentsPageRows): ?><div><span>暂无评论</span></div><?php endif; ?>
                    <input type="hidden" name="comment_id" value="">
                    <button class="text-btn danger" type="submit">批量删除</button>
                </form>
                <?php render_pagination('?page=admin#comments', $commentPage, $commentPages); ?>
                <?php foreach ($commentsPageRows as $comment): ?>
                    <dialog class="user-modal" id="admin-comment-modal-<?= (int)$comment['id'] ?>">
                        <form method="dialog" class="modal-head">
                            <h2>修改评论</h2>
                            <button class="icon-btn" value="cancel" aria-label="关闭"><span class="material-symbols-rounded">close</span></button>
                        </form>
                        <form method="post" action="?action=admin_comment_update" class="md-form">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                            <input type="hidden" name="back" value="?page=admin">
                            <label>审核状态<select name="review_status">
                                <?php foreach (['approved' => '已通过', 'pending' => '待审核', 'rejected' => '未通过'] as $key => $label): ?>
                                    <option value="<?= e($key) ?>" <?= ($comment['review_status'] ?? 'approved') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select></label>
                            <label>评论内容<textarea name="body" rows="7"><?= e($comment['body']) ?></textarea></label>
                            <button class="filled-btn" type="submit">保存评论</button>
                        </form>
                        <form method="post" action="?action=delete_comment" class="danger-zone" onsubmit="return confirm('确认删除这条评论？')">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="comment_id" value="<?= (int)$comment['id'] ?>">
                            <input type="hidden" name="back" value="?page=admin">
                            <button class="text-btn danger" type="submit">删除评论</button>
                        </form>
                    </dialog>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-panel" data-admin-panel="users">
            <div class="admin-card wide">
                <h2>用户</h2>
                <div class="filter-row">
                    <?php foreach (['' => '全部', 'admin' => '管理员', 'blogger' => '博主', 'user' => '普通用户', 'banned' => '封禁用户'] as $key => $label): ?>
                        <a class="tonal-btn <?= $userFilter === $key ? 'active' : '' ?>" href="?page=admin<?= $key !== '' ? '&group=' . e($key) : '' ?>#users"><?= e($label) ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="table-list">
                    <?php foreach ($usersPageRows as $item): ?>
                        <div>
                            <span><?= e($item['display_name']) ?><small>@<?= e($item['username']) ?> · <?= e(user_group_label($item['role'])) ?><?= is_banned_user($item) ? ' · ' . e($item['ban_until'] ?: '永久封禁') : '' ?></small></span>
                            <button class="tonal-btn" type="button" data-open-user-modal="user-modal-<?= (int)$item['id'] ?>">编辑</button>
                        </div>
                        <dialog class="user-modal" id="user-modal-<?= (int)$item['id'] ?>">
                            <form method="dialog" class="modal-head">
                                <h2>编辑用户</h2>
                                <button class="icon-btn" value="cancel" aria-label="关闭"><span class="material-symbols-rounded">close</span></button>
                            </form>
                            <form method="post" action="?action=admin_user_update" class="md-form">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$item['id'] ?>">
                                <label>用户名<input value="<?= e($item['username']) ?>" disabled></label>
                                <label>昵称<input name="display_name" value="<?= e($item['display_name']) ?>" required></label>
                                <label>邮箱<input type="email" name="email" value="<?= e($item['email']) ?>"></label>
                                <label>头像 URL<input name="avatar" value="<?= e($item['avatar']) ?>"></label>
                                <label>简介<textarea name="bio" rows="3"><?= e($item['bio']) ?></textarea></label>
                                <input type="hidden" name="previous_role" value="<?= e($item['previous_role'] ?: 'user') ?>">
                                <label>用户组<select name="role">
                                    <?php foreach (['user' => '普通用户', 'blogger' => '博主', 'admin' => '管理员', 'banned' => '封禁用户'] as $key => $label): ?>
                                        <option value="<?= e($key) ?>" <?= $item['role'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select></label>
                                <label>封禁方式<select name="ban_mode">
                                    <option value="none">不封禁/解除封禁</option>
                                    <option value="days">封禁指定天数</option>
                                    <option value="permanent" <?= $item['role'] === 'banned' && ($item['ban_until'] ?? '') === '' ? 'selected' : '' ?>>永久封禁</option>
                                </select></label>
                                <label>封禁天数<input type="number" min="1" name="ban_days" placeholder="例如 7"></label>
                                <label class="span-2">封禁理由<textarea name="ban_reason" rows="3" placeholder="请输入封禁理由"><?= e($item['ban_reason'] ?? '') ?></textarea></label>
                                <label>重置密码<input type="password" name="new_password" minlength="8" placeholder="留空则不修改"></label>
                                <button class="filled-btn" type="submit">保存用户</button>
                            </form>
                            <form method="post" action="?action=delete_user" class="danger-zone" onsubmit="return confirm('确认删除这个用户？该用户文章和评论也会被删除。')">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= (int)$item['id'] ?>">
                                <button class="text-btn danger" type="submit">删除账号</button>
                            </form>
                        </dialog>
                    <?php endforeach; ?>
                </div>
                <?php render_pagination('?page=admin' . ($userFilter !== '' ? '&group=' . urlencode($userFilter) : '') . '#users', $userPage, $userPages); ?>
            </div>
        </div>
    </section>
    <?php
}

render_header($user, $flash);

if ($page === 'home') {
    render_home($user);
} elseif ($page === 'blog') {
    render_blog();
} elseif ($page === 'search') {
    render_search(require_login());
} elseif ($page === 'post') {
    render_post($user);
} elseif ($page === 'user') {
    render_public_user(require_login());
} elseif ($page === 'login') {
    render_auth('login');
} elseif ($page === 'register') {
    render_auth('register');
} elseif ($page === 'forgot_password') {
    render_forgot_password();
} elseif ($page === 'reset_password') {
    render_reset_password();
} elseif ($page === 'profile') {
    render_profile(require_login());
} elseif ($page === 'profile_edit') {
    render_profile_edit(require_login());
} elseif ($page === 'write') {
    render_write(require_login());
} elseif ($page === 'admin') {
    require_admin();
    render_admin();
} else {
    http_response_code(404);
    echo '<section class="empty-state">页面不存在。</section>';
}

render_footer();
