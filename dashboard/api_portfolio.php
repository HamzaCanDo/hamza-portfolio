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
    if (!is_string($url) || strpos($url, '/dashboard/uploads/portfolio/') !== 0) {
        return null;
    }
    $name = basename($url);
    return preg_match('/^[a-f0-9]{32}\.webp$/', $name) ? $name : null;
}

function portfolio_delete_file($url) {
    $name = portfolio_file_url($url);
    if ($name) {
        @unlink(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'portfolio' . DIRECTORY_SEPARATOR . $name);
    }
}

function portfolio_manifest(PDO $pdo) {
    $images = $pdo->query('SELECT id,slot,image_url,alt_text,crop_x,crop_y,crop_width,crop_height,sort_order,updated_at FROM portfolio_site_images ORDER BY sort_order,id')->fetchAll();
    $skills = $pdo->query('SELECT id,category,name,icon,sort_order,updated_at FROM portfolio_skills ORDER BY sort_order,id')->fetchAll();
    $projects = $pdo->query('SELECT id,title,slug,description,url,repository_url,sort_order,updated_at FROM portfolio_projects ORDER BY sort_order,id')->fetchAll();
    $shots = $pdo->query('SELECT id,project_id,image_url,alt_text,sort_order,updated_at FROM portfolio_project_screenshots ORDER BY project_id,sort_order,id')->fetchAll();
    $timeline = $pdo->query('SELECT id,period,title,description,sort_order,updated_at FROM portfolio_timeline_items ORDER BY sort_order,id')->fetchAll();
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
    return [
        'images' => $imageMap,
        'skills' => $manifestSkills,
        'projects' => $manifestProjects,
        'about' => $manifestAbout,
    ];
}

function portfolio_legacy_content(PDO $pdo) {
    $stmt = $pdo->query('SELECT content_json FROM portfolio_content WHERE id = 1 LIMIT 1');
    $json = $stmt->fetchColumn();
    if ($json) {
        $content = json_decode((string)$json, true);
        if (is_array($content)) {
            return $content;
        }
    }
    $fallback = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'portfolio-content.json';
    if (is_readable($fallback)) {
        $content = json_decode((string) file_get_contents($fallback), true);
        if (is_array($content)) {
            return $content;
        }
    }
    return ['images' => ['hero' => '', 'about' => ''], 'skills' => [], 'projects' => [], 'about' => []];
}

function portfolio_publish(PDO $pdo) {
    $target = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'portfolio-content.json';
    $temp = tempnam(dirname($target), '.portfolio-content-');
    if ($temp === false) {
        throw new RuntimeException('Unable to create manifest temporary file');
    }
    $json = json_encode(portfolio_manifest($pdo), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($temp, $json . PHP_EOL, LOCK_EX) === false || !rename($temp, $target)) {
        @unlink($temp);
        throw new RuntimeException('Unable to publish portfolio manifest');
    }
    @chmod($target, 0644);
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
    if ($mime === '' && in_array($extension, ['jpg', 'jpeg'], true)) $mime = 'image/jpeg';
    if ($mime === 'image/jpg' || $mime === 'image/pjpeg') $mime = 'image/jpeg';
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

    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'portfolio';
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
    @chmod($path, 0640);
    return ['url' => '/dashboard/uploads/portfolio/' . $filename, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];
}

function portfolio_mutation(PDO $pdo, callable $callback) {
    dashboard_require_admin($pdo);
    dashboard_require_csrf();
    try {
        $pdo->beginTransaction();
        $result = $callback();
        portfolio_publish($pdo);
        $pdo->commit();
        dashboard_json($result);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Portfolio mutation failed: ' . $e->getMessage());
        dashboard_json(['error' => 'Portfolio update failed'], 500);
    }
}

if ($action === 'manifest') {
    dashboard_json(portfolio_manifest($pdo));
}

if ($action === 'state') {
    dashboard_require_admin($pdo);
    dashboard_json(['content' => portfolio_legacy_content($pdo), 'csrf' => dashboard_csrf_token()]);
}

if ($action === 'csrf') {
    dashboard_require_admin($pdo);
    dashboard_json(['csrf_token' => dashboard_csrf_token()]);
}

if ($action === 'publish') {
    dashboard_require_method('POST');
    portfolio_mutation($pdo, function () use ($pdo) {
        return ['ok' => true, 'manifest' => portfolio_manifest($pdo)];
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
        $contentJson = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $contentStmt = $pdo->prepare('INSERT INTO portfolio_content (id,content_json,updated_at) VALUES (1,?,NOW()) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json),updated_at=NOW()');
        $contentStmt->execute([$contentJson]);
        $siteImages = $content['site_images'] ?? $content['images'] ?? [];
        $skills = $content['skills'] ?? [];
        $projects = $content['projects'] ?? [];
        $timeline = $content['timeline'] ?? $content['about'] ?? [];

        $pdo->exec('DELETE FROM portfolio_project_screenshots');
        $pdo->exec('DELETE FROM portfolio_projects');
        $pdo->exec('DELETE FROM portfolio_site_images');
        $pdo->exec('DELETE FROM portfolio_skills');
        $pdo->exec('DELETE FROM portfolio_timeline_items');

        $imageStmt = $pdo->prepare('INSERT INTO portfolio_site_images (slot,image_url,alt_text,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        if (isset($siteImages['hero'])) $siteImages = [['slot' => 'hero', 'image_url' => $siteImages['hero']]];
        if (isset($siteImages['about'])) $siteImages[] = ['slot' => 'about', 'image_url' => $siteImages['about']];
        foreach ((array) $siteImages as $index => $image) {
            if (!is_array($image)) continue;
            $slot = portfolio_text($image['slot'] ?? '', 64);
            $url = portfolio_text($image['image_url'] ?? '', 512);
            if ($slot !== '' && $url !== '') $imageStmt->execute([$slot, $url, portfolio_text($image['alt_text'] ?? '', 255), (int) ($image['sort_order'] ?? $index)]);
        }

        $skillStmt = $pdo->prepare('INSERT INTO portfolio_skills (category,name,icon,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        foreach ((array) $skills as $index => $skill) {
            if (!is_array($skill)) continue;
            $name = portfolio_text($skill['name'] ?? '', 191);
            $icon = $skill['icon'] ?? $skill['img'] ?? '';
            if ($name !== '') $skillStmt->execute([portfolio_text($skill['category'] ?? '', 64), $name, portfolio_text($icon, 512), (int) ($skill['sort_order'] ?? $index)]);
        }

        $projectStmt = $pdo->prepare('INSERT INTO portfolio_projects (title,slug,description,url,repository_url,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,NOW(),NOW())');
        $shotStmt = $pdo->prepare('INSERT INTO portfolio_project_screenshots (project_id,image_url,alt_text,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        foreach ((array) $projects as $index => $project) {
            if (!is_array($project)) continue;
            $title = portfolio_text($project['title'] ?? $project['name'] ?? '', 191);
            if ($title === '') continue;
            $slug = portfolio_text($project['slug'] ?? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)), 191);
            $projectStmt->execute([$title, trim($slug, '-'), portfolio_text($project['description'] ?? $project['desc'] ?? '', 5000), portfolio_text($project['url'] ?? $project['demoUrl'] ?? '', 512), portfolio_text($project['repository_url'] ?? '', 512), (int) ($project['sort_order'] ?? $index)]);
            $projectId = (int) $pdo->lastInsertId();
            $shots = $project['screenshots'] ?? [];
            if (isset($project['images'])) $shots = array_map(static fn($url) => ['image_url' => $url], (array) $project['images']);
            foreach ((array) $shots as $shotIndex => $shot) {
                $url = is_array($shot) ? ($shot['image_url'] ?? '') : $shot;
                if (is_string($url) && $url !== '') $shotStmt->execute([$projectId, portfolio_text($url, 512), is_array($shot) ? portfolio_text($shot['alt_text'] ?? '', 255) : '', $shotIndex]);
            }
        }

        $timelineStmt = $pdo->prepare('INSERT INTO portfolio_timeline_items (period,title,description,sort_order,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
        foreach ((array) $timeline as $index => $item) {
            if (!is_array($item)) continue;
            $title = portfolio_text($item['title'] ?? '', 191);
            $description = portfolio_text($item['description'] ?? $item['text'] ?? '', 5000);
            if ($title !== '' && $description !== '') $timelineStmt->execute([portfolio_text($item['period'] ?? '', 191), $title, $description, (int) ($item['sort_order'] ?? $index)]);
        }
        return ['ok' => true, 'manifest' => portfolio_manifest($pdo)];
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
        portfolio_publish($pdo);
        $pdo->commit();
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
