# 정진팜 — 통합 ROADMAP

> 완료된 작업과 앞으로 남은 작업의 단일 집중 관리 문서입니다.
> 상세 설계/결정/히스토리는 각 모듈 문서의 Progress Log를 참고합니다.
> 각 항목 완료 시 → **상태 체크 + 해당 모듈 문서의 진행 로그에 상세 기록**.

**모듈·운영 설계 문서**

| 문서 | GAP/항목 |
|------|----------|
| [`MONTHLY_REPORT.md`](../modules/reports/MONTHLY_REPORT.md) | GAP-6 월간 보고서 |
| [`MASTER_DATA_ADMIN.md`](../modules/master-data/MASTER_DATA_ADMIN.md) | GAP-9 마스터 관리 IA |
| [`MULTI_TENANCY.md`](../modules/tenancy/MULTI_TENANCY.md) | GAP-10 멀티테넌시 |
| [`USER_MANUAL.md`](../manual/USER_MANUAL.md) | 사용자 매뉴얼(운영·교육) |
| [`OPERATIONS.md`](../operations/OPERATIONS.md) | OPS 인프라·cutover |

---

## 0. 표기 규약

- **우선순위 P0~P3**
  - **P0** — 운영 블로커 (없으면 정상 사용 불가)
  - **P1** — 핵심 가치 (영업/정산팀이 자주 쓰는 기능)
  - **P2** — 보강·편의 (있으면 좋음)
  - **P3** — 옵션·실험 (외부 연계, 대시보드 시각화 등)
- **공수 S/M/L/XL**
  - **S** — 0.5일 이내
  - **M** — 1~2일
  - **L** — 3~5일
  - **XL** — 1주 이상
- **상태** — ⚪ 대기 / 🟡 진행중 / 🟢 완료 / ⛔ 보류
- 의존성: "→ X 필요" 또는 "X 완료 후"

---

## 1. 전체 마일스톤 진행 현황

| M | 모듈 | 상태 | 비고 |
|---|---|---|---|
| **M1** | 로그인 + 대시보드 | 🟢 완료 | Breeze + Sakai 템플릿 통합, 관리자/영업사원 대시보드 분리 + **P2-8** 영업 대시보드 보강(차트·반려·draft 바로가기) |
| **M2** | 공지사항 | 🟢 완료 | CRUD + 첨부(private 디스크) + 읽음 현황 |
| **M3** | 실적 관리 | 🟢 완료 | Phase 4 (P4-S1~S6) + CSV 일괄 등록 + 증빙 파일 첨부(GAP-2) |
| **M4** | 제품·클라이언트·거래처 관리 | 🟡 MVP 완료 | 제품·거래처 ✓ / 약국·병원 마스터 MVP ✓ / **GAP-9** 마스터 관리 메뉴·`/master-data` 허브 ✓ / 병의원 공공데이터(HIRA) 보강 import ✓ / 사업자번호 이력(폐업·재등록, 옛 번호 검색)·숫자 정규화 ✓ / **레거시 import 미완(OPS-7)** |
| **M5** | 정산 모듈 + 내보내기 | 🟢 완료 | 생성/재계산/상태 전이 + Excel·PDF 내보내기 + 영업사원별 수수료 명세(GAP-3) + 지급 Batch/증빙(GAP-5) + 월간 보고서(GAP-6) |
| **M6** | 스케줄러·큐·알림, 운영 전환 | ⚪ 대기 | Scheduler/Queue 기반 준비, Job/알림 미작성 |
| **공통** | 사용자 관리 (admin) | 🟢 완료 | CRUD + is_active 토글 + 비밀번호 재설정 + 로그인 차단 |

**현재 테스트**: `sail test` 기준 **416개 전체 통과** (2026-06-04, GAP-10 MT-1~8 + **MT-4-finalize(tenant_id NOT NULL)** + 코드 그룹 CRUD·병의원 공공데이터·사업자번호 이력/정규화 포함)

> §2.8 **도메인 검토 후보** 섹션은 본 프로젝트 채택 여부가 결정되지 않은 도메인 후보 **23종**(BIZ/PHARM/OPS·CRM·ERP/TECH)을 별도 관리합니다. 핵심 백로그(§2.4~2.6)와 분리해 가독성을 보존합니다.

---

## 2. 작업 백로그 (우선순위 정렬)

### 2.1 운영 블로커 (P0) — 완료

| ID | 제목 | 비고 |
|---|---|---|
| ~~**P0-1**~~ | ~~**클라이언트 (약국/병원/영업사원) 마스터 도입**~~ | 🟢 MVP 완료 (2026-04-20). **잔여**: CSV import / 레거시 import / 실적 연동(companies 통합 방식) |
| ~~**P0-2**~~ | ~~**사용자 관리 화면 (admin 전용)**~~ | 🟢 완료 (2026-04-20): CRUD + 활성·비활성 토글 + 비밀번호 재설정. 11/11 PASS |

### 2.2 핵심 가치 (P1) — 완료

| ID | 제목 | 비고 |
|---|---|---|
| ~~**P1-1**~~ | ~~**실적 일괄 CSV 업로드**~~ | 🟢 완료 (2026-04-20): dry-run + commit, all-or-nothing. 9/9 PASS |
| ~~**P1-2**~~ | ~~**정산 PDF/Excel 내보내기**~~ | 🟢 완료 (2026-04-20): Excel/PDF 각 전용 라우트 |
| ~~**P1-3**~~ | ~~**거래처 상세에 "월별 정산 요약" 카드**~~ | 🟢 완료 (2026-04-20): 최근 6개월, settlements.show 링크 |
| ~~**P1-4**~~ | ~~**영업사원 측 UI 점검 (sales role)**~~ | 🟢 완료 (2026-04-20): 스모크 테스트 + 메뉴 노출 제한 |

### 2.3 운영 품질 개선 — 완료 (2026-04-24)

> 코드 품질·보안·기획 보완 일괄 적용. 테스트 183 → 185 → 191개.

| ID | 항목 | 내용 |
|---|---|---|
| ~~**QA-1**~~ | ~~**PerformanceResolver 중복 쿼리 제거**~~ | 🟢 `activeOverrideFor()` 1회 호이스팅 |
| ~~**QA-2**~~ | ~~**SettlementController 코드 중복 제거**~~ | 🟢 `loadExportRelations()` private 메서드 추출 |
| ~~**QA-3**~~ | ~~**실적 등록 미래일 차단**~~ | 🟢 `before_or_equal:today` 추가 |
| ~~**QA-4**~~ | ~~**정산 확정 후 실적 승인 경고**~~ | 🟢 `warning` flash → Toast 연동 |
| ~~**QA-5**~~ | ~~**PrimeVue Toast ↔ Inertia flash 전역 연결**~~ | 🟢 `AppLayout.vue` watch 추가 |
| ~~**QA-6**~~ | ~~**정산 재계산 후 미반영 실적 경고 배너**~~ | 🟢 `hasUnappliedPerformances` amber 배너 |
| ~~**QA-7**~~ | ~~**sales: 본인 실적/정산만 조회**~~ | 🟢 Policy + 목록 쿼리 필터 |
| ~~**QA-8**~~ | ~~**등록자 컬럼 sales에게 숨김**~~ | 🟢 `v-if="can.viewCreator"` |
| ~~**QA-9**~~ | ~~**공지 읽음 현황 (admin 전용)**~~ | 🟢 `readStats` prop + 독자 테이블 |
| ~~**QA-10**~~ | ~~**미승인 거래처 실적 등록 차단**~~ | 🟢 `isApproved()` 서버+프론트 이중 검증 |
| ~~**QA-11**~~ | ~~**공지 첨부파일 인증 접근 제어**~~ | 🟢 `public` → `local` 디스크, 전용 다운로드 라우트 |
| ~~**QA-12**~~ | ~~**공지 첨부 파일 타입 제한**~~ | 🟢 mimes 검증으로 실행 파일 차단 |
| ~~**QA-13**~~ | ~~**CSV MIME 검증 강화**~~ | 🟢 `mimetypes` 이중 검증 추가 |
| ~~**QA-14**~~ | ~~**실적번호 동시 생성 race condition 수정**~~ | 🟢 `lockForUpdate()` + `DB::transaction()` |
| ~~**GAP-2**~~ | ~~**실적 증빙 파일 첨부**~~ | 🟢 완료 (2026-04-24): `performance_files` + `PerformanceFileController` + `uploadFile` Policy. 6/6 PASS |

### 2.4 유사 시스템 대비 추가 기능 (P1~P2)

> 동종 제약 CSO 실적관리 시스템과의 gap 분석으로 도출된 항목입니다. (2026-04-24)

| ID | 제목 | 우선순위 | 공수 | 비고 |
|---|---|---|---|---|
| ~~**GAP-1**~~ | ~~**목표 관리 (Sales Quota)**~~ | P1 | M | 🟢 완료 (2026-04-24): sales_quotas + Policy + Service + Controller + Vue + 대시보드 연동. 22/22 PASS |
| ~~**GAP-3**~~ | ~~**영업사원별 수수료 명세**~~ | P2 | M | 🟢 완료 (2026-05-19): CommissionSummaryService/Policy(Gate)/Controller/Excel/PDF + Sales 대시보드 카드 + 메뉴. 16/16 PASS |
| ~~**GAP-4**~~ | ~~**영업사원-거래처 담당 배정**~~ | P2 | M | 🟢 완료 (2026-05-19): `company_sales_assignments` + 모델/Policy/Controller/검색 필터 + 거래처 상세 배정 UI + Sales 대시보드 카드. 13/13 PASS · 전체 242/242 PASS |
| ~~**GAP-5**~~ | ~~**지급 관리 고도화 (정산 지급 Batch/증빙)**~~ | P2 | M | 🟢 완료 (2026-05-19): paid_on/payment_method/batch_no/note + `settlement_payment_files` + Show 지급 모달·증빙 패널 + Excel/PDF 헤더 + Batch 필터. 16/16 PASS · 전체 258/258 PASS |
| ~~**GAP-6**~~ | ~~**리포트/집계 템플릿 (월간 보고서)**~~ | P2 | M | 🟢 완료 (2026-05-27): `MonthlyReportService`/`MonthlyReportExcelExporter`/`ReportsController` + `Reports/Monthly.vue`(3탭) + Policy/Gate + 메뉴. 12/12 PASS · 전체 270/270. 스펙: [`MONTHLY_REPORT.md`](../modules/reports/MONTHLY_REPORT.md) |
| **GAP-7** | **역할/권한 세분화 (검수자/정산 담당 등)** | P2 | M | `admin/sales` 2-role 한계 보완. 역할 확장 및 Policy 매트릭스 정의. §4.15. **GAP-10(멀티테넌시) 이후** 테넌트 내부 직무 역할로 설계 (직교 축) |
| **GAP-8** | **감사 로그 운영 규정 (reason/보관/조회)** | P2 | S | reason 필수 액션 정의, 보관/정리 정책, 조회 권한 명문화. §4.16 |
| ~~**GAP-9**~~ | ~~**기준정보 마스터 admin 분리 (병의원·약국·의약품)**~~ | P2 | S | 🟢 완료 (2026-05-29): "마스터 관리" 메뉴 그룹 + `/master-data` 허브 + 약국·병원 상세 거래처 읽기 표시. 라우트 불변. 3/3 PASS · 전체 276/276. 설계: [`MASTER_DATA_ADMIN.md`](../modules/master-data/MASTER_DATA_ADMIN.md) |
| **GAP-10** | **멀티테넌시 (제약사 테넌트 + 역할 계층)** | **P0** | **XL** | 🟡 **경로 B 확정** — MT-1~8 + **MT-4-finalize(tenant_id NOT NULL)** 🟢 + 약국·병의원 CRUD·코드 그룹 CRUD·임퍼서네이션 🟢. **Now: platform 사용자·의약품 CRUD / admin 소속 sales 관리 범위**. cutover(OPS-7)는 후속. 설계: [`MULTI_TENANCY.md`](../modules/tenancy/MULTI_TENANCY.md) |
| **GAP-11** | **의약품 도메인 재설계 (공유 마스터 + 제약사 취급품)** | P2 | XL | 🟢 설계 확정(D-1~5) · 착수 대기. 단일 `products`(테넌트 복제) → `drug_products`(공유, platform) + `company_drug_products`(제약사 취급+수수료, 등급 매트릭스 유지). 실적→취급품 참조. Pample 경량 차용. **GAP-10 안정화 후** DR-1 착수. 설계: [`DRUG_DOMAIN_REDESIGN.md`](../modules/product/DRUG_DOMAIN_REDESIGN.md) |
| **GAP-12** | **행안부(MOIS) 공공데이터 API 증분 동기화** | P2 | M | 🔵 **설계 확정(v0.4)·착수** — §2.4 HIRA 수동 업로드의 후속(API 자동 증분). 변경분(I/U/D)만 수신해 `hospitals` 건건 upsert. Pample 검증 구현 차용(위험 낮음). 인증키 재사용·업종 경로·매핑 확정. 설계: [`HOSPITAL_LOCALDATA_API_SYNC.md`](../modules/client/HOSPITAL_LOCALDATA_API_SYNC.md) |

#### GAP-4 작업 단위 — 영업사원-거래처 담당 배정 (🟢 완료)

- **GAP-4-1 (DB, S)**: `company_sales_assignments` 테이블 — 🟢 완료
  - [x] 마이그레이션: `company_id`, `user_id`, `assigned_at`, `assigned_by`, unique `(company_id, user_id)`, 인덱스 2종
- **GAP-4-2 (BE, S)**: 모델 + Policy — 🟢 완료
  - [x] `CompanySalesAssignment` 모델 + `Company::salesAssignments/salesUsers`, `User::companyAssignments/assignedCompanies`
  - [x] `CompanySalesAssignmentPolicy`: viewAny(any) / create·delete(admin)
- **GAP-4-3 (BE, M)**: 담당 배정 CRUD API — 🟢 완료
  - [x] `CompanySalesAssignmentController` (store/destroy)
  - [x] `StoreCompanySalesAssignmentRequest`: `user.role=sales` + `is_active=true` + unique `(company_id, user_id)` 검증
  - [x] `ChangeReason::with('담당 영업사원 배정/해제')` 로 activity 컨텍스트 부여
- **GAP-4-4 (BE, S)**: 거래처 검색 담당 필터 — 🟢 완료
  - [x] `CompanyController::search()`에 `assigned_to_me`(sales) / `assigned_user_id`(admin) 옵션
  - [x] 명시적 필터 없을 때 sales는 담당 거래처가 자동으로 상단에 정렬됨 (LEFT JOIN + CASE)
- **GAP-4-5 (FE, M)**: 거래처 상세 담당 영업사원 UI — 🟢 완료
  - [x] `Companies/Show.vue`: 담당 영업사원 DataTable + 배정 모달(`Dialog` + `Select`) + 해제 ConfirmDialog
  - [x] `CompanyController::show` 에 `salesAssignments`, `availableSalesUsers`, `can.manageSalesAssignments` prop
- **GAP-4-6 (FE, S)**: 영업사원 대시보드 "내 담당 거래처" 카드 — 🟢 완료
  - [x] `Sales/Dashboard.vue`: 담당 거래처 상위 5건 + 총 카운트 + 전체 보기 링크
  - [x] `SalesDashboardController` 에 `myAssignedCompanies` / `myAssignedCompanyTotal` prop
- **GAP-4-7 (Test, S)**: 담당 배정 테스트 — 🟢 완료
  - [x] `CompanySalesAssignmentTest`: 13 cases — 권한·CRUD·unique·404 변조·검색 우선순위·Sales 대시보드 prop 검증

#### GAP-5 작업 단위 — 지급 관리 고도화 (🟢 완료)

- **GAP-5-1 (DB, S)**: 지급 메타데이터 컬럼 추가 — 🟢 완료
  - [x] `settlements`에 `paid_on`(date), `payment_method`(enum bank_transfer/cash/other), `payment_batch_no`(string 50), `payment_note`(text) 추가
  - [x] 인덱스: `payment_batch_no`
- **GAP-5-2 (DB, S)**: 지급 증빙 첨부 테이블 — 🟢 완료
  - [x] `settlement_payment_files` 신규 + private 저장 + softDeletes + Factory
- **GAP-5-3 (BE, S)**: 지급 처리(pay) 확장 — 🟢 완료
  - [x] `PaySettlementRequest`: `paid_on` 필수·`before_or_equal:today`, `payment_method` enum 검증, batch/note 길이 검증
  - [x] `pay()` 에서 입력값 저장 + `ChangeReason::with('정산 지급 완료')` + activity log
- **GAP-5-4 (BE, M)**: 증빙 파일 업로드/삭제/다운로드 — 🟢 완료
  - [x] `SettlementPaymentFileController` (store/destroy/download) + `StoreSettlementPaymentFileRequest`
  - [x] `SettlementPolicy::uploadPaymentFile` (admin, confirmed/paid 상태에서만)
  - [x] URL 변조 방지: `file.settlement_id !== route settlement.id → 404`
- **GAP-5-5 (FE, M)**: 정산 상세 지급 UI — 🟢 완료
  - [x] `Settlements/Show.vue` 지급 처리 모달 (paid_on/method/batch/note Dialog)
  - [x] 지급 정보 카드 + 증빙 파일 패널(업로드/DataTable/다운로드 링크/삭제 Confirm)
- **GAP-5-6 (FE, S)**: 정산 목록 Batch 필터 — 🟢 완료
  - [x] `SettlementController::index` + `Settlements/Index.vue` `payment_batch_no` 입력
- **GAP-5-7 (Export, S)**: Excel/PDF 지급 정보 — 🟢 완료
  - [x] `SettlementExcelExporter` 헤더 4행 추가 (지급일/수단/Batch/메모) + 라인 시작 행 17로 이동
  - [x] `pdf/settlement.blade.php` 지급 정보 행 (조건부) + payment_method 한글 매핑
- **GAP-5-8 (Test, S)**: 지급/증빙 회귀 테스트 — 🟢 완료
  - [x] `SettlementPaymentTest` 7 cases — paid_on 필수·미래 거부·메타데이터 저장·잘못된 method·sales 403·draft 차단·batch 필터
  - [x] `SettlementPaymentFilesTest` 9 cases — 업로드(권한·draft/confirmed/paid 상태·mime)·삭제(디스크 제거·URL 변조 404)·다운로드(권한)
  - [x] 기존 `SettlementWorkflowTest::pay` 케이스에 `paid_on` 추가하여 회귀 유지

#### GAP-6 작업 단위 — 월간 보고서

> 상세 스펙: [`MONTHLY_REPORT.md`](../modules/reports/MONTHLY_REPORT.md)

- **GAP-6-1 (Spec, S, 선행: 없음)**: 리포트 스펙 확정(컬럼/정렬/집계 기준) — 🟢 완료 (2026-05-27)
  - [x] 거래처/영업사원/제품 3종의 컬럼 정의 + 합계 정의 → `MONTHLY_REPORT.md` §3
  - [x] 기준 데이터 확정: `performances.status=approved` 만
  - [x] 기간: 단일 월(기본) + 기간 범위(from~to) 둘 다
  - [x] Excel: 1개 파일 + 3개 시트 (거래처별/영업사원별/제품별)
  - [x] 제품 MoM(전월 대비): 미포함 (후속 보류)
- **GAP-6-2 (BE, M, 선행: GAP-6-1)**: 리포트 조회 API — 🟢 완료 (2026-05-27)
  - [x] `MonthlyReportService`(거래처/영업사원/제품 3종 집계 + totals) + `ReportsController@monthly` + 라우트 `GET /reports/monthly`
  - [x] `MonthlyReportPolicy` + Gate(`view-monthly-report`/`export-monthly-report`) admin-only
  - [x] 집계 쿼리 3종 구현 + 페이지 prop로 미리보기 제공
- **GAP-6-3 (BE, M, 선행: GAP-6-2)**: Excel export — 🟢 완료 (2026-05-27)
  - [x] `GET /reports/monthly/export.xlsx` + `MonthlyReportExcelExporter`(1파일 3시트)
  - [x] activity log: `report.monthly.export` 이벤트 기록
- **GAP-6-4 (FE, M, 선행: GAP-6-2)**: 월간 보고서 화면 — 🟢 완료 (2026-05-27)
  - [x] `Reports/Monthly.vue`: 기간(월/범위) 필터 + 합계 카드 + 3탭(거래처/영업사원/제품) 미리보기 + 다운로드 버튼
  - [x] 정산 메뉴에 "월간 보고서" 항목 추가 (admin only)
- **GAP-6-5 (Test, S, 선행: GAP-6-2, GAP-6-3)**: 리포트 테스트 — 🟢 완료 (2026-05-27)
  - [x] `MonthlyReportTest`: 12 cases — 권한(admin-only) + 3종 집계 정확성·정렬 + 합계 + 미승인 제외 + 기간 필터 + Excel(3시트)·activity log. 12/12 PASS · 전체 270/270 PASS

#### GAP-7 작업 단위 — 역할/권한 세분화

- **GAP-7-1 (Spec, S, 선행: 없음)**: 권한 매트릭스 확정
  - [ ] role 후보: `reviewer`, `settlement_manager`, `readonly` (필요 시 추가/삭제)
  - [ ] “실적 워크플로/정산 워크플로/내보내기/마스터 쓰기/사용자 관리” 기준으로 허용 액션 표 작성
- **GAP-7-2 (DB, S, 선행: GAP-7-1)**: role enum 확장
  - [ ] `users.role` enum 확장 마이그레이션 + 서버 validation 업데이트
- **GAP-7-3 (BE, S, 선행: GAP-7-2)**: 미들웨어 다중 role 지원
  - [ ] `EnsureUserRole`에서 `role:admin,reviewer` 형태 지원
- **GAP-7-4 (BE, M, 선행: GAP-7-3)**: Policy 적용(핵심만 1차)
  - [ ] Performance: review/approve/reject 권한 분리
  - [ ] Settlement: confirm/pay/recalculate/export 권한 분리
  - [ ] Master write(Products/Companies/Clients): readonly 차단
- **GAP-7-5 (FE, S, 선행: GAP-7-3)**: 메뉴/화면 role 반영
  - [ ] `AppMenu.vue` role별 visible 정리
  - [ ] `Users/*` role 선택 옵션 확장 + 안내 텍스트
- **GAP-7-6 (Test, S, 선행: GAP-7-4, GAP-7-5)**: role 매트릭스 스모크
  - [ ] `RoleMatrixSmokeTest`: reviewer/settlement_manager/readonly 핵심 흐름 검증

#### GAP-8 작업 단위 — 감사 로그 운영 규정

- **GAP-8-1 (Doc, S, 선행: 없음)**: reason 필수 액션 목록 확정
  - [ ] 제품/가격/override/실적/정산/NIMS별 “필수 reason” 액션을 문서로 고정
- **GAP-8-2 (Doc, S, 선행: GAP-8-1)**: Audit 조회 권한 원칙 확정
  - [ ] 기본 admin-only 유지 여부 + 예외(예: sales 본인 실적/정산 이벤트만) 정의
- **GAP-8-3 (Doc, S, 선행: GAP-8-2)**: 보관/정리 정책 정합성 맞춤
  - [ ] `PRODUCT_SPEC.md` §11.3(보관 기간)과 동일 기준으로 운영 규칙 명시
- **GAP-8-4 (Ops, 선택, S, 선행: GAP-8-3)**: 자동 정리 스케줄러
  - [ ] 월 1회 `activity_log` 5년 초과 삭제/아카이브 작업 정의 + 스케줄 등록
- **GAP-8-5 (Ops, 선택, S, 선행: GAP-8-3)**: 운영 점검 체크리스트 추가
  - [ ] `docs/operations/OPERATIONS.md`에 월간 점검(큐/스케줄/백업/audit 정리) 추가

#### GAP-12 작업 단위 — 행안부(MOIS) 공공데이터 API 증분 동기화

> 설계: [`HOSPITAL_LOCALDATA_API_SYNC.md`](../modules/client/HOSPITAL_LOCALDATA_API_SYNC.md) §9. **테스트 먼저**. 인증키 재사용(`.env MOIS_API_KEY`, 커밋 금지)·업종 경로·매핑 확정.

- **R1 (Refactor, S, 선행: 없음)**: `HospitalRowMapper` 추출 — `HospitalImportService` 의 `buildRow/updateColumns/mapStatus/mapHospitalType/parseDate/parseInt/parseDecimal/firstSpecialty` 를 공용 매퍼로 이동, CSV(한글헤더)·API(영문필드) 어댑터가 논리 키로 호출. 기존 CSV 테스트 그린 유지
- **R2 (BE, M, 선행: R1)**: `config/mois.php` + `.env` + `HospitalMoisApiClient`(`Http::baseUrl()->retry`, `cond[]` 쿼리, `resultCode` 검사) + `Http::fake()` 테스트
- **R3 (BE, S, 선행: 없음)**: `proj4php` 도입 + `Epsg5174ToWgs84` 변환 유틸(한반도 범위 밖 NULL) + 테스트
- **R4 (DB, S, 선행: 없음)**: `hospital_mois_syncs`·`hospital_mois_cursors` 마이그레이션(테이블+모든 컬럼 comment) + 모델
- **R5 (BE, M, 선행: R1~R4)**: `HospitalMoisSyncService`(업종 순회·페이징·DAT_UPDT_SE I/U/D 분기·upsert·변경감지 SKIP·커서 전진·report) + 멱등/격리 테스트
- **R6 (BE, S, 선행: R5)**: `SyncHospitalMoisJob` + `hospitals:sync-mois {--since} {--svc} {--dry-run}` + Scheduler(비활성 플래그)
- **R7 (검증, S, 선행: R6)**: 실데이터 1회 소규모 증분 검증 — `MNG_NO==hospital_code` 교차 확인·충돌 리포트로 §6 가정 확정
- **R8 (FE, 선택, S, 선행: R6)**: `/platform/hospitals/mois-sync` 이력·수동 트리거 UI + `role:platform` 권한 테스트
- **R9 (Doc, S, 선행: R7)**: ROADMAP/CLIENT_MANAGEMENT 진행 로그 반영

#### GAP-9 작업 단위 — 기준정보 마스터 admin 분리 (병의원·약국·의약품)

> 설계·열린 결정(D-1~D-4): [`MASTER_DATA_ADMIN.md`](../modules/master-data/MASTER_DATA_ADMIN.md). 기능은 기존 재사용, **라우트 불변**, IA(메뉴) 중심 분리.

- **GAP-9-1 (Spec, S, 선행: 없음)**: 설계·범위 확정 — 🟢 완료
  - [x] 설계 문서 작성(`MASTER_DATA_ADMIN.md`) — 배경/원칙/범위/IA안/영향파일
  - [x] 결정 확정: D-1 그룹명="마스터 관리" / D-2 허브 랜딩 신설 / D-3 영업사원 관리 영역 이동 / D-4 거래처 읽기 표시만
- **GAP-9-2 (FE, S, 선행: GAP-9-1)**: 메뉴 IA 재구성 — 🟢 완료
  - [x] `AppMenu.vue` — "마스터 관리" 그룹 신설(마스터 홈/의약품/약국/병의원), "클라이언트"에서 약국·병원 이동
  - [x] "거래처" 그룹(업체 관리) 분리 + 영업사원(조회)을 관리(admin) 영역으로 이동
- **GAP-9-3 (FE+BE, S, 선행: GAP-9-2)**: 마스터 허브 랜딩 — 🟢 완료
  - [x] `MasterDataController` + `GET /master-data`(admin) + `MasterData/Index.vue` — 3종 건수·바로가기 카드
- **GAP-9-4 (FE, S, 선행: GAP-9-2)**: 약국·병원 상세 거래처 읽기 표시 — 🟢 완료
  - [x] `Pharmacies/Show.vue`·`Hospitals/Show.vue` — 연결 거래처 있을 때만 읽기 링크(선택 링크 안내)
- **GAP-9-5 (Test, S, 선행: GAP-9-2)**: 권한·허브 prop 검증 — 🟢 완료
  - [x] `MasterDataHubTest`: admin 건수 prop / sales 403 / 비로그인 redirect. 3/3 PASS

#### GAP-10 작업 단위 — 멀티테넌시 (제약사 테넌트 + 역할 계층)

> **XL · P0급 아키텍처 변경.** 설계·열린결정(D-1~D-6): [`MULTI_TENANCY.md`](../modules/tenancy/MULTI_TENANCY.md).
> GAP-7(테넌트 *내부* 직무 역할 세분화)와 직교 — 멀티테넌시 도입 후 그 위에 얹는다.

- **MT-0 (Spec, 선행: 없음)**: 설계·범위 확정 — 🟢 완료
  - [x] 설계 문서 작성(`MULTI_TENANCY.md`) — 역할계층/테넌트모델/격리설계/백필/단계
  - [x] 결정 전체 확정: D-1 `tenants`(모델 Tenant, 표기 "제약사") / D-2 위임형(제약사 admin이 자사 sales 관리) / D-3 공지 전역+테넌트 / D-4 테넌트 선택 진입(임퍼서네이션) / D-5 이메일 전역 unique 유지 / D-6 약국·병원 super_admin 쓰기 + 제약사 admin 변경요청 승인 워크플로
- **MT-1 (DB, M, 선행: MT-0)**: `tenants` 테이블 + 모델 + role enum `super_admin` 확장 + `users.tenant_id` — 🟢 완료 (2026-05-29)
  - [x] `tenants` 마이그·모델·Factory (name/code/biz_no/status/settings/created_by + softDeletes)
  - [x] `users.role` enum 에 `super_admin` 추가(raw ALTER) + `users.tenant_id`(nullable FK, nullOnDelete)
  - [x] `User` ROLE 상수·`isSuperAdmin()`·`tenant()` 관계·fillable `tenant_id`
  - [x] `TenantSchemaTest` 5 cases — 생성/소속/super_admin null/헬퍼/삭제 시 null. 전체 281/281 PASS
- **MT-2 (DB, M, 선행: MT-1)**: 기본 제약사 시드 + 기존 데이터 백필 — 🟡 1부 완료
  - [x] **(1부)** 기본 제약사("기본 제약사"/`DEFAULT`) 시드 + 기존 admin/sales `tenant_id` 백필(super_admin 제외). 멱등 + down 복구. `DefaultTenantBackfillTest` 5/5
  - [ ] **(2부 → MT-4 로 이동)** products/companies/performances/settlements/sales_quotas `tenant_id` 부착·백필 → NOT NULL (사용자 결정으로 MT-4 와 묶음)
- **MT-4 (DB+BE, L)**: tenant-scoped 테이블에 `tenant_id` 부착 — 🟢 완료 (2026-05-29, MT-3보다 먼저)
  - [x] products/companies/performances/settlements/sales_quotas 에 `tenant_id`(**nullable**)+FK(restrict)+인덱스 + 기존 행 기본 제약사 백필
  - [x] 5개 모델 `tenant()`·fillable + `Tenant::default()` + 5개 Factory 기본 tenant. `DomainTenantColumnTest` 5/5
  - [ ] **NOT NULL 전환은 MT-3(자동 주입) 이후 finalize** (지금 NOT NULL 시 앱 생성 경로 깨짐 — 회귀로 확인)
> **실행 순서 재정렬(2026-05-29, 사용자 지정)**: super_admin 페이지(MT-6) 먼저 → MT-3 → MT-4-finalize → MT-5 → MT-7 → MT-8. (§3 Now, `MULTI_TENANCY.md` §6.1)

- **MT-6 (FE+BE, M)**: **super_admin 페이지** — 🟢 완료 (2026-05-29)
  - [x] super_admin 시드/승격 명령 `tenancy:make-super-admin` + `role:super_admin` 라우트 게이팅
  - [x] 제약사(tenant) CRUD: `TenantController`/`TenantPolicy`/`Store·UpdateTenantRequest`/라우트 + `Tenants/{Index,Create,Edit,Show}.vue`
  - [x] 제약사 admin 계정 생성(위임형 D-2): `POST /tenants/{tenant}/admins` + `StoreTenantAdminRequest` + Show 모달
  - [x] super_admin 전용 메뉴 "플랫폼 > 제약사 관리" + `/dashboard` → tenants.index 리다이렉트
  - [x] `TenantManagementTest` 8 cases. 전체 299/299 PASS
  - [ ] (임퍼서네이션 "테넌트 진입" 버튼은 MT-3 후 연결)
- **MT-3 (BE, L, 선행: MT-6)**: 격리 엔진 — 🟢 완료 (2026-05-29)
  - [x] `TenantContext` + `TenantScope`(컨텍스트 설정 시에만 필터) + `BelongsToTenant` 트레이트(스코프+자동주입)
  - [x] `ResolveTenant` 미들웨어(web) — admin/sales 격리, super_admin/게스트 전역 + 5개 모델 적용 + `nextNumberFor` 스코프 우회
  - [x] `TenantScopeTest` 6 cases. 전체 309/309 PASS, **회귀 0**
  - [ ] super_admin 임퍼서네이션(테넌트 진입)은 후속
- **MT-4-finalize (DB, S)**: 도메인 `tenant_id` NOT NULL — 🟢 완료 (2026-06-04)
  - [x] `2026_06_04_100000_finalize_domain_tenant_id_not_null` — 5테이블 NOT NULL(+잔여 null 백필 안전망)
  - [x] UserFactory 비-platform 기본 테넌트(afterMaking) + `PerformanceResolver`/`SettlementBuilder` 거래처 테넌트 상속(컨텍스트 없는 경로 보호)
  - [x] `DomainTenantColumnTest`(NOT NULL 거부로 갱신)·`TenantPolicyGuardTest`(과도기 케이스 제거). 전체 416 PASS
- **MT-5 (BE, M, 선행: MT-3)**: Policy 테넌트 조건 — 🟢 완료 (2026-05-29)
  - [x] 단일 `Gate::before`: super_admin 전체 통과 + admin/sales 교차 테넌트 거부(`class_uses_recursive` 로 `BelongsToTenant` 모델 판정) + null 테넌트 위임
  - [x] `TenantPolicyGuardTest` 4 cases. 전체 313/313 PASS, 회귀 0
  - [ ] admin 의 소속 sales 관리 범위(자사 sales만 `/users` 노출)는 후속
- **MT-7 (Test, L, 선행: MT-3~5)**: 격리 회귀 테스트 — 🟢 완료 (2026-05-29)
  - [x] `TenantIsolationTest` 8 cases — 거래처·실적·정산·목표 목록 자사 격리 / 교차 테넌트 상세 403 / 생성 시 tenant_id 자동주입(HTTP)
  - [x] 2중 격리 확인(목록=TenantScope, 상세=authorize→Gate::before). 전체 321/321 PASS, 회귀 0
- **MT-8 (BE+FE, M, 선행: MT-5)**: 약국·병원 변경요청 승인 워크플로 — 🟢 완료 (2026-05-29)
  - [x] `master_change_requests` + `MasterChangeRequestService`/`Policy` + 제약사 admin 요청(create/update)
  - [x] platform 검토·승인 반영/반려 + 약국·병원 직접 쓰기 차단(platform 전용, pharma 는 변경요청) + UI(요청 폼·검토 화면)·메뉴
- **GAP-10 후속(코드/마스터, 2026-06-02)** — 🟢 완료
  - [x] **공통 코드 그룹/코드 정의 CRUD**: `code_groups` + `CodeGroup`·`CodeDefinition`(FK) + `Platform\CodeGroupController`(중첩 정의 CRUD)·`CodeGroupPolicy` + `Platform/CodeGroups/{Index,Create,Edit,Show}.vue` + 메뉴. `CodeGroupManagementTest`
  - [x] **병의원 공공데이터(HIRA) import**: 병원/약국 정보 + 의료기관 상세(진료과목·시설·장비·진료시간 등) import 서비스·Job·아티즌 명령 + `/platform/hospitals/public-data` 업로드 + Show 보강 섹션 + 목록 지역(시도)·구분 필터. 약국 목록도 동일 보강
  - [x] **사업자번호 이력·정규화**: `business_number_histories`(폴리모픽, 적용기간·사유) + `HasBusinessNumberHistory` 트레이트 → 병의원·약국. 변경 이력(폐업·재등록) + **옛 번호로도 검색**. 숫자만 정규화(mutator+request) + morph map(별칭 hospital/pharmacy). `BusinessNumberHistoryTest`

<details>
<summary><strong>GAP-1 완료 아카이브</strong> — 목표 관리 (Sales Quota, 2026-04-24)</summary>

- **GAP-1-1 (DB, S)**: `sales_quotas` 테이블 — [x] 완료
- **GAP-1-2 (BE, S)**: 모델 + Policy — [x] 완료
- **GAP-1-3 (BE, M)**: CRUD API (`/sales-quotas`) — [x] 완료
- **GAP-1-4 (BE, M)**: `QuotaAchievementService` — [x] 완료
- **GAP-1-5 (FE, M)**: 목표 관리 화면 — [x] 완료
- **GAP-1-6~7 (FE, S)**: 관리자·영업사원 대시보드 달성률 카드 — [x] 완료
- **GAP-1-8 (Test, S)**: `SalesQuotaTest` + `QuotaAchievementTest` — [x] 22/22 PASS

</details>

### 2.5 보강·편의 (P2)

| ID | 제목 | 모듈 | 공수 | 의존성 | 비고 |
|---|---|---|---|---|---|
| **P2-1** | **거래처 등급(`default_commission_grade`) 일괄 변경** | M4 | S | 없음 | 다건 선택 → 등급 일괄 수정. CSV 업로드도 검토 |
| **P2-2** | **CSV → Excel(.xlsx) 지원 확장** | M4/M3 | S | P1-1 | `phpoffice/phpspreadsheet` 도입 — 제품·실적 Excel 입력 가능 |
| **P2-3** | **가격/override 이력 삭제 시 직전 행 `effective_to` 자동 복원** | M4 | S | 없음 | 현재는 단순 삭제만 — 정합성 회복 옵션 추가 |
| **P2-4** | **`ProductSearchAutoComplete`를 ProductForm 대체품 필드에 적용** | M4 | S | 없음 | `Show.vue` 단종 모달에는 이미 적용 |
| **P2-5** | **CSV import 결과 알림 (이메일/슬랙)** | M4 | M | M6 큐 | 큐 + Job. 대량 import 시 비동기 처리 |
| **P2-6** | **로그인 후 마지막 접속 일시 표시 + 비정상 접속 알림** | 공통 | S | 없음 | `users.last_sign_in_at` 활용 |
| ~~**P2-7**~~ | ~~**sales 전용 대시보드 분리**~~ | 공통 | M | P1-4 | 🟢 완료 (2026-04-23): `GET /sales/dashboard` + `SalesDashboardController` + `Sales/Dashboard.vue` |
| ~~**P2-8**~~ | ~~**영업사원 대시보드 보강** (월별 실적 차트·최근 반려 카드·제출 대기 바로가기)~~ | 공통 | S | P2-7 | 🟢 완료 (2026-05-29): `myMonthlyChart`/`recentRejected` prop + Chart(line)·반려 카드·draft 바로가기. 3/3 PASS (당시 273/273 → 이후 GAP-9로 **276/276**). PRODUCT_SPEC §4.7 |
| **P2-9** | **약국/병원 자동완성 API** (`PharmacyController::search`·`HospitalController::search`) | M4 | S | 없음 | CLIENT_MANAGEMENT §5. 실적·정산 폼에서 약국/병원 직접 선택이 필요할 때(현재는 companies 통합 방식) |

#### P2-8 작업 단위 — 영업사원 대시보드 보강

- **P2-8-1 (BE, S)**: `SalesDashboardController` 에 본인 월별 실적 차트 데이터 추가 — 🟢 완료
  - [x] 최근 12개월 본인 `performances` `subtotal` 월별 합산 (status=approved 기준, 빈 월 0 보강)
  - [x] `myMonthlyChart` prop: `{ labels: ['2025-06', ...], data: [12345, ...] }`
- **P2-8-2 (BE, S)**: "최근 반려된 실적" 데이터 추가 — 🟢 완료
  - [x] `Performance::where('created_by', user.id)->where('status', 'rejected')->orderByDesc('updated_at')->limit(5)` + `rejected_reason` 포함
  - [x] `recentRejected` prop
- **P2-8-3 (FE, S)**: Sales/Dashboard.vue UI 추가 — 🟢 완료
  - [x] PrimeVue Chart(line) — 월별 실적 추이 (데이터 없을 때 빈 상태 표시)
  - [x] "최근 반려 실적" 카드 (Tag + 사유 + 수정 링크)
  - [x] "제출 대기 (draft)" 바로가기 버튼 → `route('performance.index', { status: 'draft' })` (sales 는 본인 실적만 자동 필터)
- **P2-8-4 (Test, S)**: `tests/Feature/Sales/SalesDashboardEnhancementsTest.php` — 🟢 완료
  - [x] 12개월 합계 정확성 + 미승인/타 사용자 제외 / 반려 5건 한정·최신순 / draft 카운트 prop 검증. 3/3 PASS

#### P2-9 작업 단위 — 약국/병원 자동완성 API

- **P2-9-1 (Spec, S)**: 도메인 검토 — 현재 실적은 `performances.company_id → companies` 만 사용. 약국/병원 직접 선택 필요성을 운영팀과 확인
- **P2-9-2 (BE, S)**: 검색 액션 추가 (필요 확인된 경우)
  - [ ] `PharmacyController::search(q, exclude?)` → JSON `[{id, pharmacy_name, business_registration_number}]`
  - [ ] `HospitalController::search(q, exclude?)` → JSON `[{id, hospital_name, hospital_type, business_registration_number}]`
  - [ ] 라우트: `GET /pharmacies-search`, `GET /hospitals-search` (`companies-search` 패턴)
- **P2-9-3 (FE, S)**: 재사용 AutoComplete 컴포넌트
  - [ ] `Pages/Clients/Pharmacies/Partials/PharmacySearchAutoComplete.vue`
  - [ ] `Pages/Clients/Hospitals/Partials/HospitalSearchAutoComplete.vue`
- **P2-9-4 (Test, S)**: 검색 API + 권한 테스트

### 2.6 옵션·실험 (P3)

| ID | 제목 | 모듈 | 공수 | 의존성 | 비고 |
|---|---|---|---|---|---|
| **P3-1** | **NIMS 외부 어댑터 (Mock → 실 연동)** | M4 | XL | 외부 API 키 | `nims.product.*` 채널 분리 완료 — Job/Queue 기반 dispatcher 구현 |
| **P3-2** | **가격 변동 통계 대시보드** | M4 | M | 없음 | 보험약가 인상/인하 비율, 상위 변동 제품 차트 |
| **P3-3** | **audit log 기준 "거래처별 단가 통보서(PDF)" 자동 생성** | M4/M5 | M | P1-2 | 가격 변경/override 변경 audit → 거래처별 PDF |
| **P3-4** | **알림 채널 (이메일/슬랙) 통합 설정** | 공통 | M | M6 큐 | Notification 채널 + 사용자별 구독 설정 |
| **P3-5** | **다국어 i18n (한/영)** | 공통 | L | 없음 | PrimeVue locale + `lang/{ko,en}.json` |

### 2.7 인프라·운영 (M6)

| ID | 제목 | 우선순위 | 공수 | 비고 |
|---|---|---|---|---|
| ~~**OPS-1**~~ | ~~**Scheduler 단일 cron 등록**~~ | P1 | S | 🟢 완료 (2026-04-22): `routes/console.php` 스케줄 엔트리 + `docs/operations/OPERATIONS.md` |
| ~~**OPS-2**~~ | ~~**Queue worker 상시 가동 (supervisor)**~~ | P0 | S | 🟢 완료 (2026-04-22): 로컬(Sail)/운영(Supervisor) 가이드 작성 |
| ~~**OPS-3**~~ | ~~**백업 정책 (DB 일일 dump + 로테이션)**~~ | P0 | M | 🟢 완료 (2026-04-22): `scripts/backup_db.sh` + 운영 가이드 |
| ~~**OPS-4**~~ | ~~**`.env` 비밀 관리**~~ | P1 | M | 🟢 완료 (2026-04-22): `docs/operations/OPERATIONS.md` 운영 원칙/체크리스트 |
| ~~**OPS-5**~~ | ~~**GitHub Actions CI (Pest + Pint + Vite)**~~ | P1 | M | 🟢 완료 (2026-04-22): `.github/workflows/ci.yml` (MariaDB 서비스 + migrate + Pint + Pest + build) |
| **OPS-6** | **Sentry / Telescope 모니터링** | P1 | S | dev: Telescope, 운영: Sentry |
| **OPS-7** | **운영 전환 시 레거시 데이터 최종 import** | P0 | L | 모든 도메인 매핑 완료 후 | shinilset → jungjin cutover. dry-run + 차이점 리포트. 작업단위 아래 참고 |

#### OPS-7 작업 단위 — 레거시 최종 import 체크리스트

> 모든 단계는 **dry-run → 차이점 확인 → commit** 의 2-phase 진행. 실행 환경: 운영 서버. 명령은 `docs/operations/OPERATIONS.md` 참조.

- **OPS-7-1 (Prep, S)**: 사전 점검
  - [ ] 운영 DB 백업 1회 (`scripts/backup_db.sh`) — 롤백 baseline
  - [ ] 레거시 DB(`legacy` connection) 접속 확인 + 스키마 덤프 (`legacy:inspect-schema`)
  - [ ] 신규 DB 마이그레이션 최종 상태 확인 (`php artisan migrate:status`)
  - [ ] 모든 OPS-6 모니터링이 작동 중인지 확인 (실패 잡 알람 포함)
- **OPS-7-2 (Master, S)**: 약국 import
  - [ ] `php artisan legacy:import-pharmacies --dry-run` → 리포트 검토 (생성/업데이트/중복/오류 카운트)
  - [ ] commit: `php artisan legacy:import-pharmacies`
  - [ ] 사후 검증: `/pharmacies` 목록 카운트 비교
- **OPS-7-3 (Master, S)**: 병원 import
  - [ ] `php artisan legacy:import-hospitals --dry-run`
  - [ ] commit: `php artisan legacy:import-hospitals`
  - [ ] 사후 검증: `/hospitals` 목록 + `hospital_type` 분포 점검
- **OPS-7-4 (Assignments, S)**: 관계 테이블 import
  - [ ] `legacy:analyze-clients` / `legacy:analyze-assignments` 로 client_id 해석 재확인
  - [ ] `legacy:import-hospital-pharmacy-assignments --dry-run` → commit
  - [ ] `legacy:import-hospital-company-assignments --dry-run` → commit
  - [ ] (선택) `legacy:import-client-pharmacy-as-hospital-pharmacy --dry-run` → commit
- **OPS-7-5 (Sync, S)**: 실적 연동을 위한 companies 동기화
  - [ ] `clients:sync-companies --dry-run` → 신규 `companies` 행 / `partner_type` 매핑 검증
  - [ ] commit: `clients:sync-companies`
- **OPS-7-6 (Ledger, M)**: 과거 실적 import (신규 구현 필요)
  - [ ] 레거시 `performance` 테이블 → `App\Console\Commands\LegacyImportPerformancesCommand` 신규 작성
  - [ ] 매핑 규칙: company_id 변환 / product_id 변환 / 스냅샷 가격·수수료 보존 (재해석 X)
  - [ ] dry-run 리포트 → 차이점 확인 → commit (트랜잭션, all-or-nothing 권장)
- **OPS-7-7 (Settlement, M)**: 과거 정산 import (신규 구현 필요)
  - [ ] 레거시 정산 헤더 + 라인 매핑 → `LegacyImportSettlementsCommand` 신규
  - [ ] settlement_no 충돌 방지 (legacy prefix 또는 별도 보관 컬럼)
  - [ ] dry-run → commit
- **OPS-7-8 (Verify, M)**: 정합성 검증
  - [ ] 실적 합계 비교 리포트 (레거시 vs 신규 — 거래처×월)
  - [ ] 정산 합계 비교 리포트
  - [ ] 차이 발생 시 트랜잭션 단위 롤백 + 재시도 절차 문서화
- **OPS-7-9 (Cutover, S)**: 운영 전환
  - [ ] 레거시 시스템 점검 모드 전환 (쓰기 차단)
  - [ ] 최종 incremental import (OPS-7-2~7 재실행, 변경분만 반영)
  - [ ] DNS / 사용자 안내 / 권한 재발급
  - [ ] 1주일 hot-fix 대기 모드

### 2.8 도메인 검토 후보 (Domain Backlog)

> 제약 CSO 실적관리 시스템에서 일반적으로 요구되지만, **본 프로젝트에 도입할지 아직 결정되지 않은** 항목 모음입니다.
> 모두 `⚪ 대기` 상태이며, 운영팀·기획 검토 후 채택되면 정식 GAP/P2/P3 항목으로 **승격**합니다(예: `BIZ-5` → 신규 GAP-ID 부여. **GAP-9는 기준정보 마스터 분리에 이미 사용됨**).
> 승격 시 본 표에서 제거하고 해당 우선순위 표(§2.4~2.6)에 옮겨 작업 단위를 정의합니다.

#### 2.8.1 비즈니스 필수 (BIZ)

| ID | 제목 | 공수 | 도메인 영향 | 근거·메모 |
|---|---|---|---|---|
| **BIZ-1** | **세금계산서 발행 / 홈택스 연동** | XL | 매우 큼 | 정산 `paid` 는 시스템 상태일 뿐. 실제 거래처 청구에는 세금계산서(전자세금계산서) 발행이 필요. 홈택스 API 또는 외부 e-Tax 서비스 연동. 회계·법무 검토 필요 |
| **BIZ-2** | **수금 관리 (입금 확인 / 미수금 / 부분 입금)** | L | 큼 | 정산 `paid` 처리와 **실제 입금**은 분리. 부분 입금/연체·미수금 추적 테이블 + 거래처별 미수금 카드 + 입금 예정일 알림. GAP-5(지급 Batch) 와 시야 분리 필요 |
| **BIZ-3** | **반품/조정 워크플로** | M | 큼 | 현재 `performances.quantity` 음수 허용으로 반품을 표현하지만, 사유·승인·원 실적 연결·증빙이 별도로 없음. 신규 `performance_returns` 또는 reason+linked_id 컬럼 검토 |
| **BIZ-4** | **거래처 신용 한도 / 여신 관리** | M | 중간 | 거래처별 매출 한도·미수금 잔액 기준 자동 차단(soft warning + hard block). BIZ-2 미수금 데이터에 의존 |
| **BIZ-5** | **실적·정산 기간 마감 (Period Lock)** | M | 큼 | 월(또는 회계기) 단위로 해당 기간 실적 등록·수정·승인 및 정산 재계산·상태 전이 차단. 마감 해제는 admin + reason. GAP-6 월간 보고서·운영 마감 절차와 연계 |
| **BIZ-6** | **다단계 승인·대결 (승인 위임)** | M | 중간 | 검수자 부재 시 대리 승인자·2인 승인(제출→1차→2차) 옵션. GAP-7 역할 세분화(`reviewer`)와 함께 설계. `performance_approvals` 이력 테이블 검토 |
| **BIZ-7** | **정산 일괄 처리 (Bulk confirm/pay)** | S | 중간 | 월말 N건 정산을 목록에서 다건 선택 → confirm 또는 pay 일괄 실행(실패 건만 롤백·리포트). GAP-5 지급 Batch·증빙과 UI 통합 검토 |
| **BIZ-8** | **거래처·제품 블랙리스트 (거래 중단)** | S | 중간 | `companies`/`products`에 `is_blocked` 또는 `blocked_until` + 사유. 실적 등록·정산 생성 시 hard block(QA-10 미승인과 별도). 해제는 admin + audit |

#### 2.8.2 의약품 도메인 특화 (PHARM)

| ID | 제목 | 공수 | 도메인 영향 | 근거·메모 |
|---|---|---|---|---|
| **PHARM-1** | **유효기간 / Lot 번호 추적** | L | 큼 | 의약품 안전·반품·회수의 기본. `performance_lots` (performance_id, lot_no, expiry_date, quantity) 신규. 실적 라인 → Lot 분할 처리 |
| **PHARM-2** | **GS1 일련번호 / KPIS 보고** | XL | 매우 큼 | 마약·전문약 일련번호 의무 보고(식약처 KPIS). 외부 API + 일련번호 스캔 워크플로. 현재 NIMS 식별만 있음 |
| **PHARM-3** | **단체 거래처 그룹 (약사회·의사회·체인)** | M | 중간 | 다수 거래처에 동일 단가/할인 정책 일괄 적용. `company_groups` + `companies.group_id` 추가. 그룹 단위 정산 집계 옵션 |

#### 2.8.3 운영·CRM·ERP (OPS·CRM·ERP)

| ID | 제목 | 공수 | 도메인 영향 | 근거·메모 |
|---|---|---|---|---|
| **OPS-8** | **사용자 활동 로그 (로그인·페이지 접근·민감 액션)** | M | 중간 | `last_sign_in_at` 외에 접근 이력 별도. 감사·이상 행동 탐지용. GAP-8(감사 로그 운영 규정)과 별개 — 사용자 행위 로깅에 집중 |
| **OPS-9** | **2FA / 비밀번호 정책 강화** | M | 중간 | TOTP(Google Authenticator) 또는 이메일 OTP. 비밀번호 만료·복잡도·재사용 제한. 의료 도메인 권장 |
| **CRM-1** | **영업사원 활동 일지 (방문·미팅·통화)** | L | 중간 | 거래처 방문 기록 → 실적 연결 시 영업 효율 분석 가능. 별도 CRM 영역. `sales_activities` 신규 |
| **CRM-2** | **할인·프로모션 관리** | M | 중간 | 기간 한정 단가·묶음 판매·1+1. 현재는 거래처 예외 단가만. `promotions` 테이블 + 실적 적용 우선순위 정의 |
| **ERP-1** | **재무회계 시스템 연동 (전표 자동 생성)** | L | 큼 | 정산 확정/지급 → 회계 분개 자동 생성. 회사 ERP(더존·SAP 등) 연동 방식 검토 필요. 외부 의존성 |
| **OPS-10** | **워크플로 상태 변경 알림 (실적·정산)** | M | 중간 | 반려·승인·제출·정산 확정/지급 시 담당자에게 이메일/인앱. §4.2·P3-4 일반 알림보다 **이벤트·수신자 매핑**을 명시. M6 Queue 전제 |
| **OPS-11** | **실적 중복·이상 탐지** | S | 중간 | 동일 `company_id`+`product_id`+`performance_date`(+수량) 중복 경고·등록 차단(soft/hard 옵션). 비정상 단가·수량 이탈 시 검수 큐 플래그 |
| **ERP-2** | **도매·EDI 실적 자동 수신** | XL | 매우 큼 | 도매사·ERP에서 실적 파일/API 수신 → dry-run 검증 → commit. TECH-3 Webhook·P3-1 NIMS와 별 축. 매핑 규칙·중복 정책(OPS-11) 선행 |

#### 2.8.4 기술·품질 (TECH)

| ID | 제목 | 공수 | 도메인 영향 | 근거·메모 |
|---|---|---|---|---|
| **TECH-1** | **모바일 반응형 / PWA 최적화** | M | 중간 | 영업사원 외근 환경. 현재 `1280×800` 기준 — 모바일에서 실적 등록·조회 보장. PWA 오프라인 모드는 옵션 |
| **TECH-2** | **API rate limiting + 보안 헤더** | S | 작음 | 사내 한정이면 후순위. 외부 노출(OPS-9 2FA·TECH-3) 시 필수. Laravel `RateLimiter` + CSP/HSTS 등 |
| **TECH-3** | **공개 REST / Webhook API** | L | 중간 | 제약사·외부 시스템 연동 가능성. Laravel Sanctum + 토큰 발급 UI + 이벤트 Webhook |
| **TECH-4** | **단가/등급 변경 시뮬레이터** | M | 작음 | 단가 정책 변경 전 정산 영향 미리보기. 가격 정책 risk 관리 |

---

## 3. 권장 진행 순서 (Now / Next / Later)

> 기준: 2026-06-02. GAP-1~6·GAP-9·P2-7·P2-8 완료 + GAP-10 MT-1~8 + 코드 그룹 CRUD·병의원 공공데이터·사업자번호 이력/정규화 완료, 테스트 **417**개.

### ✅ 확정된 운영 경로: **B (멀티테넌시 선행)**

> **2026-05-29 기획 확정**: 여러 제약사 입주 전제 → **GAP-10 구현·격리 검증 후** 레거시 import·cutover(OPS-7). 단일 테넌트로 먼저 운영 전환(경로 A)은 **채택하지 않음**.

| 단계 | 내용 |
|------|------|
| **1. Now~Next** | GAP-10 **MT-6→MT-3→MT-5→MT-7→MT-8 + MT-4-finalize 🟢 완료** → **남은 것: platform 사용자·의약품 CRUD / admin 소속 sales 관리 범위** |
| **2. Later** | **OPS-6** → **OPS-7** cutover (테넌트 백필·MT-7 통과 후) |
| **병행(여유 시)** | P2-1~4 보강 — **tenant 격리 회귀에 영향 없는** 항목만 |

**미확정(추가 합의 시 §3·`MULTI_TENANCY.md` 반영)**

- 1차 파일럿 **입주 제약사 수**(1곳 vs 다수)
- cutover **목표 시점**(MT-7 완료 기준 역산)
- MT-8(약국·병원 변경요청)을 1차에 포함할지 2차로 미룰지

### 🔴 Now (2026-06-04 갱신 — MT 전 단계 완료, 잔여 정리)

> 상세·의존성: [`MULTI_TENANCY.md`](../modules/tenancy/MULTI_TENANCY.md) §6.1~§6.4.

1. **platform 사용자·의약품 CRUD** — `/platform` 전역 CRUD (약국·병의원·코드 그룹은 완료, 사용자·의약품은 조회만)
2. **admin 의 소속 sales 관리 범위** — 자사 sales만 `/users` 노출 (MT-5 후속)
3. **OPS-6 → OPS-7** — 모니터링 후 레거시 최종 import·cutover (격리 검증 완료)

**최근 완료(2026-06-04)**: **MT-4-finalize**(도메인 `tenant_id` NOT NULL — UserFactory 기본 테넌트 + Performance/Settlement 거래처 테넌트 상속)
**최근 완료(2026-06-02)**: **MT-8**(변경요청 워크플로) · **코드 그룹/코드 정의 CRUD** · **병의원 공공데이터(HIRA) import** · **사업자번호 이력+숫자 정규화(morph map)** · 약국 목록 보강 · 임퍼서네이션
**이전 완료**: MT-1~7(격리 엔진·게이트·회귀) · role 리네임(platform/pharma/cso) · P2-8 · GAP-9 · GAP-1~6

상세 체크리스트: §2.4 **GAP-10 작업 단위**

### 🟡 Next (MT 이후)

1. **GAP-7** — 역할 세분화(**테넌트 내부** 직무, MT-5 이후)
2. **GAP-1·GAP-4 연계** — 담당 거래처 기준 quota (테넌트 스코프 반영 후)
3. **P2-1~P2-4** — MT-7 이후 또는 격리 무관 항목만

### 🟢 Later (GAP-10·격리 검증 완료 후)

1. **OPS-6** — 모니터링(Telescope/Sentry). **OPS-7 직전 필수**
2. **OPS-7** — 레거시 최종 import·cutover (**테넌트 단위** dry-run·검증 포함)
3. **GAP-8** — 감사 로그 운영 규정
4. **P2-9**, **P3-***, **§2.8** 도메인 후보 승격 검토

> **MT-8**: §3 Now #6 에 포함. 2차로 미룰 경우 Now에서 제외하고 본 Later로 이동(미확정 항목).

---

## 4. 모듈 간 의존성 매트릭스

```
M1 로그인              ─── 모든 모듈의 전제
M2 공지사항            ─── 독립
M3 실적                ─── M4(제품/거래처) 필요
M4 제품·거래처·클라이언트  ─── 독립 (도메인 마스터)
M5 정산                ─── M3(실적) 필요
M6 스케줄러·큐·알림     ─── 비동기·자동화 필요한 곳에서 호출

GAP-1 목표 관리 🟢     ─── M3(실적) + M4(사용자/제품)
GAP-3 수수료 명세 🟢   ─── M3(실적)
GAP-4 담당 배정 🟢     ─── M4(거래처/사용자) — GAP-1 달성률·실적 필터와 연계 가능
GAP-5 지급 고도화 🟢   ─── M5(정산) — Batch/증빙 + Excel/PDF
GAP-6 월간 보고서 🟢   ─── M3(실적) — 승인 실적 집계(정산과 동일 기준)
GAP-10 멀티테넌시 🟡  ─── 경로 B 확정 — 거의 전 모듈 선행, OPS-7은 MT-7 후
P3-1 NIMS 외부 연계    ─── M4 + M6 큐
P2-5 import 결과 알림  ─── M6 큐
BIZ-5 기간 마감        ─── M3(실적) + M5(정산)
BIZ-6 다단계 승인      ─── M3 + GAP-7(역할)
BIZ-7 정산 일괄        ─── M5 + GAP-5(지급)
OPS-10 상태 알림       ─── M6 + P3-4
OPS-11 중복 탐지       ─── M3(실적)
ERP-2 EDI 연동         ─── M3 + M6 + TECH-3(선택)
```

---

## 5. 변경 관리

본 문서를 갱신하는 시점:
- 새 백로그 항목이 추가될 때 (§2 표에 한 줄 추가)
- 항목이 시작/완료될 때 (상태 변경 + 해당 모듈 문서의 진행 로그에 상세 기록)
- 우선순위 재조정이 필요할 때 (§3 권장 진행 순서 갱신)
- **기능·메뉴 IA 변경 시**: [`PRODUCT_SPEC.md`](./PRODUCT_SPEC.md) §3·§7 및 [`USER_MANUAL.md`](../manual/USER_MANUAL.md) 동기화
- **§2.8 도메인 검토 후보가 채택**될 때: 해당 행을 §2.8에서 제거하고 §2.4(GAP) 또는 §2.5(P2)/§2.6(P3) 로 옮기며 **미사용 GAP-ID**로 작업 단위 정의

**변경 이력 (요약)**

| 버전 | 날짜 | 내용 |
|------|------|------|
| 2.9 | 2026-06-04 | **MT-4-finalize 완료** — 도메인 `tenant_id` NOT NULL 전환(UserFactory 기본 테넌트 + Performance/Settlement 거래처 테넌트 상속). §3 Now=platform 사용자·의약품 CRUD, 테스트 **416** |
| 2.8 | 2026-06-02 | **MT-8 완료** + 코드 그룹/코드 정의 CRUD · 병의원 공공데이터(HIRA) import · 사업자번호 이력+숫자 정규화(morph map) · 약국 목록 보강. §3 Now=MT-4-finalize/platform 사용자·의약품 CRUD, 테스트 **417** |
| 2.7 | 2026-05-29 | **실행 순서 재정렬** — MT-4( nullable) 완료, §3 Now=**MT-6**→MT-3→…, 테스트 **291** |
| 2.6 | 2026-05-29 | MT-1·MT-2(1부) 완료 반영 — §3 Now=MT-3, 테스트 286 |
| 2.5 | 2026-05-29 | **운영 경로 B 확정** — §3 Now=GAP-10 MT-1~3, OPS-7은 Later |
| 2.4 | 2026-05-29 | §3 Now/Next/Later 정리, cutover vs GAP-10 의사결정 표, §4 GAP-6 🟢 |
| 2.3 | 2026-05-29 | GAP-9 완료, GAP-10 설계 등록 |

---

**문서 버전**: 2.9
**작성일**: 2026-04-20
**최종 갱신**: 2026-06-04 (MT-4-finalize 완료 — 도메인 tenant_id NOT NULL, Now=platform 사용자·의약품 CRUD, 테스트 416)
**갱신 책임**: 작업 시작·완료 시 해당 항목 상태 변경 + 모듈 문서에 상세 기록
