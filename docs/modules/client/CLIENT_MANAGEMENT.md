# 클라이언트 (약국·병원·영업사원) 마스터 — 설계·진행 문서

> 본 문서는 정진팜의 **거래처 중 약국/병원 유형의 독립 마스터 모듈** 및 **영업사원 목록 뷰**의 단일 설계 문서입니다. ROADMAP 상 **P0-1**.
> 앞으로 남은 작업·다음 단계는 [`docs/planning/ROADMAP.md`](../../planning/ROADMAP.md) 로 통합 관리합니다.
> 🔗 병의원 공공데이터(인허가 CSV + 심평원 Excel) 정규화 적재 설계: [`HOSPITAL_PUBLIC_DATA.md`](HOSPITAL_PUBLIC_DATA.md)
> 🔗 병의원 행안부(MOIS) API 증분 동기화(GAP-12, R1~R7 완료): [`HOSPITAL_LOCALDATA_API_SYNC.md`](HOSPITAL_LOCALDATA_API_SYNC.md)

---

## 0. 범위 결정

- **약국(Pharmacy) / 병원(Hospital)**: 독립 테이블 + 독립 CRUD 모듈.
- **영업사원(Sales Rep)**: 별도 테이블을 만들지 **않는다**. 시스템 사용자(`users.role='sales'`)를 재사용.
  - `/clients/sales` 은 **admin 전용** read-only 목록 (sales 접근 시 403)
  - 실제 CRUD 는 P0-2(`/users`)에서.
- **거래처(Company)** 와는 별개의 테이블. Company 는 "제조사/도매상(소스)" 중심이고, Pharmacy/Hospital 은 "판매처(고객)". 실적과의 연동 방식은 후속 단계에서 결정 (현재 실적은 `performances.company_id` 만 사용).

---

## 1. 데이터 모델

### 1.1 `pharmacies`

| 컬럼 | 타입 | 특성 | 설명 |
|---|---|---|---|
| `id` | PK | | 약국 PK |
| `pharmacy_code` | string(50), **unique**, nullable | 사내 식별자 |
| `pharmacy_name` | string, **index** | 약국명 |
| `business_registration_number` | string(20), **unique**, nullable | 사업자등록번호 |
| `representative_name` | string(100), nullable | 대표자명 |
| `postcode`, `address` | 주소 | |
| `landline_phone`, `mobile_phone` | 전화 | |
| `contact_person_name`, `contact_phone`, `email` | 담당자 | |
| `remarks` | text | 비고 |
| `status` | enum(`active`,`inactive`), **index** | 기본 `active` |
| `created_by`, `updated_by` | FK users | |
| `timestamps`, `softDeletes` | | |

### 1.2 `hospitals`

`pharmacies` 와 유사하되 **유형·전문 분야** 컬럼 추가.

| 추가/변경 컬럼 | 타입 | 설명 |
|---|---|---|
| `hospital_type` | enum(`general_hospital`,`hospital`,`clinic`,`dental`,`oriental`,`other`), **index** | 병의원 유형 |
| `specialty` | string(100), nullable | 전문 분야 |
| `phone` | string(20) | 약국의 `landline_phone` 자리를 단일 전화로 통일 |

### 1.3 영업사원 (별도 테이블 없음)

- `users.role = 'sales'` 로 식별.
- `/clients/sales` (read-only, **admin-only**) → `SalesRepController::index` → `User::where('role', 'sales')` 페이지네이션.
- CRUD 는 P0-2 `/users` 에서.

---

## 2. 권한 (Policy)

| 액션 | 허용 |
|---|---|
| `viewAny` / `view` | 로그인 사용자 누구나 |
| `create` / `update` / `delete` | admin 한정 |

sales 사용자는 목록·상세 조회는 가능, 등록 버튼은 숨김(`can.create=false`).

---

## 3. UI 구성

라우트 prefix 는 **복수형** 사용 (`/pharmacies`, `/hospitals`). 사이드바(GAP-9 이후): **마스터 관리 > 약국·병의원**, 영업사원(조회)은 **관리 > 영업사원(조회)** (`/clients/sales`).

- **목록** (`Index.vue`): DataTable + 검색(이름/코드/사업자번호/담당자) + 상태 필터 + 병원은 유형 필터 + Paginator.
- **등록** (`Create.vue`): PrimeVue InputText/Select/Textarea, `Partials/{Pharmacy,Hospital}Form.vue` 공용 컴포넌트.
- **수정** (`Edit.vue`): 동일 폼 컴포넌트 재사용.
- **상세** (`Show.vue`): dl 그리드 + 수정·삭제 버튼 + PrimeVue Confirm 다이얼로그.

영업사원 목록은 `Clients/Sales/Index.vue` — read-only DataTable + "사용자 관리" 링크 안내 메시지.
- 사이드바의 "영업사원" 메뉴는 **admin 에게만 노출** (`AppMenu.vue` `visible`).

---

## 4. 진행 현황 (Progress Log)

### 4.1 단계표

| 단계 | 제목 | 상태 | 시작 | 완료 | 비고 |
|---|---|---|---|---|---|
| C-S1 | `pharmacies`/`hospitals` 마이그·모델·Factory·Policy | 🟢 완료 | 2026-04-20 | 2026-04-20 | 테이블/모든 컬럼 comment 포함 |
| C-S2 | `PharmacyController`/`HospitalController` CRUD + Form Request | 🟢 완료 | 2026-04-20 | 2026-04-20 | admin 쓰기, 누구나 조회 |
| C-S3 | `SalesRepController` (role=sales 목록) | 🟢 완료 | 2026-04-20 | 2026-04-20 | read-only, P0-2 에서 CRUD |
| C-S4 | Vue 페이지 (Index/Create/Edit/Show × 2 + Sales Index) | 🟢 완료 | 2026-04-20 | 2026-04-20 | Partials 공용 Form 컴포넌트 |
| C-S5 | Pest 테스트 | 🟢 완료 | 2026-04-20 | 2026-04-20 | 17/17 PASS · 전체 151/151 PASS |
| C-S6 | sales UI 스모크(권한 누수 보강) | 🟢 완료 | 2026-04-20 | 2026-04-20 | `/clients/sales` admin-only 잠금 + 메뉴 노출 제한 + 스모크 테스트 |
| GAP-12 | 병의원 MOIS API 증분 동기화(R1~R7) | 🟢 완료 | 2026-06-04 | 2026-06-04 | 공용 매퍼·API 클라이언트·proj4php 좌표·이력/커서·증분 서비스·Job/명령/스케줄러(비활성). 실데이터 검증으로 `MNG_NO==hospital_code` 확정. 설계: [`HOSPITAL_LOCALDATA_API_SYNC.md`](HOSPITAL_LOCALDATA_API_SYNC.md). R8 이력 UI 선택 후속 |
| **GAP-4** | **영업사원-거래처 담당 배정** | 🟢 완료 | 2026-05-19 | 2026-05-19 | `company_sales_assignments` + Model/Policy/Controller/검색 담당 필터 + 거래처 상세 배정 UI + Sales 대시보드 "내 담당" 카드. 상세: [`ROADMAP.md`](../../planning/ROADMAP.md) GAP-4, [`PRODUCT_SPEC.md`](../../planning/PRODUCT_SPEC.md) §4.12 |

### 4.2 변경 파일 요약

- [migration] `database/migrations/2026_04_20_170000_create_pharmacies_table.php`, `..170100_create_hospitals_table.php`
- [model] `app/Models/Pharmacy.php`, `Hospital.php` (+ `Hospital::TYPES` 상수)
- [factory] `database/factories/PharmacyFactory.php`, `HospitalFactory.php`
- [policy] `app/Policies/PharmacyPolicy.php`, `HospitalPolicy.php`
- [request] `app/Http/Requests/Store{Pharmacy,Hospital}Request.php`, `Update{Pharmacy,Hospital}Request.php`
- [controller] `app/Http/Controllers/PharmacyController.php`, `HospitalController.php`, `SalesRepController.php`
- [route] `routes/web.php` — `Route::resource('pharmacies'|'hospitals', ...)`, `GET /clients/sales` (**admin-only**)
- [menu] `resources/js/layout/AppMenu.vue` — 링크 경로 수정 (`/pharmacies`, `/hospitals`, `/clients/sales`) + "영업사원" 메뉴 admin-only
- [page] `resources/js/Pages/Clients/Pharmacies/{Index,Create,Edit,Show}.vue` + `Partials/PharmacyForm.vue`
- [page] `resources/js/Pages/Clients/Hospitals/{Index,Create,Edit,Show}.vue` + `Partials/HospitalForm.vue`
- [page] `resources/js/Pages/Clients/Sales/Index.vue`
- [test] `tests/Feature/Clients/{PharmacyCrudTest,HospitalCrudTest,SalesIndexTest}.php` → 17/17 PASS
- [test] `tests/Feature/Smoke/SalesSmokeTest.php` 신규 — sales 권한 스모크

---

## 5. 후속 작업 (다음 세션 인계)

> 전체 백로그는 [`docs/planning/ROADMAP.md`](../../planning/ROADMAP.md) 참조. 본 모듈 관련 잔여 항목만 요약:

- **CSV 일괄 등록** — `PerformanceImportService` 패턴을 복제해 `PharmacyImportService` / `HospitalImportService` 신규. 키 컬럼: 사업자등록번호 또는 code.
- **레거시(shinilset) 데이터 import** — 기존 `pharmacies`/`hospitals` 테이블의 실제 스키마 확인 후 매핑.
  - 1) **레거시 스키마 덤프(권장)**: `php artisan legacy:inspect-schema`
    - 기본 output: `storage/app/legacy/inspect_{db}_{YYYYMMDD_HHMMSS}.json`
    - 테이블 제한: `php artisan legacy:inspect-schema --tables=pharmacies --tables=hospitals`
  - 2) 스키마 기반으로 매핑표 작성 → dry-run 리포트 → 실 import
    - 약국: `php artisan legacy:import-pharmacies --dry-run` → OK면 `php artisan legacy:import-pharmacies`
    - 병원: `php artisan legacy:import-hospitals --dry-run` → OK면 `php artisan legacy:import-hospitals`
- **실적과의 연동** — 현재 `performances.company_id` 는 `companies` 만 참조. 약국/병원을 거래처로 사용하려면 (a) `companies` 에 통합하거나 (b) `performances` 에 `partner_type`+`partner_id` 다형 관계 추가. 도메인 검토 필요.
- **영업사원-거래처 담당 배정 (GAP-4)** — 🟢 **완료**.
  - 거래처(`companies`) 기준 영업사원 담당 배정 기능은 GAP-4로 구현 완료(담당 배정 CRUD, 검색 우선순위/필터, 거래처 상세 UI, Sales 대시보드 카드).
  - 약국·병원 독립 마스터와의 직접 연동은 후속(실적은 현재 `performances.company_id` 기준).
- **영업사원별 담당 약국/병원 (레거시 pivot)** — `client_pharmacy_assignments` 를 그대로 도입할지는 **미결정** (GAP-4는 `companies` 기준).
  - (참고) 레거시 `client_pharmacy_assignments.client_id` 는 영업사원보다 **병의원(Hospital)** 케이스가 많음.
  - 병원↔약국 관계는 `hospital_pharmacy_assignments` 로 이관했고, 필요 시 `legacy:import-client-pharmacy-as-hospital-pharmacy` 로 흡수 가능.
- **자동완성 API** — 실적/정산 폼에서 약국/병원 선택이 필요해지면 `PharmacyController::search` / `HospitalController::search` 추가 (`CompanyController::search` 패턴).
- **기준정보 마스터 admin 분리 (GAP-9)** — 약국·병원을 거래처와 독립된 기준정보 마스터로 IA·메뉴 분리. 기능은 본 모듈 재사용, 라우트 불변. 설계: [`MASTER_DATA_ADMIN.md`](../master-data/MASTER_DATA_ADMIN.md).

---

**문서 버전**: 1.2
**작성일**: 2026-04-20
**최종 갱신**: 2026-05-19 (GAP-4 완료 반영, §4.1·§5 동기화)
