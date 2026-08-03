# Community Center Tournament Manager

A dynamic 1v1 tournament system for kids' events: PHP 8 + MySQL/MariaDB +
Bootstrap 5, built to run on a single laptop (server + TV display) with no
internet dependency for scoring (Bootstrap/JS libs load from CDN — the
project assumes internet is available, per the brief).

## 1. Installation

This build targets a **shared hosting account** (cPanel / iFastNet) with
an existing database called `kvannl_Zebra`. Every table in this project
is prefixed `tournament_` (`tournament_players`, `tournament_matches`,
etc.) so it can safely live alongside other tables/projects in that same
database.

1. Upload the whole `tournament/` folder to your hosting account (e.g.
   via cPanel File Manager or FTP), typically into `public_html/tournament/`.
2. In cPanel, go to **phpMyAdmin**, select the existing `kvannl_Zebra`
   database, and import, in order:
   1. `sql/schema.sql` — creates all six `tournament_*` tables inside
      `kvannl_Zebra`. (This file intentionally does **not** run
      `CREATE DATABASE` — shared hosting accounts normally can't create
      new databases, and `kvannl_Zebra` already exists.)
   2. `sql/seed_14_players.sql` — optional sample data (14 children).
3. In cPanel under **Databases → MySQL Databases**, confirm which MySQL
   user is attached to `kvannl_Zebra` (shared hosting usernames are
   prefixed too, e.g. `kvannl_something`) and make sure that user has
   **All Privileges** on it.
4. Edit `includes/config.php` and fill in that user's username/password:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'kvannl_Zebra');
   define('DB_USER', 'kvannl_your_actual_user');
   define('DB_PASS', 'your_actual_password');
   ```
5. Visit `https://yourdomain/tournament/` (or wherever you uploaded it)
   — this is the launcher page linking to the three screens:
   - `admin.php` — the score desk (use on the laptop)
   - `dashboard.php` — the TV display (open fullscreen, e.g. press F11)
   - `leaderboard.php` — full standings table

**Running locally on XAMPP instead?** Everything still works — just
create a local database also named `kvannl_Zebra` (or rename it in both
phpMyAdmin and `includes/config.php` — they just need to match), import
the same two SQL files, and use `root` / empty password as usual.

No `composer install` or build step is required — everything is plain
PHP + vanilla JS + Bootstrap 5 via CDN.

## 2. Running an event

1. On the laptop, open `admin.php`.
2. Add/edit participants under **Manage participants** if the seed data
   doesn't match your actual roster (or start with an empty `players`
   table and add everyone by hand).
3. Click **Start tournament** — this generates Round 1 automatically.
4. Put `dashboard.php` fullscreen on the TV.
5. As judges report results at the laptop, click the big **"X won"**
   button for the winner (or **Draw** / **Faulty game** if applicable).
6. Once every match in the round has a result, **Start next round**
   becomes clickable — the next round is generated automatically.
7. Repeat for as many rounds as your event schedule allows. There's no
   fixed number of rounds; run as many as time permits.
8. Kids can be added mid-event any time via **Manage participants** — they
   are automatically included starting from the very next round generated.
   A participant who leaves early can be toggled off the same way and
   will simply stop being scheduled (their existing points stay on the
   board).
9. **Correct result** on any already-decided match reopens it and lets you
   change/fix the outcome (e.g. a judge made an error).
10. **Kids can also report results themselves, directly on the TV/laptop.**
    Every "Playing…" match card on `dashboard.php` is tappable — tapping
    it opens a full-screen result screen for just that match (big win
    buttons + Draw/Faulty game), shows "✅ Thank you!" after a result is
    picked, and returns to the main dashboard view after about a second.
    This is in addition to, not instead of, the admin screen's buttons —
    either the judge (at the TV) or the adult (at `admin.php`) can enter
    the result, whichever is more convenient.

## 2a. Planning rounds around a time limit

If you want the event to fit a hard time budget (e.g. "20 kids, 2 hours
max"), use the two fields at the bottom of the control bar on `admin.php`:

- **Planned rounds** — type a number directly (e.g. `5`) and click
  **Save**. This drives the "Round X of Y" and "~N minutes left" display
  on the dashboard. It's purely informational — the scheduler doesn't
  stop itself at this number, you can always click "Start next round"
  again even past it.
- **Event length (min)** — type your total available minutes (e.g.
  `120`) and click **Calculate & save**. This divides your event length
  by the round duration (7 minutes by default) and fills in/saves a
  suggested **Planned rounds** value for you (`120 ÷ 7 ≈ 17 rounds`).

Worth knowing: that calculation is round-length only — it doesn't add
time for the gap between rounds (judges reporting results, kids walking
to their next station). For a genuinely hard 2-hour cutoff, plan for a
bit fewer rounds than the raw math suggests, or shorten the round
duration (`round_duration_seconds` in `tournament_settings`) to leave
buffer room.

You can also change the round length itself at any time — even in the
middle of a tournament — using **Ronde duur (min)** in the same control
bar. This only affects rounds generated from that point on; whichever
round is currently in progress keeps running with the duration it
already started with, so you never lose or gain time on a live timer.

## 2b. Starting a brand new tournament (reset)

To wipe an event completely and start everyone back at zero, use
**🔄 Toernooi resetten** on `admin.php`. It's a two-step safety check:
you have to type `RESET` into a text box before the confirm button even
becomes clickable, so it can't be triggered by an accidental click.

There's also a checkbox, **"Ook alle deelnemers verwijderen"**
(also remove all participants):

- **Unchecked** (default): clears every round/match/result and puts the
  tournament back to "not started," but keeps your existing roster of
  names — handy if you're running a second tournament with the same
  group of kids right after the first.
- **Checked**: also deletes every player, so you can add a completely
  new set of names for a totally different group.

This is permanent and cannot be undone — there's no "undo reset" button,
only the typed confirmation before it runs.

## 2c. Dutch interface

Every page (`dashboard.php`, `admin.php`, `leaderboard.php`, `index.php`),
all on-screen buttons/labels/messages, and the 10 station names are now
in Dutch (e.g. *Airhockey, Armworstelen, Autoracen, Dammen, Tafeltennis,
Op één been staan, Pingpongbekers, Minigolf* — *Darts* and *Uno* are the
same word in both languages).

**If you already imported the original English schema before this
update**, run `sql/migrate_dutch_stations.sql` once against your existing
`kvannl_Zebra` database (via phpMyAdmin's Import tab) to rename your
existing station rows to their Dutch equivalents. It's safe to run even
if you're not sure whether you need it — every statement only touches
rows that still have the old English name and leaves everything else
untouched.

## 3. Scoring rules (as specified)

| Outcome            | Player A | Player B | Judge |
|---------------------|:--------:|:--------:|:-----:|
| A wins              | 3        | 1        | 2     |
| B wins              | 1        | 3        | 2     |
| Draw                | 2        | 2        | 2     |
| Faulty game         | 0        | 0        | 2     |

*(Assumption: the judge always receives 2 points for judging, regardless
of the outcome — the brief specifies "Judge = 2" as a flat rule, and the
draw/fault lines only redefine the two players' points.)*

## 4. The scheduling algorithm

The scheduler (`includes/scheduler.php`) builds **one round at a time**,
on demand, rather than pre-computing the whole tournament in advance. This
is the key design decision that satisfies "recalculate future rounds if
participants are added or removed" — since future rounds don't exist yet
until you click "Start next round", there is nothing stale to recompute;
every new round is always built from whoever is active *right now* and
their *current* play/judge/idle counts.

For each round:

1. **Detect the roster.** Count `N` = currently active players.
2. **Work out match capacity.** Each match needs exactly 3 kids (2 players
   + 1 judge), so the maximum number of simultaneous matches is
   `floor(N / 3)`, further capped by the number of active stations (10).
   Any remainder (`N mod 3`, i.e. 0, 1 or 2 kids) rests that round.
3. **Pick who plays.** All active players are randomly shuffled (so ties
   break differently every round) then sorted by **play_count ascending**
   — whoever has played the fewest games so far gets first claim on a
   playing slot this round. The top `2 × matchCount` become "players this
   round". This is what keeps play counts balanced across the whole
   event.
4. **Pick who judges.** From whoever is left over, the same
   shuffle-then-sort trick is applied to **judge_count** — the kids who
   have judged the least get first claim on a judging slot. The top
   `matchCount` become judges. Because the judge pool is, by
   construction, completely disjoint from the "players this round" pool,
   **no child can ever be assigned to judge their own match**.
5. **Whoever's left rests.** The remainder — after slots for playing and
   judging are filled — sits out ("idle") for the round. Because steps 3
   and 4 start from a fresh random shuffle every round, idle duty rotates
   fairly rather than always falling on the same few kids.
6. **Pair the players (Swiss-style).** The "players this round" group is
   ranked by total points so far (highest first), then paired off using a
   classic Swiss pairing with backtracking: for each player, try every
   remaining player who has **never played them before**; if pairing them
   makes the rest of the group impossible to pair validly, backtrack and
   try the next candidate. Only if a completely repeat-free pairing is
   truly impossible does the algorithm fall back to allowing a repeat
   match (choosing the pair with the fewest prior meetings). Matches
   flagged as a rematch are marked `repeat_opponents: true` in the API
   response (shown with a `[REMATCH]` tag on the dashboard/preview).
7. **Assign stations and judges.** Active stations and the chosen judges
   are each shuffled and zipped onto the list of pairs in order.

### Live "next round preview"

`Scheduler::planNextRound()` runs the exact same logic as
`generateNextRound()` but **without writing anything to the database**.
The dashboard calls this on every refresh to show a "Next round preview"
so kids can see roughly who/where they're up next while the current round
is still playing. Because it's a live projection based on current
(possibly still-incomplete) results, it can shift slightly right up until
the admin actually clicks "Start next round" — this is called out in the
UI as a preview, not a guarantee.

### Why on-demand generation, not a pre-built Swiss bracket?

A full pre-generated multi-round bracket (like classic Swiss tournaments
compute) can't adapt cleanly to a child joining in round 4 or leaving
after round 6 — you'd have to regenerate everything downstream anyway,
which is exactly the same amount of work as just generating each round
when it's needed. Building one round at a time is simpler, is provably
always up to date, and matches how the event is actually run (one round
at a time, on a single laptop, by hand).

## 5. Database schema

See `sql/schema.sql` for the full definition. All tables live in the
existing `kvannl_Zebra` database and are prefixed `tournament_`. Summary:

- **tournament_players** — id, name, active flag, created_at.
- **tournament_stations** — id, name, active flag (the 10 stations from
  the brief).
- **tournament_rounds** — id, round_number, status (`active` / `paused` /
  `completed`), duration_seconds, started_at, ends_at,
  paused_remaining_seconds.
- **tournament_matches** — round_id, station_id, player_a_id,
  player_b_id, judge_id, result_type (`pending` / `win_a` / `win_b` /
  `draw` / `fault`), points_a/points_b/points_judge, decided_at. Foreign
  keys to tournament_rounds, tournament_stations and tournament_players;
  indexes on round/player/judge/station/result.
- **tournament_round_idles** — which players rested in which round (used
  to keep idle duty balanced). Unique on (round_id, player_id).
- **tournament_settings** — simple key/value store (`tournament_status`,
  `current_round_id`, `round_duration_seconds`).

Play/judge counts used by the scheduler and the leaderboard are **not**
duplicated/cached anywhere — they're derived by aggregating the `matches`
and `round_idles` tables every time (`includes/functions.php ::
getPlayerStats()`), so there is no risk of the numbers drifting out of
sync. With at most ~24 players and a few hundred matches over an event,
this is more than fast enough for a 2-second dashboard refresh.

## 6. Timer handling

Each round stores `started_at` and `ends_at` (`ends_at` = `started_at` +
7 minutes). The dashboard/admin pages ask the server "how many seconds
are left" on every poll; that calculation is done **inside MySQL**
(`TIMESTAMPDIFF(SECOND, NOW(), ends_at)`) rather than in PHP, specifically
to avoid any mismatch between PHP's and MySQL's configured timezones
(a real bug caught and fixed while testing this build — see
`includes/functions.php :: remainingSeconds()`). Pausing snapshots the
remaining seconds into `paused_remaining_seconds`; resuming recomputes a
fresh `ends_at` from "now".

## 7. Folder structure

```
tournament/
├── index.php                 Landing page (links to the 3 screens)
├── dashboard.php              TV screen
├── admin.php                  Score desk
├── leaderboard.php             Full standings page
├── includes/
│   ├── config.php             DB credentials / constants
│   ├── db.php                 PDO connection helper
│   ├── functions.php           Settings, stats, scoring, queries
│   └── scheduler.php           Swiss-style balanced round scheduler
├── api/
│   ├── get_dashboard_data.php  TV polling endpoint
│   ├── get_admin_data.php      Score desk polling endpoint
│   ├── get_leaderboard.php     Standings endpoint
│   ├── submit_result.php       Record a match result
│   ├── correct_result.php      Amend a match result
│   ├── start_tournament.php    Generates round 1
│   ├── start_next_round.php    Generates the next round
│   ├── pause_timer.php         Pause/resume the round clock
│   ├── add_participant.php     Add a child mid-event
│   ├── toggle_participant.php  Activate/deactivate a child
│   ├── set_total_rounds.php    Set the planned-rounds target
│   ├── set_round_duration.php  Change round length (mid-tournament OK)
│   └── reset_tournament.php    Wipe rounds/matches (optionally players)
├── assets/
│   ├── css/style.css
│   └── js/{dashboard,admin,leaderboard}.js
├── sql/
│   ├── schema.sql                   Full schema (run first)
│   ├── seed_14_players.sql          Sample data (14 children)
│   └── migrate_dutch_stations.sql   Run once if upgrading an existing DB
└── docs/
    ├── simulate_schedule.php        CLI demo: simulates N rounds end-to-end
    └── sample_10_round_schedule.txt A real generated 10-round example
```

## 8. Sample generated schedule

`docs/sample_10_round_schedule.txt` contains a real 10-round run of the
scheduler against the 14-player seed data (pairings, judges, stations,
final leaderboard, and a fairness check). It was produced by running:

```
php docs/simulate_schedule.php 10
```

From that real run, over 10 rounds / 14 players / 40 matches:

- **play_count** spread across all players: min 5, max 6 (spread of 1)
- **judge_count** spread: min 2, max 4 (spread of 2)
- **idle_count** spread: min 0, max 2 (spread of 2)
- Only **2 rematches** out of 40 matches (both in the final couple of
  rounds, when the pool of fresh opponents naturally runs low)

You can re-run this script yourself at any time (against a fresh import
of `schema.sql` + `seed_14_players.sql`) to see a different random
run — no two runs will be identical because idle/tie-break order is
shuffled every round.

## 9. Notes / assumptions

- Judge points are always 2, regardless of the match outcome (see
  section 3).
- Station rotation is a random shuffle each round rather than a strict
  per-player rotation ledger — with only 10 stations shared across up to
  8 simultaneous matches, this keeps things simple while still avoiding
  the same pairing of players+station repeating predictably.
- The database ships with the project already reset to a fresh
  "not started" state — `sql/schema.sql` creates everything, and
  `sql/seed_14_players.sql` is optional sample data you can skip if you'd
  rather add your own roster from the admin screen.
