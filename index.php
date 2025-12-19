<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf']) || strlen($_SESSION['csrf']) < 16) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function require_csrf(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(400);
        echo "Bad request (CSRF).";
        exit;
    }
}

function derived_genre(float $avgRating, int $isPopular): string
{
    // The provided SQL schema does not include genres; we derive a "vibe" genre for UI tags.
    if ($isPopular === 1) return 'Trending';
    if ($avgRating >= 8.5) return 'Drama';
    if ($avgRating >= 7.8) return 'Action';
    if ($avgRating >= 7.0) return 'Sci‑Fi';
    if ($avgRating >= 6.3) return 'Comedy';
    return 'Indie';
}

function first_letter(string $title): string
{
    $title = trim($title);
    if ($title === '') return 'M';
    if (function_exists('mb_substr')) {
        return (string)mb_substr($title, 0, 1);
    }
    return substr($title, 0, 1);
}

$pdo = null;
$dbError = null;
try {
    $pdo = mango_pdo();
} catch (Throwable $t) {
    $dbError = $t->getMessage();
}

$DEMO_USER_ID = 1;

// Handle watchlist actions
if ($pdo instanceof PDO && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_csrf();

    $action = $_POST['action'] ?? '';
    $movieId = (int)($_POST['movie_id'] ?? 0);

    if ($movieId > 0 && in_array($action, ['watch_add', 'watch_remove'], true)) {
        // Ensure demo user exists (schema doesn't define AUTO_INCREMENT, so we explicitly set id=1).
        $pdo->prepare("INSERT IGNORE INTO `User` (`id`, `username`, `profile_pic`) VALUES (:id, :u, :p)")
            ->execute([':id' => $DEMO_USER_ID, ':u' => 'demo', ':p' => '']);

        if ($action === 'watch_add') {
            $existsStmt = $pdo->prepare("SELECT 1 FROM `Watchlist` WHERE `user_id` = :uid AND `movie_id` = :mid LIMIT 1");
            $existsStmt->execute([':uid' => $DEMO_USER_ID, ':mid' => $movieId]);
            if (!$existsStmt->fetchColumn()) {
                $pdo->prepare("INSERT INTO `Watchlist` (`user_id`, `movie_id`) VALUES (:uid, :mid)")
                    ->execute([':uid' => $DEMO_USER_ID, ':mid' => $movieId]);
            }
        } else {
            $pdo->prepare("DELETE FROM `Watchlist` WHERE `user_id` = :uid AND `movie_id` = :mid")
                ->execute([':uid' => $DEMO_USER_ID, ':mid' => $movieId]);
        }
    }

    // PRG redirect back
    $back = $_POST['back'] ?? 'index.php';
    header('Location: ' . $back);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
$view = (string)($_GET['view'] ?? 'browse'); // browse | watchlist
$onlyPopular = ((string)($_GET['popular'] ?? '')) === '1';

$movies = [];
$watchlistIds = [];
$watchlistCount = 0;
$emptyState = null;

if ($pdo instanceof PDO) {
    // Fetch watchlist ids
    try {
        $pdo->prepare("INSERT IGNORE INTO `User` (`id`, `username`, `profile_pic`) VALUES (:id, :u, :p)")
            ->execute([':id' => $DEMO_USER_ID, ':u' => 'demo', ':p' => '']);

        $wlStmt = $pdo->prepare("SELECT `movie_id` FROM `Watchlist` WHERE `user_id` = :uid");
        $wlStmt->execute([':uid' => $DEMO_USER_ID]);
        foreach ($wlStmt->fetchAll() as $row) {
            $watchlistIds[(int)$row['movie_id']] = true;
        }
        $watchlistCount = count($watchlistIds);
    } catch (Throwable $t) {
        // If watchlist table isn't imported yet, don't crash the page.
        $watchlistIds = [];
        $watchlistCount = 0;
    }

    // Fetch movies (browse or watchlist view)
    $params = [];
    $where = [];

    if ($q !== '') {
        $where[] = "`title` LIKE :q";
        $params[':q'] = '%' . $q . '%';
    }

    if ($onlyPopular) {
        $where[] = "`is_popular` = 1";
    }

    if ($view === 'watchlist' && $watchlistCount > 0) {
        $ids = array_keys($watchlistIds);
        $placeholders = [];
        foreach ($ids as $i => $id) {
            $ph = ":id{$i}";
            $placeholders[] = $ph;
            $params[$ph] = $id;
        }
        $where[] = "`id` IN (" . implode(',', $placeholders) . ")";
    } elseif ($view === 'watchlist' && $watchlistCount === 0) {
        $movies = [];
        $emptyState = "Your watchlist is empty. Browse movies and hit “+ Watchlist”.";
    }

    if ($emptyState === null) {
        $sql = "SELECT `id`, `title`, `poster_url`, `release_date`, `avg_rating`, `is_popular` FROM `Movies`";
        if (count($where) > 0) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY `is_popular` DESC, `avg_rating` DESC, `release_date` DESC, `title` ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $movies = $stmt->fetchAll();

        if ($view !== 'watchlist' && count($movies) === 0) {
            $emptyState = "No movies found. If you only imported the schema, run seed.php to add sample movies.";
        }
    }
}

$baseQuery = [
    'q' => $q !== '' ? $q : null,
    'popular' => $onlyPopular ? '1' : null,
];
function url_with(array $baseQuery, array $overrides): string
{
    $q = $baseQuery;
    foreach ($overrides as $k => $v) {
        $q[$k] = $v;
    }
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    return 'index.php' . (count($q) ? ('?' . http_build_query($q)) : '');
}

$browseUrl = url_with($baseQuery, ['view' => 'browse']);
$watchlistUrl = url_with($baseQuery, ['view' => 'watchlist']);
$togglePopularUrl = url_with($baseQuery, ['view' => $view, 'popular' => $onlyPopular ? null : '1']);

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MANGO · Movies</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<div class="bg-glow" aria-hidden="true"></div>

<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= e($browseUrl) ?>">
            <span class="brand-dot" aria-hidden="true"></span>
            <span class="brand-text">MANGO</span>
        </a>

        <form class="search" method="get" action="index.php">
            <input type="hidden" name="view" value="<?= e($view) ?>">
            <?php if ($onlyPopular): ?>
                <input type="hidden" name="popular" value="1">
            <?php endif; ?>
            <label class="sr-only" for="q">Search</label>
            <input id="q" name="q" value="<?= e($q) ?>" placeholder="Search movies, titles…" autocomplete="off">
            <button class="btn btn-ghost" type="submit">Search</button>
        </form>

        <nav class="nav">
            <a class="pill <?= $view === 'browse' ? 'pill-active' : '' ?>" href="<?= e($browseUrl) ?>">Browse</a>
            <a class="pill <?= $view === 'watchlist' ? 'pill-active' : '' ?>" href="<?= e($watchlistUrl) ?>">
                Watchlist
                <span class="count" aria-label="Watchlist count"><?= (int)$watchlistCount ?></span>
            </a>
            <a class="pill <?= $onlyPopular ? 'pill-active' : '' ?>" href="<?= e($togglePopularUrl) ?>">
                <?= $onlyPopular ? 'Popular ✓' : 'Popular' ?>
            </a>
        </nav>
    </div>
</header>

<main class="wrap">
    <?php if ($dbError !== null): ?>
        <section class="panel panel-warn">
            <h2>Database not connected</h2>
            <p>This dashboard expects the MySQL/MariaDB database from <code>Mango_db.sql</code>.</p>
            <div class="codebox">
                <div><strong>Error:</strong> <?= e($dbError) ?></div>
            </div>
            <p class="muted">
                Configure env vars: <code>MANGO_DB_HOST</code>, <code>MANGO_DB_NAME</code>, <code>MANGO_DB_USER</code>, <code>MANGO_DB_PASS</code>.
            </p>
        </section>
    <?php else: ?>
        <section class="hero">
            <div class="hero-left">
                <h1><?= $view === 'watchlist' ? 'Your Watchlist' : 'MANGO Movie List' ?></h1>
                <p class="muted">
                    Posters, ratings and a clean watchlist — only for <strong>MANGO USERS</strong>.
                </p>
            </div>
            <div class="hero-right">
                <div class="stat">
                    <div class="stat-label">Showing</div>
                    <div class="stat-value"><?= (int)count($movies) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-label">Watchlist</div>
                    <div class="stat-value"><?= (int)$watchlistCount ?></div>
                </div>
            </div>
        </section>

        <?php if ($emptyState !== null): ?>
            <section class="panel">
                <h2>Nothing to show</h2>
                <p class="muted"><?= e($emptyState) ?></p>
                <div class="cta-row">
                    <a class="btn" href="<?= e($browseUrl) ?>">Back to Browse</a>
                    <a class="btn btn-ghost" href="seed.php">See Sample Movies</a>
                </div>
            </section>
        <?php else: ?>
            <section class="grid" aria-label="Movies">
                <?php foreach ($movies as $m):
                    $id = (int)$m['id'];
                    $title = (string)$m['title'];
                    $poster = (string)$m['poster_url'];
                    $release = (string)$m['release_date'];
                    $rating = (float)$m['avg_rating'];
                    $popular = (int)$m['is_popular'] === 1;
                    $year = strlen($release) >= 4 ? substr($release, 0, 4) : '';
                    $genre = derived_genre($rating, $popular ? 1 : 0);
                    $inWatchlist = isset($watchlistIds[$id]);
                    ?>
                    <article class="card">
                        <div class="poster-wrap">
                            <?php if ($popular): ?>
                                <div class="badge">Popular</div>
                            <?php endif; ?>

                            <div class="poster" style="--poster: url('<?= e($poster) ?>')">
                                <img class="poster-img" src="<?= e($poster) ?>" alt="<?= e($title) ?> poster" loading="lazy"
                                     onerror="this.style.display='none'; this.parentElement.classList.add('poster-fallback');">
                                <div class="poster-fallback-art" aria-hidden="true">
                                    <div class="poster-fallback-title"><?= e(first_letter($title)) ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="meta">
                            <div class="title-row">
                                <h3 class="title" title="<?= e($title) ?>"><?= e($title) ?></h3>
                                <div class="rating" title="Average rating">
                                    <span class="star" aria-hidden="true">★</span>
                                    <span><?= number_format($rating, 1) ?></span>
                                </div>
                            </div>

                            <div class="sub">
                                <span class="tag"><?= e($genre) ?></span>
                                <?php if ($year !== ''): ?>
                                    <span class="dot" aria-hidden="true"></span>
                                    <span class="muted"><?= e($year) ?></span>
                                <?php endif; ?>
                            </div>

                            <form method="post" class="actions">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="movie_id" value="<?= (int)$id ?>">
                                <input type="hidden" name="back" value="<?= e($_SERVER['REQUEST_URI'] ?? 'index.php') ?>">
                                <?php if ($inWatchlist): ?>
                                    <input type="hidden" name="action" value="watch_remove">
                                    <button class="btn btn-ghost btn-full" type="submit">✓ In Watchlist</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="watch_add">
                                    <button class="btn btn-full" type="submit">+ Watchlist</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>

<footer class="footer">
    <div class="footer-inner">
        <span class="muted">© <?= (int)date('Y') ?> MANGO</span>
        <span class="muted">·</span>
        <span class="muted">Build By Muneem Zaman</span>
    </div>
</footer>
</body>
</html>
