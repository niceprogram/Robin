<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tournament — Full Leaderboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="container py-4">
    <h2 class="mb-4">🏆 Full Leaderboard</h2>

    <div class="table-responsive">
        <table class="table table-dark table-striped lb-table align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Points</th>
                    <th>Wins</th>
                    <th>Losses</th>
                    <th>Draws</th>
                    <th>Faulty</th>
                    <th>Judge count</th>
                    <th>Games played</th>
                    <th>Idle rounds</th>
                </tr>
            </thead>
            <tbody id="lbBody"></tbody>
        </table>
    </div>

    <div class="mt-3 text-secondary">
        <strong>Tie-break order:</strong> total points → wins → draws → fewest losses → alphabetical.
    </div>

    <a href="dashboard.php" class="btn btn-outline-light mt-3">Back to dashboard</a>
</div>

<script src="assets/js/leaderboard.js"></script>
</body>
</html>
