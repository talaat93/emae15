<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $db = $config['db'] ?? [];
    $host = $db['host'] ?? 'localhost';
    $port = $db['port'] ?? '3306';
    $name = $db['name'] ?? '';
    $user = $db['user'] ?? 'root';
    $pass = $db['pass'] ?? '';
    $charset = $db['charset'] ?? 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        error_log('Connexion MySQL impossible : '.$e->getMessage());
        // Cas de loin le plus probable après un déploiement : le fichier
        // d'identifiants, volontairement non versionné, n'a pas été recréé.
        // On l'explique au lieu d'afficher une page blanche.
        if (trim((string)$name) === '' || trim((string)$user) === '') {
            db_fail_page(
                'Configuration de la base de données manquante',
                'Le fichier <code>config/config.local.php</code> est absent sur ce serveur. '
                . 'Il contient les identifiants de connexion et n\'est volontairement pas versionné.',
                'Créez <code>config/config.local.php</code> en repartant de <code>config/config.php.example</code>, '
                . 'et renseignez-y les identifiants de votre base.'
            );
        }
        db_fail_page(
            'Base de données indisponible',
            'Le site ne parvient pas à joindre sa base de données.',
            'Réessayez dans quelques instants. Si le problème persiste, vérifiez les identifiants '
            . 'dans <code>config/config.local.php</code>. Le détail de l\'erreur est dans le journal du serveur.'
        );
    }

    return $pdo;
}

/** Page d'erreur lisible, sans jamais divulguer d'identifiants ni de chemins. */
function db_fail_page(string $titre, string $constat, string $action): never
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $titre.' — '.strip_tags($constat).' '.strip_tags($action)."\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 3600');
    exit('<!doctype html><meta charset="utf-8"><title>'.htmlspecialchars($titre, ENT_QUOTES).'</title>'
        . '<div style="max-width:40rem;margin:4rem auto;padding:0 1.5rem;'
        . 'font:16px/1.6 system-ui,-apple-system,sans-serif;color:#1b2d6b;">'
        . '<h1 style="font-size:1.4rem;">'.htmlspecialchars($titre, ENT_QUOTES).'</h1>'
        . '<p>'.$constat.'</p><p><strong>À faire :</strong> '.$action.'</p>'
        . '<p style="color:#6b7a99;font-size:.9rem;">Aucune donnée n\'est perdue.</p></div>');
}

function db_fetch(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function db_fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function db_execute(string $sql, array $params = []): bool
{
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}

function db_last_id(): int
{
    return (int) db()->lastInsertId();
}
