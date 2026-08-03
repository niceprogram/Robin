<?php
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
<title>Toernooi — Volledig klassement</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo assetVer('assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body>

<div class="container py-4">
    <h2 class="mb-4">🏆 Volledig klassement</h2>

    <div class="table-responsive">
        <table class="table table-dark table-striped lb-table align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Naam</th>
                    <th>Punten</th>
                    <th>Winst</th>
                    <th>Verlies</th>
                    <th>Gelijkspel</th>
                    <th>Ongeldig</th>
                    <th>Aantal keer jury</th>
                    <th>Wedstrijden gespeeld</th>
                    <th>Rondes gerust</th>
                </tr>
            </thead>
            <tbody id="lbBody"></tbody>
        </table>
    </div>

    <div class="mt-3 text-secondary">
        <strong>Volgorde bij gelijke stand:</strong> totaal punten → overwinningen → gelijkspelen → minste verliezen → alfabetisch.
    </div>

    <a href="dashboard.php" class="btn btn-outline-light mt-3">Terug naar dashboard</a>
</div>

<script src="<?php echo assetVer('assets/js/leaderboard.js'); ?>"></script>
</body>
</html>
