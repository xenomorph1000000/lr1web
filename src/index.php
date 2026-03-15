<?php

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lc(string $value): string {
    if (function_exists('mb_strtolower')) {
        return (string)mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

function get_str(string $key, string $default = ''): string {
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function post_str(string $key, string $default = ''): string {
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function ensure_data_dir(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

function csv_append_row(string $file, array $header, array $row, string $delimiter = ';'): void {
    $is_new = !file_exists($file) || filesize($file) === 0;

    $fp = fopen($file, 'ab');
    if ($fp === false) {
        throw new RuntimeException('Cannot open CSV for writing.');
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            throw new RuntimeException('Cannot lock CSV.');
        }

        if ($is_new) {
            fputcsv($fp, $header, $delimiter);
        }

        fputcsv($fp, $row, $delimiter);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function csv_read_all(string $file, string $delimiter = ';'): array {
    if (!file_exists($file) || filesize($file) === 0) {
        return ['header' => [], 'rows' => []];
    }

    $fp = fopen($file, 'rb');
    if ($fp === false) {
        return ['header' => [], 'rows' => []];
    }

    $header = [];
    $rows = [];

    try {
        $first = fgetcsv($fp, 0, $delimiter);
        if (is_array($first)) {
            $header = $first;
        }

        while (($line = fgetcsv($fp, 0, $delimiter)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }
            $rows[] = $line;
        }
    } finally {
        fclose($fp);
    }

    return ['header' => $header, 'rows' => $rows];
}

$data_dir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
ensure_data_dir($data_dir);

$entities = [
    'trainers' => [
        'label' => 'Тренеры',
        'file' => $data_dir . DIRECTORY_SEPARATOR . 'trainers.csv',
        'fields' => [
            'name' => ['label' => 'ФИО', 'type' => 'text', 'required' => true, 'placeholder' => 'Иванов Иван Иванович'],
            'specialization' => ['label' => 'Специализация', 'type' => 'text', 'required' => true, 'placeholder' => 'Силовой тренинг'],
            'experience_years' => ['label' => 'Стаж (лет)', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 60, 'placeholder' => '5'],
            'phone' => ['label' => 'Телефон', 'type' => 'tel', 'required' => false, 'placeholder' => '+7...'],
        ],
    ],
    'workouts' => [
        'label' => 'Расписание',
        'file' => $data_dir . DIRECTORY_SEPARATOR . 'workouts.csv',
        'fields' => [
            'title' => ['label' => 'Тренировка', 'type' => 'text', 'required' => true, 'placeholder' => 'Функциональная'],
            'trainer_name' => ['label' => 'Тренер', 'type' => 'text', 'required' => true, 'placeholder' => 'Иванов И.И.'],
            'date' => ['label' => 'Дата', 'type' => 'date', 'required' => true],
            'duration_min' => ['label' => 'Длительность (мин)', 'type' => 'number', 'required' => true, 'min' => 10, 'max' => 300, 'placeholder' => '60'],
            'price_rub' => ['label' => 'Цена (руб)', 'type' => 'number', 'required' => false, 'min' => 0, 'max' => 100000, 'placeholder' => '1500'],
        ],
    ],
    'clients' => [
        'label' => 'Заявки',
        'file' => $data_dir . DIRECTORY_SEPARATOR . 'clients.csv',
        'fields' => [
            'name' => ['label' => 'ФИО', 'type' => 'text', 'required' => true, 'placeholder' => 'Петров Пётр'],
            'phone' => ['label' => 'Телефон', 'type' => 'tel', 'required' => true, 'placeholder' => '+7...'],
            'email' => ['label' => 'Email', 'type' => 'email', 'required' => false, 'placeholder' => 'petr@example.com'],
            'membership' => ['label' => 'Абонемент', 'type' => 'select', 'required' => true, 'options' => ['Разовый', 'Месяц', '3 месяца', 'Год']],
            'preferred_trainer' => ['label' => 'Желаемый тренер', 'type' => 'text', 'required' => false, 'placeholder' => 'Любой'],
        ],
    ],
];

$entity_key = get_str('entity', 'trainers');
if (!isset($entities[$entity_key])) {
    $entity_key = 'trainers';
}

$ok = get_str('ok', '') === '1';
$errors = [];
$values = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entity_key = post_str('entity', $entity_key);
    if (!isset($entities[$entity_key])) {
        $errors[] = 'Неизвестный тип данных.';
    } else {
        $entity = $entities[$entity_key];

        foreach ($entity['fields'] as $field_key => $meta) {
            $values[$field_key] = post_str($field_key, '');
            if (($meta['required'] ?? false) && $values[$field_key] === '') {
                $errors[] = 'Поле "' . $meta['label'] . '" обязательно.';
            }
        }

        if ($entity_key === 'clients' && ($values['email'] ?? '') !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email.';
        }

        if ($entity_key === 'workouts' && ($values['date'] ?? '') !== '') {
            $d = DateTime::createFromFormat('Y-m-d', $values['date']);
            if (!$d) {
                $errors[] = 'Некорректная дата.';
            }
        }

        if (!$errors) {
            $created_at = (new DateTime('now'))->format('Y-m-d H:i:s');
            $header = array_merge(['created_at'], array_keys($entity['fields']));
            $row = [$created_at];
            foreach (array_keys($entity['fields']) as $field_key) {
                $row[] = $values[$field_key] ?? '';
            }

            try {
                csv_append_row($entity['file'], $header, $row);
                header('Location: ?entity=' . rawurlencode($entity_key) . '&ok=1');
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Не удалось сохранить в CSV: ' . $e->getMessage();
            }
        }
    }
}

$active = $entities[$entity_key];
$q = get_str('q', '');
$download = get_str('download', '') === '1';

if ($download) {
    $filename = $entity_key . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    if (file_exists($active['file'])) {
        readfile($active['file']);
    }
    exit;
}

$list_data = csv_read_all($active['file']);
$header = $list_data['header'] ?? [];
$rows = $list_data['rows'] ?? [];

if ($q !== '' && $rows) {
    $q_lc = lc($q);
    $rows = array_values(array_filter($rows, function (array $r) use ($q_lc): bool {
        foreach ($r as $cell) {
            $cell_lc = lc((string)$cell);
            if (str_contains($cell_lc, $q_lc)) {
                return true;
            }
        }
        return false;
    }));
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Фитнес-клуб</title>
</head>
<body>
  <h1>Фитнес-клуб</h1>

  <h2>Получить</h2>
  <form method="get" action="">
    <label>
      Выбрать:
      <select name="entity">
        <?php foreach ($entities as $k => $e): ?>
          <option value="<?= h($k) ?>" <?= $k === $entity_key ? 'selected' : '' ?>><?= h($e['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit">Открыть</button>
  </form>

  <hr />

  <h2>Добавить запись (<?= h($active['label']) ?>)</h2>

  <?php if ($ok): ?>
    <p>OK: сохранено в CSV.</p>
  <?php endif; ?>

  <?php if ($errors): ?>
    <p>Ошибки:</p>
    <ul>
      <?php foreach ($errors as $err): ?>
        <li><?= h($err) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <form method="post" action="?entity=<?= h($entity_key) ?>">
    <input type="hidden" name="entity" value="<?= h($entity_key) ?>" />

    <?php foreach ($active['fields'] as $field_key => $meta): ?>
      <p>
        <label for="<?= h($field_key) ?>"><?= h($meta['label']) ?><?= ($meta['required'] ?? false) ? ' *' : '' ?></label><br/>
        <?php if (($meta['type'] ?? 'text') === 'select'): ?>
          <?php
            $selected = (string)($values[$field_key] ?? '');
            $options = $meta['options'] ?? [];
          ?>
          <select id="<?= h($field_key) ?>" name="<?= h($field_key) ?>" <?= ($meta['required'] ?? false) ? 'required' : '' ?>>
            <option value="" <?= $selected === '' ? 'selected' : '' ?>>Выберите...</option>
            <?php foreach ($options as $opt): ?>
              <option value="<?= h((string)$opt) ?>" <?= $selected === (string)$opt ? 'selected' : '' ?>><?= h((string)$opt) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <?php
            $type = $meta['type'] ?? 'text';
            $val = (string)($values[$field_key] ?? '');
            $ph = (string)($meta['placeholder'] ?? '');
            $min = isset($meta['min']) ? 'min="' . h((string)$meta['min']) . '"' : '';
            $max = isset($meta['max']) ? 'max="' . h((string)$meta['max']) . '"' : '';
          ?>
          <input
            id="<?= h($field_key) ?>"
            name="<?= h($field_key) ?>"
            type="<?= h((string)$type) ?>"
            value="<?= h($val) ?>"
            placeholder="<?= h($ph) ?>"
            <?= ($meta['required'] ?? false) ? 'required' : '' ?>
            <?= $min ?>
            <?= $max ?>
          />
        <?php endif; ?>
      </p>
    <?php endforeach; ?>

    <button type="submit">Сохранить</button>
  </form>

  <hr />

  <h2>Поиск (<?= h($active['label']) ?>)</h2>
  <form method="get" action="">
    <input type="hidden" name="entity" value="<?= h($entity_key) ?>" />
    <label for="q">Поиск:</label>
    <input id="q" name="q" type="text" value="<?= h($q) ?>" placeholder="по всем полям" />
    <button type="submit">Найти</button>
    <a href="?entity=<?= h($entity_key) ?>">Сбросить</a>
    <a href="?entity=<?= h($entity_key) ?>&download=1">Скачать CSV</a>
  </form>

  <?php if (!$header): ?>
    <p>Пока нет данных.</p>
  <?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
      <thead>
        <tr>
          <?php foreach ($header as $col): ?>
            <th><?= h((string)$col) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="<?= (int)max(1, count($header)) ?>">Ничего не найдено.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <?php foreach ($header as $i => $_): ?>
                <td><?= h((string)($r[$i] ?? '')) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>