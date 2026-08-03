<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Toernooi — Scorebureau</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">

<div class="container-fluid py-3">
    <h2 class="mb-3">🥋 Scorebureau</h2>

    <div id="alertBox"></div>

    <div class="control-bar mb-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <button id="btnStartTournament" class="btn btn-success w-100">▶️ Toernooi starten</button>
            </div>
            <div class="col-md-3">
                <button id="btnStartNextRound" class="btn btn-primary w-100" disabled>⏭ Volgende ronde starten</button>
            </div>
            <div class="col-md-3">
                <button id="btnPauseResume" class="btn btn-warning w-100" disabled>⏸ Timer pauzeren</button>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-light w-100" data-bs-toggle="modal" data-bs-target="#participantsModal">
                    👤 Deelnemers beheren
                </button>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-3">Status: <strong id="tournamentStatusLabel">—</strong></div>
            <div class="col-md-3" id="roundLabel">Geen ronde actief</div>
            <div class="col-md-2">Ontbrekende uitslagen: <span class="badge bg-danger" id="missingCount">0</span></div>
            <div class="col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Geplande rondes</span>
                    <input type="number" min="0" id="totalRoundsInput" class="form-control" placeholder="bv. 5">
                    <button class="btn btn-outline-light" id="btnSaveTotalRounds">Opslaan</button>
                </div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6 offset-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Duur evenement (min)</span>
                    <input type="number" min="1" id="eventMinutesInput" class="form-control" placeholder="bv. 120">
                    <button class="btn btn-outline-info" id="btnCalculateRounds">Berekenen &amp; opslaan</button>
                </div>
                <div class="form-text text-secondary" id="calculateHint"></div>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6 offset-md-6">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Ronde duur (min)</span>
                    <input type="number" min="1" max="60" step="0.5" id="roundMinutesInput" class="form-control" placeholder="bv. 7">
                    <button class="btn btn-outline-light" id="btnSaveRoundDuration">Opslaan</button>
                </div>
                <div class="form-text text-secondary">
                    Wijzigingen gelden vanaf de volgende ronde — de lopende ronde behoudt zijn huidige tijd.
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4">
                <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#resetModal">
                    🔄 Toernooi resetten
                </button>
            </div>
        </div>
        <div id="idleBox"></div>
    </div>

    <div id="adminMatches" class="row g-3"></div>
</div>

<!-- Uitslag corrigeren -->
<div class="modal fade" id="correctModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Uitslag corrigeren</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="correctMatchId">
        <p id="correctMatchLabel" class="fs-5"></p>
        <div class="d-grid gap-2">
            <button class="btn btn-primary" onclick="submitCorrection('win_a')">
                🏆 <span id="correctPlayerAName"></span> heeft gewonnen
            </button>
            <button class="btn btn-primary" onclick="submitCorrection('win_b')">
                🏆 <span id="correctPlayerBName"></span> heeft gewonnen
            </button>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-info flex-fill" onclick="submitCorrection('draw')">🤝 Gelijkspel</button>
                <button class="btn btn-outline-secondary flex-fill" onclick="submitCorrection('fault')">⚠️ Ongeldig spel</button>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Deelnemers beheren -->
<div class="modal fade" id="participantsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Deelnemers beheren</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="input-group mb-3">
            <input type="text" id="newParticipantName" class="form-control" placeholder="Naam nieuwe deelnemer">
            <button class="btn btn-success" id="btnAddParticipant">Toevoegen</button>
        </div>
        <p class="text-secondary small">
            Een deelnemer uitzetten sluit hem/haar uit van volgende rondes (punten/geschiedenis blijven
            behouden). Nieuwe deelnemers doen automatisch mee vanaf de eerstvolgende ronde.
        </p>
        <div id="participantList"></div>
      </div>
    </div>
  </div>
</div>

<!-- Toernooi resetten -->
<div class="modal fade" id="resetModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title text-danger">🔄 Toernooi resetten</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>
            Dit wist <strong>alle rondes, wedstrijden en uitslagen</strong>. Iedereen begint weer op nul.
            <br><strong>Dit kan niet ongedaan worden gemaakt.</strong>
        </p>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="resetClearPlayers">
            <label class="form-check-label" for="resetClearPlayers">
                Ook alle deelnemers verwijderen (voor een compleet nieuwe naamlijst)
            </label>
        </div>
        <p class="mb-1">Typ <strong>RESET</strong> hieronder om te bevestigen:</p>
        <input type="text" id="resetConfirmText" class="form-control mb-3" placeholder="RESET">
        <button class="btn btn-danger w-100" id="btnConfirmReset" disabled>Toernooi definitief resetten</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin.js"></script>
</body>
</html>
