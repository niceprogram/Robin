<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tournament Manager</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container py-5 text-center">
    <h1 class="mb-4">🏆 Community Center Tournament Manager</h1>
    <p class="text-secondary mb-5">Choose a screen to open:</p>
    <div class="row justify-content-center g-4">
        <div class="col-md-4">
            <a href="admin.php" class="btn btn-primary btn-lg w-100 py-4">
                🥋 Score Desk (Admin)<br><small>Use on the laptop</small>
            </a>
        </div>
        <div class="col-md-4">
            <a href="dashboard.php" class="btn btn-success btn-lg w-100 py-4">
                📺 Dashboard (TV)<br><small>Show fullscreen on the TV</small>
            </a>
        </div>
        <div class="col-md-4">
            <a href="leaderboard.php" class="btn btn-outline-light btn-lg w-100 py-4">
                📊 Full Leaderboard<br><small>Detailed standings</small>
            </a>
        </div>
    </div>
</div>
</body>
</html>
