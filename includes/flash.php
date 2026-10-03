<?php
/**
 * Renders (and clears) any pending flash message. Include this once,
 * right after includes/header.php, on every page.
 */
$flash = get_flash();
if ($flash):
    $iconMap = [
        'success' => 'bi-check-circle',
        'error'   => 'bi-exclamation-triangle',
        'info'    => 'bi-info-circle',
    ];
    $icon = $iconMap[$flash['type']] ?? 'bi-info-circle';
?>
<div class="flash-toast flash-<?= e($flash['type']) ?> glass" role="alert">
  <i class="bi <?= $icon ?>"></i>
  <span><?= e($flash['message']) ?></span>
</div>
<?php endif; ?>
