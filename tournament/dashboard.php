<?php
// Cache-busting: appends each file's last-modified time as a version
// query string, so browsers always fetch the latest CSS/JS after a
// deploy instead of serving a stale cached copy.
function assetVer(string $relPath): string {
    $full = __DIR__ . '/' . $relPath;
    return $relPath . '?v=' . (file_exists($full) ? filemtime($full) : time());
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Toernooi Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo assetVer('assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body>

<div class="tv-header d-flex justify-content-between align-items-center">
    <div class="tv-title">🏆 Wijkcentrum Toernooi</div>
    <div class="text-end">
        <div class="round-badge" id="roundNumber">—</div>
        <div class="progress-sub" id="roundProgress"></div>
    </div>
</div>

<div class="text-center py-3">
    <div class="timer-display timer-paused" id="timer">--:--</div>
    <div class="time-left-estimate" id="timeLeftEstimate"></div>
    <div class="fs-4 text-warning" id="statusBanner"></div>
</div>

<div class="px-4">
    <div id="idleBox"></div>
</div>

<div class="container-fluid px-4 pb-4">
    <div class="row">
        <div class="col-lg-8">
            <div class="section-title">Wedstrijden deze ronde <span class="tap-hint">— tik op een wedstrijd om de uitslag in te voeren</span></div>
            <div id="matchesContainer" class="row row-cols-1 row-cols-lg-2 g-3"></div>
            <div id="previewBox"></div>
        </div>
        <div class="col-lg-4">
            <div class="section-title">Klassement</div>
            <div class="leaderboard-panel">
                <table class="table table-borderless mb-0">
                    <thead>
                        <tr><th>#</th><th>Naam</th><th>Punten</th><th>Winst</th></tr>
                    </thead>
                    <tbody id="leaderboardBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Volledig scherm scoreformulier: opent door op een "Bezig…" wedstrijd te tikken -->
<div id="resultOverlay" class="result-overlay d-none">
    <div class="result-overlay-content" id="resultOverlayContent"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo assetVer('assets/js/dashboard.js'); ?>"></script>
</body>
</html>
