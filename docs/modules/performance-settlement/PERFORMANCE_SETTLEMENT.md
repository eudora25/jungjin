# 실적 · 정산 — 설계·프로세스 문서 (Phase 4)

> 본 문서는 정진팜 실적관리 시스템의 **실적(Performance) · 정산(Settlement) 모듈**(Phase 4) 단일 설계 문서입니다.
> 선행 문서: [`docs/modules/product/PRODUCT_MANAGEMENT.md`](../product/PRODUCT_MANAGEMENT.md) (Phase 1~3 완료 — 제품 마스터 / 가격 이력 / 거래처 예외 / Audit / NIMS 채널)
>
> **진행 현황은 본 문서 §9 “진행 현황(Progress Log)”에서 단계별로 추적합니다.** 작업 시작·완료 시마다 해당 표와 변경 파일 목록을 갱신해 주세요.

---

## 0. TL;DR

- **왜 Phase 4인가**: Phase 1~3에서 쌓은 `products` · `product_prices` · `product_commission_rates` · `company_product_overrides` · `default_commission_grade` 의 **유일한 소비자**가 곧 실적/정산. 여기서 처음으로 마스터 데이터가 "살아 있는 값"으로 검증된다.
- **핵심 설계 원칙 (타협 금지)**:
  1. **스냅샷 중심**: 실적 1행은 생성 시점의 `unit_price` / `commission_rate` 를 **값 자체**로 저장한다. 마스터/예외/가격이 나중에 바뀌어도 이 실적에는 영향이 없다.
  2. **출처 추적성**: 각 스냅샷 값이 어디에서(`override` / `product_prices(sale)` / `products.price` / `matrix` / `manual`) 해석됐는지 `*_source` enum 으로 명시하고, 가능한 경우 `FK`(override_id, price_id, commission_rate_id) 도 같이 박는다. **정산 재현성의 근거**.
  3. **정산은 파생**: `settlements` 는 언제든 재생성 가능한 "뷰/집계 머티리얼라이즈". 원장은 `performances`. 정산이 잘못되면 재계산으로 복구한다.
  4. **상태 전이는 제품과 동일 패턴**: `draft → submitted → reviewed → approved` (+ `rejected` / `cancelled`). 기존 `ProductController`/`ProductPolicy` 패턴을 복제.
  5. **모든 상태 전이는 `activity_log`** — `ChangeReason::with()` 로 `reason` 강제 포함.

---

## 1. 현재 상태 (As-Is)

- **(작성 당시) As-Is**: Phase 4 착수 시점에는 사이드바 링크만 있었고 `/performance`, `/settlements` 는 404 상태였다.
- **(현재) 구현 완료**: Phase 4(P4-S1~S6)까지 구현되어 `/performance`, `/settlements` 가 동작하며 Pest 테스트가 존재한다.

---

## 2. 데이터 모델 (To-Be)

### 2.1 `performances` — 실적 원장

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `id` | PK | |
| `performance_no` | string(20), **unique** | 사람이 읽는 실적번호 (`YYYYMMDD-NNNN`) |
| `performance_date` | date, **index** | 실적 발생일 (판매일) — 우선순위 해석 기준일 |
| `company_id` | FK companies, **index** | 거래처 |
| `product_id` | FK products, **index** | 제품 |
| `quantity` | integer (signed) | 수량 — **음수 허용**(반품/조정) |
| `unit_price` | decimal(12,2) | **스냅샷**: 생성 시점의 `Product::effectiveSalePriceFor($company, performance_date)` |
| `commission_rate` | decimal(5,2), nullable | **스냅샷**: 생성 시점의 `Product::effectiveCommissionRateFor($company, performance_date)` |
| `subtotal` | decimal(14,2), **virtual stored** | `quantity * unit_price` (DB-generated) |
| `commission_amount` | decimal(14,2), **virtual stored** | `subtotal * commission_rate / 100` (DB-generated, rate NULL이면 NULL) |
| `price_source` | enum(`override`,`product_sale`,`products_price`,`manual`) | 단가 출처 |
| `price_override_id` | FK company_product_overrides, nullable | 적용된 예외(있을 때) |
| `price_id` | FK product_prices, nullable | 적용된 sale 가격 이력(있을 때) |
| `commission_source` | enum(`override`,`matrix`,`manual`,`none`) | 수수료율 출처 |
| `commission_rate_id` | FK product_commission_rates, nullable | 적용된 매트릭스(있을 때) |
| `note` | string(500), nullable | 비고 |
| `status` | enum(`draft`,`submitted`,`reviewed`,`approved`,`rejected`,`cancelled`) default=`draft` | 상태 |
| `submitted_at/_by`, `reviewed_at/_by`, `approved_at/_by` | — | 워크플로 타임스탬프 |
| `rejected_reason` | string(500), nullable | 반려 사유 |
| `created_by` / `updated_by` | FK users | |
| `timestamps`, `softDeletes` | | |

**인덱스**:
- `INDEX(performance_date, company_id, status)`
- `INDEX(company_id, status, performance_date)`
- `INDEX(product_id, performance_date)`

### 2.2 `settlements` — 월×거래처 헤더 (P4-S4)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `id` | PK | |
| `settlement_no` | string(32), unique | 실제 구현: `YYYYMM-{company_id 6자리}` (예: `202604-000042`) |
| `company_id` | FK | |
| `period_month` | char(7) (`YYYY-MM`), **index** | 정산 월 |
| `status` | enum(`draft`,`confirmed`,`paid`,`cancelled`) default=`draft` | |
| `line_count` | int | 집계된 실적 행 수 |
| `total_quantity` | int | |
| `total_subtotal` | decimal(16,2) | 합 매출 |
| `total_commission` | decimal(16,2) | 합 수수료 |
| `calculated_at` | datetime | 마지막 재계산 시각 |
| `confirmed_at/_by`, `paid_at/_by` | — | 상태 전이 |
| `note` | text, nullable | |
| `created_by/updated_by/timestamps` | | (정산 헤더는 `company_id + period_month` unique를 위해 softDeletes 미사용) |

**unique**: `(company_id, period_month)`

### 2.3 `settlement_lines` — 정산 상세 라인 (P4-S4)

정산 생성 시점에 `performances` 중 `status=approved && performance_date ∈ period_month` 를 전부 **복사/고정**한다. 실적이 수정되면 settlement 는 자동 업데이트되지 **않는다** — 오직 정산을 **재계산(recalculate)** 해야만 반영. (원장/파생 관계 고정)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `id` | PK | |
| `settlement_id` | FK settlements, cascadeOnDelete | |
| `performance_id` | FK performances, restrictOnDelete | 원장 참조 |
| `snapshot_unit_price`, `snapshot_commission_rate`, `quantity`, `subtotal`, `commission_amount` | 스냅샷 사본 | 정산 시점의 값 (원장이 추후 수정되어도 무관) |
| `timestamps` | | |

---

## 3. 가격/수수료 해석 파이프라인

**실적 1건 생성 시 (`App\Services\Performance\PerformanceResolver`)**:

```
[입력]  company_id, product_id, performance_date, quantity
   ↓
1) Product::effectiveSalePriceFor($company, $performance_date)
     ├─ activeOverrideFor(...) && override_unit_price != NULL  →  price_source = override
     ├─ currentPriceOf('sale', $on)                            →  price_source = product_sale
     └─ products.price (fallback)                              →  price_source = products_price
2) Product::effectiveCommissionRateFor($company, $performance_date)
     ├─ activeOverrideFor(...) && override_commission_rate != NULL  →  commission_source = override
     ├─ currentCommissionRate($on) → grade matrix                   →  commission_source = matrix
     └─ 없음                                                          →  commission_source = none, commission_rate = NULL
   ↓
[저장]  unit_price, commission_rate, price_source, price_override_id, price_id, commission_source, commission_rate_id
```

수동 override(관리자 직접 타이핑)는 `*_source = manual` 로 저장 + `FK` 는 `NULL`. 단 **감사 로그에 반드시 `reason` 남김**.

---

## 4. 상태 머신

```
draft ──submit()──▶ submitted ──review()──▶ reviewed ──approve()──▶ approved
   │                      │                      │
   │                      └──reject()──┐         │
   └────────── cancel() ────────────── ▼         │
                                    rejected     │
                                                 │
                                    cancelled ◀──┘ (approved 도 취소 가능, 정산에 물려있으면 금지)
```

- **권한**: `draft` 생성·수정은 일반 사용자, `review`/`approve`/`reject` 는 관리자. (ProductPolicy 와 동일 규칙)
- **정산 연결 시 잠금**: `settlement_lines` 가 있는 실적은 `update`/`cancel` 금지 (Policy에서 검사).

---

## 5. UI 구성 (P4-S2 ~ P4-S5)

- **`/performance`** — 실적 목록 (필터: 기간·거래처·제품·상태)
- **`/performance/create`** — 실적 등록 폼 (거래처 선택 → 제품 선택 → 수량; 제품 선택 시 **해석된 단가/수수료를 미리보기**로 표시)
- **`/performance/{performance}`** — 실적 상세 + 상태 전이 버튼 + 변경 이력 탭
- **`/settlements`** — 월×거래처 리스트
- **`/settlements/{settlement}`** — 라인 상세 (재계산은 현재 `/settlements`의 “정산 생성/재계산” 폼에서 수행)

제품 상세 페이지 하단에도 "이 제품이 포함된 최근 실적 N건" 미니 패널을 (P4-S5에) 추가하여 피드백 루프 완성.

---

## 6. 감사 로그 (Audit)

- `Performance` 모델에 `LogsActivity` 적용 (`log_name='performance'`).
- 로깅 대상 컬럼: `company_id`, `product_id`, `quantity`, `unit_price`, `commission_rate`, `status`, `note`, `rejected_reason`.
- 상태 전이 시 컨트롤러에서 `ChangeReason::with('reason', fn() => ...)` 로 사유를 properties 에 기록.
- `Settlement` 는 **별도 채널 `settlement`** 로 기록.
  - `SettlementLine` 은 현재 activity log 대상이 아니다(필요 시 후속 단계로 추가).

---

## 7. 테스트 전략 (Pest)

1. **스냅샷 우선순위**: (override) > (product_prices sale) > (products.price) 각각 케이스에서 실적 저장 후 `unit_price` / `price_source` 검증.
2. **수수료 우선순위**: (override) > (matrix × company grade) > (없음) 케이스.
3. **시점 해석**: `performance_date` 가 과거일 때 해당 시점의 가격이 적용되는지 (override 기간 경계 케이스).
4. **워크플로 권한**: 일반/관리자 분리, `approved` 실적은 수정 불가.
5. **정산 일관성**: 실적 N건 생성 → 정산 생성 → `line_count`/`total_*` 합계 일치, 라인 스냅샷이 원장과 동일.
6. **원장 수정 불변성**: 정산 후 실적을 수정(또는 이론상 수정 가능 경로)하더라도 기존 정산 스냅샷이 변하지 않음.

---

## 8. 네이밍 결정 (확정)

- 라우트 prefix: **`/performance`** (복수형 아님 — 기존 사이드바 링크와 동일). 단 정산은 **`/settlements`**.
- 모델: `App\Models\Performance`, `App\Models\Settlement`, `App\Models\SettlementLine`.
- 서비스: `App\Services\Performance\PerformanceResolver` (스냅샷 해석), `App\Services\Settlement\SettlementBuilder` (월 집계 생성/재계산).
- Activity log name: `performance`, `settlement`.

---

## 9. 진행 현황 (Progress Log)

### 9.1 단계표

| 단계 | 제목 | 상태 | 시작 | 완료 | 비고 |
|---|---|---|---|---|---|
| P4-S1 | `performances` 마이그 + 모델(스냅샷/Audit) + Policy + Factory + Resolver + Pest | 🟢 완료 | 2026-04-20 | 2026-04-20 | 7/7 PASS · 전체 112/112 PASS |
| P4-S2 | 실적 등록 UI + Form Request + Service(resolver) | 🟢 완료 | 2026-04-20 | 2026-04-20 | `Create.vue` + `StorePerformanceRequest` + `resolve-preview` API |
| P4-S3 | 실적 목록 + 필터 + 검수/승인 워크플로 | 🟢 완료 | 2026-04-20 | 2026-04-20 | `Index.vue`/`Show.vue`/`Edit.vue` + 5종 상태 전이 라우트 |
| P4-S4 | `settlements` / `settlement_lines` 마이그 + 모델 + Builder 서비스 | 🟢 완료 | 2026-04-20 | 2026-04-20 | `SettlementBuilder` + 스냅샷 카피 |
| P4-S5 | 정산 상세 화면 + 재계산 버튼 + 제품 상세 "최근 실적" 미니 패널 | 🟢 완료 | 2026-04-20 | 2026-04-20 | 정산 상세/재계산 ✓, 제품 상세 "최근 실적" 미니 패널 ✓ (P4-S5 잔여분 완료) |
| P4-S6 | E2E Pest (우선순위 → 실적 스냅샷 → 정산 라인 일관성) | 🟢 완료 | 2026-04-20 | 2026-04-20 | `PerformanceHttpTest`/`SettlementBuilderTest`/`PerformanceToSettlementE2ETest` 포함 20/20 PASS · 전체 125/125 PASS |
| **P5-S1** | **실적 CSV 일괄 등록 (ROADMAP P1-1)** | 🟢 완료 | 2026-04-20 | 2026-04-20 | `PerformanceImportService` + `PerformanceImportController` + `Performance/Import.vue` + 9/9 PASS · 전체 134/134 PASS |
| **P5-S2** | **정산 상태 전이 + 실적 잠금 + 정산 상세 재계산 UX (운영 안정화)** | 🟢 완료 | 2026-04-20 | 2026-04-20 | `SettlementWorkflowTest` 추가 · 전체 165/165 PASS |
| **P5-S3** | **정산 Excel 내보내기 (ROADMAP P1-2 일부)** | 🟢 완료 | 2026-04-20 | 2026-04-20 | `settlements.export.excel` + `SettlementExcelExporter` + `SettlementExportTest` · 전체 167/167 PASS |
| **P5-S4** | **정산 PDF 내보내기 (ROADMAP P1-2)** | 🟢 완료 | 2026-04-20 | 2026-04-20 | `settlements.export.pdf` + Blade 템플릿 + `SettlementPdfExportTest` · 전체 169/169 PASS |
| **GAP-3** | **영업사원별 수수료 명세** | 🟢 완료 | 2026-05-19 | 2026-05-19 | `CommissionSummaryService`/`Policy`(Gate)/`Controller`/Excel/PDF + Sales 대시보드 카드 + 메뉴. 16/16 PASS · 전체 229/229 PASS |
| **GAP-4** | **영업사원-거래처 담당 배정** | 🟢 완료 | 2026-05-19 | 2026-05-19 | `company_sales_assignments` + 모델/Policy/Controller/검색 우선순위 필터 + 거래처 상세 배정 UI + Sales 대시보드 "내 담당" 카드. 13/13 PASS · 전체 242/242 PASS |
| **GAP-5** | **지급 관리 고도화 (Batch/증빙)** | 🟢 완료 | 2026-05-19 | 2026-05-19 | `settlements` 4컬럼 + `settlement_payment_files` + `PaySettlementRequest` + 증빙 컨트롤러/Policy + Show 지급 모달·증빙 패널 + Excel/PDF 헤더 + Batch 필터. 16/16 PASS · 전체 258/258 PASS |

### 9.2 단계별 변경 로그

#### P4-S1 (완료, 2026-04-20)

- [docs] `docs/modules/performance-settlement/PERFORMANCE_SETTLEMENT.md` 신규.
- [migration] `database/migrations/2026_04_20_140000_create_performances_table.php` 신규.
  - `subtotal`, `commission_amount` 은 **MariaDB `GENERATED ... STORED` 컬럼**으로 DB 수준에서 계산 (애플리케이션 오염 방지).
  - 외래키는 `companies`/`products` 에 `restrictOnDelete` — 원장이라 삭제 방어.
- [model] `app/Models/Performance.php` 신규 — 스냅샷 컬럼·LogsActivity(`log_name=performance`)·상태 머신 헬퍼(`isLocked`/`isRejectable`)·`nextNumberFor()`.
- [service] `app/Services/Performance/PerformanceResolver.php` 신규 — `Product` 헬퍼 결과를 `source`/`FK` 와 함께 돌려주는 해석기. 실적 저장의 단일 진입점.
- [policy] `app/Policies/PerformancePolicy.php` 신규 — `create/update/delete` + 상태 전이(`submit`/`review`/`approve`/`reject`/`cancel`) 권한.
- [factory] `database/factories/PerformanceFactory.php` 신규 — `approved()`/`submitted()`/`forCompany()`/`forProduct()` 상태 포함.
- [test] `tests/Feature/Performance/PerformanceSnapshotTest.php` 신규 — 7 cases:
  1. 단가 우선순위 override > product_sale > products.price
  2. 수수료율 우선순위 override > matrix × grade > none
  3. 과거 시점 해석
  4. `subtotal`/`commission_amount` 가상 컬럼 계산
  5. `commission_rate=NULL` → `commission_amount=NULL`
  6. `activity_log` 에 `performance` 채널로 `reason` 포함 기록
  7. `performance_no` `YYYYMMDD-NNNN` 순증

**검증**: `sail artisan migrate` OK, `sail test --filter=PerformanceSnapshotTest` → 7/7 PASS, 전체 112/112 PASS.

#### P4-S2 (완료, 2026-04-20)

- [request] `app/Http/Requests/StorePerformanceRequest.php`, `UpdatePerformanceRequest.php` 신규 — `performance_date/company_id/product_id/quantity` 검증.
- [controller] `app/Http/Controllers/PerformanceController.php` `create` / `store` / `resolvePreview` 추가 — `PerformanceResolver` 로 단가/수수료 해석 후 스냅샷 저장. `resolve-preview`는 거래처+제품+날짜 입력 시점에 비동기로 단가·수수료를 미리 보여주는 JSON 엔드포인트.
- [route] `POST performance/resolve-preview` → `performance.resolve-preview` (resource 위에 등록).
- [page] `resources/js/Pages/Performance/Create.vue` 신규 — 거래처 AutoComplete (기존 `CompanySearchAutoComplete`) + 제품 AutoComplete (기존 `ProductSearchAutoComplete`) + 날짜·수량 입력 + 미리보기 카드(단가/수수료/소계/수수료 금액 + source 뱃지).

#### P4-S3 (완료, 2026-04-20)

- [page] `resources/js/Pages/Performance/Index.vue`, `Show.vue`, `Edit.vue` 신규 — 기간/거래처/제품/상태 필터 + 페이지네이션 + 상태 뱃지(`draft`/`submitted`/`reviewed`/`approved`/`rejected`/`cancelled`).
- [request] `app/Http/Requests/RejectPerformanceRequest.php` 신규 — `reason` 2~500자 필수.
- [controller] `PerformanceController` 에 `submit`/`review`/`approve`/`reject`/`cancel` 5종 액션 + `index`/`show`/`edit`/`update`/`destroy` 보강. `ChangeReason::with()` 로 모든 상태 전이를 audit log 에 사유 포함 기록.
- [route] `routes/web.php`:
  - `POST performance/{performance}/{submit|review|approve|reject|cancel}` 5종 추가
  - `Route::resource('performance', PerformanceController::class)` 본체 (`->parameters(['performance' => 'performance'])` 적용)
- [policy] `PerformancePolicy` 보강 — 작성자는 `draft/rejected`만 수정·삭제·재제출 가능.
  - 정산 라인 포함 실적 잠금은 문서 목표였으나, 현재 Policy에는 미반영(후속 작업 후보: `docs/planning/ROADMAP.md` 참고).

#### P4-S4 (완료, 2026-04-20)

- [migration] `database/migrations/2026_04_20_160000_create_settlements_table.php` 신규 — `settlement_no`(unique, `YYYYMM-NNNNNN`), `company_id`, `period_month`(YYYY-MM), `status` enum(`draft|confirmed|paid|cancelled`), 합계 4종(`line_count`/`total_quantity`/`total_subtotal`/`total_commission`), `calculated_at`/`confirmed_at`/`paid_at` + 담당자 FK.
- [migration] `database/migrations/2026_04_20_160100_create_settlement_lines_table.php` 신규 — `settlement_id`/`performance_id` FK + `snapshot_unit_price`/`snapshot_commission_rate`/`quantity`/`subtotal`/`commission_amount` 스냅샷 컬럼. unique(`settlement_id`, `performance_id`).
- [model] `app/Models/Settlement.php`, `SettlementLine.php` 신규 — 관계, casts, `settlementNoFor(periodMonth, companyId)` 헬퍼.
- [service] `app/Services/Settlement/SettlementBuilder.php` 신규 — `createOrRebuild(Company, period_month, user)` / `replaceLines(Settlement, user)`.
  - **승인된** 실적만 라인으로 카피하고 합계를 계산.
  - 트랜잭션 내 `settlement_lines` 전체 삭제 후 재작성(멱등성).
- [request] `app/Http/Requests/StoreSettlementRequest.php` 신규 — `company_id` + `period_month`(YYYY-MM regex).
- [controller] `app/Http/Controllers/SettlementController.php` `index`/`show`/`store` (재계산 포함).
- [route] `/settlements` 라우트 3종:
  - `GET /settlements` (`settlements.index`)
  - `POST /settlements` (`settlements.store`) — 생성/재계산(멱등)
  - `GET /settlements/{settlement}` (`settlements.show`)

#### P4-S5 (완료, 2026-04-20)

- [page] `resources/js/Pages/Settlements/Index.vue`, `Show.vue` 신규 — 월×거래처 리스트, 라인 상세, 합계 카드.
  - 생성/재계산은 `Index.vue`의 관리자 폼에서 `POST /settlements` 로 수행.
- [page] `resources/js/Pages/Products/Partials/RecentPerformancePanel.vue` 신규 — 제품 상세 하단 "최근 실적 10건" 미니 패널 (DataTable: 실적번호 링크/일자/거래처+등급/수량/단가/소계/수수료율/수수료/상태 뱃지). 비어있을 때는 "이 제품의 실적이 아직 없습니다." 표시.
- [controller] `ProductController::show` — `recentPerformances` prop 추가. `Performance::where('product_id', $product->id)->with('company:id,company_name,default_commission_grade')->orderByDesc('performance_date')->orderByDesc('id')->limit(10)` 후 직렬화.
- [page] `resources/js/Pages/Products/Show.vue` — `RecentPerformancePanel` import + `recentPerformances` prop + 변경 이력 카드 위에 카드 추가.

#### P4-S6 (완료, 2026-04-20)

- [test] `tests/Feature/Performance/PerformanceHttpTest.php` 신규 — HTTP CRUD + 권한 + 상태 전이 흐름.
- [test] `tests/Feature/Settlement/SettlementBuilderTest.php` 신규 — `SettlementBuilder::build`/`recalculate`, 승인 실적만 집계, draft 가 아닌 정산 재계산 차단, admin 권한 검사.
- [test] `tests/Feature/Settlement/PerformanceToSettlementE2ETest.php` 신규 — 실적 등록(HTTP) → 승인 → 정산 생성 → 라인 스냅샷이 실적과 일치하는지 검증, 동월 두 건 합계 일치.

**검증** (2026-04-20):
- `sail artisan migrate` 모든 마이그 통과 (batch 7~8: performances/settlements/settlement_lines)
- `sail test --filter='Performance|Settlement'` → **20/20 PASS**
- `sail test` 전체 → **125/125 PASS**
- HTTP 스모크: `/performance` 200, `/performance/create` 200, `/settlements` 200, `/products/{id}` 200 (RecentPerformancePanel 정상 렌더)

#### P5-S1 (완료, 2026-04-20)

ROADMAP P1-1 — **실적 CSV 일괄 등록**.

- [service] `app/Services/Performance/PerformanceImportService.php` 신규 — `ProductImportService` 동일 파이프라인(`parseCsv` → `validateHeaders` → `analyze` → `import`). 실적은 upsert 가 아니라 항상 **신규 draft** 생성, `PerformanceResolver::fill()` 로 스냅샷 해석. all-or-nothing 트랜잭션 + `ChangeReason::with('실적 CSV 일괄 등록')` 로 audit log 채움.
- [request] `app/Http/Requests/ImportPerformancesRequest.php` 신규 — file/token/mode 검증.
- [controller] `app/Http/Controllers/PerformanceImportController.php` 신규 — `form` / `handle` (analyze→commit 2단계) / `sample` (샘플 CSV 다운로드).
- [page] `resources/js/Pages/Performance/Import.vue` 신규 — 파일 업로드 + 분석 결과 테이블(스냅샷 미리보기: 단가·수수료율·매출·수수료 + source 뱃지) + 확정 적용 버튼.
- [route] `routes/web.php`:
  - `GET /performance/import` → `performance.import.form`
  - `POST /performance/import` → `performance.import.handle`
  - `GET /performance/import/sample` → `performance.import.sample`
  - `performance.resource` 보다 먼저 선언해 `/performance/{performance}` 충돌 회피.
- [menu] `resources/js/layout/AppMenu.vue` — "실적 CSV 일괄 등록" 링크 추가.
- [ui] `Performance/Index.vue` 헤더에 "CSV 일괄 등록" 버튼 추가.
- [test] `tests/Feature/Performance/PerformanceImportTest.php` 신규 — 9 cases: 헤더 검증(필수/허용), analyze(정상/missing 거래처/quantity 0), import(all-or-nothing 롤백, ChangeReason 기록), HTTP(analyze 모드 Inertia prop, commit 후 draft 저장 + 목록 리다이렉트).

**CSV 컬럼 설계** (허용 헤더 7종):
- 필수: `performance_date`, `quantity`
- 거래처 키(택1): `company_biz_no`(사업자등록번호, 우선 매칭) / `company_name`(fallback)
- 제품 키(택1): `insurance_code`(우선) / `product_code`(fallback)
- 선택: `note`
- 다중 매칭 시 에러 (`biz_no` 로 지정하라는 메시지 포함)
- 빈 quantity·0·비정수 거부. 반품은 음수로 표기.

**검증** (2026-04-20):
- `sail test --filter=PerformanceImportTest` → 9/9 PASS
- 전체 회귀 `sail test` → **134/134 PASS**

#### P5-S2 (완료, 2026-04-20)

- [route] `routes/web.php`
  - `POST /settlements/{settlement}/recalculate` (`settlements.recalculate`)
  - `POST /settlements/{settlement}/confirm` (`settlements.confirm`)
  - `POST /settlements/{settlement}/pay` (`settlements.pay`)
  - `POST /settlements/{settlement}/cancel` (`settlements.cancel`)
- [controller] `app/Http/Controllers/SettlementController.php`
  - `recalculate`(draft만), `confirm`(draft→confirmed), `pay`(confirmed→paid), `cancel`(draft/confirmed→cancelled)
- [policy] `app/Policies/SettlementPolicy.php`
  - `recalculate/confirm/pay/cancel` 권한 및 상태 조건 추가
- [ui] `resources/js/Pages/Settlements/Show.vue`
  - 상세 화면에 재계산/확정/지급완료/취소 버튼 추가(ConfirmDialog 포함)
- [policy] `app/Policies/PerformancePolicy.php`
  - `settlement_lines`에 포함된 실적은 `update/delete/cancel` 차단(원장 잠금)
- [test] `tests/Feature/Settlement/SettlementWorkflowTest.php` 신규 (3 cases)

**검증** (2026-04-20):
- `sail test --filter=SettlementWorkflowTest` → 3/3 PASS
- 전체 회귀 `sail test` → **165/165 PASS**

#### P5-S3 (완료, 2026-04-20)

- [deps] `phpoffice/phpspreadsheet` 추가 (Excel 생성)
- [route] `GET /settlements/{settlement}/export.xlsx` (`settlements.export.excel`)
- [controller] `app/Http/Controllers/SettlementController.php`
  - `exportExcel()` — StreamedResponse로 `.xlsx` 다운로드
- [service] `app/Services/Settlement/SettlementExcelExporter.php` 신규 — 정산 헤더 + 라인 테이블을 1시트로 출력
- [policy] `app/Policies/SettlementPolicy.php` — `export` 권한(관리자) 추가
- [ui] `resources/js/Pages/Settlements/Show.vue` — Excel 다운로드 버튼 추가
- [test] `tests/Feature/Settlement/SettlementExportTest.php` 신규 (admin 다운로드, sales 403)

**검증** (2026-04-20):
- `sail test --filter=SettlementExportTest` → 2/2 PASS
- 전체 회귀 `sail test` → **167/167 PASS**

#### P5-S4 (완료, 2026-04-20)

- [deps] `barryvdh/laravel-dompdf` 추가 (PDF 생성)
- [route] `GET /settlements/{settlement}/export.pdf` (`settlements.export.pdf`)
- [controller] `app/Http/Controllers/SettlementController.php`
  - `exportPdf()` — dompdf로 렌더 후 StreamedResponse 다운로드
- [view] `resources/views/pdf/settlement.blade.php` 신규 — 정산 헤더 + 라인 테이블 PDF 템플릿
- [ui] `resources/js/Pages/Settlements/Show.vue` — PDF 다운로드 버튼 추가
- [test] `tests/Feature/Settlement/SettlementPdfExportTest.php` 신규 (admin 다운로드, sales 403)

**검증** (2026-04-20):
- `sail test --filter=SettlementPdfExportTest` → 2/2 PASS
- 전체 회귀 `sail test` → **169/169 PASS**

#### QA 개선 (완료, 2026-04-24)

운영 품질 보강 — 실적/정산 관련 변경 사항:

- [resolver] `app/Services/Performance/PerformanceResolver.php`
  - `resolve()` 상단에서 `activeOverrideFor()` 1회 호출 후 `resolvePrice/resolveCommission`에 전달 (중복 DB 쿼리 제거)
- [controller] `app/Http/Controllers/PerformanceController.php`
  - `store()`: `DB::transaction()` 안에서 `nextNumberFor()` 호출 (race condition 방지)
  - `approve()`: 해당 월 confirmed/paid 정산 존재 시 `warning` flash 추가
  - `store()`: 미승인 거래처(`company->isApproved()`) 차단
  - `index()`: sales는 본인 실적만 조회
  - `can.viewCreator`: admin only — 프론트 등록자 컬럼 조건부 표시
- [controller] `app/Http/Controllers/SettlementController.php`
  - `loadExportRelations()` private 메서드 추출 (Excel/PDF 중복 제거)
  - `show()`: `hasUnappliedPerformances` prop 추가 (재계산 후 미반영 실적 경고)
  - `index()`: sales는 본인 실적 포함 정산만 조회
- [request] `StorePerformanceRequest` / `UpdatePerformanceRequest`: `before_or_equal:today` 추가
- [model] `Performance::nextNumberFor()`: `lockForUpdate()` 추가
- [model] `Company::isApproved()` 신규 메서드
- [policy] `PerformancePolicy::view()`: sales는 `created_by === user->id` 만 허용
- [policy] `SettlementPolicy::view/export()`: sales는 본인 실적이 포함된 정산만 허용
- [middleware] `HandleInertiaRequests`: `warning` flash 타입 추가
- [ui] `AppLayout.vue`: `watch(page.props.flash)` → PrimeVue Toast 전역 연결
- [ui] `Settlements/Show.vue`: `hasUnappliedPerformances` 배너 추가
- [ui] `Performance/Index.vue`: `can.viewCreator` 조건부 등록자 컬럼
- [ui] `Performance/Create.vue`: 미승인 거래처 경고 메시지 + 버튼 비활성화
- [factory] `ProductFactory`: `approval_status: 'approved'` 기본값 추가 (isSalable 테스트 오류 수정)

**검증** (2026-04-24):
- `sail test` → **185/185 PASS**

#### GAP-2 실적 증빙 파일 첨부 (완료, 2026-04-24)

- [migration] `2026_04_24_100000_create_performance_files_table.php` 신규 — `performance_files` 테이블 (FK cascadeOnDelete, softDeletes)
- [model] `app/Models/PerformanceFile.php` 신규 — SoftDeletes, fillable, belongsTo(Performance/User)
- [model] `app/Models/Performance.php` — `files()` HasMany 관계 추가
- [policy] `app/Policies/PerformancePolicy.php` — `uploadFile()` 메서드 추가 (admin: 비취소 상태, sales: draft/rejected 본인만)
- [request] `app/Http/Requests/StorePerformanceFileRequest.php` 신규 — 최대 10MB, mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,hwp
- [controller] `app/Http/Controllers/PerformanceFileController.php` 신규 — store/destroy/download (private 디스크, 최대 5개 제한)
- [routes] `web.php` — `performance.files.store/destroy/download` 3개 라우트 추가
- [ui] `resources/js/Pages/Performance/Show.vue` — 증빙 파일 목록·업로드·삭제·다운로드 섹션 추가
- [test] `tests/Feature/Performance/PerformanceFileTest.php` 신규 — 6/6 PASS

**검증** (2026-04-24):
- `sail test` → **191/191 PASS**

#### GAP-1 목표 관리 / Sales Quota (완료, 2026-04-24)

**설계 결정 사항**:
- MariaDB/MySQL UNIQUE 인덱스는 NULL을 서로 다른 값으로 취급 → `product_id=NULL` 중복을 DB 레벨에서 잡을 수 없음 → DB unique 제약 없이, `StoreSalesQuotaRequest`에서 `Rule::unique()->where(closure)` + `->whereNull('product_id')` 로 앱 레벨 검증
- `performances.subtotal`은 STORED GENERATED 컬럼 (`quantity * unit_price`) → `sum('subtotal')` Eloquent 쿼리 정상 작동 (DashboardController에서 이미 사용 중)
- `Route::resource('sales-quotas', ...)` 생성 라우트 파라미터명은 **`sales_quota`** (snake_case) — `FormRequest`에서 `$this->route('sales_quota')` 로 모델 바인딩 접근. `camelCase(salesQuota)` 사용 시 null 반환되어 ignore() 미작동

**생성/변경 파일**:
- [migration] `database/migrations/2026_04_24_110000_create_sales_quotas_table.php` — `sales_quotas` 테이블: `user_id`/`product_id`(nullable)/`period_type`(enum)/`period`(string 10)/`target_amount`(decimal 14,2)/`created_by`, 인덱스 2개 (user+period, product+period), DB unique 제약 없음
- [model] `app/Models/SalesQuota.php` — fillable 6개, `user()`/`product()`/`creator()` BelongsTo
- [policy] `app/Policies/SalesQuotaPolicy.php` — `viewAny/create/update/delete` 모두 `isAdmin()` 전용
- [service] `app/Services/QuotaAchievementService.php` — `calculate(userId, periodType, period, ?productId)`: 기간 파싱(monthly/quarterly/yearly) → `performance_date` 범위 → approved 실적 subtotal 합산
- [request] `app/Http/Requests/StoreSalesQuotaRequest.php` — authorize admin, period 형식 closure 검증, `Rule::unique` with nullable product_id 처리, `->ignore($this->route('sales_quota')?->id)`
- [controller] `app/Http/Controllers/SalesQuotaController.php` — `index`(필터+달성률 인라인 계산)/`store`/`update`/`destroy`, `QuotaAchievementService` 주입
- [factory] `database/factories/SalesQuotaFactory.php` — `monthly()`/`quarterly()`/`yearly()` states
- [routes] `routes/web.php` — `admin` 미들웨어 그룹에 `Route::resource('sales-quotas', ...)->only([index,store,update,destroy])` 추가
- [vue] `resources/js/Pages/SalesQuotas/Index.vue` — 필터(영업사원/기간유형/기간) + DataTable(달성률 ProgressBar) + Dialog 모달(등록/수정)
- [menu] `resources/js/layout/AppMenu.vue` — "관리" 섹션에 "목표 관리" 항목 추가 (admin only)
- [controller] `app/Http/Controllers/DashboardController.php` — 이번달 월별 quota 목록 + 달성률 계산 → `quotaAchievements` prop 추가
- [controller] `app/Http/Controllers/SalesDashboardController.php` — 본인 이번달 monthly quota → `myQuota` prop 추가
- [test] `tests/Feature/SalesQuota/SalesQuotaTest.php` — 15 cases: 권한/CRUD/중복/기간형식 검증
- [test] `tests/Feature/SalesQuota/QuotaAchievementTest.php` — 7 cases: monthly·quarterly·yearly 달성액, 미승인 제외, 제품 필터, HTTP prop 포함 확인

**빌드 주의**: 새 Vue 파일 추가 후 Vite manifest 갱신 필요 — Sail 컨테이너 내부 `rollup linux arm64` 미포함으로 `sail npm run build` 실패. **호스트(Mac)에서 `npm run build` 실행**.

**검증** (2026-04-24):
- `sail test tests/Feature/SalesQuota/` → **22/22 PASS**
- `sail test` (전체 회귀) → **213/213 PASS**

#### GAP-3 영업사원별 수수료 명세 (완료, 2026-05-19)

**설계 결정 사항**:
- 별도 테이블 신설 없이 `performances` (status=approved)의 `commission_amount` / `subtotal` 합산으로 구현
- `CommissionSummaryPolicy` 는 단일 모델에 묶이지 않으므로 **Gate**(`AppServiceProvider::boot`)로 4개 ability 등록: `view-commission-summary`, `export-commission-summary`, `view-commission-statement`, `export-commission-statement`
- 본인 명세서 접근은 `admin OR self`. URL은 `GET /commission-summary/users/{user}/statement` — RESTful URL 유지하면서 self 체크
- 기간 필터는 month(YYYY-MM) 또는 from/to(YYYY-MM-DD) 둘 다 지원. 기본은 현재 월

**생성/변경 파일**:
- [service] `app/Services/CommissionSummaryService.php` — `summaryByUser(from,to,?userId)` / `statementFor(userId,from,to)` / `monthlyCommissionFor(userId,month)` / `monthRange(month)` / `resolveRange(from,to,month)`
- [service] `app/Services/CommissionSummaryExcelExporter.php` — `streamSummary(rows,from,to)` 영업사원별 합계 Excel
- [policy] `app/Policies/CommissionSummaryPolicy.php` — Gate 등록용 4개 메서드 (viewAny/export/viewStatement/exportStatement)
- [controller] `app/Http/Controllers/CommissionSummaryController.php` — index / exportExcel / statement / statementPdf 4 액션
- [provider] `app/Providers/AppServiceProvider.php` — Gate::define 4개 등록
- [view] `resources/views/pdf/commission_statement.blade.php` — DomPDF 명세서 템플릿 (헤더 + 합계 + 라인 테이블 + tfoot 합계)
- [vue] `resources/js/Pages/CommissionSummary/Index.vue` — admin 합계 페이지 (필터/합계카드/DataTable/Excel 버튼)
- [vue] `resources/js/Pages/CommissionSummary/Statement.vue` — 개인 명세 페이지 (필터/합계카드/라인 테이블/PDF 버튼)
- [controller] `app/Http/Controllers/SalesDashboardController.php` — `CommissionSummaryService` 주입 + `thisMonthCommission` prop
- [vue] `resources/js/Pages/Sales/Dashboard.vue` — "이번달 내 수수료 합계" 카드 + 상세 명세 바로가기 링크
- [menu] `resources/js/layout/AppMenu.vue` — 정산 섹션에 "수수료 명세" 메뉴 추가 (admin → 합계 페이지, sales → 본인 명세서 직링크)
- [routes] `routes/web.php` — 4개 라우트 추가:
  - `GET /commission-summary` (`commission-summary.index`, admin)
  - `GET /commission-summary/export.xlsx` (`commission-summary.export.excel`, admin)
  - `GET /commission-summary/users/{user}/statement` (`commission-summary.statement`, self/admin)
  - `GET /commission-summary/users/{user}/statement.pdf` (`commission-summary.statement.pdf`, self/admin)
- [test] `tests/Feature/CommissionSummary/CommissionSummaryTest.php` — 8 cases (접근 권한, 집계 정확성, 미승인 제외, Excel 다운로드, 기간 필터)
- [test] `tests/Feature/CommissionSummary/CommissionStatementTest.php` — 8 cases (본인/타인 접근, 합계 정확성, PDF 다운로드, Sales 대시보드 prop)

**검증** (2026-05-19):
- `sail test --filter=CommissionSummary` → **16/16 PASS**
- `sail test` (전체 회귀) → **229/229 PASS**

#### GAP-4 영업사원-거래처 담당 배정 (완료, 2026-05-19)

**설계 결정 사항**:
- 기존 `hospital_pharmacy_assignments` / `hospital_company_assignments` 와는 별개 — 영업사원 배정은 신규 `company_sales_assignments` 로 분리 (PRODUCT_SPEC §4.12 결정 반영)
- 거래처(`companies`) × 사용자(`users`) 다대다 pivot. unique `(company_id, user_id)` — 한 거래처에 같은 영업사원 중복 배정 금지
- 영업사원이 아닌 사용자(admin/inactive)는 담당 지정 불가 — `StoreCompanySalesAssignmentRequest::rules()` 에서 `users.role=sales AND is_active=true` 검증
- 거래처 자동완성(`companies.search`)에서 **명시적 필터 없을 때도 sales 의 담당 거래처가 자동으로 상단에 정렬**되도록 LEFT JOIN + `ORDER BY CASE WHEN csa.id IS NULL THEN 1 ELSE 0 END` 추가 (실적 등록 UX 개선)
- Policy 는 모델 묶임 (`CompanySalesAssignmentPolicy`) — Laravel 11 auto-discovery 로 자동 매핑

**생성/변경 파일**:
- [migration] `database/migrations/2026_05_19_163030_create_company_sales_assignments_table.php` — 테이블 + unique + 인덱스 2종
- [model] `app/Models/CompanySalesAssignment.php` — fillable, casts, `company`/`salesUser`/`assigner` BelongsTo
- [model] `app/Models/Company.php` — `salesAssignments(): HasMany` + `salesUsers(): BelongsToMany` (pivot `assigned_at/assigned_by`)
- [model] `app/Models/User.php` — `isSales()` 메서드 + `companyAssignments(): HasMany` + `assignedCompanies(): BelongsToMany`
- [factory] `database/factories/CompanySalesAssignmentFactory.php` — 기본 sales 사용자 생성
- [policy] `app/Policies/CompanySalesAssignmentPolicy.php` — viewAny(any) / create·delete(admin)
- [request] `app/Http/Requests/StoreCompanySalesAssignmentRequest.php` — role/active/unique 검증 + 한국어 메시지
- [controller] `app/Http/Controllers/CompanySalesAssignmentController.php` — store / destroy + `ChangeReason::with()` 컨텍스트
- [controller] `app/Http/Controllers/CompanyController.php` — `show()` 에 `salesAssignments` / `availableSalesUsers` / `can.manageSalesAssignments` prop 추가, `search()` 에 `assigned_to_me`·`assigned_user_id` 옵션 + sales 자동 우선 정렬
- [controller] `app/Http/Controllers/SalesDashboardController.php` — `myAssignedCompanies` (상위 5건) / `myAssignedCompanyTotal` prop
- [vue] `resources/js/Pages/Companies/Show.vue` — 담당 영업사원 DataTable + `Dialog`+`Select` 배정 모달 + ConfirmDialog 해제
- [vue] `resources/js/Pages/Sales/Dashboard.vue` — "내 담당 거래처" 카드 (Tag/Link로 거래처 칩 + 전체 보기 링크)
- [routes] `routes/web.php` — 2개 라우트:
  - `POST /companies/{company}/sales-assignments` (`companies.sales-assignments.store`, admin)
  - `DELETE /companies/{company}/sales-assignments/{assignment}` (`companies.sales-assignments.destroy`, admin)
- [test] `tests/Feature/CompanySalesAssignment/CompanySalesAssignmentTest.php` — 13 cases:
  1. 비로그인 접근 차단
  2. sales 등록 403
  3. admin 등록 성공 (assigned_at/by 기록)
  4. role=admin 사용자 지정 422
  5. is_active=false sales 지정 422
  6. 동일 (company, user) 중복 등록 422
  7. admin 해제 성공
  8. sales 해제 403
  9. 거래처 ID 변조 시 404
  10. 거래처 상세 prop 검증
  11. sales 검색 시 담당 거래처 우선 정렬
  12. `assigned_to_me=1` 필터 정확성
  13. Sales 대시보드 `myAssignedCompanies` prop 포함

**검증** (2026-05-19):
- `sail test --filter=CompanySalesAssignment` → **13/13 PASS**
- `sail test` (전체 회귀) → **242/242 PASS**

#### GAP-5 지급 관리 고도화 (완료, 2026-05-19)

**설계 결정 사항**:
- `paid_at`(시스템 상태 전이 시각, datetime) 과 `paid_on`(실제 지급일, date) 을 **분리** — 회계상 날짜와 시스템 처리 시각을 구분
- `payment_method` 는 enum(`bank_transfer`/`cash`/`other`) — 추후 카드/어음 추가 시 enum 확장
- `payment_batch_no` 는 string(50, indexed) — 월말 N건 일괄 지급 시 동일 값 부여하여 목록 필터링 가능
- `PaySettlementRequest::authorize()` 에서 `SettlementPolicy::pay()` 호출 — 컨트롤러 메서드에서 별도 `authorize()` 불필요
- `SettlementPolicy::uploadPaymentFile` 은 `confirmed` 또는 `paid` 상태에서만 허용 — 지급 처리 전(이체 예정 캡처)이나 후(이체확인서) 모두 첨부 가능
- 증빙 파일은 `local`(private) 디스크 + 인증 다운로드 라우트 + URL 변조 방지(file.settlement_id 검증)
- Excel/PDF 헤더에 지급 정보 4행 추가 — 라인 시작 행을 13 → 17로 이동

**생성/변경 파일**:
- [migration] `database/migrations/2026_05_19_170328_add_payment_columns_to_settlements_table.php` — `paid_on`/`payment_method`(enum)/`payment_batch_no`(string 50 indexed)/`payment_note`(text)
- [migration] `database/migrations/2026_05_19_170329_create_settlement_payment_files_table.php` — `settlement_id` cascade + softDeletes + 표준 파일 컬럼
- [model] `app/Models/Settlement.php` — `PAYMENT_METHOD_*` 상수 + fillable/casts 확장 + `paymentFiles()` HasMany + activity log 컬럼에 지급 4종 추가
- [model] `app/Models/SettlementPaymentFile.php` — fillable + `settlement`/`uploader` BelongsTo + SoftDeletes
- [factory] `database/factories/SettlementPaymentFileFactory.php`
- [request] `app/Http/Requests/PaySettlementRequest.php` — `paid_on` required + `before_or_equal:today` + method enum + 길이 제한, `authorize()` 에서 `pay` Policy 호출
- [request] `app/Http/Requests/StoreSettlementPaymentFileRequest.php` — 10MB + mimes(pdf/jpg/jpeg/png/doc/docx/xls/xlsx/hwp)
- [policy] `app/Policies/SettlementPolicy.php` — `uploadPaymentFile(admin, confirmed|paid)` 추가
- [controller] `app/Http/Controllers/SettlementController.php` — `pay()` 시그니처를 `PaySettlementRequest` 로 변경하고 4종 컬럼 저장, `index()` 에 `payment_batch_no` 필터, `show()` 에 `paymentFiles`/`uploadPaymentFile` prop
- [controller] `app/Http/Controllers/SettlementPaymentFileController.php` — store/destroy/download (private 디스크, `settlement-payment-files/{id}/` 경로)
- [exporter] `app/Services/Settlement/SettlementExcelExporter.php` — 헤더 4행 추가 (지급일/수단/Batch/메모) + 라인 시작 17행
- [view] `resources/views/pdf/settlement.blade.php` — 지급 정보 조건부 행 + payment_method 한글 매핑
- [vue] `resources/js/Pages/Settlements/Show.vue` — 지급 처리 Dialog(date/Select/InputText/Textarea), 지급 정보 카드, 증빙 파일 업로드/DataTable/다운로드/삭제 ConfirmDialog
- [vue] `resources/js/Pages/Settlements/Index.vue` — `payment_batch_no` 필터 입력 추가 + 3열 → 4열 그리드
- [routes] `routes/web.php` — 3개 라우트:
  - `POST /settlements/{settlement}/payment-files` (`settlements.payment-files.store`)
  - `DELETE /settlements/{settlement}/payment-files/{file}` (`settlements.payment-files.destroy`)
  - `GET /settlements/{settlement}/payment-files/{file}/download` (`settlements.payment-files.download`)
- [test] `tests/Feature/Settlement/SettlementPaymentTest.php` — 7 cases (paid_on 필수·미래 거부·메타데이터 저장·잘못된 method·sales 403·draft 차단·batch 필터)
- [test] `tests/Feature/Settlement/SettlementPaymentFilesTest.php` — 9 cases (업로드 권한 매트릭스·실행 파일 차단·삭제 디스크 cascade·URL 변조 404·다운로드 권한)
- [test] `tests/Feature/Settlement/SettlementWorkflowTest.php` — `paid_on` 인자 추가 회귀 유지

**검증** (2026-05-19):
- `sail test --filter='SettlementPayment|SettlementWorkflow'` → **19/19 PASS**
- `sail test` (전체 회귀) → **258/258 PASS**

### 9.3 다음 세션 인계 메모

- Phase 4 (P4-S1~S6) + Phase 5-S1 (CSV 일괄 등록) + QA 개선 + GAP-1·2·3·4 + **GAP-5 완료**.
- 다음 작업: **GAP-6 월간 보고서 템플릿** — 거래처/영업사원/제품 3종 요약 리포트(Excel). 상세는 [`ROADMAP.md`](../../planning/ROADMAP.md) GAP-6-1~5 (작업 단위: GAP-6-1~5, 스펙 확정은 GAP-6-1)
- 운영 검증 권장 흐름:
  - `/performance/create` 에서 승인된 거래처+제품 선택 → 등록 → submit → review → approve
  - `/performance/import` 에서 샘플 CSV 다운로드 → 수정·업로드 → 검증 → 확정 적용
  - `/performance/{id}` 에서 증빙 파일 업로드(최대 5개) → 다운로드(인증 라우트) → 삭제 확인
  - `/settlements` 에서 정산 생성 → Show 에서 합계·라인 확인 → 재계산
  - `/commission-summary` (admin) 에서 영업사원별 수수료 합계 확인 → Excel 다운로드 → 특정 영업사원 명세 진입 → PDF 다운로드
  - `/sales/dashboard` (sales) 에서 "이번달 내 수수료 합계" 카드 → "상세 명세" 클릭 → 본인 명세서 진입
  - `/companies/{id}` (admin) 에서 "담당 영업사원" 카드 → 영업사원 배정 모달 → 다른 영업사원으로 변경 (해제 후 재배정) → Sales 대시보드 "내 담당 거래처" 변화 확인
  - `/settlements/{id}` 에서 confirmed 정산 → "지급완료" 버튼 → 모달에서 paid_on/method/Batch/메모 입력 → 저장 후 지급 정보 카드 표시 → 증빙 파일 업로드(이체확인서 PDF) → 다운로드 → `/settlements?payment_batch_no=...` 필터로 동일 Batch 정산 목록 조회 → Excel/PDF에 지급 정보 출력 확인
  - `/notices` 에서 첨부파일 업로드 → 다운로드(인증 라우트) 확인

> **앞으로의 작업**은 모두 [`docs/planning/ROADMAP.md`](../../planning/ROADMAP.md) 로 통합 관리합니다. 본 문서는 진행 로그만 유지합니다.
