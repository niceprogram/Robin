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
<title>Toernooi — Scorebureau</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo assetVer('assets/css/style.css'); ?>" rel="stylesheet">
</head>
<body class="admin-body">

<div class="container-fluid py-3">
    <h2 class="mb-3">🥋 Scorebureau</h2>

    <div id="alertBox"></div>

    <div class="v2-layout">
        <div class="v2-sidebar">
            <div class="v2-sidebar-status">
                Status: <strong id="tournamentStatusLabel">—</strong><br>
                <span id="roundLabel">Geen ronde actief</span><br>
                Ontbrekende uitslagen: <span class="badge bg-danger" id="missingCount">0</span>
            </div>

            <button id="btnStartTournament" class="btn btn-success btn-square-start">
                <span class="square-emoji">▶️</span>
                <span class="square-label">Toernooi starten</span>
            </button>

            <div class="v2-secondary-stack">
                <button id="btnStartNextRound" class="btn btn-primary" disabled>⏭ Volgende ronde starten</button>
                <button id="btnPauseResume" class="btn btn-warning" disabled>⏸ Timer pauzeren</button>
                <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#participantsModal">
                    👤 Deelnemers beheren
                </button>
                <button class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#stationsModal">
                    🎮 Spellen beheren
                </button>
            </div>

            <div class="v2-settings-block">
                <div class="v2-settings-field">
                    <label for="totalRoundsInput">Geplande rondes</label>
                    <div class="input-group input-group-sm">
                        <input type="number" min="0" id="totalRoundsInput" class="form-control" placeholder="bv. 5">
                        <button class="btn btn-outline-light" id="btnSaveTotalRounds">OK</button>
                    </div>
                </div>
                <div class="v2-settings-field">
                    <label for="eventMinutesInput">Duur evenement (min)</label>
                    <div class="input-group input-group-sm">
                        <input type="number" min="1" id="eventMinutesInput" class="form-control" placeholder="bv. 120">
                        <button class="btn btn-outline-info" id="btnCalculateRounds">Bereken</button>
                    </div>
                </div>
                <div class="form-text text-secondary" id="calculateHint"></div>
                <div class="v2-settings-field mt-2">
                    <label for="roundMinutesInput">Ronde duur (min)</label>
                    <div class="input-group input-group-sm">
                        <input type="number" min="1" max="60" step="0.5" id="roundMinutesInput" class="form-control" placeholder="bv. 7">
                        <button class="btn btn-outline-light" id="btnSaveRoundDuration">OK</button>
                    </div>
                </div>
            </div>

            <div class="v2-danger-box">
                <button class="btn btn-outline-danger w-100 btn-sm" data-bs-toggle="modal" data-bs-target="#resetModal">
                    🔄 Toernooi resetten
                </button>
            </div>

            <div id="idleBox"></div>
        </div>

        <div class="v2-main">
            <div id="adminMatches" class="row g-3"></div>
        </div>
    </div>
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

<!-- Spellen beheren -->
<div class="modal fade" id="stationsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content bg-dark text-light">
      <div class="modal-header">
        <h5 class="modal-title">Spellen beheren</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="input-group mb-3">
            <input type="text" id="newStationName" class="form-control" placeholder="Naam nieuw spel">
            <button class="btn btn-success" id="btnAddStation">Toevoegen</button>
        </div>
        <p class="text-secondary small">
            Een spel uitzetten sluit dat station uit van volgende rondes. Er is minstens 1 actief
            spel nodig om een ronde te kunnen starten.
        </p>
        <div id="stationList"></div>
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
        <div id="resetAdviceBox"></div>
        <p>
            Dit wist <strong>alle rondes, wedstrijden en uitslagen</strong>. Iedereen begint weer op nul.
            <br>De deelnemerslijst (namen) blijft ongewijzigd.
            <br><strong>Dit kan niet ongedaan worden gemaakt.</strong>
        </p>
        <p class="mb-1">Typ <strong>RESET</strong> hieronder om te bevestigen:</p>
        <input type="text" id="resetConfirmText" class="form-control mb-3" placeholder="RESET">
        <button class="btn btn-danger w-100" id="btnConfirmReset" disabled>Toernooi definitief resetten</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo assetVer('assets/js/admin.js'); ?>"></script>
</body>
</html>
