<?php
/**
 * Plain layout without the menu: sign-in and sign-up, invitations, error
 * pages, the public timetable and the print view. Has its own theme toggle,
 * so visitors without an account can choose light or dark too.
 * @var string $content
 * Optional: $publicPage (true on /s/{slug}), $themePref (signed-in user's choice), $forceLight (print)
 */
use App\Core\Theme;

$pref = $themePref ?? null;
?>
<!doctype html>
<html lang="en"<?= $pref ? ' data-theme-pref="' . e($pref) . '"' : '' ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="<?= e($csrf->token()) ?>">
  <?php if ($pref): ?><meta name="theme-save-url" content="<?= e(\App\Core\Request::basePath() . '/api/theme.php') ?>"><?php endif; ?>
  <title><?= e($title !== '' ? $title . ' | ' : '') ?><?= e($platformName ?? 'Timetable') ?></title>
  <?= Theme::headScript() ?>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="plain">
<header class="plain-top">
  <a class="brand-link" href="<?= e(url('home')) ?>"><?= e($platformName ?? 'Timetable') ?> <span class="brand-tagline">class timetables made for you</span></a>
  <div class="plain-top-actions">
    <a class="btn btn-small btn-plain" href="<?= e(url('home')) ?>">Home</a>
    <a class="btn btn-small btn-plain" href="<?= e(url('about')) ?>">About</a>
    <a class="btn btn-small" href="<?= e(url('help')) ?>"><?= icon('help') ?> Help</a>
    <button type="button" class="icon-button" data-theme-toggle aria-label="Change theme">
      <span data-theme-icon="system"><?= icon('system') ?></span>
      <span data-theme-icon="light" hidden><?= icon('sun') ?></span>
      <span data-theme-icon="dark" hidden><?= icon('moon') ?></span>
      <span class="visually-hidden" data-theme-label>System</span>
    </button>
  </div>
</header>
<main id="main" class="plain-content">
  <?php foreach ($flashes as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($flash['message']) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
<footer class="plain-foot">
  <a href="<?= e(url('home')) ?>">Home</a> · <a href="<?= e(url('about')) ?>">About this app</a> · <a href="<?= e(url('help')) ?>">Help Centre</a> · <?= e($platformName ?? 'Timetable') ?>: class timetables made for you
</footer>
</body>
</html>
