let allMatchesCache = [];
let roundDurationSeconds = 420;
let renamingPlayerId = null;
let lastPlayersCache = [];
let renamingStationId = null;
let lastStationsCache = [];

function fmtTime(sec) {
    sec = Math.max(0, Math.round(sec));
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

async function apiPost(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body || {}),
    });
    return res.json();
}

function showAlert(msg, type = 'danger') {
    const box = document.getElementById('alertBox');
    box.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${msg}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function resultLabel(type) {
    switch (type) {
        case 'win_a': return 'Gewonnen';
        case 'win_b': return 'Gewonnen';
        case 'draw': return 'Gelijkspel';
        case 'fault': return 'Ongeldig spel';
        default: return 'Bezig';
    }
}

function renderMatches(matches) {
    allMatchesCache = matches;
    const container = document.getElementById('adminMatches');
    if (!matches.length) {
        container.innerHTML = '<p class="text-secondary">Nog geen wedstrijden in deze ronde.</p>';
        return;
    }
    container.innerHTML = matches.map(m => {
        const decided = m.result_type !== 'pending';
        return `
        <div class="col-md-6">
            <div class="admin-match-card ${decided ? 'decided-card' : ''}">
                <div class="d-flex justify-content-between">
                    <strong>${m.station_name}</strong>
                    <span class="badge ${decided ? 'bg-success' : 'bg-warning text-dark'}">${resultLabel(m.result_type)}</span>
                </div>
                <div class="text-secondary small mb-2">Jury: ${m.judge_name}</div>
                ${decided ? `
                    <div class="fs-5 mb-2">${m.player_a_name} tegen ${m.player_b_name}</div>
                    <button class="btn btn-outline-light btn-sm" onclick="openCorrectModal(${m.id})">
                        ✏️ Corrigeren
                    </button>
                ` : `
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary big-win-btn" onclick="submitResult(${m.id}, 'win_a')">
                            🏆 ${m.player_a_name} heeft gewonnen
                        </button>
                        <button class="btn btn-primary big-win-btn" onclick="submitResult(${m.id}, 'win_b')">
                            🏆 ${m.player_b_name} heeft gewonnen
                        </button>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-info small-result-btn flex-fill" onclick="submitResult(${m.id}, 'draw')">🤝 Gelijkspel</button>
                            <button class="btn btn-outline-secondary small-result-btn flex-fill" onclick="submitResult(${m.id}, 'fault')">⚠️ Ongeldig spel</button>
                        </div>
                    </div>
                `}
            </div>
        </div>`;
    }).join('');
}

async function submitResult(matchId, result) {
    const out = await apiPost('api/submit_result.php', { match_id: matchId, result });
    if (!out.success) {
        showAlert(out.error || 'Kon uitslag niet opslaan.');
        return;
    }
    refresh();
}

function openCorrectModal(matchId) {
    const match = allMatchesCache.find(m => m.id === matchId);
    if (!match) return;
    document.getElementById('correctMatchId').value = matchId;
    document.getElementById('correctMatchLabel').textContent =
        `${match.player_a_name} tegen ${match.player_b_name} (${match.station_name})`;
    document.getElementById('correctPlayerAName').textContent = match.player_a_name;
    document.getElementById('correctPlayerBName').textContent = match.player_b_name;
    const modal = new bootstrap.Modal(document.getElementById('correctModal'));
    modal.show();
}

async function submitCorrection(result) {
    const matchId = parseInt(document.getElementById('correctMatchId').value, 10);
    const out = await apiPost('api/correct_result.php', { match_id: matchId, result });
    bootstrap.Modal.getInstance(document.getElementById('correctModal')).hide();
    if (!out.success) {
        showAlert(out.error || 'Kon uitslag niet corrigeren.');
        return;
    }
    refresh();
}

function renderControls(data) {
    document.getElementById('btnStartTournament').disabled = !data.can_start_tournament;
    document.getElementById('btnStartNextRound').disabled = !data.can_start_next_round;

    const pauseBtn = document.getElementById('btnPauseResume');
    if (data.round && data.round.status === 'paused') {
        pauseBtn.textContent = '▶️ Timer hervatten';
        pauseBtn.disabled = false;
    } else if (data.round && data.round.status === 'active') {
        pauseBtn.textContent = '⏸ Timer pauzeren';
        pauseBtn.disabled = false;
    } else {
        pauseBtn.textContent = '⏸ Timer pauzeren';
        pauseBtn.disabled = true;
    }

    document.getElementById('tournamentStatusLabel').textContent =
        data.tournament_status === 'not_started' ? 'Niet gestart' :
        data.tournament_status === 'running' ? 'Bezig' : data.tournament_status;

    if (data.round) {
        document.getElementById('roundLabel').textContent =
            `Ronde ${data.round.round_number} — nog ${fmtTime(data.round.remaining_seconds)}`;
    } else {
        document.getElementById('roundLabel').textContent = 'Geen ronde actief';
    }

    document.getElementById('missingCount').textContent = data.missing_count;

    roundDurationSeconds = data.round_duration_seconds || 420;

    const totalRoundsInput = document.getElementById('totalRoundsInput');
    if (document.activeElement !== totalRoundsInput) {
        totalRoundsInput.value = data.total_rounds ?? '';
    }

    const roundMinutesInput = document.getElementById('roundMinutesInput');
    if (document.activeElement !== roundMinutesInput) {
        roundMinutesInput.value = Math.round((roundDurationSeconds / 60) * 10) / 10;
    }

    const eventMinutesInput = document.getElementById('eventMinutesInput');
    if (document.activeElement !== eventMinutesInput) {
        updateCalculateHint();
    }
}

function updateCalculateHint() {
    const eventMinutesInput = document.getElementById('eventMinutesInput');
    const hint = document.getElementById('calculateHint');
    const roundMins = Math.round(roundDurationSeconds / 60);
    const mins = parseInt(eventMinutesInput.value, 10);
    if (!isNaN(mins) && mins > 0) {
        const suggested = Math.max(1, Math.floor(mins / roundMins));
        hint.textContent = `≈ ${suggested} rondes van ${roundMins} min. In de praktijk duurt het vaak iets langer ` +
            `(tijd tussen rondes voor het doorgeven van uitslagen en wisselen van station), reken dus een marge in.`;
    } else {
        hint.textContent = `Elke ronde duurt ${roundMins} min.`;
    }
}

function renderIdle(idle) {
    const box = document.getElementById('idleBox');
    if (!idle.length) { box.innerHTML = ''; return; }
    box.innerHTML = `<div class="mt-2"><strong>Rust:</strong> ${idle.map(p => p.name).join(', ')}</div>`;
}

function renderParticipants(players) {
    lastPlayersCache = players;
    const box = document.getElementById('participantList');
    box.innerHTML = players.map(p => {
        if (renamingPlayerId === p.id) {
            return `
                <div class="d-flex justify-content-between align-items-center border-bottom border-secondary py-1 gap-2">
                    <input type="text" id="renameInput_${p.id}" class="form-control form-control-sm"
                           value="${p.name.replace(/"/g, '&quot;')}" autofocus>
                    <button class="btn btn-success btn-sm" onclick="saveRename(${p.id})">✔</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="cancelRename()">✕</button>
                </div>
            `;
        }
        return `
        <div class="d-flex justify-content-between align-items-center border-bottom border-secondary py-1">
            <span class="${p.active == 0 ? 'text-secondary text-decoration-line-through' : ''}">${p.name}</span>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-light btn-sm" onclick="startRename(${p.id})" title="Naam wijzigen">✏️</button>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" ${p.active == 1 ? 'checked' : ''}
                           onchange="toggleParticipant(${p.id}, this.checked)">
                </div>
            </div>
        </div>
        `;
    }).join('');

    if (renamingPlayerId !== null) {
        const input = document.getElementById(`renameInput_${renamingPlayerId}`);
        if (input) {
            input.focus();
            input.select();
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') saveRename(renamingPlayerId);
                if (e.key === 'Escape') cancelRename();
            });
        }
    }
}

function startRename(id) {
    renamingPlayerId = id;
    renderParticipants(lastPlayersCache);
}

function cancelRename() {
    renamingPlayerId = null;
    renderParticipants(lastPlayersCache);
}

async function saveRename(id) {
    const input = document.getElementById(`renameInput_${id}`);
    const name = input ? input.value.trim() : '';
    if (!name) {
        showAlert('Naam mag niet leeg zijn.');
        return;
    }
    const out = await apiPost('api/rename_participant.php', { player_id: id, name });
    renamingPlayerId = null;
    if (!out.success) {
        showAlert(out.error || 'Kon naam niet wijzigen.');
    }
    refresh();
}

async function toggleParticipant(id, active) {
    await apiPost('api/toggle_participant.php', { player_id: id, active: active ? 1 : 0 });
    refresh();
}

async function addParticipant() {
    const input = document.getElementById('newParticipantName');
    const name = input.value.trim();
    if (!name) return;
    const out = await apiPost('api/add_participant.php', { name });
    if (!out.success) {
        showAlert(out.error || 'Kon deelnemer niet toevoegen.');
        return;
    }
    input.value = '';
    refresh();
}

function renderStations(stations) {
    lastStationsCache = stations;
    const box = document.getElementById('stationList');
    box.innerHTML = stations.map(s => {
        if (renamingStationId === s.id) {
            return `
                <div class="d-flex justify-content-between align-items-center border-bottom border-secondary py-1 gap-2">
                    <input type="text" id="renameStationInput_${s.id}" class="form-control form-control-sm"
                           value="${s.name.replace(/"/g, '&quot;')}" autofocus>
                    <button class="btn btn-success btn-sm" onclick="saveStationRename(${s.id})">✔</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="cancelStationRename()">✕</button>
                </div>
            `;
        }
        return `
        <div class="d-flex justify-content-between align-items-center border-bottom border-secondary py-1">
            <span class="${s.active == 0 ? 'text-secondary text-decoration-line-through' : ''}">${s.name}</span>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-outline-light btn-sm" onclick="startStationRename(${s.id})" title="Naam wijzigen">✏️</button>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" ${s.active == 1 ? 'checked' : ''}
                           onchange="toggleStation(${s.id}, this.checked)">
                </div>
            </div>
        </div>
        `;
    }).join('');

    if (renamingStationId !== null) {
        const input = document.getElementById(`renameStationInput_${renamingStationId}`);
        if (input) {
            input.focus();
            input.select();
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') saveStationRename(renamingStationId);
                if (e.key === 'Escape') cancelStationRename();
            });
        }
    }
}

function startStationRename(id) {
    renamingStationId = id;
    renderStations(lastStationsCache);
}

function cancelStationRename() {
    renamingStationId = null;
    renderStations(lastStationsCache);
}

async function saveStationRename(id) {
    const input = document.getElementById(`renameStationInput_${id}`);
    const name = input ? input.value.trim() : '';
    if (!name) {
        showAlert('Naam mag niet leeg zijn.');
        return;
    }
    const out = await apiPost('api/rename_station.php', { station_id: id, name });
    renamingStationId = null;
    if (!out.success) {
        showAlert(out.error || 'Kon naam niet wijzigen.');
    }
    refresh();
}

async function toggleStation(id, active) {
    await apiPost('api/toggle_station.php', { station_id: id, active: active ? 1 : 0 });
    refresh();
}

async function addStation() {
    const input = document.getElementById('newStationName');
    const name = input.value.trim();
    if (!name) return;
    const out = await apiPost('api/add_station.php', { name });
    if (!out.success) {
        showAlert(out.error || 'Kon spel niet toevoegen.');
        return;
    }
    input.value = '';
    refresh();
}

async function refresh() {
    try {
        const res = await fetch('api/get_admin_data.php', { cache: 'no-store' });
        const data = await res.json();
        renderControls(data);
        renderMatches(data.matches);
        renderIdle(data.idle_players);
        if (renamingPlayerId === null) {
            renderParticipants(data.players);
        } else {
            lastPlayersCache = data.players;
        }
        if (renamingStationId === null) {
            renderStations(data.stations);
        } else {
            lastStationsCache = data.stations;
        }
    } catch (e) {
        console.error('Admin refresh failed', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnStartTournament').addEventListener('click', async () => {
        const out = await apiPost('api/start_tournament.php');
        if (!out.success) showAlert(out.error);
        refresh();
    });
    document.getElementById('btnStartNextRound').addEventListener('click', async () => {
        const out = await apiPost('api/start_next_round.php');
        if (!out.success) showAlert(out.error);
        refresh();
    });
    document.getElementById('btnPauseResume').addEventListener('click', async () => {
        const out = await apiPost('api/pause_timer.php');
        if (!out.success) showAlert(out.error);
        refresh();
    });
    document.getElementById('btnAddParticipant').addEventListener('click', addParticipant);
    document.getElementById('btnAddStation').addEventListener('click', addStation);

    document.getElementById('btnSaveTotalRounds').addEventListener('click', async () => {
        const val = parseInt(document.getElementById('totalRoundsInput').value, 10);
        const out = await apiPost('api/set_total_rounds.php', { total_rounds: isNaN(val) ? 0 : val });
        if (!out.success) showAlert(out.error);
        refresh();
    });

    document.getElementById('eventMinutesInput').addEventListener('input', updateCalculateHint);
    document.getElementById('btnCalculateRounds').addEventListener('click', async () => {
        const mins = parseInt(document.getElementById('eventMinutesInput').value, 10);
        if (isNaN(mins) || mins <= 0) {
            showAlert('Vul eerst in hoeveel minuten het evenement mag duren.');
            return;
        }
        const roundMins = Math.round(roundDurationSeconds / 60);
        const suggested = Math.max(1, Math.floor(mins / roundMins));
        document.getElementById('totalRoundsInput').value = suggested;
        const out = await apiPost('api/set_total_rounds.php', { total_rounds: suggested });
        if (!out.success) showAlert(out.error);
        refresh();
    });

    document.getElementById('btnSaveRoundDuration').addEventListener('click', async () => {
        const mins = parseFloat(document.getElementById('roundMinutesInput').value);
        if (isNaN(mins) || mins <= 0) {
            showAlert('Vul een geldige ronde duur in (in minuten).');
            return;
        }
        const out = await apiPost('api/set_round_duration.php', { minutes: mins });
        if (!out.success) showAlert(out.error);
        refresh();
    });

    // Reset tournament: require typing RESET before the confirm button activates.
    const resetConfirmText = document.getElementById('resetConfirmText');
    const btnConfirmReset = document.getElementById('btnConfirmReset');
    resetConfirmText.addEventListener('input', () => {
        btnConfirmReset.disabled = resetConfirmText.value.trim().toUpperCase() !== 'RESET';
    });
    btnConfirmReset.addEventListener('click', async () => {
        const out = await apiPost('api/reset_tournament.php', { confirm: true });
        bootstrap.Modal.getInstance(document.getElementById('resetModal')).hide();
        resetConfirmText.value = '';
        btnConfirmReset.disabled = true;
        if (!out.success) {
            showAlert(out.error || 'Reset mislukt.');
        } else {
            showAlert('Toernooi is gereset. Iedereen begint weer op nul.', 'success');
        }
        refresh();
    });

    refresh();
    setInterval(refresh, 3000);
});
