# Deploying to Render + Aiven MySQL

The site runs as a Docker container on **Render** (free web service) with its
database on **Aiven** (free MySQL). After the one-time setup, every
`git push` to `master` redeploys the site automatically.

---

## 1. Database (Aiven) — once

1. Create an account at aiven.io and create a **MySQL** service on the free plan.
2. Wait until the service status shows **Running**. Its hostname doesn't exist
   in DNS before then, so connections fail with "Unknown MySQL server host".
3. On the service page, note **Host**, **Port**, **User** (`avnadmin`) and
   **Password**, and save the **CA certificate** as `Downloads\aiven-ca.pem`.
4. Export your local database from Command Prompt. The file stays outside the
   project because it contains user data and must never be committed. Use a
   fresh export: an old phpMyAdmin dump will be missing newer tables.

   ```bat
   C:\wamp64\bin\mysql\mysql9.1.0\bin\mysqldump.exe -u root --single-transaction --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 masterpiecemovie > %USERPROFILE%\Downloads\masterpiecemovie-deploy.sql
   ```

5. Import it into Aiven (replace HOST and PORT). It asks for the password.

   ```bat
   C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h HOST -P PORT -u avnadmin -p --ssl-mode=VERIFY_CA --ssl-ca=%USERPROFILE%\Downloads\aiven-ca.pem defaultdb < %USERPROFILE%\Downloads\masterpiecemovie-deploy.sql
   ```

## 2. Web service (Render) — once

1. Commit and push the deployment files (see step 3).
2. In the Render dashboard: **New > Blueprint**, connect GitHub, pick this repo.
   Render reads `render.yaml` and asks for the secret values:
   `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `TMDB_API_KEY`, `GROQ_API_KEY`.
3. Deploy. The first build takes a few minutes. Check **Logs** if it fails.

The database certificate is bundled in the image as `docker/db-ca.pem`, so no
Secret File is needed. It's Aiven's public CA certificate, not a secret. If you
ever create a new Aiven project, replace that file with the new project's
certificate.

## 3. Every update

```bat
git add -A
git commit -m "Describe the change"
git push
```

Render rebuilds and redeploys on its own.

Check `git status` before `git add -A`: scratch files such as `check_db.php`,
`test_*.php` and any `.sql` export should not be committed.

---

## What the free plan changes

| Behaviour | Why |
|---|---|
| First visit after ~15 minutes idle takes up to a minute | Free services sleep when unused |
| TMDB cache and uploaded avatars reset on each deploy or restart | The container disk isn't permanent. The cache refills itself. |
| Logins survive restarts | Remember-me tokens live in the database |
| Ingestion, transcoding and migrations can't run | No shell access and no ffmpeg in the web image |
