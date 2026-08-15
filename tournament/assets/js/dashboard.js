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

/* ---------------- Live commentaar (gratis, geen AI nodig) ---------------- */

const COMMENTARY_WIN = [
    '🏆 {winner} verslaat {loser} bij {station}!',
    'Wat een wedstrijd! {winner} wint van {loser} bij {station}!',
    '{winner} pakt de winst tegen {loser} bij {station}! 🎉',
    'Daar gaat {loser}… {winner} wint bij {station}!',
    'Knap gespeeld, {winner}! {loser} moet het onderspit delven bij {station}.',
    'En de winnaar is… {winner}! Goed gedaan bij {station}.',
    '{winner} laat zien hoe het moet bij {station}!',
];
const COMMENTARY_DRAW = [
    '🤝 Gelijkspel tussen {a} en {b} bij {station}!',
    'Niemand wint! {a} en {b} eindigen gelijk bij {station}.',
    'Spannend! {a} en {b} houden elkaar in evenwicht bij {station}.',
    'Zo close! {a} en {b} delen de punten bij {station}.',
];
const COMMENTARY_FAULT = [
    '⚠️ Ongeldig spel bij {station} tussen {a} en {b} — die telt niet!',
    'Hmm, dat ging niet helemaal goed bij {station} voor {a} en {b}.',
    'Opnieuw proberen maar! Ongeldig spel bij {station}.',
];
const COMMENTARY_ROUND_START = [
    '📣 Ronde {round} begint! Succes allemaal!',
    '🔔 Daar gaat ronde {round}!',
    'Ronde {round} is gestart — veel plezier!',
];

let previousMatchResults = {};
let previousRoundNumber = null;
let isFirstCommentaryLoad = true;
let commentaryLines = [];
let speechEnabled = localStorage.getItem('speechEnabled') === 'true';

function pickRandom(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

function fillTemplate(tpl, vars) {
    return tpl.replace(/\{(\w+)\}/g, (_, key) => vars[key] ?? '');
}

function speak(text) {
    if (!speechEnabled || !('speechSynthesis' in window)) return;
    const utterance = new SpeechSynthesisUtterance(text.replace(/[🏆🎉🤝⚠️📣🔔]/g, ''));
    utterance.lang = 'nl-NL';
    const voices = window.speechSynthesis.getVoices();
    const nlVoice = voices.find(v => v.lang && v.lang.toLowerCase().startsWith('nl'));
    if (nlVoice) utterance.voice = nlVoice;
    window.speechSynthesis.speak(utterance);
}

function addCommentary(text) {
    commentaryLines.unshift(text);
    commentaryLines = commentaryLines.slice(0, 12);
    renderCommentary();
    speak(text);
}

function renderCommentary() {
    const box = document.getElementById('commentaryFeed');
    if (!commentaryLines.length) {
        box.innerHTML = '<div class="commentary-empty">Hier verschijnt live commentaar zodra er uitslagen binnenkomen…</div>';
        return;
    }
    box.innerHTML = commentaryLines.map(line => `<div class="commentary-line">${line}</div>`).join('');
}

/**
 * Compares the freshly-fetched matches/round against what we saw last
 * time, and generates commentary lines for anything new: a match that
 * just got a result, or a new round starting.
 */
function updateCommentaryFromData(data) {
    if (isFirstCommentaryLoad) {
        // Don't retroactively comment on results that were already
        // decided before the dashboard was opened/reloaded.
        (data.matches || []).forEach(m => { previousMatchResults[m.id] = m.result_type; });
        previousRoundNumber = data.round ? data.round.round_number : null;
        isFirstCommentaryLoad = false;
        return;
    }

    if (data.round && data.round.round_number !== previousRoundNumber) {
        addCommentary(fillTemplate(pickRandom(COMMENTARY_ROUND_START), { round: data.round.round_number }));
        previousRoundNumber = data.round.round_number;
    }

    (data.matches || []).forEach(m => {
        const prev = previousMatchResults[m.id];
        if (prev === 'pending' && m.result_type !== 'pending') {
            const vars = {
                a: m.player_a_name,
                b: m.player_b_name,
                station: m.station_name,
            };
            if (m.result_type === 'win_a') {
                addCommentary(fillTemplate(pickRandom(COMMENTARY_WIN), { winner: m.player_a_name, loser: m.player_b_name, station: m.station_name }));
            } else if (m.result_type === 'win_b') {
                addCommentary(fillTemplate(pickRandom(COMMENTARY_WIN), { winner: m.player_b_name, loser: m.player_a_name, station: m.station_name }));
            } else if (m.result_type === 'draw') {
                addCommentary(fillTemplate(pickRandom(COMMENTARY_DRAW), vars));
            } else if (m.result_type === 'fault') {
                addCommentary(fillTemplate(pickRandom(COMMENTARY_FAULT), vars));
            }
        }
        previousMatchResults[m.id] = m.result_type;
    });
}


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

/**
 * Chooses how many grid columns/rows to use based on how many matches
 * are actually running this round, so a small tournament (e.g. 2-4
 * matches) gets fewer, bigger, easier-to-read cards instead of always
 * being squeezed into a fixed 3-column grid meant for a full 20+ player
 * event.
 */
function computeGridDims(matchCount) {
    let columns;
    if (currentDisplayMode === 'vertical') {
        // Portrait screens are narrow — cap at 2 columns however many
        // matches are running, so cards stay legible.
        columns = matchCount <= 1 ? 1 : 2;
    } else if (matchCount <= 2) columns = matchCount;       // 1 or 2 matches: 1 or 2 columns
    else if (matchCount <= 4) columns = 2;            // 3-4 matches: 2 columns
    else columns = 3;                                 // 5+ matches: 3 columns (up to 8 max)
    const rows = Math.max(1, Math.ceil(matchCount / columns));
    return { columns, rows };
}

function renderMatches(matches) {
    currentMatchesCache = matches;
    const container = document.getElementById('matchesContainer');
    if (!matches.length) {
        container.style.gridTemplateColumns = '';
        container.style.gridTemplateRows = '';
        container.innerHTML = '<p class="text-secondary fs-5">Nog geen wedstrijden — wachten op de volgende ronde.</p>';
        return;
    }

    const { columns, rows } = computeGridDims(matches.length);
    container.style.gridTemplateColumns = `repeat(${columns}, 1fr)`;
    container.style.gridTemplateRows = `repeat(${rows}, 1fr)`;

    container.innerHTML = matches.map(m => {
        const emoji = STATION_EMOJI[m.station_name] || '🎮';
        const pending = m.result_type === 'pending';
        const cardCls = pending ? 'match-card-clickable' : 'decided-card';
        const clickAttr = pending ? `onclick="openResultOverlay(${m.id})"` : '';
        return `
            <div class="match-card ${cardCls}" ${clickAttr}>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="match-station"><span class="emoji">${emoji}</span>${m.station_name}</div>
                    ${resultBadge(m.result_type)}
                </div>
                <div class="match-players">${m.player_a_name}<span class="match-vs">tegen</span>${m.player_b_name}</div>
                <div class="match-judge"><span class="badge">Jury</span> ${m.judge_name}</div>
                ${pending ? '<div class="tap-hint-card">👉 Tik om de uitslag in te voeren</div>' : ''}
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
        <div class="idle-banner">
            <span class="idle-banner-label">🪑 Zit deze ronde uit:</span>
            ${idlePlayers.map(p => `<span class="pill idle-pill">${p.name}</span>`).join('')}
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
    fitLeaderboard(rows.length);
}

/**
 * Shrinks the leaderboard's font-size and row padding so that ALL
 * players fit inside the panel's fixed height without ever needing a
 * scrollbar — the dashboard is a kiosk display (mouse/touch only, no
 * one can scroll a TV), so everything must be visible at once
 * regardless of whether there are 10 or 24 participants.
 *
 * This measures the ACTUAL rendered height and shrinks step by step
 * until it genuinely fits, rather than estimating via a formula —
 * formulas drift out from real line-height/border/padding rounding,
 * measurement never does.
 */
function fitLeaderboard(rowCount) {
    const panel = document.querySelector('.leaderboard-panel');
    if (!panel || rowCount === 0) return;
    const table = panel.querySelector('table');
    if (!table) return;

    const MIN_FONT_SIZE = 7;    // readability floor
    const MAX_FONT_SIZE = 56;   // generous ceiling — lets "leaderboard only" mode
                                 // actually fill a big panel instead of staying
                                 // sized for the small side-panel case
    const MAX_ITERATIONS = 20;

    function apply(fontSize) {
        const rowPadding = Math.max(0, fontSize * 0.45 - 3);
        panel.style.setProperty('--lb-font-size', fontSize.toFixed(1) + 'px');
        panel.style.setProperty('--lb-row-padding', rowPadding.toFixed(1) + 'px');
    }

    // Binary search for the largest font size that still fits the panel's
    // real (measured) height without overflowing — measuring the actual
    // rendered height (rather than a formula) is what makes this correct
    // for any row count AND any panel size, from the small side-panel on
    // the normal dashboard up to a full-screen "leaderboard only" panel.
    let lo = MIN_FONT_SIZE, hi = MAX_FONT_SIZE, best = MIN_FONT_SIZE;
    for (let i = 0; i < MAX_ITERATIONS && lo <= hi; i++) {
        const mid = (lo + hi) / 2;
        apply(mid);
        if (table.scrollHeight <= panel.clientHeight) {
            best = mid;
            lo = mid + 0.5;
        } else {
            hi = mid - 0.5;
        }
    }
    apply(best);
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
        <div class="preview-list">
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
        updateCommentaryFromData(data);
    } catch (e) {
        console.error('Dashboard refresh failed', e);
    }
}

/**
 * Scales the fixed 1920x1080 #stage canvas to exactly fit the real
 * window/screen, preserving aspect ratio (letterboxed if the screen's
 * aspect ratio differs). This is what lets the same dashboard layout
 * work correctly on any TV resolution or landscape screen size without
 * scrolling or overflow — see the matching CSS in style.css.
 */
/**
 * Display modes trade off how much of the fixed design canvas maps onto
 * the real screen. #stage is scaled as one uniform CSS transform, so
 * shrinking the virtual canvas (width/height below) makes everything
 * inside it — text, cards, buttons — render proportionally bigger on
 * the same physical screen, with zero per-element font-size changes.
 *
 * "vertical" is a genuinely different (portrait) canvas + layout — see
 * the .stage-vertical rules in style.css, which stack the matches and
 * leaderboard columns instead of placing them side by side.
 */
const DISPLAY_PROFILES = {
    regular:  { width: 1920, height: 1080, vertical: false }, // original default, unchanged
    tv:       { width: 1440, height: 810,  vertical: false }, // ~33% bigger, same layout — for viewing from across a room
    vertical: { width: 1080, height: 1920, vertical: true },  // portrait-mounted screen
};

let currentDisplayMode = localStorage.getItem('displayMode') || 'regular';
if (!DISPLAY_PROFILES[currentDisplayMode]) currentDisplayMode = 'regular';
let leaderboardOnly = localStorage.getItem('leaderboardOnly') === 'true';

function fitStage() {
    const stage = document.getElementById('stage');
    if (!stage) return;
    const profile = DISPLAY_PROFILES[currentDisplayMode];
    stage.style.width = profile.width + 'px';
    stage.style.height = profile.height + 'px';
    const scale = Math.min(
        window.innerWidth / profile.width,
        window.innerHeight / profile.height
    );
    stage.style.transform = `scale(${scale})`;
}

/**
 * Switches display mode (tv / regular / vertical), persists the choice
 * per-device (localStorage, same pattern as speechEnabled — each
 * physical screen remembers its own setting independently), and
 * re-fits/re-renders for the new canvas size.
 */
function applyDisplayMode(mode) {
    if (!DISPLAY_PROFILES[mode]) return;
    currentDisplayMode = mode;
    localStorage.setItem('displayMode', mode);
    document.getElementById('stage').classList.toggle('stage-vertical', DISPLAY_PROFILES[mode].vertical);
    document.querySelectorAll('.display-mode-bar [data-mode]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.mode === mode);
    });
    fitStage();
    renderMatches(currentMatchesCache); // grid column count depends on the mode
}

/**
 * Toggles a screen between the normal dashboard and a leaderboard-only
 * view (matches/idle/commentary hidden, standings fill the whole
 * screen) — for events running a second dedicated standings screen.
 */
function applyLeaderboardOnly(on) {
    leaderboardOnly = on;
    localStorage.setItem('leaderboardOnly', on ? 'true' : 'false');
    document.getElementById('stage').classList.toggle('leaderboard-only-mode', on);
    document.getElementById('btnLeaderboardOnly').classList.toggle('active', on);
    fitLeaderboard(document.querySelectorAll('#leaderboardBody tr').length);
}

/**
 * The dashboard is a kiosk display (Raspberry Pi driving a TV, mouse
 * only, nobody can scroll it) — this locks out page scrolling entirely.
 * Applied via JS rather than a shared CSS rule so it can never
 * accidentally leak into admin.php/leaderboard.php, which DO need
 * normal scrolling (they're used on a laptop/phone).
 */
function disablePageScroll() {
    document.documentElement.style.height = '100%';
    document.documentElement.style.overflow = 'hidden';
    document.body.style.height = '100%';
    document.body.style.margin = '0';
    document.body.style.overflow = 'hidden';
}

document.addEventListener('DOMContentLoaded', () => {
    disablePageScroll();
    document.getElementById('stage').classList.toggle('stage-vertical', DISPLAY_PROFILES[currentDisplayMode].vertical);
    document.getElementById('stage').classList.toggle('leaderboard-only-mode', leaderboardOnly);
    fitStage();
    window.addEventListener('resize', fitStage);

    document.querySelectorAll('.display-mode-bar [data-mode]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.mode === currentDisplayMode);
        btn.addEventListener('click', () => applyDisplayMode(btn.dataset.mode));
    });
    const lbBtn = document.getElementById('btnLeaderboardOnly');
    lbBtn.classList.toggle('active', leaderboardOnly);
    lbBtn.addEventListener('click', () => applyLeaderboardOnly(!leaderboardOnly));

    renderCommentary();

    const speechBtn = document.getElementById('btnToggleSpeech');
    speechBtn.textContent = speechEnabled ? '🔊' : '🔇';
    speechBtn.addEventListener('click', () => {
        speechEnabled = !speechEnabled;
        localStorage.setItem('speechEnabled', speechEnabled ? 'true' : 'false');
        speechBtn.textContent = speechEnabled ? '🔊' : '🔇';
        if (speechEnabled) {
            // Nudge the browser's speech engine awake on this user click,
            // and load the voice list (some browsers populate it lazily).
            window.speechSynthesis.getVoices();
            speak('Commentaar staat aan!');
        } else {
            window.speechSynthesis.cancel();
        }
    });

    startLocalTicking();
    refresh();
    setInterval(refresh, 2000);
});