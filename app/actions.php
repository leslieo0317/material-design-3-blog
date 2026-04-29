<?php
declare(strict_types=1);

function handle_action(string $action): void
{
    if ($action === 'verify_email') {
        $stmt = db()->prepare("UPDATE users SET email_verified = 1, email_verify_token = '' WHERE email_verify_token = ? AND email_verify_token != ''");
        $stmt->execute([$_GET['token'] ?? '']);
        flash('邮箱验证已完成。');
        redirect('?page=profile');
    }
    if ($action === 'send_email_code') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('index.php');
        }
        verify_csrf();
        $email = trim($_POST['email'] ?? '');
        header('Content-Type: application/json');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'message' => '邮箱格式不正确。'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $code = (string)random_int(100000, 999999);
        $_SESSION['email_verify_code'] = $code;
        $_SESSION['email_verify_target'] = $email;
        $_SESSION['email_verify_expires'] = time() + 600;
        $sent = send_site_mail($email, '邮箱验证码', '你的验证码是：' . $code . '，10 分钟内有效。');
        echo json_encode(['ok' => $sent, 'message' => $sent ? '验证码已发送，请查看邮箱。' : '验证码发送失败，请检查 SMTP 或服务器 mail 配置。'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'oidc_start') {
        handle_oidc_start();
    }
    if ($action === 'oidc_callback') {
        handle_oidc_callback();
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('index.php');
    }
    verify_csrf();

    switch ($action) {
        case 'register':
            if (setting('registration_enabled', '1') !== '1') {
                flash('当前暂未开放注册。');
                redirect('?page=register');
            }
            if (!verify_hcaptcha('register')) {
                flash('验证码校验失败。');
                redirect('?page=register');
            }
            $username = trim($_POST['username'] ?? '');
            $displayName = trim($_POST['display_name'] ?? $username);
            $password = (string)($_POST['password'] ?? '');
            if ($username === '' || $password === '' || strlen($password) < 8) {
                flash('用户名和至少 8 位密码不能为空。');
                redirect('?page=register');
            }
            try {
                $email = trim($_POST['email'] ?? '');
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    flash('请填写正确的邮箱地址。');
                    redirect('?page=register');
                }
                $emailCode = trim($_POST['email_code'] ?? '');
                if ($emailCode === '' || $emailCode !== ($_SESSION['email_verify_code'] ?? '') || $email !== ($_SESSION['email_verify_target'] ?? '') || time() > (int)($_SESSION['email_verify_expires'] ?? 0)) {
                    flash('邮箱验证码不正确或已过期。');
                    redirect('?page=register');
                }
                $verifyToken = $email !== '' ? bin2hex(random_bytes(20)) : '';
                $stmt = db()->prepare('INSERT INTO users (username, display_name, email, avatar, password_hash, email_verified, email_verify_token) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    $username,
                    $displayName,
                    $email,
                    trim($_POST['avatar'] ?? ''),
                    password_hash($password, PASSWORD_DEFAULT),
                    $email !== '' ? 1 : 0,
                    $verifyToken,
                ]);
                unset($_SESSION['email_verify_code'], $_SESSION['email_verify_target'], $_SESSION['email_verify_expires']);
                $_SESSION['user_id'] = (int)db()->lastInsertId();
                flash('注册成功，欢迎回来。');
                redirect('?page=profile');
            } catch (PDOException $e) {
                flash('用户名已存在，请换一个。');
                redirect('?page=register');
            }

        case 'login':
            if (!verify_hcaptcha('login')) {
                flash('验证码校验失败。');
                redirect('?page=login');
            }
            $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
            $stmt->execute([trim($_POST['username'] ?? '')]);
            $user = $stmt->fetch();
            if (!$user || !password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
                flash('用户名或密码不正确。');
                redirect('?page=login');
            }
            $_SESSION['user_id'] = (int)$user['id'];
            flash('登录成功。');
            redirect('?page=profile');

        case 'logout':
            session_destroy();
            redirect('index.php');

        case 'forgot_password':
            $email = trim($_POST['email'] ?? '');
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND email_verified = 1');
            $stmt->execute([$email]);
            $userId = (int)$stmt->fetchColumn();
            if ($userId > 0) {
                $token = bin2hex(random_bytes(24));
                db()->prepare('UPDATE users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?')->execute([$token, date('Y-m-d H:i:s', strtotime('+30 minutes')), $userId]);
                send_site_mail($email, '重置你的密码', '请访问：' . site_url('?page=reset_password&token=' . $token));
            }
            flash('如果邮箱存在且已验证，重置邮件已发送。');
            redirect('?page=login');

        case 'reset_password':
            $token = trim($_POST['token'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            if (strlen($password) < 8) {
                flash('新密码至少需要 8 位。');
                redirect('?page=reset_password&token=' . urlencode($token));
            }
            $stmt = db()->prepare("UPDATE users SET password_hash = ?, password_reset_token = '', password_reset_expires = '' WHERE password_reset_token = ? AND password_reset_expires > datetime('now')");
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $token]);
            flash('密码已重置，请重新登录。');
            redirect('?page=login');

        case 'profile':
            $user = require_login();
            $newEmail = trim($_POST['email'] ?? '');
            if ($newEmail !== ($user['email'] ?? '')) {
                $emailCode = trim($_POST['email_code'] ?? '');
                if ($newEmail === '' || $emailCode === '' || $emailCode !== ($_SESSION['email_verify_code'] ?? '') || $newEmail !== ($_SESSION['email_verify_target'] ?? '') || time() > (int)($_SESSION['email_verify_expires'] ?? 0)) {
                    flash('邮箱验证码不正确或已过期。');
                    redirect('?page=profile_edit');
                }
            }
            $avatar = trim($_POST['avatar'] ?? '');
            $uploadedAvatar = upload_file('avatar_file', 'avatars');
            $croppedAvatar = save_data_image(trim($_POST['avatar_cropped'] ?? ''), 'avatars');
            if ($croppedAvatar !== '') {
                $avatar = $croppedAvatar;
            } elseif ($uploadedAvatar !== '') {
                $avatar = $uploadedAvatar;
            }
            $newPassword = (string)($_POST['new_password'] ?? '');
            if ($newPassword !== '') {
                $currentPassword = (string)($_POST['current_password'] ?? '');
                if (!password_verify($currentPassword, $user['password_hash'])) {
                    flash('当前密码不正确。');
                    redirect('?page=profile_edit');
                }
                if (strlen($newPassword) < 8) {
                    flash('新密码至少需要 8 位。');
                    redirect('?page=profile_edit');
                }
                $passwordStmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $passwordStmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);
            }
            $emailVerified = $newEmail === '' ? 0 : 1;
            $verifyToken = '';
            $stmt = db()->prepare('UPDATE users SET display_name = ?, email = ?, avatar = ?, bio = ?, email_verified = ?, email_verify_token = ? WHERE id = ?');
            $stmt->execute([
                trim($_POST['display_name'] ?? ''),
                $newEmail,
                $avatar,
                trim($_POST['bio'] ?? ''),
                $emailVerified,
                $verifyToken,
                $user['id'],
            ]);
            unset($_SESSION['email_verify_code'], $_SESSION['email_verify_target'], $_SESSION['email_verify_expires']);
            flash('个人资料已保存。');
            redirect('?page=profile');

        case 'save_post':
            $user = require_login();
            if (!can_publish_post($user)) {
                flash(is_banned_user($user) ? '账号封禁期间不能发布博客。' : '当前账号没有发布博客权限。');
                redirect('?page=profile');
            }
            if (!verify_hcaptcha('post')) {
                flash('验证码校验失败。');
                redirect('?page=write');
            }
            $title = trim($_POST['title'] ?? '');
            $content = trim((string)($_POST['content'] ?? ''));
            if ($title === '' || markdown_plain_text($content) === '') {
                flash('标题和正文不能为空。');
                redirect('?page=write');
            }
            $excerpt = trim($_POST['excerpt'] ?? '');
            if ($excerpt === '') {
                $excerpt = text_excerpt(markdown_plain_text($content), 120);
            }
            $cover = trim($_POST['cover'] ?? '');
            $uploadedCover = upload_file('cover_file', 'covers');
            if ($uploadedCover !== '') {
                $cover = $uploadedCover;
            }
            $featuredHome = !empty($_POST['featured_home']) ? 1 : 0;
            $tagIds = $_POST['tag_ids'] ?? [];
            if (!is_array($tagIds)) {
                $tagIds = [];
            }
            $postId = (int)($_POST['post_id'] ?? 0);
            if ($postId > 0) {
                $post = fetch_post_by_id($postId);
                if (!$post || ((int)$post['user_id'] !== (int)$user['id'] && $user['role'] !== 'admin')) {
                    http_response_code(403);
                    exit('Forbidden');
                }
                $status = in_array($_POST['status'] ?? 'published', ['published', 'hidden', 'draft'], true) ? $_POST['status'] : 'published';
                $reviewStatus = $post['review_status'] ?? 'approved';
                $reviewNote = $post['review_note'] ?? '';
                if ($user['role'] === 'admin') {
                    $reviewStatus = 'approved';
                    $reviewNote = '';
                } elseif ($status !== 'draft' && setting('review_posts_enabled', '0') === '1') {
                    $reviewStatus = 'pending';
                    $reviewNote = '';
                }
                $stmt = db()->prepare('UPDATE posts SET category_id = NULL, title = ?, excerpt = ?, content = ?, cover = ?, featured_home = ?, status = ?, review_status = ?, review_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$title, $excerpt, $content, $cover, $featuredHome, $status, $reviewStatus, $reviewNote, $postId]);
                sync_post_tags($postId, $tagIds);
                notify_mentions($content . ' ' . $title, (int)$user['id'], '?page=post&slug=' . urlencode($post['slug']));
                flash($reviewStatus === 'pending' ? '文章已提交审核。' : '文章已更新。');
                redirect('?page=post&slug=' . urlencode($post['slug']));
            }
            $slug = slugify($title);
            $status = in_array($_POST['status'] ?? 'published', ['published', 'hidden', 'draft'], true) ? $_POST['status'] : 'published';
            $reviewStatus = ($user['role'] !== 'admin' && $status !== 'draft' && setting('review_posts_enabled', '0') === '1') ? 'pending' : 'approved';
            $stmt = db()->prepare('INSERT INTO posts (user_id, category_id, title, slug, excerpt, content, cover, featured_home, status, review_status) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], $title, $slug, $excerpt, $content, $cover, $featuredHome, $status, $reviewStatus]);
            sync_post_tags((int)db()->lastInsertId(), $tagIds);
            notify_mentions($content . ' ' . $title, (int)$user['id'], '?page=post&slug=' . urlencode($slug));
            flash($reviewStatus === 'pending' ? '文章已提交审核。' : '文章已保存。');
            redirect('?page=post&slug=' . urlencode($slug));

        case 'comment':
            $user = require_login();
            if (!can_comment($user)) {
                flash(is_banned_user($user) ? '账号封禁期间不能评论。' : '当前暂未开放评论。');
                redirect($_POST['back'] ?? 'index.php');
            }
            if (!verify_hcaptcha('comment')) {
                flash('验证码校验失败。');
                redirect($_POST['back'] ?? 'index.php');
            }
            $postId = (int)($_POST['post_id'] ?? 0);
            $body = trim($_POST['body'] ?? '');
            $parentId = (int)($_POST['parent_id'] ?? 0);
            if ($postId > 0 && $body !== '') {
                $reviewStatus = setting('review_comments_enabled', '0') === '1' && $user['role'] !== 'admin' ? 'pending' : 'approved';
                $stmt = db()->prepare('INSERT INTO comments (post_id, user_id, parent_id, body, review_status) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$postId, $user['id'], $parentId ?: null, $body, $reviewStatus]);
                $commentUrl = $_POST['back'] ?? 'index.php';
                $post = fetch_post_by_id($postId);
                if ($post && (int)$post['user_id'] !== (int)$user['id']) {
                    notify_user((int)$post['user_id'], (int)$user['id'], 'post_reply', '你的博客收到了新评论。', $commentUrl);
                }
                if ($parentId > 0) {
                    $parent = db()->prepare('SELECT user_id FROM comments WHERE id = ?');
                    $parent->execute([$parentId]);
                    $parentUserId = (int)$parent->fetchColumn();
                    if ($parentUserId > 0) {
                        notify_user($parentUserId, (int)$user['id'], 'comment_reply', '你的评论收到了回复。', $commentUrl);
                    }
                }
                notify_mentions($body, (int)$user['id'], $commentUrl);
                flash($reviewStatus === 'pending' ? '评论已提交审核。' : '评论已发布。');
            }
            redirect($_POST['back'] ?? 'index.php');

        case 'comment_like':
            $user = require_login();
            if (is_banned_user($user)) {
                flash('账号封禁期间不能点赞评论。');
                redirect($_POST['back'] ?? 'index.php');
            }
            $commentId = (int)($_POST['comment_id'] ?? 0);
            $visible = db()->prepare("SELECT COUNT(*) FROM comments WHERE id = ? AND review_status = 'approved'");
            $visible->execute([$commentId]);
            if ((int)$visible->fetchColumn() === 0) {
                redirect($_POST['back'] ?? 'index.php');
            }
            $stmt = db()->prepare('SELECT COUNT(*) FROM comment_likes WHERE comment_id = ? AND user_id = ?');
            $stmt->execute([$commentId, $user['id']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $delete = db()->prepare('DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?');
                $delete->execute([$commentId, $user['id']]);
            } else {
                $insert = db()->prepare('INSERT OR IGNORE INTO comment_likes (comment_id, user_id) VALUES (?, ?)');
                $insert->execute([$commentId, $user['id']]);
            }
            $count = comment_stats($commentId, (int)$user['id']);
            if (($_POST['ajax'] ?? '') === '1') {
                header('Content-Type: application/json');
                echo json_encode(['likes' => $count['likes'], 'liked' => $count['liked']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            redirect($_POST['back'] ?? 'index.php');

        case 'favorite_post':
            $user = require_login();
            $postId = (int)($_POST['post_id'] ?? 0);
            $stmt = db()->prepare('SELECT COUNT(*) FROM favorites WHERE post_id = ? AND user_id = ?');
            $stmt->execute([$postId, $user['id']]);
            if ((int)$stmt->fetchColumn() > 0) {
                db()->prepare('DELETE FROM favorites WHERE post_id = ? AND user_id = ?')->execute([$postId, $user['id']]);
                flash('已取消收藏。');
            } else {
                db()->prepare('INSERT OR IGNORE INTO favorites (post_id, user_id) VALUES (?, ?)')->execute([$postId, $user['id']]);
                flash('已收藏博客。');
            }
            redirect($_POST['back'] ?? 'index.php');

        case 'follow_user':
            $user = require_login();
            $targetId = (int)($_POST['target_id'] ?? 0);
            if ($targetId > 0 && $targetId !== (int)$user['id']) {
                $stmt = db()->prepare('SELECT COUNT(*) FROM follows WHERE follower_id = ? AND following_id = ?');
                $stmt->execute([$user['id'], $targetId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    db()->prepare('DELETE FROM follows WHERE follower_id = ? AND following_id = ?')->execute([$user['id'], $targetId]);
                    flash('已取消关注。');
                } else {
                    db()->prepare('INSERT OR IGNORE INTO follows (follower_id, following_id) VALUES (?, ?)')->execute([$user['id'], $targetId]);
                    notify_user($targetId, (int)$user['id'], 'follow', '有人关注了你。', '?page=user&id=' . (int)$user['id']);
                    flash('已关注用户。');
                }
            }
            redirect($_POST['back'] ?? 'index.php');

        case 'bind_oidc':
            $user = require_login();
            if (($user['email'] ?? '') === '' || empty($user['email_verified'])) {
                flash('请先填写并验证邮箱后再绑定 OIDC。');
                redirect('?page=profile');
            }
            db()->prepare('UPDATE users SET oidc_email = ? WHERE id = ?')->execute([$user['email'], $user['id']]);
            flash('OIDC 邮箱已绑定。');
            redirect('?page=profile');

        case 'unbind_oidc':
            $user = require_login();
            db()->prepare("UPDATE users SET oidc_email = '' WHERE id = ?")->execute([$user['id']]);
            flash('OIDC 绑定已解除。');
            redirect('?page=profile');

        case 'mark_notifications_read':
            $user = require_login();
            db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$user['id']]);
            flash('消息已标记已读。');
            redirect('?page=profile');

        case 'like':
            $user = require_login();
            $postId = (int)($_POST['post_id'] ?? 0);
            $stmt = db()->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ? AND user_id = ?');
            $stmt->execute([$postId, $user['id']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $delete = db()->prepare('DELETE FROM likes WHERE post_id = ? AND user_id = ?');
                $delete->execute([$postId, $user['id']]);
            } else {
                $insert = db()->prepare('INSERT OR IGNORE INTO likes (post_id, user_id) VALUES (?, ?)');
                $insert->execute([$postId, $user['id']]);
            }
            redirect($_POST['back'] ?? 'index.php');

        case 'admin_settings':
            require_admin();
            foreach (array_keys(default_settings()) as $key) {
                if (array_key_exists($key, $_POST)) {
                    if ($key === 'announcement_enabled') {
                        set_setting($key, $_POST[$key] === '1' ? '1' : '0');
                    } else {
                        set_setting($key, trim($_POST[$key] ?? ''));
                    }
                } elseif ($key === 'announcement_enabled' && array_key_exists('announcement_title', $_POST)) {
                    set_setting($key, '0');
                }
            }
            foreach ($_POST as $key => $value) {
                if (str_starts_with($key, 'color_')) {
                    set_setting($key, trim($_POST[$key] ?? ''));
                }
            }
            flash('保存成功！');
            redirect('?page=admin');

        case 'admin_hero_button':
            require_admin();
            $buttons = hero_buttons();
            $index = (int)($_POST['button_index'] ?? -1);
            $button = [
                'icon' => trim($_POST['icon'] ?? 'open_in_new'),
                'text' => trim($_POST['text'] ?? ''),
                'url' => trim($_POST['url'] ?? '#'),
                'style' => ($_POST['style'] ?? 'tonal') === 'filled' ? 'filled' : 'tonal',
                'sort' => (int)($_POST['sort'] ?? 10),
            ];
            if ($button['text'] !== '') {
                if ($index >= 0 && isset($buttons[$index])) {
                    $buttons[$index] = $button;
                } elseif (count($buttons) < 6) {
                    $buttons[] = $button;
                }
                set_setting('hero_buttons_json', json_encode($buttons, JSON_UNESCAPED_UNICODE));
            }
            flash('首页按钮已保存。');
            redirect('?page=admin');

        case 'delete_hero_button':
            require_admin();
            $buttons = hero_buttons();
            $index = (int)($_POST['button_index'] ?? -1);
            if (isset($buttons[$index])) {
                array_splice($buttons, $index, 1);
                set_setting('hero_buttons_json', json_encode($buttons, JSON_UNESCAPED_UNICODE));
            }
            flash('首页按钮已删除。');
            redirect('?page=admin');

        case 'admin_user_update':
            require_admin();
            $userId = (int)($_POST['user_id'] ?? 0);
            $current = current_user();
            $role = in_array($_POST['role'] ?? 'user', ['admin', 'blogger', 'user', 'banned'], true) ? $_POST['role'] : 'user';
            $banMode = $_POST['ban_mode'] ?? 'none';
            $banReason = trim($_POST['ban_reason'] ?? '');
            $banUntil = '';
            $previousRole = in_array($_POST['previous_role'] ?? 'user', ['admin', 'blogger', 'user'], true) ? $_POST['previous_role'] : 'user';
            if ($role === 'banned' || $banMode !== 'none') {
                if ($banReason === '') {
                    flash('封禁用户时必须填写封禁理由。');
                    redirect('?page=admin');
                }
                $previousRole = in_array($_POST['role'] ?? 'user', ['admin', 'blogger', 'user'], true) ? $_POST['role'] : $previousRole;
                $role = 'banned';
                if ($banMode === 'days') {
                    $days = max(1, (int)($_POST['ban_days'] ?? 1));
                    $banUntil = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
                }
            }
            if ($current && (int)$current['id'] === $userId) {
                $role = 'admin';
                $banUntil = '';
                $banReason = '';
            }
            $stmt = db()->prepare('UPDATE users SET display_name = ?, email = ?, avatar = ?, bio = ?, role = ?, banned = ?, previous_role = ?, ban_until = ?, ban_reason = ? WHERE id = ?');
            $stmt->execute([
                trim($_POST['display_name'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['avatar'] ?? ''),
                trim($_POST['bio'] ?? ''),
                $role,
                $role === 'banned' ? 1 : 0,
                $role === 'banned' ? $previousRole : 'user',
                $banUntil,
                $banReason,
                $userId,
            ]);
            $newPassword = (string)($_POST['new_password'] ?? '');
            if ($newPassword !== '' && strlen($newPassword) >= 8) {
                $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
            }
            flash('用户资料已更新。');
            redirect('?page=admin');

        case 'review_post':
            require_admin();
            $postId = (int)($_POST['post_id'] ?? 0);
            $decision = ($_POST['decision'] ?? 'approved') === 'rejected' ? 'rejected' : 'approved';
            $stmt = db()->prepare('UPDATE posts SET review_status = ?, review_note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$decision, trim($_POST['review_note'] ?? ''), $postId]);
            flash($decision === 'approved' ? '文章审核已通过。' : '文章已标记为未通过。');
            redirect('?page=admin');

        case 'review_comment':
            require_admin();
            $commentId = (int)($_POST['comment_id'] ?? 0);
            $decision = ($_POST['decision'] ?? 'approved') === 'rejected' ? 'rejected' : 'approved';
            $stmt = db()->prepare('UPDATE comments SET review_status = ?, review_note = ? WHERE id = ?');
            $stmt->execute([$decision, trim($_POST['review_note'] ?? ''), $commentId]);
            flash($decision === 'approved' ? '评论审核已通过。' : '评论已标记为未通过。');
            redirect($_POST['back'] ?? '?page=admin');

        case 'admin_comment_update':
            require_admin();
            $stmt = db()->prepare('UPDATE comments SET body = ?, review_status = ? WHERE id = ?');
            $status = in_array($_POST['review_status'] ?? 'approved', ['approved', 'pending', 'rejected'], true) ? $_POST['review_status'] : 'approved';
            $stmt->execute([trim($_POST['body'] ?? ''), $status, (int)($_POST['comment_id'] ?? 0)]);
            flash('评论已更新。');
            redirect($_POST['back'] ?? '?page=admin');

        case 'delete_comment':
            require_admin();
            $ids = $_POST['comment_ids'] ?? [(int)($_POST['comment_id'] ?? 0)];
            if (!is_array($ids)) {
                $ids = [$ids];
            }
            $stmt = db()->prepare('DELETE FROM comments WHERE id = ?');
            foreach (array_unique(array_map('intval', $ids)) as $id) {
                if ($id > 0) {
                    $stmt->execute([$id]);
                }
            }
            flash('评论已删除。');
            redirect($_POST['back'] ?? '?page=admin');

        case 'delete_user':
            require_admin();
            $userId = (int)($_POST['user_id'] ?? 0);
            $current = current_user();
            if ($current && (int)$current['id'] !== $userId) {
                $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$userId]);
                flash('用户已删除。');
            }
            redirect('?page=admin');

        case 'admin_link':
            require_admin();
            $linkId = (int)($_POST['link_id'] ?? 0);
            $payload = [
                trim($_POST['title'] ?? ''),
                trim($_POST['url'] ?? ''),
                trim($_POST['description'] ?? ''),
                (int)($_POST['sort_order'] ?? 0),
            ];
            if ($linkId > 0) {
                $stmt = db()->prepare('UPDATE links SET title = ?, url = ?, description = ?, sort_order = ? WHERE id = ?');
                $stmt->execute([...$payload, $linkId]);
                flash('站点链接已更新。');
            } else {
                $stmt = db()->prepare('INSERT INTO links (title, url, description, sort_order) VALUES (?, ?, ?, ?)');
                $stmt->execute($payload);
                flash('站点链接已添加。');
            }
            redirect('?page=admin');

        case 'delete_link':
            require_admin();
            $stmt = db()->prepare('DELETE FROM links WHERE id = ?');
            $stmt->execute([(int)($_POST['link_id'] ?? 0)]);
            flash('站点链接已删除。');
            redirect('?page=admin');

        case 'admin_tag':
            $tagUser = require_login();
            if (!in_array($tagUser['role'], ['admin', 'blogger'], true)) {
                http_response_code(403);
                exit('Forbidden');
            }
            $tagId = (int)($_POST['tag_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                flash('标签名称不能为空。');
                redirect('?page=admin');
            }
            $slug = trim($_POST['slug'] ?? '') ?: slugify($name);
            if ($tagId > 0) {
                $stmt = db()->prepare('UPDATE tags SET name = ?, slug = ? WHERE id = ?');
                $stmt->execute([$name, $slug, $tagId]);
                flash('标签已更新。');
            } else {
                $stmt = db()->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
                $stmt->execute([$name, $slug]);
                flash('标签已添加。');
            }
            redirect('?page=admin');

        case 'delete_tag':
            require_admin();
            $stmt = db()->prepare('DELETE FROM tags WHERE id = ?');
            $stmt->execute([(int)($_POST['tag_id'] ?? 0)]);
            flash('标签已删除。');
            redirect('?page=admin');

        case 'delete_post':
            require_admin();
            $stmt = db()->prepare('DELETE FROM posts WHERE id = ?');
            $stmt->execute([(int)($_POST['post_id'] ?? 0)]);
            flash('文章已删除。');
            redirect('?page=admin');
    }

    redirect('index.php');
}

function fetch_post_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    return $post ?: null;
}

function oidc_config(): array
{
    $issuer = rtrim(setting('oidc_issuer'), '/');
    if ($issuer === '') {
        return [];
    }
    $json = @file_get_contents($issuer . '/.well-known/openid-configuration');
    $config = $json ? json_decode($json, true) : [];
    return is_array($config) ? $config : [];
}

function handle_oidc_start(): void
{
    if (setting('oidc_enabled', '0') !== '1') {
        flash('OIDC 登录未启用。');
        redirect('?page=login');
    }
    $config = oidc_config();
    if (empty($config['authorization_endpoint']) || setting('oidc_client_id') === '' || setting('oidc_redirect_uri') === '') {
        flash('OIDC 配置不完整。');
        redirect('?page=login');
    }
    $state = bin2hex(random_bytes(16));
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['oidc_state'] = $state;
    $_SESSION['oidc_nonce'] = $nonce;
    $query = http_build_query([
        'client_id' => setting('oidc_client_id'),
        'redirect_uri' => setting('oidc_redirect_uri'),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'nonce' => $nonce,
    ]);
    redirect($config['authorization_endpoint'] . '?' . $query);
}

function handle_oidc_callback(): void
{
    if (setting('oidc_enabled', '0') !== '1') {
        redirect('?page=login');
    }
    if (($_GET['state'] ?? '') !== ($_SESSION['oidc_state'] ?? '') || empty($_GET['code'])) {
        flash('OIDC 登录校验失败。');
        redirect('?page=login');
    }
    $config = oidc_config();
    if (empty($config['token_endpoint'])) {
        flash('OIDC Token 端点不可用。');
        redirect('?page=login');
    }
    $payload = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $_GET['code'],
        'redirect_uri' => setting('oidc_redirect_uri'),
        'client_id' => setting('oidc_client_id'),
        'client_secret' => setting('oidc_client_secret'),
    ]);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 8,
        ],
    ]);
    $response = @file_get_contents($config['token_endpoint'], false, $context);
    $token = $response ? json_decode($response, true) : [];
    $claims = [];
    if (!empty($token['id_token'])) {
        $parts = explode('.', $token['id_token']);
        if (count($parts) >= 2) {
            $payload = strtr($parts[1], '-_', '+/');
            $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
            $claims = json_decode(base64_decode($payload), true) ?: [];
        }
    }
    $email = trim($claims['email'] ?? '');
    if ($email === '') {
        flash('OIDC 未返回邮箱，无法关联用户。');
        redirect('?page=login');
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE oidc_email = ? OR email = ? ORDER BY oidc_email != "" DESC LIMIT 1');
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();
    if (!$user) {
        $usernameBase = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', explode('@', $email)[0]), '-')) ?: 'oidc-user';
        $username = $usernameBase;
        $i = 2;
        while (true) {
            $check = db()->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $check->execute([$username]);
            if ((int)$check->fetchColumn() === 0) {
                break;
            }
            $username = $usernameBase . '-' . $i++;
        }
        $stmt = db()->prepare('INSERT INTO users (username, display_name, email, avatar, password_hash, role) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $username,
            trim($claims['name'] ?? $username),
            $email,
            trim($claims['picture'] ?? ''),
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'user',
        ]);
        $_SESSION['user_id'] = (int)db()->lastInsertId();
    } else {
        $_SESSION['user_id'] = (int)$user['id'];
    }
    unset($_SESSION['oidc_state'], $_SESSION['oidc_nonce']);
    flash('OIDC 登录成功。');
    redirect('?page=profile');
}
