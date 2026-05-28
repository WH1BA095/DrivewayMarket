<?php
require_once '../config/auth.php';
require_once '../config/db.php';

header('Content-Type: application/json; charset=utf-8');

function db(): PDO { return Database::getInstance()->getConnection(); }
function ok(array $d = []): void  { echo json_encode(array_merge(['success' => true], $d));  exit; }
function err(string $m): void      { echo json_encode(['success' => false, 'message' => $m]); exit; }

function displayName(array $row): string {
    $n = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
    if ($n === '') $n = $row['full_name'] ?? '';
    return $n ?: 'Пользователь';
}

$action     = $_POST['action'] ?? $_GET['action'] ?? '';
$product_id = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? 0);

switch ($action) {
    case 'get_reviews':       getReviews($product_id);   break;
    case 'add_review':        addReview($product_id);    break;
    case 'delete_review':     deleteReview();            break;
    case 'get_user_reviews':  getUserReviews();          break;
    case 'get_questions':      getQuestions($product_id); break;
    case 'add_question':       addQuestion($product_id);  break;
    case 'delete_question':    deleteQuestion();          break;
    case 'get_user_questions': getUserQuestions();        break;
    case 'send_support':      sendSupport();             break;
    case 'get_user_support':  getUserSupport();          break;
    default: err('Неизвестное действие');
}

function getReviews(int $pid): void {
    if (!$pid) err('Не указан товар');

    $st = db()->prepare("
        SELECT r.id, r.rating, r.title, r.body, r.admin_reply, r.admin_reply_at, r.created_at,
               u.firstname, u.lastname, u.full_name, u.avatar,
               IF(r.user_id = :uid, 1, 0) AS is_mine
        FROM product_reviews r
        JOIN users u ON u.id = r.user_id
        WHERE r.product_id = :pid AND r.status = 'approved'
        ORDER BY r.created_at DESC
    ");
    $uid = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
    $st->execute([':pid' => $pid, ':uid' => $uid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['display_name'] = displayName($r);
        unset($r['firstname'], $r['lastname'], $r['full_name']);
    }

    $st2 = db()->prepare("SELECT ROUND(AVG(rating),1), COUNT(*) FROM product_reviews WHERE product_id=? AND status='approved'");
    $st2->execute([$pid]);
    [$avg, $cnt] = $st2->fetch(PDO::FETCH_NUM);

    // Если залогинен — передать его собственный черновик (если есть)
    $myReview = null;
    if (isLoggedIn()) {
        $sm = db()->prepare("SELECT id, rating, title, body FROM product_reviews WHERE product_id=? AND user_id=?");
        $sm->execute([$pid, $_SESSION['user_id']]);
        $myReview = $sm->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    ok(['reviews' => $rows, 'avg' => (float)($avg ?? 0), 'count' => (int)$cnt, 'my_review' => $myReview]);
}

function addReview(int $pid): void {
    if (!isLoggedIn()) err('Необходима авторизация');
    if (!$pid) err('Не указан товар');

    $rating = (int)($_POST['rating'] ?? 0);
    $title  = mb_substr(strip_tags(trim($_POST['title'] ?? '')), 0, 120);
    $body   = mb_substr(strip_tags(trim($_POST['body']  ?? '')), 0, 2000);

    if ($rating < 1 || $rating > 5) err('Выберите оценку от 1 до 5');
    if (mb_strlen($body) < 5)       err('Отзыв слишком короткий (минимум 5 символов)');
    if (mb_strlen($body) > 2000)    err('Отзыв слишком длинный (максимум 2000 символов)');

    $st = db()->prepare("
        INSERT INTO product_reviews (product_id, user_id, rating, title, body, status)
        VALUES (?, ?, ?, ?, ?, 'approved')
        ON DUPLICATE KEY UPDATE
            rating = VALUES(rating),
            title  = VALUES(title),
            body   = VALUES(body),
            status = 'approved',
            admin_reply = NULL,
            admin_reply_at = NULL
    ");
    $st->execute([$pid, $_SESSION['user_id'], $rating, $title, $body]);
    ok(['message' => 'Отзыв опубликован!']);
}

function deleteReview(): void {
    if (!isLoggedIn()) err('Необходима авторизация');
    $id = (int)($_POST['review_id'] ?? 0);
    if (!$id) err('Не указан ID');
    $st = db()->prepare("DELETE FROM product_reviews WHERE id=? AND user_id=?");
    $st->execute([$id, $_SESSION['user_id']]);
    ok(['message' => 'Отзыв удалён']);
}

function getUserReviews(): void {
    if (!isLoggedIn()) err('Необходима авторизация');
    $st = db()->prepare("
        SELECT r.id, r.product_id, r.rating, r.title, r.body,
               r.status, r.admin_reply, r.admin_reply_at, r.created_at,
               p.name AS product_name, p.image AS product_image
        FROM product_reviews r
        JOIN products p ON p.id = r.product_id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $st->execute([$_SESSION['user_id']]);
    ok(['reviews' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

function getQuestions(int $pid): void {
    if (!$pid) err('Не указан товар');

    $st = db()->prepare("
        SELECT q.id, q.question, q.answer, q.answered_at, q.status, q.created_at,
               u.firstname, u.lastname, u.full_name,
               IF(q.user_id = :uid, 1, 0) AS is_mine
        FROM product_questions q
        JOIN users u ON u.id = q.user_id
        WHERE q.product_id = :pid
        ORDER BY q.created_at DESC
    ");
    $uid = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
    $st->execute([':pid' => $pid, ':uid' => $uid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$q) {
        $q['display_name'] = displayName($q);
        unset($q['firstname'], $q['lastname'], $q['full_name']);
    }
    ok(['questions' => $rows]);
}

function addQuestion(int $pid): void {
    if (!isLoggedIn()) err('Необходима авторизация');
    if (!$pid) err('Не указан товар');

    $question = mb_substr(strip_tags(trim($_POST['question'] ?? '')), 0, 1000);
    if (mb_strlen($question) < 5)    err('Вопрос слишком короткий (минимум 5 символов)');
    if (mb_strlen($question) > 1000) err('Вопрос слишком длинный (максимум 1000 символов)');

    $st = db()->prepare("INSERT INTO product_questions (product_id, user_id, question) VALUES (?,?,?)");
    $st->execute([$pid, $_SESSION['user_id'], $question]);
    ok(['message' => 'Вопрос отправлен! Администратор ответит в ближайшее время.']);
}

function deleteQuestion(): void {
    if (!isLoggedIn()) err('Необходима авторизация');
    $id = (int)($_POST['question_id'] ?? 0);
    if (!$id) err('Не указан ID');
    $st = db()->prepare("DELETE FROM product_questions WHERE id=? AND user_id=?");
    $st->execute([$id, $_SESSION['user_id']]);
    ok(['message' => 'Вопрос удалён']);
}

function getUserQuestions(): void {
    if (!isLoggedIn()) err('Необходима авторизация');
    $st = db()->prepare("
        SELECT q.id, q.product_id, q.question, q.answer,
               q.status, q.answered_at, q.created_at,
               p.name AS product_name, p.image AS product_image
        FROM product_questions q
        JOIN products p ON p.id = q.product_id
        WHERE q.user_id = ?
        ORDER BY q.created_at DESC
    ");
    $st->execute([$_SESSION['user_id']]);
    ok(['questions' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

function sendSupport(): void {
    $name    = mb_substr(strip_tags(trim($_POST['name']    ?? '')), 0, 100);
    $email   = mb_strtolower(mb_substr(strip_tags(trim($_POST['email'] ?? '')), 0, 150));
    $subject = mb_substr(strip_tags(trim($_POST['subject'] ?? '')), 0, 120);
    $message = mb_substr(strip_tags(trim($_POST['message'] ?? '')), 0, 5000);

    if (!$name)    err('Введите имя');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) err('Некорректный email');
    if (mb_strlen($message) < 10)   err('Сообщение слишком короткое (минимум 10 символов)');
    if (mb_strlen($message) > 5000) err('Сообщение слишком длинное (максимум 5000 символов)');

    $uid = isLoggedIn() ? $_SESSION['user_id'] : null;
    $st  = db()->prepare("
        INSERT INTO support_messages (user_id, name, email, subject, message)
        VALUES (?,?,?,?,?)
    ");
    $st->execute([$uid, $name, $email, $subject, $message]);
    ok(['message' => 'Сообщение отправлено! Мы ответим на ' . $email]);
}

function getUserSupport(): void {
    if (!isLoggedIn()) err('Необходима авторизация');
    $st = db()->prepare("
        SELECT id, subject, message, status, reply, replied_at, created_at
        FROM support_messages
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $st->execute([$_SESSION['user_id']]);
    ok(['messages' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}
