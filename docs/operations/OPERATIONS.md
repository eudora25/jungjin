# 운영/인프라 실행 가이드 (M6)

## OPS-2 — Queue worker 상시 가동

### 로컬 (Sail)

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan queue:work --sleep=1 --tries=3 --timeout=90
```

### 운영 (Supervisor 예시)

`/etc/supervisor/conf.d/jungjin-queue.conf`

```ini
[program:jungjin-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work --sleep=1 --tries=3 --timeout=90
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue-worker.log
stopwaitsecs=3600
```

적용:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start jungjin-queue:*
```

---

## OPS-1 — Scheduler 단일 cron 등록

Laravel Scheduler는 `routes/console.php`에 등록된 스케줄을 기준으로 실행됩니다.

### 운영 서버 cron (권장: 1분마다)

```bash
* * * * * cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

### 로컬 (테스트)

```bash
php artisan schedule:list
php artisan schedule:run
```

---

## OPS-5 — GitHub Actions CI

- 워크플로 파일: `.github/workflows/ci.yml`
- 수행: MariaDB 서비스 기동 → migrate → Pint(--test) → Pest → `npm run build`

---

## OPS-3 — 백업 정책 (MariaDB 일일 dump + 로테이션)

### 로컬 (Sail)

```bash
./vendor/bin/sail up -d
./scripts/backup_db.sh
./scripts/backup_db.sh jungjin_legacy
```

백업 파일은 기본으로 `storage/backups/db/` 아래에 생성됩니다.

### 운영 cron 예시 (매일 03:10)

```bash
10 3 * * * cd /var/www/html && BACKUP_KEEP_DAYS=14 ./scripts/backup_db.sh >> /var/www/html/storage/logs/db-backup.log 2>&1
```

복구(예시):

```bash
gunzip -c storage/backups/db/jungjin_YYYYMMDD_HHMMSS.sql.gz | mariadb -h 127.0.0.1 -uroot -p jungjin
```

---

## OPS-4 — `.env` 비밀 관리 (운영)

### 원칙

- **`.env`는 커밋하지 않는다** (`.gitignore`에 포함되어 있음)
- 운영에서는 **서버 환경 변수/시크릿 매니저를 통해 주입**하고, 파일로 남기더라도 접근 권한을 최소화한다.

### 최소 권장

- DB/메일/외부 API 키는 운영 서버에서만 주입
- GitHub Actions 등 CI에서는 테스트용 값만 사용 (실제 운영 시크릿은 `GitHub Secrets`로 관리)

### 체크리스트

- [ ] `.env.production` 같은 파일을 저장소에 추가하지 않기
- [ ] 운영 서버에서 `APP_KEY`, `DB_*`, `MAIL_*`, 외부 API 키 주입 방식 문서화

---

## 레거시 스키마 점검 (P0-1 지원 도구)

레거시 DB(`config/database.php`의 `legacy` 커넥션) 스키마를 `information_schema`에서 읽어 JSON으로 덤프합니다.

```bash
./vendor/bin/sail artisan legacy:inspect-schema
./vendor/bin/sail artisan legacy:inspect-schema --tables=pharmacies --tables=hospitals
```

로컬(호스트)에서 직접 실행하려면 `LEGACY_DB_HOST/PORT`가 호스트에서 접근 가능한 값(예: `127.0.0.1` + 포워딩 포트)이어야 합니다.

### 레거시 약국/병원 import

```bash
./vendor/bin/sail artisan legacy:import-pharmacies --dry-run
./vendor/bin/sail artisan legacy:import-pharmacies

./vendor/bin/sail artisan legacy:import-hospitals --dry-run
./vendor/bin/sail artisan legacy:import-hospitals
```

### 레거시 clients/배정 테이블 의미 분석

`client_pharmacy_assignments.client_id`가 “영업사원(User)”인지, 별도 “클라이언트” 엔티티인지 먼저 판별해야 합니다.

```bash
./vendor/bin/sail artisan legacy:analyze-clients
./vendor/bin/sail artisan legacy:analyze-assignments
```

### 레거시 병원↔약국 / 병원↔업체 연결 이관

먼저 신규 마이그레이션 반영:

```bash
./vendor/bin/sail artisan migrate
```

그 다음 dry-run → 실적용:

```bash
./vendor/bin/sail artisan legacy:import-hospital-pharmacy-assignments --dry-run
./vendor/bin/sail artisan legacy:import-hospital-pharmacy-assignments

./vendor/bin/sail artisan legacy:import-hospital-company-assignments --dry-run
./vendor/bin/sail artisan legacy:import-hospital-company-assignments
```

### (선택) 레거시 client_pharmacy_assignments 흡수

레거시에는 `hospital_pharmacy_assignments`와 별개로 `client_pharmacy_assignments`가 존재할 수 있습니다.
분석 결과 `client_id`가 병원을 가리키는 케이스라면 아래 커맨드로 `hospital_pharmacy_assignments`에 흡수합니다(중복은 upsert).

```bash
./vendor/bin/sail artisan legacy:import-client-pharmacy-as-hospital-pharmacy --dry-run
./vendor/bin/sail artisan legacy:import-client-pharmacy-as-hospital-pharmacy
```

---

## 실적↔약국/병원 연동 (옵션 A: companies 통합)

실적은 이미 `performances.company_id → companies`로 연결되어 있으므로,
약국/병원을 `companies`로 동기화해 **실적 입력에서 그대로 사용**합니다.

### 1) 마이그레이션

```bash
./vendor/bin/sail artisan migrate
```

### 2) 동기화 실행 (dry-run 권장)

```bash
./vendor/bin/sail artisan clients:sync-companies --dry-run
./vendor/bin/sail artisan clients:sync-companies
```

필요 시 한쪽만:

```bash
./vendor/bin/sail artisan clients:sync-companies --only=pharmacy
./vendor/bin/sail artisan clients:sync-companies --only=hospital
```

