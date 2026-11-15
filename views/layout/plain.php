<?php
/**
 * Plain layout without menu: sign-in, errors and the public timetable.
 * @var string $content
 */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
  <title><?= e($title !== '' ? $title . ' | ' : '') ?>Timetable</title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="plain">
<main id="main" class="plain-content">
  <?php foreach ($flashes as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
      <?= e($flash['message']) ?>
    </div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
