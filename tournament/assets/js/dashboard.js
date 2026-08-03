const STATION_EMOJI = {
    'Airhockey': '🏒',
    'Darts': '🎯',
    'Uno': '🃏',
    'Armworstelen': '💪',
    'Autoracen': '🏎️',
    'Dammen': '⚫',
    'Tafeltennis': '🏓',
    'Op één been staan': '🦩',
    'Pingpongbekers': '🥤',
    'Minigolf': '⛳',
};

let localRemaining = null;
let localDuration = null;
let localStatus = 'active';
let tickHandle = null;
let currentMatchesCache = [];
let overlayOpen = false;

function fmtTime(sec) {
    sec = Math.max(0, Math.round(sec));
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

function updateTimerDisplay() {
    const el = document.getElementById('timer');
    if (localRemaining === null) {
        el.textContent = '--:--';
        el.className = 'timer-display timer-paused';
        return;
    }
    el.textContent = fmtTime(localRemaining);
    let cls = 'timer-ok';
    if (localStatus === 'paused') {
        cls = 'timer-paused';
    } else if (localDuration) {
        const pct = localRemaining / localDuration;
        if (pct <= 0.15) cls = 'timer-danger';
        else if (pct <= 0.4) cls = 'timer-warning';
    }
    el.className = 'timer-display ' + cls;
}

function startLocalTicking() {
    if (tickHandle) clearInterval(tickHandle);
    tickHandle = setInterval(() => {
        if (localStatus === 'active' && localRemaining !== null && localRemaining > 0) {
            localRemaining -= 1;
            updateTimerDisplay();
        }
    }, 1000);
}

function resultBadge(type) {
    switch (type) {
        case 'win_a': return '<span class="badge bg-success match-result-badge">Klaar</span>';
        case 'win_b': return '<span class="badge bg-success match-result-badge">Klaar</span>';
        case 'draw':  return '<span class="badge bg-info match-result-badge">Gelijkspel</span>';
        case 'fault': return '<span class="badge bg-secondary match-result-badge">Ongeldig spel</span>';
        default:      return '<span class="badge bg-warning text-dark match-result-badge">Bezig…</span>';
    }
}

function renderMatches(matches) {
    currentMatchesCache = matches;
    const container = document.getElementById('matchesContainer');
    if (!matches.length) {
        container.innerHTML = '<p class="text-secondary fs-4">Nog geen wedstrijden — wachten op de volgende ronde.</p>';
        return;
    }
    container.innerHTML = matches.map(m => {
        const emoji = STATION_EMOJI[m.station_name] || '🎮';
        const pending = m.result_type === 'pending';
        const cardCls = pending ? 'match-card-clickable' : 'decided-card';
        const clickAttr = pending ? `onclick="openResultOverlay(${m.id})"` : '';
        return `
            <div class="col">
                <div class="match-card ${cardCls}" ${clickAttr}>
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="match-station"><span class="emoji">${emoji}</span>${m.station_name}</div>
                        ${resultBadge(m.result_type)}
                    </div>
                    <div class="match-players">${m.player_a_name}<span class="match-vs">tegen</span>${m.player_b_name}</div>
                    <div class="match-judge"><span class="badge">Jury</span> ${m.judge_name}</div>
                    ${pending ? '<div class="tap-hint-card">👉 Tik om de uitslag in te voeren</div>' : ''}
                </div>
            </div>
        `;
    }).join('');
}

function renderIdle(idlePlayers) {
    const box = document.getElementById('idleBox');
    if (!idlePlayers.length) {
        box.innerHTML = '';
        return;
    }
    box.innerHTML = `
        <div class="idle-list mt-3">
            <div class="section-title">Rust deze ronde</div>
            ${idlePlayers.map(p => `<span class="pill">${p.name}</span>`).join('')}
        </div>
    `;
}

function renderLeaderboard(rows) {
    const body = document.getElementById('leaderboardBody');
    body.innerHTML = rows.map(r => `
        <tr>
            <td>${r.rank}</td>
            <td>${r.name}</td>
            <td>${r.points}</td>
            <td>${r.wins}</td>
        </tr>
    `).join('');
}

function renderPreview(preview) {
    const box = document.getElementById('previewBox');
    if (!preview || !preview.feasible) {
        box.innerHTML = '';
        return;
    }
    const rows = preview.matches.map(m => {
        const emoji = STATION_EMOJI[m.station_name] || '🎮';
        return `<div class="pill">${emoji} ${m.player_a_name} tegen ${m.player_b_name} <small>(jury: ${m.judge_name})</small></div>`;
    }).join('');
    box.innerHTML = `
        <div class="preview-list mt-3">
            <div class="section-title">Voorproefje volgende ronde</div>
            ${rows || '<span class="text-secondary">—</span>'}
        </div>
    `;
}

function renderProgress(data) {
    const progressEl = document.getElementById('roundProgress');
    const estimateEl = document.getElementById('timeLeftEstimate');

    if (data.total_rounds && data.round) {
        progressEl.textContent = `Ronde ${data.round.round_number} van ${data.total_rounds}`;
    } else if (data.total_rounds && !data.round) {
        progressEl.textContent = `0 van ${data.total_rounds} rondes`;
    } else {
        progressEl.textContent = '';
    }

    if (data.estimated_seconds_left !== null && data.estimated_seconds_left !== undefined) {
        const mins = Math.round(data.estimated_seconds_left / 60);
        estimateEl.textContent = mins <= 0 ? 'Bijna klaar!' : `~${mins} minu${mins === 1 ? 'ut' : 'ten'} speeltijd over`;
    } else {
        estimateEl.textContent = '';
    }
}

/* ---------------- Scoreformulier (overlay) ---------------- */

function openResultOverlay(matchId) {
    const match = currentMatchesCache.find(m => m.id === matchId);
    if (!match || match.result_type !== 'pending') return;

    overlayOpen = true;
    const emoji = STATION_EMOJI[match.station_name] || '🎮';
    const content = document.getElementById('resultOverlayContent');
    content.innerHTML = `
        <button class="btn btn-outline-light btn-sm overlay-back" onclick="closeResultOverlay()">← Terug</button>
        <div class="result-station-title">${emoji} ${match.station_name}</div>
        <div class="result-players">${match.player_a_name} <span class="match-vs">tegen</span> ${match.player_b_name}</div>
        <div class="result-judge mb-4">Jury: ${match.judge_name}</div>
        <div class="d-grid gap-3">
            <button class="btn btn-primary result-big-btn" onclick="chooseResult(${match.id}, 'win_a')">
                🏆 ${match.player_a_name} heeft gewonnen
            </button>
            <button class="btn btn-primary result-big-btn" onclick="chooseResult(${match.id}, 'win_b')">
                🏆 ${match.player_b_name} heeft gewonnen
            </button>
            <div class="d-flex gap-3">
                <button class="btn btn-outline-info result-small-btn flex-fill" onclick="chooseResult(${match.id}, 'draw')">🤝 Gelijkspel</button>
                <button class="btn btn-outline-secondary result-small-btn flex-fill" onclick="chooseResult(${match.id}, 'fault')">⚠️ Ongeldig spel</button>
            </div>
        </div>
    `;
    document.getElementById('resultOverlay').classList.remove('d-none');
}

function closeResultOverlay() {
    overlayOpen = false;
    document.getElementById('resultOverlay').classList.add('d-none');
}

async function chooseResult(matchId, result) {
    const content = document.getElementById('resultOverlayContent');
    content.innerHTML = `<div class="thank-you-screen">⏳ Bezig met opslaan…</div>`;

    try {
        const res = await fetch('api/submit_result.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ match_id: matchId, result }),
        });
        const out = await res.json();

        if (out.success) {
            content.innerHTML = `<div class="thank-you-screen">✅ Bedankt!</div>`;
        } else {
            content.innerHTML = `<div class="thank-you-screen thank-you-error">⚠️ ${out.error || 'Kon uitslag niet opslaan.'}</div>`;
        }
    } catch (e) {
        content.innerHTML = `<div class="thank-you-screen thank-you-error">⚠️ Verbindingsprobleem — probeer opnieuw.</div>`;
    }

    setTimeout(() => {
        closeResultOverlay();
        refresh();
    }, 1200);
}

async function refresh() {
    try {
        const res = await fetch('api/get_dashboard_data.php', { cache: 'no-store' });
        const data = await res.json();

        document.getElementById('statusBanner').textContent =
            data.tournament_status === 'not_started' ? 'Wachten tot het toernooi begint…' :
            data.tournament_status === 'running' ? '' : 'Toernooi afgelopen!';

        if (data.round) {
            document.getElementById('roundNumber').textContent = 'Ronde ' + data.round.round_number;
            localRemaining = data.round.remaining_seconds;
            localDuration = data.round.duration_seconds;
            localStatus = data.round.status;
        } else {
            document.getElementById('roundNumber').textContent = data.tournament_status === 'not_started' ? 'Niet gestart' : '—';
            localRemaining = null;
            localStatus = 'paused';
        }
        updateTimerDisplay();
        renderProgress(data);

        renderMatches(data.matches);
        renderIdle(data.idle_players);
        renderLeaderboard(data.leaderboard);
        renderPreview(data.next_round_preview);
    } catch (e) {
        console.error('Dashboard refresh failed', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    startLocalTicking();
    refresh();
    setInterval(refresh, 2000);
});
