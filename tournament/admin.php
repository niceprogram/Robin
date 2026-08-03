<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tournament — Score Desk</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">

<div class="container-fluid py-3">
    <h2 class="mb-3">🥋 Score Desk</h2>

    <div id="alertBox"></div>

    <div class="control-bar mb-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <button id="btnStartTournament" class="btn btn-success w-100">▶️ Start tournament</button>
            </div>
            <div class="col-md-3">
                <button id="btnStartNextRound" class="btn btn-primary w-100" disabled>⏭ Start next round</button>
            </div>
            <div class="col-md-3">
                <button id="btnPauseResume" class="btn btn-warning w-100" disabled>⏸ Pause timer</button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-light w-100" data-bs-toggle="modal" data-bs-target="#participantsModal">
                    👤 Manage participants
                </button>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-3">Status: <strong id="tournamentStatusLabel">—</strong></div>
            <div class="col-md-3" id="roundLabel">No round in progress</div>
            <div class="col-md-2">Missing results: <span class="badge bg-danger" id="missingCount">0</span></div>
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Planned rounds</span>
                    <input type="number" min="0" id="totalRoundsInput" class="form-control" placeholder="e.g. 5">
                    <button class="btn btn-outline-light" id="btnSaveTotalRounds">Save</button>
                </div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6 offset-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Event length (min)</span>
                    <input type="number" min="1" id="eventMinutesInput" class="form-control" placeholder="e.g. 120">
                    <button class="btn btn-outline-info" id="btnCalculateRounds">Calculate &amp; save</button>
                </div>
                <div class="form-text text-secondary" id="calculateHint"></div>
            </div>
        </div>
        <div id="idleBox"></div>
    </div>

    <div id="adminMatches" class="row g-3"></div>
</div>

<!-- Correct Result Modal -->
<div class="modal fade" id="correctModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Correct result</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="correctMatchId">
        <p id="correctMatchLabel" class="fs-5"></p>
        <div class="d-grid gap-2">
            <button class="btn btn-primary" onclick="submitCorrection('win_a')">
                🏆 <span id="correctPlayerAName"></span> won
            </button>
            <button class="btn btn-primary" onclick="submitCorrection('win_b')">
                🏆 <span id="correctPlayerBName"></span> won
            </button>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info flex-fill" onclick="submitCorrection('draw')">🤝 Draw</button>
                <button class="btn btn-outline-secondary flex-fill" onclick="submitCorrection('fault')">⚠️ Faulty game</button>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Manage Participants Modal -->
<div class="modal fade" id="participantsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Manage participants</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="input-group mb-3">
            <input type="text" id="newParticipantName" class="form-control" placeholder="New participant name">
            <button class="btn btn-success" id="btnAddParticipant">Add</button>
        </div>
        <p class="text-secondary small">
            Toggling a participant off removes them from future rounds (they keep their existing
            points/history). New participants are automatically included starting from the next
            generated round.
        </p>
        <div id="participantList"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin.js"></script>
</body>
</html>
