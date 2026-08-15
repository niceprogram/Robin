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

<!-- Small, semi-transparent controls, always rendered at true screen
     size (not scaled with #stage) so they stay a comfortable click
     target regardless of display mode. Each physical screen remembers
     its own choice (localStorage), so a second Pi/TV can be set up
     differently from the main one. -->
<div class="display-mode-bar">
    <button data-mode="tv" title="TV — groter, voor bekijken van afstand">📺 TV</button>
    <button data-mode="regular" title="Gewoon beeldscherm">🖥️ Monitor</button>
    <button data-mode="vertical" title="Verticaal gemonteerd scherm">📱 Verticaal</button>
    <span class="mode-bar-divider"></span>
    <button id="btnLeaderboardOnly" title="Dit scherm toont alleen het volledige klassement">🏆 Alleen klassement</button>
</div>

<!--
    The dashboard is drawn onto a fixed 1920x1080 "stage" which
    dashboard.js then scales (via CSS transform) to exactly fit whatever
    real screen/window it's displayed on — any TV resolution, any
    landscape aspect ratio, no scrolling, no overflow, no manual
    per-screen tweaking. See fitStage() in dashboard.js.
-->
<div id="stageOuter">
    <div id="stage">

        <div class="tv-header d-flex justify-content-between align-items-center">
            <div class="tv-title">🏆 Wijkcentrum Toernooi</div>
            <div class="timer-display timer-paused" id="timer">--:--</div>
            <div class="text-end">
                <div class="round-badge" id="roundNumber">—</div>
                <div class="progress-sub" id="roundProgress"></div>
                <div class="time-left-estimate" id="timeLeftEstimate"></div>
            </div>
        </div>

        <div class="text-center" id="statusBanner"></div>

        <div id="idleBox"></div>

        <div class="stage-main">
            <div class="stage-left">
                <div id="matchesContainer" class="matches-grid"></div>
                <div id="previewBox"></div>
            </div>
            <div class="stage-right">
                <div class="section-title">Klassement</div>
                <div class="leaderboard-panel">
                    <table class="table table-borderless mb-0">
                        <thead>
                            <tr><th>#</th><th>Naam</th><th>Punten</th><th>Winst</th></tr>
                        </thead>
                        <tbody id="leaderboardBody"></tbody>
                    </table>
                </div>
                <div class="section-title mt-2 d-flex justify-content-between align-items-center" id="commentaryTitle">
                    Live commentaar
                    <button id="btnToggleSpeech" class="btn btn-sm btn-outline-light" title="Commentaar hardop voorlezen aan/uit">🔇</button>
                </div>
                <div class="commentary-panel" id="commentaryFeed"></div>
            </div>
        </div>

    </div>
</div>

<!-- Volledig scherm scoreformulier: opent door op een "Bezig…" wedstrijd te tikken.
     Deliberately OUTSIDE #stage so it always renders at full real screen
     size (big touch targets), unaffected by the stage's scale factor. -->
<div id="resultOverlay" class="result-overlay d-none">
    <div class="result-overlay-content" id="resultOverlayContent"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo assetVer('assets/js/dashboard.js'); ?>"></script>
</body>
</html>
