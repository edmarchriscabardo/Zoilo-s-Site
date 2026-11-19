<?php
declare(strict_types=1);

/**
 * Generic CRUD helpers driven by config/entities.php.
 * Identifiers come from that trusted config; every VALUE is a bound parameter.
 */

function entities(): array
{
    static $all = null;
    if ($all === null) {
        $all = require __DIR__ . '/../config/entities.php';
    }
    return $all;
}

function entity_or_redirect(string $key): array
{
    $all = entities();
    if (!isset($all[$key])) {
        flash('error', 'Unknown record type.');
        redirect('public/dashboard.php');
    }
    $ent = $all[$key];
    $ent['key'] = $key;
    return $ent;
}

function fk_options(PDO $pdo, array $fk): array
{
    $sql = sprintf(
        'SELECT `%s` AS id, `%s` AS label FROM `%s` ORDER BY `%s`',
        $fk['key'], $fk['label'], $fk['table'], $fk['label']
    );
    return $pdo->query($sql)->fetchAll();
}

function crud_find(PDO $pdo, array $ent, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM `{$ent['table']}` WHERE `{$ent['pk']}` = :id");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** @return array{rows: array, total: int, page: int, pages: int} */
function crud_list(PDO $pdo, array $ent, string $q, int $page, int $perPage = 15): array
{
    $select = ['t.*'];
    $joins = [];
    $i = 0;
    foreach ($ent['fields'] as $name => $f) {
        if ($f['type'] === 'select') {
            $alias = 'j' . $i++;
            $fk = $f['fk'];
            $select[] = "`{$alias}`.`{$fk['label']}` AS `{$name}__label`";
            $joins[] = "LEFT JOIN `{$fk['table']}` `{$alias}` ON `{$alias}`.`{$fk['key']}` = t.`{$name}`";
        }
    }

    $where = '';
    $params = [];
    if ($q !== '' && !empty($ent['search'])) {
        $parts = [];
        foreach (array_values($ent['search']) as $n => $col) {
            $parts[] = "t.`{$col}` LIKE :q{$n}";
            $params["q{$n}"] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $where = 'WHERE (' . implode(' OR ', $parts) . ')';
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$ent['table']}` t {$where}");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, $page), $pages);

    $sql = 'SELECT ' . implode(', ', $select)
        . " FROM `{$ent['table']}` t " . implode(' ', $joins)
        . " {$where} ORDER BY t.`{$ent['order']}` ASC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();

    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
}

/**
 * Validate submitted values against the entity definition.
 * @return array{0: array, 1: array} [cleanValues, errors]
 */
function crud_validate(PDO $pdo, array $ent, array $input, ?int $id): array
{
    $clean = [];
    $errors = [];

    foreach ($ent['fields'] as $name => $f) {
        $label = $f['label'];
        $raw = $input[$name] ?? '';
        $raw = is_string($raw) ? trim($raw) : '';
        $failed = false;

        if ($raw === '') {
            if (!empty($f['required'])) {
                $errors[] = "{$label} is required.";
            } else {
                $clean[$name] = null;
            }
            continue;
        }

        switch ($f['type']) {
            case 'text':
            case 'email':
                if (mb_strlen($raw) > $f['max']) {
                    $errors[] = "{$label} must be {$f['max']} characters or fewer.";
                    $failed = true;
                } elseif ($f['type'] === 'email' && !filter_var($raw, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "{$label} must be a valid email address.";
                    $failed = true;
                } elseif (!empty($f['pattern']) && !preg_match($f['pattern'], $raw)) {
                    $errors[] = $f['pattern_message'] ?? "{$label} has an invalid format.";
                    $failed = true;
                }
                $clean[$name] = $raw;
                break;

            case 'money':
                $m = normalize_money($raw);
                if ($m === null) {
                    $errors[] = "{$label} must be a positive amount with up to 2 decimal places.";
                    $failed = true;
                } else {
                    $clean[$name] = $m;
                }
                break;

            case 'int':
                $max = $f['max'] ?? 1000000;
                if (!ctype_digit($raw) || (int)$raw > $max) {
                    $errors[] = "{$label} must be a whole number from 0 to {$max}.";
                    $failed = true;
                } else {
                    $clean[$name] = (int)$raw;
                }
                break;

            case 'photo':
                if (!in_array($raw, product_photos(), true)) {
                    $errors[] = "Please choose a valid photo for {$label}.";
                    $failed = true;
                } else {
                    $clean[$name] = $raw;
                }
                break;

            case 'select':
                $fk = $f['fk'];
                if (!ctype_digit($raw) || !row_exists($pdo, $fk['table'], $fk['key'], (int)$raw)) {
                    $errors[] = "Please choose a valid option for {$label}.";
                    $failed = true;
                } else {
                    $clean[$name] = (int)$raw;
                }
                break;
        }

        if (!$failed && !empty($f['unique'])) {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `{$ent['table']}` WHERE `{$name}` = :v AND `{$ent['pk']}` <> :id"
            );
            $stmt->execute(['v' => $raw, 'id' => $id ?? 0]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = "That {$label} is already in use.";
            }
        }
    }

    return [$clean, $errors];
}

/** Insert (when $id is null) or update. Returns the record id. */
function crud_save(PDO $pdo, array $ent, array $clean, ?int $id): int
{
    $cols = array_keys($clean);
    if ($id === null) {
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $ent['table'],
            implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
            implode(', ', array_map(fn($c) => ":{$c}", $cols))
        );
        $pdo->prepare($sql)->execute($clean);
        return (int)$pdo->lastInsertId();
    }

    $sets = implode(', ', array_map(fn($c) => "`{$c}` = :{$c}", $cols));
    $sql = "UPDATE `{$ent['table']}` SET {$sets} WHERE `{$ent['pk']}` = :__id";
    $pdo->prepare($sql)->execute($clean + ['__id' => $id]);
    return $id;
}

/** Render one list cell (already HTML-escaped). */
function crud_cell(array $ent, array $row, string $name): string
{
    $f = $ent['fields'][$name];
    $value = $row[$name] ?? null;
    if ($f['type'] === 'select') {
        $value = $row[$name . '__label'] ?? null;
    }
    if ($value === null || $value === '') {
        if ($f['type'] === 'photo') {
            return '<img class="thumb" src="' . e(product_image_url(null)) . '" alt="No photo" width="48" height="48" loading="lazy">';
        }
        return '<span class="muted">&mdash;</span>';
    }
    if ($f['type'] === 'photo') {
        return '<img class="thumb" src="' . e(product_image_url((string)$value)) . '" alt="" width="48" height="48" loading="lazy">';
    }
    return $f['type'] === 'money' ? e(money($value)) : e($value);
}
