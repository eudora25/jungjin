# 정진팜 프로젝트 셋업·현황 노트

> M1(로그인+대시보드) 시점의 구성·접속 메모를 기반으로, **현행 구현 상태**를 함께 기록합니다.
> 작업 백로그·우선순위는 [`ROADMAP.md`](../planning/ROADMAP.md), 기능 명세는 [`PRODUCT_SPEC.md`](../planning/PRODUCT_SPEC.md) 를 단일 기준으로 합니다.

## 구성

- Laravel 11 + Breeze (Inertia / Vue 3 + TS + Pest + SSR)
- Sail (PHP 8.4) + MariaDB 11 + Redis + Mailpit
- PrimeVue 4 (Aura 테마) + Sakai 5 레이아웃 (Inertia용 포팅)
- Tailwind v4 + Pinia
- `EnsureUserRole` 미들웨어 + `users.role` (`super_admin` / `admin` / `sales`, GAP-10 MT-1~)

## 접속 정보

- 앱: <http://localhost:8088>
- Mailpit: <http://localhost:8025>
- DB: `127.0.0.1:3306` (jungjin / password)
- 관리자: `admin@jungjin.test` / `jungjin1234!`
- 영업(데모): `sales-demo@jungjin.test` / `jungjin1234!` (시드에 따라 다를 수 있음)

## 현재 구현 스냅샷 (2026-05-29)

| 영역 | 상태 | 비고 |
|------|------|------|
| 대시보드 | 🟢 | 관리자/영업 분리 + P2-8(월별 차트·반려·draft 바로가기) |
| 공지 | 🟢 | CRUD, private 첨부, 읽음 현황 |
| 실적·정산 | 🟢 | 워크플로, CSV, 증빙(GAP-2), Excel/PDF, 지급 Batch(GAP-5) |
| 목표·수수료 | 🟢 | GAP-1 `/sales-quotas`, GAP-3 `/commission-summary` |
| 담당 배정 | 🟢 | GAP-4 `company_sales_assignments` |
| 월간 보고서 | 🟢 | GAP-6 `/reports/monthly` |
| 마스터 IA | 🟢 | GAP-9 마스터 관리 메뉴 + `/master-data` |
| 거래처·제품·약국·병원 | 🟡 MVP | **OPS-7** 레거시 최종 import 잔여 |
| 멀티테넌시 | 🟡 진행 | **경로 B** — MT-1·MT-2(1부) 🟢, **Now: MT-3** (TenantScope) |

**테스트**: `./vendor/bin/sail test` → **286개** 전체 통과 (2026-05-29)

**다음 권장 작업**: [`ROADMAP.md`](../planning/ROADMAP.md) §3 — **GAP-10 MT-3** (`TenantScope`·`ResolveTenant`)

## 검증 (로컬)

- `npm run build` (호스트 Mac)
- `sail up` + `sail artisan migrate`
- `sail test`
- 로그인 → `/dashboard` 또는 `/sales/dashboard`

## 관련 문서

- 사용자 매뉴얼: [`manual/USER_MANUAL.md`](../manual/USER_MANUAL.md)
- 운영: [`operations/OPERATIONS.md`](../operations/OPERATIONS.md)
