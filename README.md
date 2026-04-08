# Tracker — setup and updates

## Fresh install

1. Add a file named `.env` in the project root (same folder as `index.php`).

2. Copy the template below and fill in your values. Use the variable names exactly as shown (e.g. `DOMAIN_START`, not `$DOMAIN_START`).

```
#---------------------------------------------

# Database connection
#DO NOT CHANGE
DB_HOST=localhost

#Database name
DB_NAME=

#Database username
DB_USER=

#Database password
DB_PASS=

# Optional: lock the admin UI (index.php). Public endpoints under public/ are not affected.
# Generate a long random string. First visit: index.php?tracker_token=YOUR_TOKEN
TRACKER_ACCESS_TOKEN=
# Optional: comma-separated IPs allowed when token is set (IPv4/IPv6), e.g. 127.0.0.1,::1
TRACKER_ALLOWED_IPS=
# Optional: behind a reverse proxy, set to X_FORWARDED_FOR so IP allowlist uses the real client IP
# TRACKER_IP_SOURCE=X_FORWARDED_FOR

# Base URL for redirects
DOMAIN_START=
BASE_URL=

# Bot for messages
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
#---------------------------------------------
```

3. Create a MySQL database and user matching `DB_*`, then **run database migrations** once:

   - **CLI (recommended):** from the project root, run  
     `php src/migrations/migrate.php`  
     (or `php src/migrations/migrate.php up`)

   - **Browser:** open `src/migrations/migrate.php` on your site (same as you would any PHP URL under the docroot).

   Re-run after updates; only pending migration files are applied.

4. Create a Telegram bot by searching **BotFather**.

   - Type: `/start` then `/newbot`, follow prompts for name and username.
   - Open the chat with the bot, copy the token → `TELEGRAM_BOT_TOKEN`.
   - In the browser open: `https://api.telegram.org/bot<BOT_TOKEN>/getUpdates` (replace `<BOT_TOKEN>` with your token). You should see `OK`.
   - Send a message to the bot, refresh that URL, find `"chat":{"id":...}` → put that id in `TELEGRAM_CHAT_ID`.

5. **Cron:** schedule the daily job (example: 07:00 server time):

   `0 7 * * * php /full/path/to/project/src/bin/daily_campaign_job.php`

6. If you set `TRACKER_ACCESS_TOKEN`, open the admin UI once with  
   `index.php?tracker_token=YOUR_TOKEN`  
   (from an allowed IP if you configured `TRACKER_ALLOWED_IPS`). After that, a cookie keeps you logged in.

---

## After installing an updated tracker (upgrade)

Do this whenever you deploy a new version over an existing install:

1. **Back up** the database (and your current `.env`) before changing files.

2. **Merge `.env`:** keep your existing secrets and database settings. Add any **new** keys from the template above that your old `.env` is missing (especially `TRACKER_ACCESS_TOKEN`, `TRACKER_ALLOWED_IPS`, and optionally `TRACKER_IP_SOURCE`). If you omit `TRACKER_ACCESS_TOKEN`, the admin UI stays open like older builds.

3. **Run migrations again** (`php src/migrations/migrate.php` or open `migrate.php` in the browser) so new tables/columns match the code.

4. **Cron:** confirm the scheduled task still points to  
   `src/bin/daily_campaign_job.php`  
   (full server path).

5. **Smoke test:** load the dashboard, hit redirect/postback URLs under `public/` if you use them, and verify Telegram alerts if you rely on them.

