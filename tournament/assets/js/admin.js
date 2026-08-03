let allMatchesCache = [];
let roundDurationSeconds = 420;

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
        case 'win_a': return 'Player A won';
        case 'win_b': return 'Player B won';
        case 'draw': return 'Draw';
        case 'fault': return 'Faulty game';
        default: return 'Pending';
    }
}

function renderMatches(matches) {
    allMatchesCache = matches;
    const container = document.getElementById('adminMatches');
    if (!matches.length) {
        container.innerHTML = '<p class="text-secondary">No matches in this round yet.</p>';
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
                <div class="text-secondary small mb-2">Judge: ${m.judge_name}</div>
                ${decided ? `
                    <div class="fs-5 mb-2">${m.player_a_name} vs ${m.player_b_name}</div>
                    <button class="btn btn-outline-light btn-sm" onclick="openCorrectModal(${m.id})">
                        ✏️ Correct result
                    </button>
                ` : `
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary big-win-btn" onclick="submitResult(${m.id}, 'win_a')">
                            🏆 ${m.player_a_name} won
                        </button>
                        <button class="btn btn-primary big-win-btn" onclick="submitResult(${m.id}, 'win_b')">
                            🏆 ${m.player_b_name} won
                        </button>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-info small-result-btn flex-fill" onclick="submitResult(${m.id}, 'draw')">🤝 Draw</button>
                            <button class="btn btn-outline-secondary small-result-btn flex-fill" onclick="submitResult(${m.id}, 'fault')">⚠️ Faulty game</button>
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
        showAlert(out.error || 'Could not save result.');
        return;
    }
    refresh();
}

function openCorrectModal(matchId) {
    const match = allMatchesCache.find(m => m.id === matchId);
    if (!match) return;
    document.getElementById('correctMatchId').value = matchId;
    document.getElementById('correctMatchLabel').textContent =
        `${match.player_a_name} vs ${match.player_b_name} (${match.station_name})`;
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
        showAlert(out.error || 'Could not correct result.');
        return;
    }
    refresh();
}

function renderControls(data) {
    document.getElementById('btnStartTournament').disabled = !data.can_start_tournament;
    document.getElementById('btnStartNextRound').disabled = !data.can_start_next_round;

    const pauseBtn = document.getElementById('btnPauseResume');
    if (data.round && data.round.status === 'paused') {
        pauseBtn.textContent = '▶️ Resume timer';
        pauseBtn.disabled = false;
    } else if (data.round && data.round.status === 'active') {
        pauseBtn.textContent = '⏸ Pause timer';
        pauseBtn.disabled = false;
    } else {
        pauseBtn.textContent = '⏸ Pause timer';
        pauseBtn.disabled = true;
    }

    document.getElementById('tournamentStatusLabel').textContent =
        data.tournament_status === 'not_started' ? 'Not started' :
        data.tournament_status === 'running' ? 'Running' : data.tournament_status;

    if (data.round) {
        document.getElementById('roundLabel').textContent =
            `Round ${data.round.round_number} — ${fmtTime(data.round.remaining_seconds)} left`;
    } else {
        document.getElementById('roundLabel').textContent = 'No round in progress';
    }

    document.getElementById('missingCount').textContent = data.missing_count;

    roundDurationSeconds = data.round_duration_seconds || 420;

    const totalRoundsInput = document.getElementById('totalRoundsInput');
    if (document.activeElement !== totalRoundsInput) {
        totalRoundsInput.value = data.total_rounds ?? '';
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
        hint.textContent = `≈ ${suggested} rounds at ${roundMins} min each. Real events run a bit slower ` +
            `than the math (time between rounds for reporting results and moving stations), so build in a buffer.`;
    } else {
        hint.textContent = `Rounds are ${roundMins} min each.`;
    }
}

function renderIdle(idle) {
    const box = document.getElementById('idleBox');
    if (!idle.length) { box.innerHTML = ''; return; }
    box.innerHTML = `<div class="mt-2"><strong>Resting:</strong> ${idle.map(p => p.name).join(', ')}</div>`;
}

function renderParticipants(players) {
    const box = document.getElementById('participantList');
    box.innerHTML = players.map(p => `
        <div class="d-flex justify-content-between align-items-center border-bottom border-secondary py-1">
            <span class="${p.active == 0 ? 'text-secondary text-decoration-line-through' : ''}">${p.name}</span>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" ${p.active == 1 ? 'checked' : ''}
                       onchange="toggleParticipant(${p.id}, this.checked)">
            </div>
        </div>
    `).join('');
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
        showAlert(out.error || 'Could not add participant.');
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
        renderParticipants(data.players);
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
            showAlert('Enter how many minutes the event should run first.');
            return;
        }
        const roundMins = Math.round(roundDurationSeconds / 60);
        const suggested = Math.max(1, Math.floor(mins / roundMins));
        document.getElementById('totalRoundsInput').value = suggested;
        const out = await apiPost('api/set_total_rounds.php', { total_rounds: suggested });
        if (!out.success) showAlert(out.error);
        refresh();
    });

    refresh();
    setInterval(refresh, 3000);
});
