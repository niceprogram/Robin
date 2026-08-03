function renderLeaderboard(rows) {
    const body = document.getElementById('lbBody');
    body.innerHTML = rows.map(r => `
        <tr>
            <td>${r.rank}</td>
            <td>${r.name}</td>
            <td>${r.points}</td>
            <td>${r.wins}</td>
            <td>${r.losses}</td>
            <td>${r.draws}</td>
            <td>${r.faults}</td>
            <td>${r.judge_count}</td>
            <td>${r.games_played}</td>
            <td>${r.idle_count}</td>
        </tr>
    `).join('');
}

async function refresh() {
    try {
        const res = await fetch('api/get_leaderboard.php', { cache: 'no-store' });
        const data = await res.json();
        renderLeaderboard(data.leaderboard);
    } catch (e) {
        console.error('Leaderboard refresh failed', e);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    refresh();
    setInterval(refresh, 5000);
});
