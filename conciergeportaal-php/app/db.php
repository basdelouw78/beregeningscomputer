<?php
// Databaseverbinding (MySQL via PDO) en het databaseschema.

function db_connect(array $cfg): PDO
{
    $dsn = 'mysql:host=' . $cfg['db_host'] . ';dbname=' . $cfg['db_name'] . ';charset=utf8mb4';
    return new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
}

function db(): PDO
{
    return $GLOBALS['__db'];
}

// {tabel} in een query wordt vervangen door de tabelnaam met voorvoegsel.
function sql(string $query): string
{
    $prefix = $GLOBALS['__config']['db_prefix'] ?? '';
    return preg_replace('/\{([a-z_]+)\}/', '`' . $prefix . '$1`', $query);
}

function q(string $query, array $params = []): PDOStatement
{
    $stmt = db()->prepare(sql($query));
    $stmt->execute(array_values($params));
    return $stmt;
}

function one(string $query, array $params = []): ?array
{
    $row = q($query, $params)->fetch();
    return $row === false ? null : $row;
}

function all(string $query, array $params = []): array
{
    return q($query, $params)->fetchAll();
}

function val(string $query, array $params = [])
{
    $v = q($query, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function insert_id(): int
{
    return (int) db()->lastInsertId();
}

function transaction(callable $fn)
{
    db()->beginTransaction();
    try {
        $result = $fn();
        db()->commit();
        return $result;
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
}

function db_schema(): array
{
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS {users} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(80) NOT NULL,
            phone VARCHAR(30) NOT NULL DEFAULT '',
            is_admin TINYINT(1) NOT NULL DEFAULT 0,
            setup_done TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        ) $t",
        "CREATE TABLE IF NOT EXISTS {schools} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            address VARCHAR(200) NOT NULL DEFAULT '',
            lat DOUBLE NOT NULL,
            lng DOUBLE NOT NULL
        ) $t",
        "CREATE TABLE IF NOT EXISTS {schedules} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            school_id INT UNSIGNED NOT NULL,
            day TINYINT UNSIGNED NOT NULL,
            start_time CHAR(5) NOT NULL,
            end_time CHAR(5) NOT NULL,
            FOREIGN KEY (user_id) REFERENCES {users}(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES {schools}(id) ON DELETE CASCADE
        ) $t",
        "CREATE TABLE IF NOT EXISTS {materials} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            school_id INT UNSIGNED NULL,
            owner_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (school_id) REFERENCES {schools}(id) ON DELETE SET NULL,
            FOREIGN KEY (owner_id) REFERENCES {users}(id) ON DELETE SET NULL
        ) $t",
        "CREATE TABLE IF NOT EXISTS {loans} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            material_id INT UNSIGNED NOT NULL,
            borrower_id INT UNSIGNED NULL,
            school_id INT UNSIGNED NULL,
            due_date DATE NULL,
            note VARCHAR(300) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            returned_at DATETIME NULL,
            FOREIGN KEY (material_id) REFERENCES {materials}(id) ON DELETE CASCADE,
            FOREIGN KEY (borrower_id) REFERENCES {users}(id) ON DELETE SET NULL,
            FOREIGN KEY (school_id) REFERENCES {schools}(id) ON DELETE SET NULL
        ) $t",
        "CREATE TABLE IF NOT EXISTS {meetings} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            description TEXT NOT NULL,
            date DATE NOT NULL,
            start_time CHAR(5) NOT NULL,
            end_time VARCHAR(5) NOT NULL DEFAULT '',
            place VARCHAR(120) NOT NULL DEFAULT '',
            organizer_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (organizer_id) REFERENCES {users}(id) ON DELETE SET NULL
        ) $t",
        "CREATE TABLE IF NOT EXISTS {meeting_attendees} (
            meeting_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (meeting_id, user_id),
            FOREIGN KEY (meeting_id) REFERENCES {meetings}(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {users}(id) ON DELETE CASCADE
        ) $t",
        "CREATE TABLE IF NOT EXISTS {questions} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            title VARCHAR(200) NOT NULL,
            body TEXT NOT NULL,
            solved TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES {users}(id) ON DELETE SET NULL
        ) $t",
        "CREATE TABLE IF NOT EXISTS {answers} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NULL,
            body TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (question_id) REFERENCES {questions}(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES {users}(id) ON DELETE SET NULL
        ) $t",
        "CREATE TABLE IF NOT EXISTS {tutorials} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            title VARCHAR(150) NOT NULL,
            category VARCHAR(40) NOT NULL DEFAULT 'Overig',
            description TEXT NOT NULL,
            steps MEDIUMTEXT NOT NULL,
            video_url VARCHAR(300) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES {users}(id) ON DELETE SET NULL
        ) $t",
        "CREATE TABLE IF NOT EXISTS {tutorial_files} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tutorial_id INT UNSIGNED NOT NULL,
            filename VARCHAR(64) NOT NULL UNIQUE,
            original_name VARCHAR(200) NOT NULL,
            mimetype VARCHAR(60) NOT NULL,
            size INT UNSIGNED NOT NULL DEFAULT 0,
            FOREIGN KEY (tutorial_id) REFERENCES {tutorials}(id) ON DELETE CASCADE
        ) $t",
        "CREATE TABLE IF NOT EXISTS {sessions} (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data MEDIUMTEXT NOT NULL,
            expires INT UNSIGNED NOT NULL,
            INDEX (expires)
        ) $t",
        "CREATE TABLE IF NOT EXISTS {login_attempts} (
            ip VARCHAR(45) NOT NULL PRIMARY KEY,
            attempts INT UNSIGNED NOT NULL,
            first_at INT UNSIGNED NOT NULL
        ) $t",
    ];
}

function db_install(): void
{
    foreach (db_schema() as $stmt) {
        db()->exec(sql($stmt));
    }
    if ((int) val('SELECT COUNT(*) FROM {schools}') === 0) {
        // Voorbeeldscholen; pas ze aan via Beheer.
        $schools = [
            ['Basisschool De Regenboog', 'Voorbeeldstraat 1', 52.0990, 5.1050],
            ['OBS De Linde', 'Lindelaan 12', 52.0855, 5.1290],
            ['CBS Het Kompas', 'Kompasweg 3', 52.1080, 5.1400],
            ['Montessorischool De Wilg', 'Wilgenhof 8', 52.0760, 5.0980],
            ['Daltonschool Het Anker', 'Havenkade 20', 52.0930, 5.1600],
            ['KBS De Vlinder', 'Vlinderpad 5', 52.1150, 5.0900],
        ];
        foreach ($schools as $s) {
            q('INSERT INTO {schools} (name, address, lat, lng) VALUES (?, ?, ?, ?)', $s);
        }
    }
}
