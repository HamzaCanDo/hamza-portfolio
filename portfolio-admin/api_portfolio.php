<?php
require_once __DIR__ . '/api_config.php';

dashboard_start_session();
$pdo = db_connect();
$action = $_GET['action'] ?? 'manifest';

function portfolio_text($value, $limit) {
    $value = trim((string)$value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
}

function portfolio_body() {
    $body = getJsonBody();
    if (!is_array($body)) {
        dashboard_json(['error' => 'A JSON request body is required'], 400);
    }
    return $body;
}

function portfolio_id($value) {
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        dashboard_json(['error' => 'A valid id is required'], 422);
    }
    return (int)$id;
}

function portfolio_file_url($url) {
    if (!is_string($url) || strpos($url, '/portfolio-admin/uploads/portfolio/') !== 0) {
        return null;
    }
    $name = basename($url);
    return preg_match('/^[a-f0-9]{32}\.webp$/', $name) ? $name : null;
}

function portfolio_upload_dir() {
    return __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'portfolio';
}

function portfolio_public_file() {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'portfolio-content.json';
}

function portfolio_delete_file($url) {
    $name = portfolio_file_url($url);
    if ($name) {
        @unlink(portfolio_upload_dir() . DIRECTORY_SEPARATOR . $name);
    }
}

function portfolio_ensure_schema(PDO $pdo) {
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec('CREATE TABLE IF NOT EXISTS `portfolio_content` (
      `id` TINYINT UNSIGNED NOT NULL,
      `content_json` LONGTEXT NOT NULL,
      `updated_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS `portfolio_site_images` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `slot` VARCHAR(64) NOT NULL,
      `image_url` VARCHAR(512) NOT NULL DEFAULT \'\',
      `alt_text` VARCHAR(255) NOT NULL DEFAULT \'\',
      `crop_x` INT UNSIGNED NOT NULL DEFAULT 0,
      `crop_y` INT UNSIGNED NOT NULL DEFAULT 0,
      `crop_width` INT UNSIGNED NOT NULL DEFAULT 0,
      `crop_height` INT UNSIGNED NOT NULL DEFAULT 0,
      `sort_order` INT NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL,
      `updated_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `portfolio_site_images_slot` (`slot`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS `portfolio_skills` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `category` VARCHAR(64) NOT NULL DEFAULT \'\',
      `name` VARCHAR(191) NOT NULL,
      `icon` VARCHAR(512) NOT NULL DEFAULT \'\',
      `sort_order` INT NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL,
      `updated_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS `portfolio_projects` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `title` VARCHAR(191) NOT NULL,
      `slug` VARCHAR(191) NOT NULL,
      `description` TEXT NOT NULL,
      `url` VARCHAR(512) NOT NULL DEFAULT \'\',
      `repository_url` VARCHAR(512) NOT NULL DEFAULT \'\',
      `sort_order` INT NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL,
      `updated_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `portfolio_projects_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS `portfolio_project_screenshots` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `project_id` INT UNSIGNED NOT NULL,
      `image_url` VARCHAR(512) NOT NULL DEFAULT \'\',
      `alt_text` VARCHAR(255) NOT NULL DEFAULT \'\',
      `sort_order` INT NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL,
      `updated_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`),
      KEY `portfolio_screenshots_project` (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $pdo->exec('CREATE TABLE IF NOT EXISTS `portfolio_timeline_items` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `period` VARCHAR(191) NOT NULL DEFAULT \'\',
      `title` VARCHAR(191) NOT NULL,
      `description` TEXT NOT NULL,
      `sort_order` INT NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL,
      `updated_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

    $ready = true;
}

function portfolio_read_public_file() {
    $fallback = portfolio_public_file();
    if (!is_readable($fallback)) {
        return null;
    }
    $content = json_decode((string) file_get_contents($fallback), true);
    return is_array($content) ? $content : null;
}

function portfolio_normalize_public_content(array $content) {
    $images = is_array($content['images'] ?? null) ? $content['images'] : [];
    $skills = [];
    foreach ((array) ($content['skills'] ?? []) as $skill) {
        if (!is_array($skill)) {
            continue;
        }
        $name = portfolio_text($skill['name'] ?? '', 191);
        if ($name === '') {
            continue;
        }
        $skills[] = [
            'name' => $name,
            'img' => portfolio_text($skill['img'] ?? $skill['icon'] ?? '', 512),
        ];
    }
    $projects = [];
    foreach ((array) ($content['projects'] ?? []) as $project) {
        if (!is_array($project)) {
            continue;
        }
        $name = portfolio_text($project['name'] ?? $project['title'] ?? '', 191);
        if ($name === '') {
            continue;
        }
        $imagesList = [];
        $shots = $project['images'] ?? $project['screenshots'] ?? [];
        foreach ((array) $shots as $shot) {
            $url = is_array($shot) ? ($shot['image_url'] ?? '') : $shot;
            if (is_string($url) && $url !== '') {
                $imagesList[] = portfolio_text($url, 512);
            }
        }
        $projects[] = [
            'name' => $name,
            'desc' => portfolio_text($project['desc'] ?? $project['description'] ?? '', 5000),
            'images' => $imagesList,
            'demoUrl' => portfolio_text($project['demoUrl'] ?? $project['url'] ?? '', 512),
        ];
    }
    $about = [];
    foreach ((array) ($content['about'] ?? $content['timeline'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = portfolio_text($item['title'] ?? '', 191);
        $text = portfolio_text($item['text'] ?? $item['description'] ?? '', 5000);
        if ($title !== '' && $text !== '') {
            $about[] = ['title' => $title, 'text' => $text];
        }
    }
    return [
        'images' => [
            'hero' => portfolio_text($images['hero'] ?? '', 512),
            'about' => portfolio_text($images['about'] ?? '', 512),
        ],
        'skills' => $skills,
        'projects' => $projects,
        'about' => $about,
    ];
}

function portfolio_write_public_json(array $content) {
    $target = portfolio_public_file();
    $directory = dirname($target);
    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException('Site root is not writable for portfolio-content.json');
    }
    $temp = $directory . DIRECTORY_SEPARATOR . '.portfolio-content.' . bin2hex(random_bytes(8)) . '.tmp';
    $json = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($temp, $json . PHP_EOL, LOCK_EX) === false) {
        @unlink($temp);
        throw new RuntimeException('Unable to write temporary portfolio-content.json');
    }
    if (!@rename($temp, $target)) {
        $copied = @copy($temp, $target);
        @unlink($temp);
        if (!$copied) {
            throw new RuntimeException('Unable to publish portfolio-content.json (set write permission on that file/folder)');
        }
    }
    @chmod($target, 0644);
}

function portfolio_manifest(PDO $pdo) {
    try {
        portfolio_ensure_schema($pdo);
        $images = $pdo->query('SELECT id,slot,image_url,alt_text,crop_x,crop_y,crop_width,crop_height,sort_order,updated_at FROM portfolio_site_images ORDER BY sort_order,id')->fetchAll();
        $skills = $pdo->query('SELECT id,category,name,icon,sort_order,updated_at FROM portfolio_skills ORDER BY sort_order,id')->fetchAll();
        $projects = $pdo->query('SELECT id,title,slug,description,url,repository_url,sort_order,updated_at FROM portfolio_projects ORDER BY sort_order,id')->fetchAll();
        $shots = $pdo->query('SELECT id,project_id,image_url,alt_text,sort_order,updated_at FROM portfolio_project_screenshots ORDER BY project_id,sort_order,id')->fetchAll();
        $timeline = $pdo->query('SELECT id,period,title,description,sort_order,updated_at FROM portfolio_timeline_items ORDER BY sort_order,id')->fetchAll();
    } catch (Throwable $e) {
        $fallback = portfolio_read_public_file();
        if ($fallback) {
            return portfolio_normalize_public_content($fallback);
        }
        throw $e;
    }

    $imageMap = ['hero' => '', 'about' => ''];
    foreach ($images as $image) {
        if (isset($imageMap[$image['slot']])) {
            $imageMap[$image['slot']] = $image['image_url'];
        }
    }
    $manifestSkills = [];
    foreach ($skills as $skill) {
        $manifestSkills[] = ['name' => $skill['name'], 'img' => $skill['icon']];
    }
    $byProject = [];
    foreach ($shots as $shot) {
        $projectId = (int)$shot['project_id'];
        $byProject[$projectId][] = $shot['image_url'];
    }
    $manifestProjects = [];
    foreach ($projects as $project) {
        $manifestProjects[] = [
            'name' => $project['title'],
            'desc' => $project['description'],
            'images' => $byProject[(int)$project['id']] ?? [],
            'demoUrl' => $project['url'],
        ];
    }
    $manifestAbout = [];
    foreach ($timeline as $item) {
        $manifestAbout[] = ['title' => $item['title'], 'text' => $item['description']];
    }

    $manifest = [
        'images' => $imageMap,
        'skills' => $manifestSkills,
        'projects' => $manifestProjects,
        'about' => $manifestAbout,
    ];

    // If tables are still empty, keep serving the published JSON so the site does not go blank.
    if ($manifestSkills === [] && $manifestProjects === [] && $manifestAbout === [] && $imageMap['hero'] === '' && $imageMap['about'] === '') {
        $fallback = portfolio_read_public_file();
        if ($fallback) {
            return portfolio_normalize_public_content($fallback);
        }
    }

    return $manifest;
}

function portfolio_legacy_content(PDO $pdo) {
    try {
        portfolio_ensure_schema($pdo);
        $stmt = $pdo->query('SELECT content_json FROM portfolio_content WHERE id = 1 LIMIT 1');
        $json = $stmt->fetchColumn();
        if ($json) {
            $content = json_decode((string)$json, true);
            if (is_array($content)) {
                return portfolio_normalize_public_content($content);
            }
        }
    } catch (Throwable $e) {
        // Fall through to public file.
    }
    $fallback = portfolio_read_public_file();
    if ($fallback) {
        return portfolio_normalize_public_content($fallback);
    }
    return ['images' => ['hero' => '', 'about' => ''], 'skills' => [], 'projects' => [], 'about' => []];
}

function portfolio_publish(PDO $pdo, array $content = null) {
    if ($content === null) {
        $content = portfolio_manifest($pdo);
    } else {
        $content = portfolio_normalize_public_content($content);
    }
    portfolio_write_public_json($content);
    return $content;
}

function portfolio_upload($kind, $id, $crop) {
    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        dashboard_json(['error' => 'No image was received by PHP. Check the server upload limits and multipart form request.'], 422);
    }
    $file = $_FILES['image'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'The image exceeds the server upload_max_filesize limit',
            UPLOAD_ERR_FORM_SIZE => 'The image exceeds the form size limit',
            UPLOAD_ERR_PARTIAL => 'The image upload was interrupted',
            UPLOAD_ERR_NO_FILE => 'No image was selected',
            UPLOAD_ERR_NO_TMP_DIR => 'The server temporary upload directory is missing',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded image',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the image upload',
        ];
        $code = (int)$file['error'];
        dashboard_json(['error' => $uploadErrors[$code] ?? ('Image upload failed with PHP error code ' . $code)], 422);
    }
    if (!is_uploaded_file($file['tmp_name']) || $file['size'] < 1 || $file['size'] > 8 * 1024 * 1024) {
        dashboard_json(['error' => 'Image must be an uploaded file no larger than 8 MB'], 422);
    }
    $info = @getimagesize($file['tmp_name']);
    $mime = strtolower((string)($info['mime'] ?? ''));
    $extension = strtolower((string)pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    // Some hosts/browsers report JPEGs as image/jpg or image/pjpeg. The image
    // signature from getimagesize() remains required; the extension is only a
    // fallback for hosts that do not provide a MIME value.
    if ($mime === '' && in_array($extension, ['jpg', 'jpeg'], true)) {
        $mime = 'image/jpeg';
    }
    if ($mime === 'image/jpg' || $mime === 'image/pjpeg') {
        $mime = 'image/jpeg';
    }
    if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] > 8000 || $info[1] > 8000) {
        dashboard_json(['error' => 'Only valid JPEG, PNG, or WebP images up to 8000px are allowed'], 422);
    }
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
        dashboard_json(['error' => 'Server image processing is not available (GD/WebP is required)'], 500);
    }
    if ($mime === 'image/jpeg' && !function_exists('imagecreatefromjpeg')) {
        dashboard_json(['error' => 'Server cannot process JPEG images (GD JPEG support is required)'], 500);
    }
    if ($mime === 'image/png' && !function_exists('imagecreatefrompng')) {
        dashboard_json(['error' => 'Server cannot process PNG images (GD PNG support is required)'], 500);
    }
    if ($mime === 'image/webp' && !function_exists('imagecreatefromwebp')) {
        dashboard_json(['error' => 'Server cannot process WebP images (GD WebP support is required)'], 500);
    }
    $source = $mime === 'image/jpeg' ? @imagecreatefromjpeg($file['tmp_name']) :
        ($mime === 'image/png' ? @imagecreatefrompng($file['tmp_name']) : @imagecreatefromwebp($file['tmp_name']));
    if (!$source) {
        dashboard_json(['error' => 'Image could not be decoded'], 422);
    }
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $x = max(0, min($sourceWidth - 1, (int)($crop['x'] ?? 0)));
    $y = max(0, min($sourceHeight - 1, (int)($crop['y'] ?? 0)));
    $width = max(1, min((int)($crop['width'] ?? 0) ?: $sourceWidth - $x, $sourceWidth - $x));
    $height = max(1, min((int)($crop['height'] ?? 0) ?: $sourceHeight - $y, $sourceHeight - $y));
    $destination = imagecreatetruecolor($width, $height);
    imagealphablending($destination, false);
    imagesavealpha($destination, true);
    imagecopy($destination, $source, 0, 0, $x, $y, $width, $height);
    imagedestroy($source);

    $directory = portfolio_upload_dir();
    if (!is_dir($directory) && !mkdir($directory, 0750, true)) {
        imagedestroy($destination);
        dashboard_json(['error' => 'Unable to create image storage'], 500);
    }
    $filename = bin2hex(random_bytes(16)) . '.webp';
    $path = $directory . DIRECTORY_SEPARATOR . $filename;
    if (!imagewebp($destination, $path, 86)) {
        imagedestroy($destination);
        dashboard_json(['error' => 'Unable to save processed image'], 500);
    }
    imagedestroy($destination);
    @chmod($path, 0644);
    return ['url' => '/portfolio-admin/uploads/portfolio/' . $filename, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
}

function portfolio_mutation(PDO $pdo, callable $callback) {
    dashboard_require_admin($pdo);
    dashboard_require_csrf();
    try {
        portfolio_ensure_schema($pdo);
        $pdo->beginTransaction();
        $result = $callback();
        $pdo->commit();
        if (!isset($result['skip_publish']) || !$result['skip_publish']) {
            $published = isset($result['content']) && is_array($result['content'])
                ? portfolio_publish($pdo, $result['content'])
                : portfolio_publish($pdo);
            $result['manifest'] = $published;
        }
        unset($result['skip_publish'], $result['content']);
        dashboard_json($result);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Portfolio mutation failed: ' . $e->getMessage());
        dashboard_json(['error' => 'Portfolio update failed: ' . $e->getMessage()], 500);
    }
}

if ($action === 'manifest') {
    try {
        dashboard_json(portfolio_manifest($pdo));
    } catch (Throwable $e) {
        $fallback = portfolio_read_public_file();
        if ($fallback) {
            dashboard_json(portfolio_normalize_public_content($fallback));
        }
        error_log('Portfolio manifest failed: ' . $e->getMessage());
        dashboard_json(['error' => 'Unable to load portfolio manifest'], 500);
    }
}

if ($action === 'state') {
    dashboard_require_admin($pdo);
    try {
        portfolio_ensure_schema($pdo);
    } catch (Throwable $e) {
        error_log('Portfolio schema ensure failed: ' . $e->getMessage());
        $fallback = portfolio_read_public_file();
        dashboard_json([
            'error' => 'Portfolio storage is not ready. Import migrate.sql (or portfolio.sql) into the dashboard database, then reload this page.',
            'content' => portfolio_normalize_public_content($fallback ?: []),
            'csrf' => dashboard_csrf_token(),
            'storage_ready' => false,
        ], 503);
    }
    dashboard_json([
        'content' => portfolio_legacy_content($pdo),
        'csrf' => dashboard_csrf_token(),
        'storage_ready' => true,
    ]);
}

if ($action === 'csrf') {
    dashboard_require_admin($pdo);
    dashboard_json(['csrf_token' => dashboard_csrf_token()]);
}

if ($action === 'publish') {
    dashboard_require_method('POST');
    portfolio_mutation($pdo, function () use ($pdo) {
        return ['ok' => true];
    });
}

if ($action === 'save') {
    dashboard_require_method('POST');
    $body = portfolio_body();
    portfolio_mutation($pdo, function () use ($pdo, $body) {
        $content = $body['content'] ?? [];
        if (!is_array($content)) {
            throw new InvalidArgumentException('Portfolio content must be an object');
        }
        $normalized = portfolio_normalize_public_content($content);
        $contentJson = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $contentStmt = $pdo->prepare('INSERT INTO portfolio_content (id,content_json,updated_at) VALUES (1,?,NOW()) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json),updated_at=NOW()');
        $contentStmt->execute([$contentJson]);
        $siteImages = $normalized['images'] ?? [];
        $skills = $normalized['skills'] ?? [];
        $projects = $normalized['projects'] ?? [];
        $timeline = $normalized['about'] ?? [];

        $pdo->exec('DELETE FROM portfolio_project_screenshots');
        $pdo->exec('DELETE FROM portfolio_projects');
        $pdo->exec('DELETE FROM portfolio_site_images');
        $pdo->exec('DELETE FROM portfolio_skills');
        $pdo->exec('DELETE FROM portfolio_timeline_items');

        $imageStmt = $pdo->prepare('INSERT INTO portfolio_site_images (slot,image_url,alt_text,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        foreach (['hero', 'about'] as $index => $slot) {
            $url = portfolio_text($siteImages[$slot] ?? '', 512);
            if ($url !== '') {
                $imageStmt->execute([$slot, $url, '', $index]);
            }
        }

        $skillStmt = $pdo->prepare('INSERT INTO portfolio_skills (category,name,icon,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        foreach ((array) $skills as $index => $skill) {
            if (!is_array($skill)) continue;
            $name = portfolio_text($skill['name'] ?? '', 191);
            if ($name !== '') $skillStmt->execute(['', $name, portfolio_text($skill['img'] ?? '', 512), $index]);
        }

        $projectStmt = $pdo->prepare('INSERT INTO portfolio_projects (title,slug,description,url,repository_url,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())');
        $shotStmt = $pdo->prepare('INSERT INTO portfolio_project_screenshots (project_id,image_url,alt_text,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        foreach ((array) $projects as $index => $project) {
            if (!is_array($project)) continue;
            $title = portfolio_text($project['name'] ?? '', 191);
            if ($title === '') continue;
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
            $projectStmt->execute([$title, trim($slug, '-'), portfolio_text($project['desc'] ?? '', 5000), portfolio_text($project['demoUrl'] ?? '', 512), '', $index]);
            $projectId = (int) $pdo->lastInsertId();
            foreach ((array) ($project['images'] ?? []) as $shotIndex => $url) {
                if (is_string($url) && $url !== '') $shotStmt->execute([$projectId, portfolio_text($url, 512), '', $shotIndex]);
            }
        }

        $timelineStmt = $pdo->prepare('INSERT INTO portfolio_timeline_items (period,title,description,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        foreach ((array) $timeline as $index => $item) {
            if (!is_array($item)) continue;
            $title = portfolio_text($item['title'] ?? '', 191);
            $description = portfolio_text($item['text'] ?? '', 5000);
            if ($title !== '' && $description !== '') $timelineStmt->execute(['', $title, $description, $index]);
        }
        return ['ok' => true, 'content' => $normalized];
    });
}

$resources = [
    'site_images' => ['table' => 'portfolio_site_images', 'columns' => 'id,slot,image_url,alt_text,crop_x,crop_y,crop_width,crop_height,sort_order,updated_at', 'fields' => ['slot', 'image_url', 'alt_text', 'sort_order']],
    'skills' => ['table' => 'portfolio_skills', 'columns' => 'id,category,name,icon,sort_order,updated_at', 'fields' => ['category', 'name', 'icon', 'sort_order']],
    'projects' => ['table' => 'portfolio_projects', 'columns' => 'id,title,slug,description,url,repository_url,sort_order,updated_at', 'fields' => ['title', 'slug', 'description', 'url', 'repository_url', 'sort_order']],
    'timeline' => ['table' => 'portfolio_timeline_items', 'columns' => 'id,period,title,description,sort_order,updated_at', 'fields' => ['period', 'title', 'description', 'sort_order']],
];

if (isset($resources[$action])) {
    $resource = $resources[$action];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = $pdo->query('SELECT ' . $resource['columns'] . ' FROM ' . $resource['table'] . ' ORDER BY sort_order,id')->fetchAll();
        dashboard_json([$action => $rows]);
    }
    dashboard_require_method('POST');
    $body = portfolio_body();
    portfolio_mutation($pdo, function () use ($pdo, $resource, $body) {
        $operation = $body['operation'] ?? 'create';
        if ($operation === 'delete') {
            $id = portfolio_id($body['id'] ?? 0);
            $old = null;
            if ($resource['table'] === 'portfolio_site_images') {
                $stmt = $pdo->prepare('SELECT image_url FROM ' . $resource['table'] . ' WHERE id = ?');
                $stmt->execute([$id]);
                $old = $stmt->fetchColumn();
            }
            $stmt = $pdo->prepare('DELETE FROM ' . $resource['table'] . ' WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) {
                throw new InvalidArgumentException('Record not found');
            }
            portfolio_delete_file($old);
            return ['ok' => true, 'id' => $id];
        }
        $values = [];
        foreach ($resource['fields'] as $field) {
            $value = $body[$field] ?? ($field === 'sort_order' ? 0 : '');
            $values[] = $field === 'sort_order' ? (int)$value : portfolio_text($value, $field === 'description' ? 5000 : 512);
        }
        if ($operation === 'update') {
            $id = portfolio_id($body['id'] ?? 0);
            $set = implode(' = ?, ', $resource['fields']) . ' = ?';
            $stmt = $pdo->prepare('UPDATE ' . $resource['table'] . ' SET ' . $set . ', updated_at = NOW() WHERE id = ?');
            $stmt->execute(array_merge($values, [$id]));
            return ['ok' => true, 'id' => $id];
        }
        $fields = implode(',', $resource['fields']);
        $stmt = $pdo->prepare('INSERT INTO ' . $resource['table'] . ' (' . $fields . ',created_at,updated_at) VALUES (' . implode(',', array_fill(0, count($resource['fields']), '?')) . ',NOW(),NOW())');
        $stmt->execute($values);
        return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
    });
}

if ($action === 'project_screenshots') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $projectId = portfolio_id($_GET['project_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id,project_id,image_url,alt_text,sort_order,updated_at FROM portfolio_project_screenshots WHERE project_id = ? ORDER BY sort_order,id');
        $stmt->execute([$projectId]);
        dashboard_json(['screenshots' => $stmt->fetchAll()]);
    }
    dashboard_require_method('POST');
    $body = portfolio_body();
    portfolio_mutation($pdo, function () use ($pdo, $body) {
        $operation = $body['operation'] ?? 'create';
        if ($operation === 'delete') {
            $id = portfolio_id($body['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT image_url FROM portfolio_project_screenshots WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetchColumn();
            $stmt = $pdo->prepare('DELETE FROM portfolio_project_screenshots WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) {
                throw new InvalidArgumentException('Screenshot not found');
            }
            portfolio_delete_file($old);
            return ['ok' => true, 'id' => $id];
        }
        $projectId = portfolio_id($body['project_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id FROM portfolio_projects WHERE id = ?');
        $stmt->execute([$projectId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('Project not found');
        }
        $stmt = $pdo->prepare('INSERT INTO portfolio_project_screenshots (project_id,image_url,alt_text,sort_order,created_at,updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$projectId, '', portfolio_text($body['alt_text'] ?? '', 255), (int)($body['sort_order'] ?? 0)]);
        return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
    });
}

if ($action === 'upload') {
    dashboard_require_method('POST');
    dashboard_require_admin($pdo);
    dashboard_require_csrf();
    try {
        portfolio_ensure_schema($pdo);
    } catch (Throwable $e) {
        dashboard_json(['error' => 'Unable to prepare portfolio storage: ' . $e->getMessage()], 500);
    }
    $legacyUpload = empty($_POST['id']) && empty($_POST['kind']);
    $kind = ($_POST['kind'] ?? '') === 'project_screenshot' ? 'project_screenshot' : 'site_image';
    if ($legacyUpload) {
        $file = portfolio_upload($kind, 0, []);
        dashboard_json(['ok' => true, 'path' => $file['url'], 'width' => $file['width'] ?? 0, 'height' => $file['height'] ?? 0]);
    }
    $id = portfolio_id($_POST['id'] ?? 0);
    $crop = ['x' => $_POST['crop_x'] ?? 0, 'y' => $_POST['crop_y'] ?? 0, 'width' => $_POST['crop_width'] ?? 0, 'height' => $_POST['crop_height'] ?? 0];
    $newFile = null;
    try {
        $pdo->beginTransaction();
        $newFile = portfolio_upload($kind, $id, $crop);
        if ($kind === 'site_image') {
            $stmt = $pdo->prepare('SELECT image_url FROM portfolio_site_images WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetchColumn();
            $stmt = $pdo->prepare('UPDATE portfolio_site_images SET image_url=?,crop_x=?,crop_y=?,crop_width=?,crop_height=?,updated_at=NOW() WHERE id=?');
            $stmt->execute([$newFile['url'], $newFile['x'], $newFile['y'], $newFile['width'], $newFile['height'], $id]);
        } else {
            $stmt = $pdo->prepare('SELECT image_url FROM portfolio_project_screenshots WHERE id = ?');
            $stmt->execute([$id]);
            $old = $stmt->fetchColumn();
            $stmt = $pdo->prepare('UPDATE portfolio_project_screenshots SET image_url=?,updated_at=NOW() WHERE id=?');
            $stmt->execute([$newFile['url'], $id]);
        }
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('Image record not found');
        }
        $pdo->commit();
        portfolio_publish($pdo);
        portfolio_delete_file($old);
        dashboard_json(['ok' => true, 'image_url' => $newFile['url']]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($newFile) {
            portfolio_delete_file($newFile['url']);
        }
        error_log('Portfolio image upload failed: ' . $e->getMessage());
        dashboard_json(['error' => 'Image upload failed: ' . $e->getMessage()], 500);
    }
}

dashboard_json(['error' => 'Unknown action'], 404);
?>
