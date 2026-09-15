<?php
/**
 * FoodBridge - Shared footer + closing </body></html>
 * ======================================================
 * Included at the bottom of nearly every page. One compact footer works
 * everywhere - marketing pages and dashboards alike - so it never has to
 * be swapped out per section.
 */
?>
    <footer class="fb-footer py-3">
      <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2 small">
        <div class="fb-footer-brand">🌉 <?= e(SITE_NAME) ?></div>
        <div class="text-center">&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?> &middot; Reducing food waste, together.</div>
        <div class="d-flex gap-3">
          <a href="<?= e(BASE_URL) ?>index.php">Home</a>
          <a href="<?= e(BASE_URL) ?>about.php">About</a>
          <a href="<?= e(BASE_URL) ?>how-it-works.php">How It Works</a>
        </div>
      </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(BASE_URL) ?>js/script.js?v=<?= filemtime(__DIR__ . '/../js/script.js') ?>"></script>
</body>
</html>
