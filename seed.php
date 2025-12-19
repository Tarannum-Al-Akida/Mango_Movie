<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Inserts sample movies if Movies table is empty.
 * Uses id values explicitly (schema doesn't define AUTO_INCREMENT).
 */
try {
    $pdo = mango_pdo();

    $count = (int)$pdo->query("SELECT COUNT(*) FROM `Movies`")->fetchColumn();
    if ($count > 0) {
        header('Location: index.php');
        exit;
    }

    $samples = [
        [
            'id' => 1,
            'title' => 'Mango Nights',
            'poster_url' => 'https://images.unsplash.com/photo-1524985069026-dd778a71c7b4?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2022-06-18',
            'avg_rating' => 8.6,
            'is_popular' => 1,
        ],
        [
            'id' => 2,
            'title' => 'Neon Boulevard',
            'poster_url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2023-02-10',
            'avg_rating' => 7.9,
            'is_popular' => 1,
        ],
        [
            'id' => 3,
            'title' => 'The Last Signal',
            'poster_url' => 'https://images.unsplash.com/photo-1524985069026-dd778a71c7b4?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2021-11-04',
            'avg_rating' => 7.2,
            'is_popular' => 0,
        ],
        [
            'id' => 4,
            'title' => 'Midnight Cinema Club',
            'poster_url' => 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2020-09-12',
            'avg_rating' => 6.8,
            'is_popular' => 0,
        ],
        [
            'id' => 5,
            'title' => 'Orbit Runner',
            'poster_url' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2024-04-21',
            'avg_rating' => 8.1,
            'is_popular' => 1,
        ],
        [
            'id' => 6,
            'title' => 'Laugh Track',
            'poster_url' => 'https://images.unsplash.com/photo-1460881680858-30d872d5b530?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2019-01-19',
            'avg_rating' => 6.4,
            'is_popular' => 0,
        ],
        [
            'id' => 7,
            'title' => 'Sunset Heist',
            'poster_url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2022-10-03',
            'avg_rating' => 7.7,
            'is_popular' => 1,
        ],
        [
            'id' => 8,
            'title' => 'Paper Planets',
            'poster_url' => 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2018-05-26',
            'avg_rating' => 6.1,
            'is_popular' => 0,
        ],
        [
            'id' => 9,
            'title' => 'Afterglow',
            'poster_url' => 'https://images.unsplash.com/photo-1536440136628-849c177e76a1?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2023-08-14',
            'avg_rating' => 8.0,
            'is_popular' => 1,
        ],
        [
            'id' => 10,
            'title' => 'Quiet Hours',
            'poster_url' => 'https://images.unsplash.com/photo-1460881680858-30d872d5b530?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2017-12-08',
            'avg_rating' => 6.9,
            'is_popular' => 0,
        ],
        [
            'id' => 11,
            'title' => 'Golden Frame',
            'poster_url' => 'https://images.unsplash.com/photo-1524985069026-dd778a71c7b4?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2020-03-30',
            'avg_rating' => 8.9,
            'is_popular' => 0,
        ],
        [
            'id' => 12,
            'title' => 'Streetlight Stories',
            'poster_url' => 'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=800&q=80',
            'release_date' => '2021-07-07',
            'avg_rating' => 7.1,
            'is_popular' => 0,
        ],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO `Movies` (`id`, `title`, `poster_url`, `release_date`, `avg_rating`, `is_popular`)
        VALUES (:id, :title, :poster_url, :release_date, :avg_rating, :is_popular)
    ");
    foreach ($samples as $s) {
        $stmt->execute([
            ':id' => (int)$s['id'],
            ':title' => (string)$s['title'],
            ':poster_url' => (string)$s['poster_url'],
            ':release_date' => (string)$s['release_date'],
            ':avg_rating' => (float)$s['avg_rating'],
            ':is_popular' => (int)$s['is_popular'],
        ]);
    }

    header('Location: index.php');
    exit;
} catch (Throwable $t) {
    http_response_code(500);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>MANGO · Seed error</title>
        <link rel="stylesheet" href="assets/styles.css">
    </head>
    <body>
    <main class="wrap">
        <section class="panel panel-warn">
            <h2>Seeding failed</h2>
            <p class="muted">Make sure you imported <code>Mango_db.sql</code> into MySQL/MariaDB first.</p>
            <div class="codebox"><strong>Error:</strong> <?= e($t->getMessage()) ?></div>
            <div class="cta-row">
                <a class="btn" href="index.php">Back</a>
            </div>
        </section>
    </main>
    </body>
    </html>
    <?php
}
