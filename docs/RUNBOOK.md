# MediTrack — Production Operations & Runbook

This document details standard operating procedures (SOPs), deployment workflows, backup/restore mechanics, disaster recovery, and incident response for the **MediTrack** platform.

---

## 1. System Components & Port Allocations

| Service | Port / Socket | Responsibility | Health / Metrics Check |
|---|---|---|---|
| **Nginx Reverse Proxy** | `80`, `443` | SSL termination, Static file CDN, rate limiting, request routing | `http://localhost/health` |
| **PHP-FPM 8.3** | `php-fpm:9000` | FastRoute API execution, OPcache preloaded execution | `php-fpm -t`, Monolog JSON logs |
| **MySQL 8.0** | `3306` | Persistent transactional store (InnoDB, UTF8mb4) | `SELECT 1;` via `/health` |
| **Redis 7.x** | `6379` | Token blacklists, catalog cache, atomic Lua rate limits, FIFO queue | `redis-cli ping` |
| **Queue Worker** | CLI daemon | Asynchronous jobs (Dompdf invoice generation, FCM push, SMS) | `php bin/worker.php` |
| **Scheduler** | Cron / CLI | Daily expiry alerts, refill reminders, unpaid order cleanup | `php bin/schedule.php` |

---

## 2. Database Backup & Restore Procedures

### A. Automated Daily MySQL Backup
Backups are performed using `mysqldump` with single-transaction consistency to ensure zero read locks:

```bash
#!/usr/bin/env bash
set -euo pipefail

BACKUP_DIR="/var/backups/meditrack/mysql"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/meditrack_${TIMESTAMP}.sql.gz"

mkdir -p "${BACKUP_DIR}"

# Execute dump with single transaction consistency (InnoDB)
mysqldump \
  --host="${DB_HOST:-127.0.0.1}" \
  --port="${DB_PORT:-3306}" \
  --user="${DB_USERNAME}" \
  --password="${DB_PASSWORD}" \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  "${DB_DATABASE:-meditrack}" | gzip -9 > "${BACKUP_FILE}"

# Enforce 30-day retention policy
find "${BACKUP_DIR}" -type f -name "*.sql.gz" -mtime +30 -delete
echo "[SUCCESS] Backup written to ${BACKUP_FILE}"
```

### B. On-Demand Database Backup via Admin API
Authorized administrators can trigger a server-side backup via:
```http
POST /api/v1/admin/backup
Authorization: Bearer <ADMIN_JWT>
```
The endpoint executes `mysqldump`, generates a password-protected gzip archive, and returns a short-lived signed download URL.

### C. Database Restoration
To restore a snapshot:
```bash
# 1. Uncompress backup
gunzip < /var/backups/meditrack/mysql/meditrack_YYYYMMDD.sql.gz > /tmp/restore.sql

# 2. Restore into target database
mysql \
  --host="${DB_HOST:-127.0.0.1}" \
  --port="${DB_PORT:-3306}" \
  --user="${DB_USERNAME}" \
  --password="${DB_PASSWORD}" \
  "${DB_DATABASE:-meditrack}" < /tmp/restore.sql

# 3. Re-run pending migrations if restoring older snapshot
cd /var/www/backend && php bin/migrate.php

rm /tmp/restore.sql
```

### D. Redis State Persistence
Redis runs with append-only file (AOF) + RDB snapshots enabled:
```bash
# Force background snapshot
redis-cli -h 127.0.0.1 BGSAVE

# Backup RDB file
cp /var/lib/redis/dump.rdb /var/backups/meditrack/redis/dump_$(date +%Y%m%d).rdb
```

---

## 3. Production Deployment Guide

### Deployment Workflow (Zero-Downtime)
1. **Pull Latest Release / Git Artifact**:
   ```bash
   git pull origin main
   ```
2. **Execute Database Migrations**:
   All migrations are strictly backward-compatible.
   ```bash
   cd backend && php bin/migrate.php
   ```
3. **Build Frontend Production Assets**:
   ```bash
   cd frontend
   npm ci
   npm run build
   ```
4. **Reload PHP-FPM OPcache & Containers**:
   ```bash
   # Reload PHP-FPM workers gracefully to compile preloaded classes
   kill -USR2 $(cat /var/run/php-fpm.pid)
   
   # Or reload docker services
   docker compose -f docker-compose.prod.yml up -d --no-deps php-fpm nginx
   ```
5. **Verify System Health**:
   ```bash
   curl -f http://localhost:8080/health
   ```

---

## 4. Rollback Runbook

If a critical fault or high error rate occurs post-deployment:
1. **Rollback Git Release**:
   ```bash
   git checkout PREVIOUS_RELEASE_TAG
   ```
2. **Rollback Database Migrations**:
   If the migration introduced a breaking column or table, execute the corresponding rollback SQL script.
3. **Rebuild Assets & Invalidate Cache**:
   ```bash
   cd frontend && npm run build
   # Invalidate Redis catalog cache keys
   redis-cli -h 127.0.0.1 KEYS "meditrack:*" | xargs redis-cli -h 127.0.0.1 DEL
   # Reload PHP-FPM
   kill -USR2 $(cat /var/run/php-fpm.pid)
   ```

---

## 5. Secret & Key Rotation Procedures

### A. JWT Secret (`JWT_SECRET`)
- To rotate without invalidating user sessions:
  1. Add new secret as primary signing key.
  2. Keep old secret in secondary verification key array for 15 minutes (access token TTL).
  3. After 15 minutes, decommission old secret.

### B. Application Key (`APP_KEY`)
- `APP_KEY` signs prescription download URLs with HMAC SHA-256.
- Signed URLs have a 5-minute lifespan. Rotating `APP_KEY` only invalidates links older than 5 minutes.

---

## 6. Incident Response Playbook

| Symptom | Probable Cause | Immediate Action |
|---|---|---|
| **HTTP 500 Responses** | Database connection exhausted or uncaught exception | Check `storage/logs/app.log` for JSON stack trace + request_id. Verify MySQL max connections (`SHOW PROCESSLIST;`). |
| **HTTP 429 Errors across app** | Misbehaving client IP or bot attack | Inspect Redis rate limiting keys. Whitelist critical IPs or tune `config/rate_limits.php`. |
| **Worker Queue Lagging (>60s)** | Failed SMS/email provider timeout | Check `SELECT COUNT(*) FROM failed_jobs;`. Restart supervisor queue workers: `supervisorctl restart meditrack-worker:*`. |
| **High Memory / Slow Queries** | Missing index or un-indexed search query | Check MySQL slow query log (`/var/log/mysql/slow.log`). Run `EXPLAIN` and verify `004_performance_indexes.sql` was applied. |

