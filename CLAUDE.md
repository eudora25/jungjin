# Jungjin_project — 정진팜 실적관리 시스템 (Laravel 재구축)

## 프로젝트 개요
- **정진팜(신일셋) 실적관리 시스템**을 **Laravel 11 + Inertia + Vue 3 + PrimeVue (Sakai 템플릿)** 스택으로 재구축하는 프로젝트입니다.
- 기존 CodeIgniter 3 시스템에서 이관합니다.

## 📘 반드시 먼저 읽어야 할 문서

> 전체 목록·폴더 설명: [`docs/README.md`](docs/README.md)  
> **사용자 매뉴얼**(운영·교육): [`docs/manual/USER_MANUAL.md`](docs/manual/USER_MANUAL.md)
- **`docs/planning/PRODUCT_SPEC.md`**: 서비스 기획서 (전체 기능 명세 + 향후 계획) ← 전체 그림은 여기서
- **`docs/onboarding/HANDOFF.md`**: 프로젝트 핸드오프(배경/스택/원칙/이관 가이드)
- **`docs/planning/ROADMAP.md`**: 작업 진행 보드 (완료·예정 항목 추적)
- **`docs/modules/product/PRODUCT_MANAGEMENT.md`**: 제품 관리(Phase 1~3) 설계 + 변경 로그
- **`docs/modules/product/DRUG_DOMAIN_REDESIGN.md`**: 의약품 도메인 재설계(GAP-11) — 공유 마스터+제약사 취급품 (Pample 차용, 설계중)
- **`docs/modules/performance-settlement/PERFORMANCE_SETTLEMENT.md`**: 실적·정산(Phase 4) 설계 + 변경 로그
- **`docs/modules/client/CLIENT_MANAGEMENT.md`**: 약국·병원·영업사원 마스터(M4) 설계 + 변경 로그
- **`docs/modules/master-data/MASTER_DATA_ADMIN.md`**: 기준정보 마스터 admin 분리(GAP-9) — 병의원·약국·의약품을 거래처와 독립 마스터로
- **`docs/modules/tenancy/MULTI_TENANCY.md`**: 멀티테넌시(GAP-10, XL) — 제약사 테넌트 + `super_admin`/`admin`/`sales` 역할 계층 + 데이터 격리 (**구현중**: MT-1~7 완료(격리 회귀 통과), 다음 MT-8/마스터 CRUD)
- **`docs/modules/reports/MONTHLY_REPORT.md`**: 월간 보고서(GAP-6) 설계·스펙 (거래처/영업사원/제품 요약 Excel)

## 참조 경로
- **기존 CI3 프로젝트**: `/Users/hoony/Desktop/dev-work/shinilset_project/`
  - DB 스키마: `source/sql/`
  - 기존 모델: `source/application/models/`
  - 비즈니스 문서: `doc/` 폴더
  - AdminKit 템플릿: `doc/adminkit-3.1.0/`
- **신규 프로젝트 (현재)**: `/Users/hoony/Desktop/dev-work/Jungjin_project/`

## 현재 상태
- Laravel 11 + Breeze(Inertia/Vue/TS/SSR) + PrimeVue(Sakai) 기반으로 **핵심 기능 구현 완료**:
  - 마일스톤 M1~M3·M5 🟢 완료 / M4 🟡 MVP 완료(레거시 import 미완) / M6 ⚪ 대기
  - 제품 관리(Phase 1~3): 마스터/가격 이력/CSV/거래처 예외/첨부/Audit/NIMS 채널
  - 실적·정산(Phase 4): 실적 등록·목록·상세·워크플로 + 정산 생성/재계산 + Excel·PDF 내보내기 + UI + Pest 테스트
  - 클라이언트 마스터(M4 MVP): 제품·거래처·약국·병원 마스터 + 자동완성. **레거시 데이터 최종 import는 미완(OPS-7)**
  - 운영 품질 개선(QA-1~14): 보안 강화, race condition 수정, 미승인 거래처 차단, flash 전역 연결 등
  - 인프라(OPS-1~5): Scheduler 단일 cron / Queue worker(supervisor) / DB 백업 / `.env` 비밀 관리 / GitHub Actions CI
  - GAP-1: 목표 관리 (`sales_quotas`, `SalesQuotaController`, `QuotaAchievementService`, `SalesQuotas/Index.vue`, 대시보드 연동)
  - GAP-2: 실적 증빙 파일 첨부 (`performance_files`, `PerformanceFileController`, `uploadFile` Policy)
  - GAP-3: 영업사원별 수수료 명세 (`CommissionSummaryController`, `/commission-summary`, Excel/PDF)
  - GAP-4: 영업사원-거래처 담당 배정 — 🟢 **완료** (`CompanySalesAssignment` 모델/Policy/Controller + 거래처 상세 배정 UI + Sales 대시보드 카드 + 검색 필터)
  - GAP-5: 지급 관리 고도화 — 🟢 **완료** (정산 `paid_on/payment_method/batch_no/note` + `settlement_payment_files` 증빙 + Show 지급 모달 + Excel/PDF 헤더 + Batch 필터)
  - GAP-6: 월간 보고서 — 🟢 **완료** (`MonthlyReportService`/`MonthlyReportExcelExporter`/`ReportsController` + `Reports/Monthly.vue` 3탭(거래처/영업사원/제품) + Policy/Gate + 1파일 3시트 Excel)
  - P2-8: 영업사원 대시보드 보강 — 🟢 **완료** (`myMonthlyChart`/`recentRejected` prop + Chart(line)·반려 카드·draft 바로가기)
  - GAP-9: 기준정보 마스터 admin 분리 — 🟢 **완료** ("마스터 관리" 메뉴 그룹 + `/master-data` 허브 + 약국·병원 상세 거래처 읽기 표시. 라우트 불변)
  - GAP-10 MT-1: 멀티테넌시 스키마 토대 — 🟢 **완료** (`tenants` + `Tenant` 모델 + role enum `super_admin` + `users.tenant_id`)
  - GAP-10 MT-2(1부): 기본 제약사 시드 + users 백필 — 🟢 **완료**
  - GAP-10 MT-4: 도메인 5개 테이블 `tenant_id` 부착(nullable)+FK+백필 — 🟢 **완료** (NOT NULL 전환은 MT-3 자동주입 후)
  - GAP-10 MT-6: super_admin 전용 `/platform/*` 영역 — 🟢 **완료** (제약사 CRUD + 제약사 admin 생성(위임형) + 의약품·병의원·약국·사용자 **전역 목록**(제약사 칸) + `tenancy:make-super-admin` + "플랫폼" 메뉴/대시보드 리다이렉트. 마스터 CRUD 는 후속)
  - GAP-10 MT-3: 테넌트 격리 엔진 — 🟢 **완료** (`TenantContext`+`TenantScope`+`BelongsToTenant`+`ResolveTenant` 미들웨어. admin/sales 자사 격리·super_admin 전역. 회귀 0. NOT NULL 전환(MT-4-finalize)은 선행조건 있음)
  - GAP-10 MT-5: 테넌트 권한 게이트 — 🟢 **완료** (단일 `Gate::before`: super_admin 전체 통과 + 교차 테넌트 거부 + null 테넌트 위임)
  - GAP-10 MT-7: 테넌트 격리 회귀 — 🟢 **완료** (`TenantIsolationTest` 8 — 거래처·실적·정산·목표 목록 자사 격리 / 교차 테넌트 상세 403 / 생성 자동주입. 회귀 0)
  - GAP-10 마스터 CRUD(1차): `/platform` 약국·병의원 전역 CRUD + 공공데이터 CSV 일괄 등록 — 🟢 **완료** (super_admin 직접 관리, 기존 Form Request·import 서비스 재사용. samples CSV 검증. 의약품·사용자 CRUD는 후속)
  - GAP-10 role 리네임 + 코드 테이블 — 🟢 **완료**: `users.role` = **`platform`/`pharma`/`cso`** (구 super_admin/admin/sales), 헬퍼 `isPlatform/isPharma/isCso`. 코드 의미는 `code_definitions`(group_code=`user_role`) 테이블에 저장·조회. tenant_id=null 로 구분 안 함
  - GAP-10 임퍼서네이션 — 🟢 **완료**: platform 이 제약사로 "진입"(세션) → 그 테넌트 스코프로 운영 화면 사용 + 상단 배너/진입 종료. `Platform\TenantController::enter/exit`, `User::managesCurrentTenant()`, AppMenu 진입 인지
  - GAP-10 MT-8 변경요청 워크플로(백엔드) — 🟡 **백엔드 완료**: 공유 마스터(약국·병의원) pharma 직접 쓰기 차단(platform 전용) + 변경요청 제출(pharma)→검토·승인/반려(platform) 반영. `master_change_requests`, `MasterChangeRequestService`, `MasterChangeRequestPolicy`. UI(요청 폼·검토 화면)는 후속
- 테스트: `./vendor/bin/sail test` 기준 **350개 전체 통과** (2026-05-29)
- CI: `.github/workflows/ci.yml` (GitHub Actions — MariaDB + Pint + Pest + Vite build)

## 남은 작업 (요약 — 상세는 `docs/planning/ROADMAP.md` §3)
- **운영 경로 B 확정** — **GAP-10 멀티테넌시(MT-1~)** 선행 → MT-7 격리 검증 후 **OPS-6·OPS-7** cutover
- **Now**: ~~MT-1~7 + 약국·병의원 CRUD + role 리네임 + 임퍼서네이션~~ 🟢 → **MT-8(변경요청 워크플로)** / 사용자·의약품 CRUD / MT-4-finalize(NOT NULL §6.2)
- **Later**: P2-1~4(소규모)·GAP-7/8·OPS-7·M6 알림

## 빌드 주의사항
- **Vite 빌드는 호스트(Mac)에서** 실행: `npm run build` — Sail 컨테이너 내부는 rollup linux arm64 native 모듈 미포함으로 빌드 실패
- 새 Vue 페이지 추가 후 테스트 전에 반드시 `npm run build` 실행 필요 (manifest 미등록 시 Inertia 렌더 500 오류)

## 작업 시작 시
1. `docs/planning/ROADMAP.md` §3 확인 (현재: **경로 B** — GAP-10 **MT-8/마스터 CRUD**부터, `docs/modules/tenancy/MULTI_TENANCY.md` §6.1~§6.4)
2. 모듈 설계/결정이 필요하면 `docs/modules/product/PRODUCT_MANAGEMENT.md`, `docs/modules/performance-settlement/PERFORMANCE_SETTLEMENT.md` 참고
3. 구현 변경 후 `./vendor/bin/sail test`로 회귀 확인
4. 새 Vue 페이지 추가 시 `npm run build` (호스트에서) → 그 다음 테스트

## 확정된 기술 스택
- Laravel 11 (PHP 8.2+)
- Inertia.js + Vue 3 (Composition API, TypeScript)
- Laravel Breeze (Inertia-Vue 스캐폴딩)
- PrimeVue + Sakai 템플릿 (MIT)
- Tailwind CSS + Vite
- Pinia (상태관리)
- MariaDB (기존 DB 재활용)
- Pest (테스트), Playwright (E2E)

## 개발 원칙 (요약)
- 기존 CI3 코드 **포팅 금지**, 재설계·재작성
- 테스트 먼저 (Pest Feature)
- Eloquent 관계·Policy·Form Request 적극 활용
- 무거운 작업은 Queue, 반복은 Scheduler
- 상세 원칙은 HANDOFF.md 섹션 9 참조
