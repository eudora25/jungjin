# 멀티테넌시 — 제약사 테넌트 + 역할 계층 (GAP-10)

> 한 시스템에 **여러 제약사**가 입주하는 **멀티테넌트(multi-tenant)** 구조로 전환하는 설계 문서.
> 규모 **XL** — 거의 모든 도메인 테이블·쿼리·Policy·메뉴에 영향. 단계(Phase) 분할 진행.
> 진행/백로그는 [`docs/planning/ROADMAP.md`](../../planning/ROADMAP.md) **GAP-10** 으로 통합 관리.

> **운영 경로 B 확정 (2026-05-29)**: 멀티테넌시 구현·격리 검증(MT-7) **후** 레거시 cutover(OPS-7).
> **진행**: MT-1·MT-2(1부) 🟢 완료 → **Now = MT-3** (`TenantScope`·`ResolveTenant`). ROADMAP §3·GAP-10 작업 단위 참조.

---

## 0. 배경 / 목표

현재 시스템은 **단일 조직(single-tenant)** 전제다. `users.role` 은 `admin`/`sales` 2종뿐이고, 모든 도메인 데이터(제품·실적·정산·거래처)가 전역 공유된다.

요구사항: 정진팜 플랫폼 위에 **여러 제약사**를 입주시키고, 각 제약사는 자사 데이터만 보고 관리하며, 영업사원은 소속 제약사에 종속된다. 정진팜 자체 운영자는 전체를 관리한다.

```
정진팜 플랫폼
 ├─ 제약사 A (테넌트)
 │    ├─ admin(A)        ← 자사 제품·실적·정산·거래처·소속 영업사원 관리
 │    └─ sales(A) ×N     ← A의 제품 판매 실적 등록
 ├─ 제약사 B (테넌트)
 │    ├─ admin(B)
 │    └─ sales(B) ×N
 └─ super_admin          ← 정진팜 운영자: 전체 제약사·시스템 관리
```

---

## 1. 역할 계층 (Role Hierarchy)

`users.role` enum 을 확장한다.

| role | 명칭 | 소속(tenant) | 권한 요약 |
|---|---|---|---|
| `super_admin` (신규) | 플랫폼 운영자 (정진팜) | **없음(null)** | 전체 제약사 CRUD, 모든 테넌트 데이터 조회, 시스템 설정 |
| `admin` (기존, 의미 변경) | 제약사 관리자 | 특정 제약사 1곳 | **자사 테넌트 한정** 전권(제품/실적/정산/거래처/목표/소속 sales 관리) |
| `sales` (기존) | 영업사원 | 특정 제약사 1곳 | 소속 제약사 내 본인 실적 등록·조회 (기존 sales 권한 유지) |

- 기존 `admin` 의 권한 범위가 "전역" → **"자사 테넌트"** 로 축소된다. (super_admin 이 전역 담당)
- GAP-7(검수자/정산담당 등 *역할 세분화*)는 **테넌트 내부의 직무 축**으로, 본 계층과 직교한다. 멀티테넌시 도입 후 그 위에 얹는다.

---

## 2. 테넌트 모델 — 제약사 엔티티

**신규 테이블 `tenants`** (✅ D-1 확정). 모델명 `Tenant`, 화면·문서 표기는 "제약사". 기존 `companies`(거래처=판매처/거래상대)와 **완전히 별개**다. (거래처·약국과 명칭 혼동을 피하려 범용 `tenants` 채택)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `id` | PK | 제약사 PK |
| `name` | string | 제약사명 (자사) |
| `code` | string, unique, nullable | 사내/플랫폼 식별 코드 |
| `business_registration_number` | string, unique, nullable | 사업자등록번호 |
| `status` | enum(`active`,`inactive`) | 입주 상태 |
| `settings` | json, nullable | 테넌트별 설정(후속) |
| `created_by` | FK users (super_admin) | |
| `timestamps`, `softDeletes` | | |

> 명칭(`tenants` vs `pharma_companies`)은 **D-1** 에서 확정.

---

## 3. 데이터 격리 설계 (Tenant Scoping)

### 3.1 테넌트 종속 (tenant-scoped) — 제약사별 격리

> 주요 집계 루트 테이블에 `tenant_id`(FK, NOT NULL) 추가. 자식 테이블은 부모 관계로 스코핑(또는 필요 시 denormalize).

| 테이블 | 스코핑 방식 |
|---|---|
| `products` | `tenant_id` 직접 |
| `product_prices`, `product_commission_rates`, `product_files`, `company_product_overrides` | 부모 `product`/`company` 통해 상속 |
| `companies` (거래처) | `tenant_id` 직접 |
| `company_sales_assignments` | `company` 통해 상속 |
| `performances` | `tenant_id` 직접 |
| `performance_files` | 부모 `performance` 상속 |
| `settlements` (+ lines, `settlement_payment_files`) | `tenant_id` 직접(헤더) + 자식 상속 |
| `sales_quotas` | `tenant_id` 직접 |
| `users` (admin/sales) | `tenant_id` (super_admin 은 null) |

### 3.2 공유 (shared / global) — 제약사 간 공통

| 테이블 | 사유 |
|---|---|
| `pharmacies`, `hospitals` | **공유 마스터** (결정). 조회는 전체, **쓰기는 super_admin 전용 + 제약사 admin 변경요청 승인 워크플로**(D-6, §3.3). GAP-9 독립 마스터와 일관 |
| `hospital_pharmacy_assignments` | 공유 마스터 간 관계 |
| `health_individual_drugs` (심평원 참조 등) | 외부 참조 데이터 |
| `notices` (공지) | ✅ **전역 + 테넌트 둘 다**(D-3). `tenant_id` **nullable** — null=전역(super_admin 작성), 값 있으면 테넌트 공지(제약사 admin 작성). 조회: 사용자는 `tenant_id IS NULL OR tenant_id = 내 테넌트` |
| `activity_log` | 전역 기록, 단 조회는 테넌트 필터 |

> **주의**: `hospital_company_assignments` 는 공유 `hospital` ↔ 테넌트-scoped `company` 연결. company 쪽 tenant 로 가시성 결정.

### 3.3 공유 마스터 변경요청 승인 워크플로 (D-6) — 약국·병원

약국·병원은 **공유 마스터**이므로 데이터 품질을 위해 **실제 쓰기는 super_admin 전용**. 제약사 admin 은 직접 수정하지 못하고, **변경요청을 제출**하면 super_admin 이 검토·승인 시 반영한다(반려 가능).

**신규 테이블 `master_change_requests`**

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `id` | PK | |
| `tenant_id` | FK tenants | 요청한 제약사 |
| `requested_by` | FK users | 요청자(제약사 admin) |
| `target_type` | enum(`pharmacy`,`hospital`) | 대상 마스터 종류 |
| `target_id` | FK, **nullable** | 수정 대상 PK (신규 등록이면 null) |
| `request_type` | enum(`create`,`update`) | 신규 등록 / 수정 |
| `payload` | json | 제안 값(생성/변경 필드 전체) |
| `status` | enum(`pending`,`approved`,`rejected`) | 기본 `pending` |
| `reviewed_by` | FK users, nullable | 검토자(super_admin) |
| `reviewed_at` | datetime, nullable | |
| `review_note` | text, nullable | 승인/반려 사유 |
| `applied_target_id` | FK, nullable | 승인 후 생성/수정된 마스터 PK(추적용) |
| `timestamps`, `softDeletes` | | |

**흐름**
1. 제약사 admin: 약국/병원 화면에서 "신규 등록 요청" 또는 상세에서 "수정 요청" → `payload` 작성 → `pending` 생성.
2. super_admin: 변경요청 목록(`pending`) 검토 → **승인**(payload 를 실제 `pharmacies`/`hospitals` 에 create/update 반영 + `approved`) 또는 **반려**(`rejected` + 사유).
3. 모든 처리 activity log 기록. 요청자에게 결과 표시(상태/사유).

> 제약사 admin 의 약국·병원 직접 CRUD 라우트는 **차단**(조회만). 변경은 요청 경로로만.

---

## 4. 접근 제어 (Enforcement)

1. **현재 테넌트 해석**: 로그인 사용자의 `tenant_id` 로 "현재 테넌트" 결정. 미들웨어(`ResolveTenant`)에서 컨텍스트 주입.
2. **전역 스코프(Global Scope)**: tenant-scoped 모델에 `TenantScope` (Eloquent global scope) 부착 → 모든 쿼리에 `where tenant_id = 현재테넌트` 자동 적용.
   - `super_admin`: ✅ **테넌트 선택 후 진입(임퍼서네이션)**(D-4). 세션에 "현재 테넌트" 저장 → 그 테넌트로 스코프 적용(기존 admin UX 재사용). 미선택 시 제약사 관리/전역 대시보드만 접근. 전체 합산 통계는 super_admin 전용 화면에서 스코프 우회.
3. **생성 시 자동 주입**: tenant-scoped 모델 `creating` 이벤트에서 `tenant_id` 자동 세팅.
4. **Policy**: 기존 admin/sales Policy 에 "동일 테넌트" 조건 추가. super_admin 은 통과.
5. **메뉴/라우트**: super_admin 전용(제약사 관리), admin/sales 는 기존 메뉴 유지하되 테넌트 자동 격리.

---

## 5. 기존 데이터 마이그레이션 (Backfill)

현 단일 조직 데이터를 깨지 않기 위해:

1. **기본 제약사 1개 생성** (예: 현재 운영 주체). `tenants` 시드.
2. 기존 `products`/`companies`/`performances`/`settlements`/`sales_quotas`/`users(admin,sales)` 의 `tenant_id` 를 **기본 제약사로 백필**.
3. 기존 `admin` 계정 중 **플랫폼 운영자**가 될 계정을 `super_admin` 으로 승격(별도 지정).
4. `tenant_id` NOT NULL 제약은 백필 **이후** 적용.

> dry-run → 카운트 검증 → commit. OPS-7(레거시 import)과 충돌 없는 순서로.

---

## 6. 단계 분할 (Phasing) — XL

| Phase | 내용 | 비고 |
|---|---|---|
| **MT-1** | `tenants` 테이블 + 모델 + role enum 확장(`super_admin`) + `users.tenant_id` | 스키마 토대 |
| **MT-2** | **(1부 ✅)** 기본 제약사 시드 + users 백필 / **(2부→MT-4)** 도메인 테이블 백필 | 사용자 결정으로 도메인 테이블 tenant_id 는 MT-4 와 묶어 처리. NOT NULL 은 백필 후 |
| **MT-3** | `TenantScope` + `ResolveTenant` 미들웨어 + 생성 시 tenant_id 자동 주입 | 핵심 격리 엔진 |
| **MT-4** ✅ | tenant-scoped 테이블에 `tenant_id` 부착 (products/companies/performances/settlements/sales_quotas) | nullable+FK+백필 완료. **NOT NULL 은 MT-3 이후 finalize** |
| **MT-5** | Policy 테넌트 조건 + super_admin 우회/선택 | 권한 |
| **MT-6** | super_admin 제약사 관리 화면(CRUD) + 제약사 admin 생성(위임형) | UI (sales 관리 범위는 MT-5) |
| **MT-7** | 회귀 테스트 (격리 누수·교차 테넌트 차단·super_admin 전역) | 보안 핵심 |
| **MT-8** | 약국·병원 변경요청 승인 워크플로 (`master_change_requests`) | §3.3. 제약사 admin 요청 → super_admin 승인 반영. 약국·병원 직접 쓰기 차단 |

### 6.1 실행 순서 (재정렬 — 2026-05-29, 사용자 지정: super_admin 페이지 우선)

> 위 표는 **논리적 분류**이고, 실제 구현은 아래 순서로 진행한다. **super_admin 제약사 관리 페이지(MT-6)를 먼저** 만들고 이후 순서대로.

| 순서 | Phase | 내용 | 상태 |
|---|---|---|---|
| ✅ | MT-1 | 스키마 토대(`tenants`/role/`users.tenant_id`) | 🟢 |
| ✅ | MT-2(1부) | 기본 제약사 시드 + users 백필 | 🟢 |
| ✅ | MT-4 | 도메인 5테이블 `tenant_id`(nullable)+FK+백필 | 🟢 |
| **1** | **MT-6** | **super_admin 페이지** — super_admin 시드/게이팅 + 제약사(tenant) CRUD + 제약사 admin 생성(위임형) + 전용 메뉴 | 🟢 **완료 (2026-05-29)** |
| 2 | MT-3 | 격리 엔진 — `ResolveTenant` + `TenantScope` + 생성 시 자동 주입 | 🟢 **완료 (2026-05-29)** (super_admin 임퍼서네이션 진입은 후속) |
| 3 | MT-4-finalize | 도메인 `tenant_id` NOT NULL 전환 | ⚪ **다음** (단, 테스트 admin tenant 부여 선행 필요 — 아래 메모) |
| 4 | MT-5 | Policy 테넌트 조건(admin/sales 동일 테넌트, super_admin 통과) | 🟢 **완료 (2026-05-29)** (단일 `Gate::before`. admin 소속 sales 관리 범위는 후속) |
| 5 | MT-7 | 격리 회귀 테스트(누수·교차 테넌트 차단·super_admin 전역) | 🟢 **완료 (2026-05-29)** |
| 6 | MT-8 | 약국·병원 변경요청 승인 워크플로 | ⚪ |

> **의존성 메모**: MT-6 제약사 CRUD 는 `tenants`(전역, super_admin 전용) 대상이라 `TenantScope` 불필요 → MT-3 전에 선행 가능. 단 **"테넌트 진입(임퍼서네이션)" 버튼은 MT-3(ResolveTenant) 완료 후 연결**한다. super_admin 계정은 MT-6 에서 시드/승격 경로 마련.

### 6.2 MT-3 완료 메모 / MT-4-finalize 선행 조건

**MT-3 (격리 엔진) 완료 (2026-05-29)** — 변경 파일:
- `app/Tenancy/TenantContext.php` (요청 단위 싱글톤 — 현재 테넌트 보관)
- `app/Models/Scopes/TenantScope.php` (글로벌 스코프 — **컨텍스트가 설정된 경우에만** `where tenant_id` 적용)
- `app/Models/Concerns/BelongsToTenant.php` (트레이트 — 스코프 부착 + 생성 시 tenant_id 자동 주입)
- `app/Http/Middleware/ResolveTenant.php` (web 그룹 — admin/sales 의 tenant_id 로 컨텍스트 설정, super_admin/게스트는 미설정=전역)
- 5개 모델(`Product`/`Company`/`Performance`/`Settlement`/`SalesQuota`)에 `use BelongsToTenant`
- `Performance::nextNumberFor()` 에 `withoutGlobalScope(TenantScope::class)` — 실적번호 전역 채번(교차 테넌트 중복 방지)
- 테스트 `TenantScopeTest` 6 cases. 전체 **309/309 PASS, 회귀 0**

**설계 원칙(중요)**: 스코프는 **컨텍스트가 설정된 경우에만** 작동한다.
- admin/sales (tenant_id 보유) → 자기 테넌트로 격리
- super_admin / 콘솔 / 큐 / 게스트 → 미설정 = **전역**(비스코프) → 비-HTTP 경로·super_admin 안전
- 덕분에 tenant_id 가 null 인 사용자(과도기·테스트)는 비스코프로 동작 → **기존 기능 무손상**

### 6.4 MT-7 완료 메모 (2026-05-29) — 격리 회귀 (보안 핵심)

`TenantIsolationTest` 8 cases — 실제 컨트롤러 HTTP 경유로 격리 검증:
- **목록 격리(TenantScope/MT-3)**: 거래처·실적·정산·목표 index → admin 은 자기 제약사 것만.
- **상세 차단**: 다른 제약사의 거래처·실적·정산 상세 → **403** (모든 `show` 의 `authorize` → Gate::before/MT-5).
- **생성 자동 주입**: admin 이 거래처 생성 → `tenant_id` 자동으로 자기 제약사.

> **2중 격리 구조 확인**: ① 목록/쿼리 = TenantScope 자동 필터, ② 단건 상세(route-model 바인딩) = `SubstituteBindings` 가 `ResolveTenant` 보다 먼저라 바인딩 자체는 스코프 미적용이지만, 모든 show/edit/update/delete 가 `authorize` 를 호출 → Gate::before 의 교차 테넌트 거부로 **403** 차단. (전 컨트롤러 authorize 호출이 프로젝트 규약)
> 전체 **321/321 PASS, 회귀 0**.

### 6.3 MT-5 완료 메모 (2026-05-29) — 테넌트 권한 게이트

`AppServiceProvider::boot()` 에 **단일 `Gate::before`** 추가 (모든 Policy 이전 실행):
1. `super_admin` → 전체 통과 (플랫폼 운영자)
2. admin/sales(테넌트 보유)가 **다른 테넌트의 `BelongsToTenant` 모델**에 접근 → **거부** (방어선; 1차 격리는 MT-3 TenantScope)
3. 그 외(null 테넌트/게스트) → `null` 반환 → 기존 Policy 위임 (과도기 호환)

- `class_uses_recursive($model)` 로 `BelongsToTenant` 사용 모델만 판정 → 5개 도메인 모델 일괄 커버, 개별 Policy 수정 불필요.
- 테스트 `TenantPolicyGuardTest` 4 cases. 전체 **313/313 PASS, 회귀 0**.
- `super_admin` 전체 통과가 기존 admin/sales 정책에 영향 없음(super_admin 은 기존 테스트에 등장하지 않음).

**⚠️ MT-4-finalize(NOT NULL) 선행 조건**: 현재 `tenant_id` 는 nullable. NOT NULL 로 바꾸려면 **앱 생성 경로가 항상 tenant_id 를 채워야** 하는데, 컨텍스트 미설정(테스트의 tenant 없는 admin, super_admin 전역 생성)에서는 null 이 들어갈 수 있다. → NOT NULL 전환 전에 (a) 기존 feature 테스트의 admin/sales 에 tenant 부여, (b) super_admin 의 도메인 생성 경로에서 tenant 명시 주입 규칙 확정이 필요. 그래서 **MT-4-finalize 는 별도 단계로 둔다**(지금 강행하면 회귀).

---

## 7. 결정 (전체 확정 — 2026-05-29)

- **D-1 테넌트 테이블명**: ✅ **`tenants`** (모델 `Tenant`, 표기 "제약사"). 거래처/약국과 명칭 혼동 회피.
- **D-2 계정 생성 권한**: ✅ **A. 위임형**. super_admin → 제약사(tenant) + 제약사 admin 생성. 제약사 admin → **자사 sales 직접 생성·관리**(자기 테넌트 한정). super_admin 끼리 생성은 시드/별도 승격(D 후속). → `/users` 화면은 admin 에게 **자사 sales 만** 노출.
- **D-3 공지(notices) 범위**: ✅ **둘 다(전역+테넌트)**. `notices.tenant_id` nullable — null=전역, 값=테넌트 공지. 조회는 `tenant_id IS NULL OR = 내 테넌트`.
- **D-4 super_admin 데이터 접근**: ✅ **테넌트 선택 후 진입(임퍼서네이션)**. 세션 "현재 테넌트" 기준 스코프. 전역 합산은 전용 대시보드에서 우회.
- **D-5 사용자 이메일 유일성**: ✅ **전역 unique 유지**. 로그인 단순(이메일+비밀번호). 겸직 케이스 없음 전제. 로그인 화면 변경 없음.
- **D-6 약국·병원 쓰기 권한**: ✅ **super_admin 전용 쓰기 + 제약사 admin 변경요청 승인 워크플로**. 제약사 admin 은 신규 등록/수정을 *요청*만, super_admin 검토·승인 시 반영(반려 가능). `master_change_requests` 테이블(§3.3), 단계 MT-8.

---

## 8. 진행 현황 (Progress Log)

| 단계 | 상태 | 비고 |
|---|---|---|
| MT-0 설계 | 🟢 완료 | 2026-05-29. D-1~D-6 전체 확정(§7) |
| **MT-1 스키마 토대** | 🟢 완료 | 2026-05-29. `tenants` 테이블+모델+Factory / role enum `super_admin` 추가 / `users.tenant_id`(nullable FK) + `User::isSuperAdmin()`·`tenant()`·ROLE 상수. `TenantSchemaTest` 5/5 · 전체 281/281 PASS |
| **MT-2 (1부) 기본 제약사 시드 + users 백필** | 🟢 완료 | 2026-05-29. 데이터 마이그레이션 — 기본 제약사("기본 제약사"/code `DEFAULT`) 생성 + 기존 admin/sales `tenant_id` 백필(super_admin 제외). **도메인 테이블 tenant_id 부착·백필은 MT-4 로 연기**(사용자 결정). `DefaultTenantBackfillTest` 5/5 · 전체 286/286 PASS |
| **MT-4 도메인 tenant_id 부착** | 🟢 완료 | 2026-05-29. products/companies/performances/settlements/sales_quotas 에 `tenant_id`(**nullable**)+FK(restrict)+인덱스 추가, 기존 행 기본 제약사 백필. 5개 모델 `tenant()`·fillable, 5개 Factory 기본 tenant. **NOT NULL 전환은 MT-3 자동주입 이후로 연기**(앱 생성 경로 보호). `DomainTenantColumnTest` 5/5 · 전체 291/291 PASS |
| MT-6 | ⚪ 대기 (**다음**) | super_admin 시드/게이팅 + 제약사(tenant) CRUD + 제약사 admin 생성 + 전용 메뉴 (§6.1 #1) |
| MT-3 | ⚪ 대기 | MT-6 후 — `TenantScope` + `ResolveTenant` + 생성 시 tenant_id 자동 주입 + 테넌트 진입(임퍼서네이션) |
| MT-4-finalize | ⚪ 대기 | MT-3 완료 후 도메인 `tenant_id` NOT NULL 전환 |
| MT-5~8 | ⚪ 대기 | Policy/super_admin/UI/테스트/변경요청 |

### MT-1 변경 파일
- [migration] `2026_05_29_100000_create_tenants_table.php`, `..100100_add_tenant_and_super_admin_role_to_users_table.php`
- [model] `app/Models/Tenant.php` (신규), `app/Models/User.php` (ROLE 상수·`isSuperAdmin`·`tenant()`·fillable `tenant_id`)
- [factory] `database/factories/TenantFactory.php` (신규)
- [test] `tests/Feature/Tenancy/TenantSchemaTest.php` (신규, 5 cases)

### MT-2 변경 파일
- [migration] `2026_05_29_100200_seed_default_tenant_and_backfill_users.php` (데이터 마이그, 멱등 + down 복구)
- [test] `tests/Feature/Tenancy/DefaultTenantBackfillTest.php` (신규, 5 cases)
- 잔여(MT-4 로 이동): products/companies/performances/settlements/sales_quotas 의 `tenant_id` 컬럼 부착 + 기본 제약사로 백필

### MT-4 변경 파일
- [migration] `2026_05_29_100300_add_tenant_id_to_domain_tables.php` — 5개 루트 테이블 `tenant_id`(nullable)+FK(restrict)+index + 백필
- [model] `Product`/`Company`/`Performance`/`Settlement`/`SalesQuota` — `tenant_id` fillable + `tenant()` 관계
- [model] `Tenant::default()` 헬퍼(code=DEFAULT firstOrCreate)
- [factory] 위 5개 Factory — `tenant_id` 기본값 `Tenant::default()->id`
- [test] `tests/Feature/Tenancy/DomainTenantColumnTest.php` (신규, 5 cases)
- **잔여**: NOT NULL 전환(MT-3 자동주입 후 finalize 마이그레이션)

---

**문서 버전**: 1.3
**작성일**: 2026-05-29
**최종 갱신**: 2026-05-29 (MT-4 도메인 tenant_id 부착 완료 — nullable+백필. 5/5 신규, 전체 291/291 PASS)
**상태**: 🟡 구현중 — MT-1·MT-2(1부)·MT-3·MT-4·MT-5·MT-6·MT-7 완료(전체 321/321, 격리 회귀 통과). **핵심 격리 완성.** 남음 = MT-8(변경요청 워크플로) / MT-4-finalize(NOT NULL §6.2) / super_admin 임퍼서네이션 / `/platform` 마스터 CRUD.

### MT-6 설계 보정 (2026-05-29) — super_admin = 전역 슈퍼유저

> 사용자 확정: super_admin 은 제약사 관리 + **제품·병의원·약국·사용자를 전(全) 제약사 횡단으로 조회·관리**. admin 페이지를 분기하지 않고 **`/platform/*` 전용 영역으로 분리**한다.
> 제품 등 제약사별 데이터(`tenant_id`)는 그대로 유지 — super_admin 은 스코프를 우회해 **전체를 본다**(MT-3 에서 스코프 + 우회 확정).

**구현(이번 단계 = 목록·조회 먼저, CRUD 는 후속)**
- [area] `/platform/*` (prefix) · route name `platform.*` · `App\Http\Controllers\Platform\*` · `Pages/Platform/*` · 미들웨어 `role:super_admin`
- [command] `MakeSuperAdmin` — `php artisan tenancy:make-super-admin {email}`
- [tenant CRUD] `Platform\TenantController`(resource + `storeAdmin`) + `Platform/Tenants/{Index,Create,Edit,Show}.vue` + `TenantPolicy` + `Store/UpdateTenantRequest`·`StoreTenantAdminRequest`
- [전역 목록(조회)] `Platform\{Product,Hospital,Pharmacy,User}Controller@index` + `Platform/{Products,Hospitals,Pharmacies,Users}/Index.vue`
  - 제품·사용자 목록엔 **"제약사" 칸**(횡단), 병의원·약국은 공유 마스터
- [menu] `AppMenu.vue` "플랫폼" 그룹(super_admin): 제약사/의약품/병의원/약국/사용자 관리
- [dashboard] super_admin → `platform.tenants.index` 리다이렉트
- [test] `TenantManagementTest`(8) + `PlatformListingTest`(4). 전체 **303/303 PASS**
- **접근**: 동일 `/login` → role=super_admin 이면 `/dashboard` 가 `/platform/tenants` 로 리다이렉트 + "플랫폼" 메뉴. 최초 계정은 artisan 으로 생성/승격.
- **후속(다음 단계)**: 의약품·병의원·약국·사용자 **등록/수정(CRUD)** + 상세. (MT-3 스코프 우회 확정 후 전역 관리 완성)
