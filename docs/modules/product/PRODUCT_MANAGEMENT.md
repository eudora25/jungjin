# 의약품(제품) 관리 — 설계·프로세스 문서

> 본 문서는 정진팜 실적관리 시스템의 **의약품(제품) 관리 모듈**에 대한 단일 설계 문서입니다.
> 현행 코드 상태를 기반으로, 운영에 필요한 데이터 모델·라이프사이클·가격 정책·작업 우선순위를 정의합니다.
> 관련 상위 문서: [`docs/onboarding/HANDOFF.md`](../../onboarding/HANDOFF.md)
>
> **진행 현황은 본 문서 §13 “진행 현황(Progress Log)”에서 단계별로 추적합니다.** 작업 시작·완료 시마다 해당 표와 변경 파일 목록을 갱신해 주세요.

---

## 0. TL;DR

- **현재**: `products` 마스터 + `product_commission_rates`(제품×기준월×등급) 까지 구현됨
- **보강 방향**:
  1. **마스터 식별 컬럼 강화** (표준코드/성분명/함량/단위/포장/약품유형)
  2. **승인 라이프사이클** 도입 (`approval_status`, `reviewed_*`, `approved_*`) — **4단계**(`draft → pending → reviewed → approved`)
  3. **가격 이력 테이블 분리** (`product_prices`: 보험가/매입가/매출가)
  4. **거래처별 예외 단가/수수료** (선택, `company_product_overrides`)
  5. **CSV 일괄 업서트(가격 포함)** + **변경 이력(Audit)**
  6. **마약/향정 NIMS 연계** 시야 포함 (식별/이벤트 설계는 Phase 1, 외부 API 연동은 추후 단계)
  7. **첨부 문서**(`product_files`)는 **Phase 1에 포함**
- **MVP 우선순위**: Phase 1(마스터 + 승인 4단계 + 첨부) → Phase 2(가격 이력 + CSV 업서트) → Phase 3(거래처 예외 + Audit + NIMS 연계)

---

## 1. 현재 상태 (As-Is)

### 1.1 테이블

#### `products` (마이그레이션: `2026_04_20_093500_create_products_table.php`)

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `id` | PK | |
| `insurance_code` | string(50), index | 건강보험 EDI 코드 |
| `product_code` | string(50), index | 사내 관리용 코드 |
| `product_name` | string, index | 제품명 |
| `manufacturer` | string(100), index, nullable | 제조사명 |
| `category` | string(100), index, nullable | 제품 카테고리 |
| `description` | text, nullable | 설명 |
| `image_path` | string, nullable | 이미지 스토리지 경로 |
| `price` | decimal(12,2), nullable | 약가(단일 컬럼) |
| `status` | enum(`active|inactive|discontinued`), default `active` | 제품 상태 |
| `remarks` | text, nullable | 비고 |
| `created_by` / `updated_by` | FK users.id | |
| `timestamps`, `softDeletes` | | |

#### `product_commission_rates` (마이그레이션: `2026_04_20_093501_create_product_commission_rates_table.php`)

- 제품 × 기준월(`base_month` YYYY-MM) × 등급 A~E 수수료율 매트릭스
- `effective_from / effective_to`로 적용 기간 관리
- unique(`product_id`, `base_month`, `effective_from`)
- `Product::currentCommissionRate(?Carbon $date)` 헬퍼 제공
- 거래처(`companies.default_commission_grade` A~E)와 자연스럽게 매칭

### 1.2 코드/UI

- `App\Models\Product`, `App\Models\ProductCommissionRate`
- `App\Http\Controllers\ProductController` — CRUD, 검색(`search`/`category`/`status`), 카테고리 distinct
- `App\Http\Controllers\ProductCommissionRateController` — 수수료율 관리(별도)
- 좌측 메뉴: **마스터 관리 > 의약품 관리 → `/products`** (GAP-9)

### 1.3 갭(Gap) 요약

- `products.price` **단일 컬럼**으로는 약가 변경 이력(고시 변경, 매입가 협상)을 표현할 수 없음
- **승인 흐름** 부재 (Company는 `approval_status` 있지만 Product는 없음)
- 의약품 식별을 위한 **표준코드/성분명/함량/단위/포장** 등 도메인 핵심 컬럼 부재
- **마약/향정** 별도 정책을 적용할 enum 부재
- **거래처 단가 예외** 표현 수단 없음 (현재는 등급 매트릭스만)
- **CSV 일괄 등록**, **변경 이력** 기능 부재

---

## 2. 목표 아키텍처 (To-Be)

```
products (마스터, 식별/표시 정보)
 ├─ product_prices                (가격 이력 — 보험가/매입가/매출가)
 ├─ product_commission_rates      (등급 A~E × 기준월 × 기간)
 ├─ company_product_overrides ?   (거래처×제품 예외 단가/수수료, 선택)
 ├─ product_files ?               (허가증·시판후 안전정보 등 첨부, 선택)
 └─ activity_log (audit)          (변경 이력, 선택: spatie/laravel-activitylog)
```

가격 조회 우선순위(매출 기준):

```
거래처 예외(override) → 등급 매트릭스(commission_rates) → 기본 매출가(product_prices: sale)
```

상태 관리는 **2축으로 분리**:
- `approval_status` (`draft|pending|approved|rejected`) — **비즈니스 승인**
- `status` (`active|inactive|discontinued`) — **판매 가능 여부**

---

## 3. 데이터 모델 보강안

### 3.1 `products` 컬럼 추가

| 컬럼 | 타입 | 설명 | 비고 |
|---|---|---|---|
| `standard_code` | string(50), nullable, index | 식약처 표준코드(KD 코드) | 외부 매칭 보조키 |
| `barcode_gtin` | string(20), nullable, index | 유통 GTIN(바코드) | 공급망 매칭 |
| `generic_name` | string(150), nullable, index | 성분명/일반명 | 검색 향상 |
| `strength` | string(50), nullable | 함량 (예: `500mg`) | |
| `unit` | string(20), nullable | 기본 단위 (`정/캡슐/병/앰플/포`) | |
| `pack_size` | unsignedInt, nullable | 포장 수량 (예: 100정/박스) | |
| `drug_type` | enum(`general`,`etc`,`narcotic`,`psychotropic`), default `general`, index | 일반/전문/마약/향정 | 정책 분기에 사용 |
| `storage_condition` | enum(`room`,`cold`,`frozen`), default `room` | 보관 조건 | 운송·반품 영향 |
| `replacement_product_id` | FK products.id, nullable | 단종 시 대체품 (선택, 강제 X) | 자기참조 |
| `approval_status` | enum(`draft`,`pending`,`reviewed`,`approved`,`rejected`), default `draft`, index | 승인 상태 (**4단계**) | |
| `reviewed_at` | timestamp, nullable | 검수 완료 일시 | |
| `reviewed_by` | FK users.id, nullable | 검수자 | |
| `approved_at` | timestamp, nullable | 승인 일시 | |
| `approved_by` | FK users.id, nullable | 승인자 | |
| `nims_managed` | boolean, default false, index | NIMS 관리대상 여부 (마약/향정 자동 true) | |
| `nims_item_code` | string(50), nullable, index | NIMS 품목코드 (외부 매칭키) | |

추가 제약:
- `insurance_code`에 **unique** 부여 (기존은 index만)
- `(approval_status, status)` 복합 인덱스 권장

> `price`는 호환성/성능을 위해 **표시용 캐시**로 남겨두고, 실 데이터는 `product_prices`를 신뢰원으로 사용.

### 3.2 신규 테이블: `product_prices` (가격 이력)

```text
product_prices
- id (PK)
- product_id (FK products.id, cascadeOnDelete)
- price_type enum('insurance','cost','sale')   -- 보험약가 / 매입가 / 매출가
- amount decimal(12,2)
- effective_from date
- effective_to date null                       -- NULL = 현재 적용
- source string null                           -- 예: '보건복지부 고시 2026-XX', '제약사 견적서'
- created_by / updated_by FK users.id null
- timestamps
- unique(product_id, price_type, effective_from)
- index(product_id, price_type, effective_from, effective_to)
```

조회 헬퍼(예시 시그니처):

```php
Product::currentPrice(string $type = 'sale', ?Carbon $on = null): ?ProductPrice
```

### 3.3 (선택) 신규 테이블: `company_product_overrides`

```text
company_product_overrides
- id (PK)
- company_id (FK companies.id, cascadeOnDelete)
- product_id (FK products.id, cascadeOnDelete)
- override_unit_price decimal(12,2) null       -- 거래처 전용 단가 (있으면 적용)
- override_commission_rate decimal(5,2) null   -- 거래처 전용 수수료율 (있으면 적용)
- effective_from date
- effective_to date null
- reason string null
- timestamps
- unique(company_id, product_id, effective_from)
```

### 3.4 신규 테이블: `product_files` (Phase 1 포함)

- `notice_files` 패턴 재사용 (`original_name / stored_name / path / size / mime_type / extension / uploaded_by`)
- 추가 컬럼: `file_type` enum(`license`, `safety`, `catalog`, `etc`) — 허가증/안전정보/카탈로그/기타 분류
- 용도: 허가증, 시판후 안전정보(PMS), 카탈로그 등
- 정책: 업로드/삭제는 `update` 권한자(=admin)만, 다운로드는 인증 사용자 전체

---

## 4. 라이프사이클(승인 프로세스)

### 4.1 상태 전이 (4단계)

```
draft  ──[제출]──▶  pending  ──[검수완료]──▶  reviewed  ──[최종승인]──▶  approved
                       │                          │                        │
                       └─[반려]──▶ rejected      └─[반려]──▶ rejected     ├─[일시중지]──▶ status=inactive
                                                                          └─[단종]──────▶ status=discontinued (+ replacement_product_id, 선택)
```

- 등록 직후 기본값: `approval_status=draft`, `status=active`
- **검수(reviewed)**: 식별 컬럼/가격/문서 등이 사실에 부합하는지 1차 확인 (담당자가 수행)
- **승인(approved)**: 판매·정산 적용에 대한 최종 책임 결재 (관리자 수행)
- 판매 가능 = `approval_status=approved` AND `status=active`
- 거래/실적 입력 화면의 제품 선택 목록은 **판매 가능 제품**만 노출

### 4.2 권한 정책 (Policy)

- `viewAny / view`: 인증 사용자 전체
- `create / update / submit`: **admin만** (운영 합의 후 담당자 권한 분리 가능)
- `review / approve / reject / discontinue`: **admin만**
  - `review`: pending → reviewed
  - `approve`: reviewed → approved
  - `reject`: pending|reviewed → rejected (사유 필수)
- 마약/향정 (`drug_type in {narcotic, psychotropic}`) 변경 시 추가 정책:
  - 변경 사유(`change_reason`) 필수 입력 → 변경 이력에 기록
  - `nims_managed=true` 자동 세팅, NIMS 관련 컬럼 변경은 **별도 이벤트 로그**로 강제

### 4.3 단종 처리

- `status=discontinued` 전환 시 `replacement_product_id` 입력은 **선택(권장)** — 강제 X
- (확장) 단종 시점에 거래처 자동 공지(Notice) 생성 — 기존 Notice/Queue 모듈 재사용

---

## 5. 가격 정책

### 5.1 가격 종류

| 종류 | `price_type` | 설명 | 갱신 트리거 |
|---|---|---|---|
| 보험약가 | `insurance` | 정부 고시가(상한가) | 복지부 고시 변경 시 |
| 매입가 | `cost` | 제약사로부터의 매입 단가 | 제약사 견적/계약 변경 시 |
| 매출가 | `sale` | 거래처 표준 매출 단가 | 사내 가격정책 변경 시 |

### 5.2 적용 우선순위 (매출 산출)

```
company_product_overrides.override_unit_price  (있으면 우선)
  └─ 없으면 ─▶ product_prices(sale, 시점)
```

수수료(정산용)는 별도 라인에서:

```
company_product_overrides.override_commission_rate  (있으면 우선)
  └─ 없으면 ─▶ product_commission_rates × companies.default_commission_grade
```

### 5.3 무결성 규칙

- 동일 `(product_id, price_type)`에 대해 `effective_from` 기준 unique
- 신규 가격 등록 시 직전 활성 레코드의 `effective_to`를 자동으로 `effective_from - 1d`로 마감(서비스 레이어에서 트랜잭션 처리)
- 음수/0 단가는 입력 허용하지 않음 (Form Request 검증)

---

## 6. UI/UX 가이드

### 6.1 목록(`/products`)

- 컬럼: 보험코드 · 제품명 · 제조사 · 분류 · 약품유형(태그) · 현재 매출가 · 상태(승인/판매)
- 필터: 검색(이름/보험코드/표준코드/제조사/성분명) · 카테고리 · `drug_type` · `approval_status` · `status`
- 액션: 신규 등록(admin), CSV 업로드(admin), 행 클릭 → 상세

### 6.2 상세(`/products/{id}`)

- 탭 구성:
  1. **기본 정보** (식별 컬럼·이미지)
  2. **가격 이력** (`product_prices`, type별 토글)
  3. **수수료율** (`product_commission_rates` 매트릭스, 기준월 선택)
  4. **거래처 예외** (`company_product_overrides`, 선택)
  5. **첨부 문서** (`product_files`, 선택)
  6. **변경 이력** (audit, 선택)
- 상단 우측: 승인/반려/단종 버튼 (admin, 상태별 노출)

### 6.3 등록/수정

- 좌측: 식별 정보 / 우측: 분류·이미지·상태
- “가격”은 등록 시 **현재 매출가 1건**만 입력받고, 이후 변경은 “가격 이력 추가” UX로 분리 (이력 보존)

### 6.4 좌측 메뉴

- 현재: `마스터 관리 > 의약품 관리 → /products` (GAP-9)
- 운영 안정화 후 하위 메뉴(승인 대기/단종 등) 분기 검토 — **본 단계에서는 추가하지 않음**

---

## 7. CSV 업서트 (운영 필수, **가격 포함**)

### 7.1 입력 키 / 동작

- 입력 키: `insurance_code` (없으면 `standard_code` → 없으면 `barcode_gtin`)
- 동작: 존재하면 update, 없으면 create
  - `status`, `approval_status`는 **기본 보존** (의도적 변경은 별도 컬럼으로만 허용)
  - 신규 생성 시 `approval_status=draft` 강제

### 7.2 가격 컬럼 처리 (이력 자동 생성)

CSV에 가격 컬럼이 포함된 경우, **`product_prices`에 이력 행을 자동 추가**합니다.

| CSV 컬럼 | 매핑 | 비고 |
|---|---|---|
| `insurance_price` | `product_prices(price_type=insurance)` | 보험약가 |
| `cost_price` | `product_prices(price_type=cost)` | 매입가 |
| `sale_price` | `product_prices(price_type=sale)` | 매출가 |
| `price_effective_from` | 모든 가격의 `effective_from` 공통 (없으면 오늘) | YYYY-MM-DD |
| `price_source` | 모든 가격의 `source` 공통 | 예: `복지부 고시 2026-XX` |

규칙:
- 동일 `(product_id, price_type, effective_from)`이 이미 존재하면 **스킵**(중복 방지)
- 가격이 추가되면 직전 활성 레코드의 `effective_to`를 `effective_from - 1d`로 자동 마감
- 가격 컬럼은 **양수**만 허용 (검증 실패 시 해당 행 전체 거부)

### 7.3 검증/리포트

- 필수 컬럼 누락·형식 오류 행은 **사전 리포트** 후 트랜잭션 일괄 적용
- 결과 리포트: `created`, `updated`, `price_inserted`, `skipped`, `errors[{row, code, message}]`
- 마약/향정 컬럼이 변경되는 행은 `change_reason` 컬럼이 비어 있으면 거부

### 7.4 이력

- 업로드 1회 = audit 1건 (`bulk_upload`)으로 묶어 기록
- 개별 행 변경은 모델 단위 audit에 의해 자동 기록 (Phase 3)

---

## 8. 변경 이력 (Audit) & NIMS 연계

### 8.1 일반 변경 이력

- 권장 패키지: [`spatie/laravel-activitylog`](https://github.com/spatie/laravel-activitylog)
- 대상 모델: `Product`, `ProductPrice`, `ProductCommissionRate`, `CompanyProductOverride`, `ProductFile`
- 마약/향정 변경, 가격 변경, 승인/반려/단종은 **별도 이벤트 로그**로 강제

### 8.2 NIMS(마약류통합관리시스템) 연계

> 외부 API 연동은 추후 단계지만, **데이터/이벤트 모델은 Phase 1부터 NIMS 호환성을 염두에 두고 설계**합니다.

- 식별 컬럼: `nims_managed`, `nims_item_code` (3.1 참조)
- 자동 규칙: `drug_type ∈ {narcotic, psychotropic}` → `nims_managed=true` 강제
- 이벤트(향후 외부 보고와 1:1 매칭):
  - `nims.product.registered` (등록/승인)
  - `nims.product.updated` (식별/단가 등 핵심 변경)
  - `nims.product.discontinued` (단종)
  - `nims.transaction.recorded` (실적 입력 시점, **실적 모듈 도입 후**)
- 저장: `audit log`에 별도 채널/컨텍스트로 분리 기록 → 추후 NIMS API 연동 시 재전송 가능
- 권한: NIMS 관련 컬럼/이벤트 변경은 **admin만**, 변경 사유 필수
- 보안: NIMS 관리대상 제품의 가격·재고·실적 조회는 별도 정책으로 마스킹/로깅 검토

---

## 9. 마이그레이션 로드맵 (단계별 작업 순서, 결정사항 반영)

### Phase 1 — 마스터 보강 + 승인 4단계 + 첨부 + NIMS 식별
- [ ] `products`에 식별 컬럼 추가(`standard_code`, `barcode_gtin`, `generic_name`, `strength`, `unit`, `pack_size`, `drug_type`, `storage_condition`, `replacement_product_id`)
- [ ] `products`에 NIMS 컬럼 추가(`nims_managed`, `nims_item_code`)
- [ ] `products`에 승인 컬럼 추가(`approval_status` enum 5종, `reviewed_at`, `reviewed_by`, `approved_at`, `approved_by`)
- [ ] `insurance_code` **unique** 변경 (기존 데이터 정합성 확인 후)
- [ ] `product_files` 신규 테이블 (`file_type` 포함)
- [ ] `Product` 모델 fillable / casts / scope(`approved`, `salable`) 보강
- [ ] `Product` 저장 훅: `drug_type` 마약/향정이면 `nims_managed=true` 자동 세팅
- [ ] `ProductPolicy` 갱신: `submit`, `review`, `approve`, `reject`, `discontinue` 액션 추가 (admin 한정)
- [ ] `ProductController`/Form Request 갱신, 상세 화면 상태 뱃지(검수/승인 분리)
- [ ] `ProductFileController` (업로드/삭제/다운로드) + UI(상세 “첨부 문서” 탭)
- [ ] Pest Feature: 등록 → 제출 → 검수 → 승인 → 판매가능 흐름 1건

### Phase 2 — 가격 이력 + CSV 업서트(가격 포함)
- [ ] `product_prices` 마이그레이션 + 모델
- [ ] `Product::currentPrice($type, $on)` 메서드, 신규 가격 등록 시 직전 레코드 `effective_to` 자동 마감(트랜잭션)
- [ ] 기존 `products.price` → `product_prices(sale)` 1건으로 **시드 마이그레이션**(데이터 보존)
- [ ] 등록/수정 UX: 단일 “현재 매출가” 입력 → 이력 자동 생성, 별도 “가격 이력” 탭
- [ ] Form Request 검증(음수/0 금지, `effective_from`/`effective_to` 정합성)
- [ ] **CSV 업서트** (식별/마스터 + `insurance_price/cost_price/sale_price/price_effective_from/price_source`)
- [ ] CSV 검증 리포트 (`created/updated/price_inserted/skipped/errors`)
- [ ] Pest Feature: 가격 추가 시 직전 레코드 `effective_to` 자동 마감 / CSV 업서트 dry-run

### Phase 3 — 거래처 예외 + Audit + NIMS 연계
- [ ] `company_product_overrides` 마이그레이션 + 모델/Policy
- [ ] 매출/수수료 산출 로직: `override → 등급 매트릭스 → 기본 매출가` 우선순위 적용
- [ ] `spatie/laravel-activitylog` 도입 + 대상 모델 `LogsActivity`
- [ ] 마약/향정 변경 사유 필수 검증 (`change_reason`)
- [ ] NIMS 이벤트 로그(`nims.product.*`) 분리 채널 구축 (외부 API 연동은 별도 단계)

> 각 Phase는 **마이그레이션 → 모델 → 컨트롤러/요청 → 정책 → UI → 테스트** 순으로 진행합니다.

---

## 10. 수용 기준 (Acceptance Checklist)

### Phase 1
- [ ] 신규 제품 등록 시 `approval_status=draft`로 생성된다
- [ ] admin이 “제출 → 검수 → 승인” 4단계를 모두 거치며 각 단계 시각/담당자가 기록된다
- [ ] 일반 사용자는 `submit/review/approve/reject/discontinue` 버튼이 노출되지 않는다
- [ ] `insurance_code` 중복 등록이 거부된다
- [ ] `drug_type`이 마약/향정으로 저장되면 `nims_managed`가 자동으로 true가 된다
- [ ] 제품 상세에서 첨부 문서를 업로드/다운로드할 수 있고 `file_type` 분류가 표시된다

### Phase 2
- [ ] 제품 상세에서 “가격 이력” 탭으로 보험/매입/매출가 변경 이력을 볼 수 있다
- [ ] `Product::currentPrice('sale')`이 시점에 맞는 값을 반환한다
- [ ] 기존 `products.price`는 “표시용 캐시”로만 사용되고 직접 편집 UI는 제거된다
- [ ] CSV 업로드 시 가격 컬럼이 있으면 `product_prices`에 이력이 자동 추가되고 직전 레코드가 마감된다
- [ ] 동일 `(product_id, price_type, effective_from)` 가격은 중복 추가되지 않는다(skipped)

### Phase 3
- [ ] 거래처 예외 단가/수수료가 등급 매트릭스보다 우선 적용된다
- [ ] 마약/향정 제품의 컬럼 변경은 사유 없이는 저장되지 않는다
- [ ] 승인/검수/단종/가격 변경 이력이 audit log에 남는다
- [ ] NIMS 관리대상 제품의 등록·변경·단종 이벤트가 `nims.product.*` 채널로 분리 기록된다

---

## 11. 결정 사항 (Confirmed)

| 항목 | 결정 | 적용 위치 |
|---|---|---|
| 승인 단계 | **4단계** `draft → pending → reviewed → approved` | §3.1, §4.1, §4.2 |
| 단종 시 대체품 | **선택(권장)** — 강제 X | §3.1, §4.3 |
| CSV 업서트 범위 | **가격 포함** (보험/매입/매출 + effective_from + source) | §7 |
| 마약/향정 정책 | **NIMS 연계 범위 확장** (Phase 1: 식별·이벤트, Phase 3: 분리 채널 / 외부 API는 추후) | §3.1, §4.2, §8.2 |
| 첨부 문서(`product_files`) | **Phase 1 포함** (`file_type` 분류) | §3.4, §9 Phase 1 |

---

## 12. 부록 — 마이그레이션 스니펫 (참고용)

> 실제 적용 시 별도 마이그레이션 파일로 분리 작성합니다.

### `add_product_master_columns_to_products_table` (예시)

```php
Schema::table('products', function (Blueprint $table) {
    $table->string('standard_code', 50)->nullable()->index()->after('insurance_code')->comment('식약처 표준코드(KD)');
    $table->string('barcode_gtin', 20)->nullable()->index()->after('standard_code')->comment('GTIN 바코드');
    $table->string('generic_name', 150)->nullable()->index()->after('product_name')->comment('성분명');
    $table->string('strength', 50)->nullable()->after('generic_name')->comment('함량');
    $table->string('unit', 20)->nullable()->after('strength')->comment('기본 단위');
    $table->unsignedInteger('pack_size')->nullable()->after('unit')->comment('포장 수량');
    $table->enum('drug_type', ['general','etc','narcotic','psychotropic'])
          ->default('general')->index()->after('category')->comment('약품 유형');
    $table->enum('storage_condition', ['room','cold','frozen'])
          ->default('room')->after('drug_type')->comment('보관 조건');
    $table->foreignId('replacement_product_id')->nullable()
          ->constrained('products')->nullOnDelete()->after('status')
          ->comment('단종 시 대체품 FK (선택)');

    $table->enum('approval_status', ['draft','pending','reviewed','approved','rejected'])
          ->default('draft')->index()->after('status')->comment('승인 상태 (4단계)');
    $table->timestamp('reviewed_at')->nullable()->after('approval_status')->comment('검수 완료 일시');
    $table->foreignId('reviewed_by')->nullable()
          ->constrained('users')->nullOnDelete()->after('reviewed_at')->comment('검수자 FK');
    $table->timestamp('approved_at')->nullable()->after('reviewed_by')->comment('승인 일시');
    $table->foreignId('approved_by')->nullable()
          ->constrained('users')->nullOnDelete()->after('approved_at')->comment('승인자 FK');

    $table->boolean('nims_managed')->default(false)->index()->after('approved_by')
          ->comment('NIMS 관리대상 여부 (마약/향정이면 자동 true)');
    $table->string('nims_item_code', 50)->nullable()->index()->after('nims_managed')
          ->comment('NIMS 품목코드');

    $table->unique('insurance_code', 'products_insurance_code_unique');
});
```

### `create_product_prices_table` (예시)

```php
Schema::create('product_prices', function (Blueprint $table) {
    $table->comment('제품 가격 이력 (보험가/매입가/매출가)');
    $table->id();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->enum('price_type', ['insurance','cost','sale'])->index();
    $table->decimal('amount', 12, 2);
    $table->date('effective_from');
    $table->date('effective_to')->nullable();
    $table->string('source')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->unique(['product_id','price_type','effective_from'], 'pp_unique_product_type_from');
    $table->index(['product_id','price_type','effective_from','effective_to'], 'pp_lookup_idx');
});
```

---

### `create_product_files_table` (예시, Phase 1)

```php
Schema::create('product_files', function (Blueprint $table) {
    $table->comment('제품 첨부 문서 (허가증/안전정보/카탈로그 등)');
    $table->id();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->enum('file_type', ['license','safety','catalog','etc'])->index();
    $table->string('original_name');
    $table->string('stored_name');
    $table->string('path');
    $table->unsignedBigInteger('size');
    $table->string('mime_type');
    $table->string('extension', 20)->nullable();
    $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

---

**문서 버전**: 0.4 (S1 완료 기록)
**작성일**: 2026-04-20
**다음 액션**: Phase 1 / S1 마이그레이션 착수
  1. `add_product_master_columns_to_products_table`
  2. `create_product_files_table`
  3. 모델/Policy/Form Request/UI 갱신 → Pest Feature

---

## 13. 진행 현황 (Progress Log)

> 매 작업 시작/완료 시 아래 표와 단계별 변경 로그를 갱신합니다.
> 새로운 세션으로 넘어가더라도 이 섹션만 보면 “어디까지 했고, 다음에 무엇을 해야 하는지” 바로 파악할 수 있습니다.

### 13.1 단계별 진행 표

| 단계 | 내용 | 상태 | 시작일 | 완료일 | 비고 |
|---|---|---|---|---|---|
| S1 | 마이그레이션 2종(`add_product_master_columns_to_products_table`, `create_product_files_table`) + `Product`/`ProductFile` 모델 보강 + `saving` 훅(`drug_type` → `nims_managed`) | ✅ 완료 | 2026-04-20 | 2026-04-20 | `insurance_code` unique 포함, lint OK, **마이그레이션 미실행(다음 세션에서 `php artisan migrate`)** |
| S2 | `ProductPolicy`에 `submit / review / approve / reject / discontinue` 액션 추가 + `Store/UpdateProductRequest` 신규 컬럼 검증 (마약/향정 변경 시 `change_reason` 필수) | ✅ 완료 | 2026-04-20 | 2026-04-20 | admin 한정, lint OK |
| S3 | `ProductController`에 상태 전이 메서드 추가 + 전용 라우트(POST `products/{id}/{action}`) | ✅ 완료 | 2026-04-20 | 2026-04-20 | UI 변경 없음, lint OK |
| S4 | `ProductFileController` + 라우트(업로드/삭제/다운로드) | ✅ 완료 | 2026-04-20 | 2026-04-20 | `file_type` 분류, `ProductFilePolicy` 추가, lint OK |
| S5 | 제품 상세 UI: 상태 뱃지(검수/승인 분리) + 액션 버튼 권한별 노출 | ✅ 완료 | 2026-04-20 | 2026-04-20 | PrimeVue Tag/Button/Dialog, lint OK |
| S6 | 제품 상세 UI: “첨부 문서” 탭 + `file_type` 필터 | ✅ 완료 | 2026-04-20 | 2026-04-20 | PrimeVue Tabs/DataTable, lint OK |
| S7 | Pest Feature 테스트 3건 (승인 흐름 / 첨부 업로드·삭제 / `insurance_code` 중복 거부) | ✅ 완료 (작성) | 2026-04-20 | 2026-04-20 | 사용자 환경에서 `sail test` 실행 필요 |
| P1.5-1 | `Pages/Products/Index.vue` 에 `approval_status` 필터 + 컬럼 + 약품/NIMS 미니 뱃지 + 필터 초기화 버튼 | ✅ 완료 | 2026-04-20 | 2026-04-20 | 백엔드 필터 기존 활용, lint OK |
| P1.5-2 | `ProductForm.vue` 에 신규 컬럼(표준코드/바코드/성분명/함량/단위/포장/약품유형/보관조건/NIMS 코드/`change_reason`) 입력 필드 + Create/Edit useForm 갱신 | ✅ 완료 | 2026-04-20 | 2026-04-20 | NIMS 자동 안내, change_reason 강조, lint OK |
| P1.5-3 | `GET /products/search` 검색 API + `ProductSearchAutoComplete.vue` + `Show.vue` 단종 모달 AutoComplete 적용 | ✅ 완료 | 2026-04-20 | 2026-04-20 | resource 위에 라우트 등록(충돌 방지), lint OK |
| P2-S1 | `product_prices` 마이그레이션 + `ProductPrice` 모델 + `Product` 관계/`currentPrice/latestPriceOf` 헬퍼 + 기존 `products.price` 시드 마이그레이션 (sale 1건) | ✅ 완료 | 2026-04-20 | 2026-04-20 | Factory 포함, lint OK |
| P2-S2 | `Store/UpdateProductPriceRequest` + `ProductPricePolicy` + `ProductPriceController` (CRUD) + 라우트 등록 + 직전 가격 자동 마감 트랜잭션 (`ProductPriceService`) | ✅ 완료 | 2026-04-20 | 2026-04-20 | `show()`에 prices eager load + `current_prices` 응답 추가 |
| P2-S3 | 제품 상세 화면 “가격 이력” 카드 + `price_type` 토글 + 등록/수정/삭제 모달 (`ProductPricePanel.vue`) | ✅ 완료 | 2026-04-20 | 2026-04-20 | 적용중 표시, 직전 자동 마감 안내 메시지 포함 |
| P2-S4 | 가격 변동 차트 (보험·원가·판매 step line — PrimeVue Chart + Chart.js, `ProductPriceChart.vue`) | ✅ 완료 | 2026-04-20 | 2026-04-20 | `ProductPricePanel` 상단 배치, `step:'before'`로 이력 사이 가격 일정 유지 |
| P2-S5 | CSV Bulk Upsert (`ProductImportService` + `ProductImportController` + Dry-run/결과 화면) — Excel은 후속 | ✅ 완료 | 2026-04-20 | 2026-04-20 | 토큰 기반 2단계 (analyze→commit), 샘플 CSV 다운로드, Index에 진입 버튼 |
| P2-S6 | Pest 테스트 (가격 등록 시 직전 자동 마감 / `currentPrice` 우선순위 / CSV upsert + 가격 동시 / Dry-run) | ✅ 완료 | 2026-04-20 | 2026-04-20 | `ProductPriceTest` 11 cases / `ProductImportTest` 8 cases |
| P3-S1 | `company_product_overrides` 마이그레이션 + `CompanyProductOverride` 모델 + `Company`/`Product` 관계 + `ProductPolicy` 스타일 정책 + `Product::effectiveSalePriceFor()`/`effectiveCommissionRateFor()` 헬퍼 | ✅ 완료 | 2026-04-20 | 2026-04-20 | unique `(company, product, effective_from)`, Factory 포함 |
| P3-S2 | 거래처 예외 CRUD (`Store/UpdateCompanyProductOverrideRequest` + `CompanyProductOverridePolicy` + `Service` 직전 자동 마감 + Controller + Routes) + `companies.search` API | ✅ 완료 | 2026-04-20 | 2026-04-20 | admin 한정, 가격 정책과 같은 패턴 |
| P3-S3 | 제품 상세 “거래처 예외” 카드 (`CompanyProductOverridePanel.vue` + `CompanySearchAutoComplete.vue`) + Show.vue 통합 | ✅ 완료 | 2026-04-20 | 2026-04-20 | DataTable + 등록/수정/삭제 모달 + companies.search API |
| P3-S4 | `spatie/laravel-activitylog` 도입 (composer require) + 마이그레이션 + `Product`/`ProductPrice`/`ProductFile`/`CompanyProductOverride` 에 `LogsActivity` trait + `ChangeReason` 헬퍼 + NIMS 채널 자동 분기(`tapActivity`) | ✅ 완료 | 2026-04-20 | 2026-04-20 | log_name: product / product.price / product.file / product.override / nims.product |
| P3-S5 | 승인/검수/반려/단종/가격 변경 audit 기록 (`change_reason` 영구 저장) + `remarks` 임시 부기 정리 + 명시적 `activity()` 로그 호출 | ✅ 완료 | 2026-04-20 | 2026-04-20 | reject/discontinue 사유는 properties.reason 으로 영구 저장 |
| P3-S6 | 제품 상세 “변경 이력” 탭 (`ProductActivityLogPanel.vue`) + NIMS 분리 채널 (`nims.product` log_name 자동 분기) + Show.vue에 activities prop 전달 | ✅ 완료 | 2026-04-20 | 2026-04-20 | 채널별 탭 + 행 펼침으로 raw properties 확인 |
| P3-S7 | Pest 테스트 12 cases (override 우선순위 / 자동 마감 / activity log 기록 / NIMS 채널 분리 / `change_reason` 영구 저장 / 권한) | ✅ 완료 (작성) | 2026-04-20 | 2026-04-20 | `CompanyProductOverrideTest` 12 cases — `sail test` 실행 필요 |

> 상태 표기: ⬜ 대기 / 🟡 진행중 / ✅ 완료 / ⛔ 보류

### 13.2 단계별 변경 로그

각 단계 완료 시 “변경된 파일 목록 + 핵심 결정”을 아래에 누적 기록합니다.

#### S1 — ✅ 완료 (2026-04-20)
- **변경(추가) 파일**:
  - `database/migrations/2026_04_20_100000_add_product_master_columns_to_products_table.php` (신규)
  - `database/migrations/2026_04_20_100001_create_product_files_table.php` (신규)
  - `app/Models/ProductFile.php` (신규)
  - `app/Models/Product.php` (수정 — 컬럼/관계/스코프/booted 훅 보강)
- **마이그레이션 요약**:
  - `products` 컬럼 추가: `standard_code`, `barcode_gtin`, `generic_name`, `strength`, `unit`, `pack_size`, `drug_type`(enum), `storage_condition`(enum), `replacement_product_id`(자기참조 FK), `approval_status`(5종 enum), `reviewed_at/by`, `approved_at/by`, `nims_managed`, `nims_item_code`
  - 인덱스: `standard_code`, `barcode_gtin`, `generic_name`, `drug_type`, `approval_status`, `nims_managed`, `nims_item_code` (각각 단일 index)
  - **`insurance_code` UNIQUE 부여** (개발 DB 데이터 중복 없음 가정)
  - `product_files` 신규 테이블: `file_type` enum(`license/safety/catalog/etc`) + `notice_files` 컬럼 패턴 재사용 + softDeletes
- **`Product` 모델 보강**:
  - 상수 정의: `STATUS_*`, `APPROVAL_*`, `DRUG_TYPE_*`, `NIMS_DRUG_TYPES`
  - 관계 추가: `files()`, `reviewer()`, `approver()`, `replacement()`
  - 스코프 추가: `scopeApproved()`, `scopeSalable()`
  - 헬퍼: `isSalable()`, `isNimsControlled()`
  - `booted()` `saving` 훅: `drug_type ∈ {narcotic, psychotropic}` → `nims_managed = true` 자동 세팅
  - casts 보강: `pack_size:int`, `reviewed_at:datetime`, `approved_at:datetime`, `nims_managed:boolean`
- **결정/메모**:
  - `insurance_code`는 인덱스를 unique로 변경. dev에서 충돌 시 마이그레이션이 실패할 수 있어 사전 확인 권장(`SELECT insurance_code, COUNT(*) FROM products GROUP BY insurance_code HAVING COUNT(*) > 1`).
  - `replacement_product_id`는 자기참조 + `nullOnDelete()` (대체품이 삭제돼도 본 제품은 유지).
  - `approval_status`/`reviewed_*`/`approved_*`는 별도 컬럼으로 추가하여 기존 `status`(active/inactive/discontinued)와 책임 분리.
  - 컨트롤러/Form Request/Policy는 **S2 이후**에 일괄 변경. 본 단계에서 `ProductController` 코드를 만지지 않았으므로 기존 화면은 영향 없음(신규 컬럼은 `nullable` 또는 default 보유).
- **확인 필요(다음 세션 시작 시)**:
  - `php artisan migrate` 실행 (sail 환경: `./vendor/bin/sail artisan migrate`)
  - 실패 시 `insurance_code` 중복 행 정리 후 재시도

#### S2 — ✅ 완료 (2026-04-20)
- **변경(수정) 파일**:
  - `app/Policies/ProductPolicy.php` (수정)
  - `app/Http/Requests/StoreProductRequest.php` (수정)
  - `app/Http/Requests/UpdateProductRequest.php` (수정)
- **`ProductPolicy` 추가 액션 (admin + 상태 조건)**:
  - `submit(Product)`: `approval_status ∈ {draft, rejected}`
  - `review(Product)`: `approval_status = pending`
  - `approve(Product)`: `approval_status = reviewed`
  - `reject(Product)`: `approval_status ∈ {pending, reviewed}`
  - `discontinue(Product)`: `status ≠ discontinued`
  - 정책에서 상태 조건까지 함께 검사 → UI에서 `can()`으로 버튼 노출 분기 가능
- **`StoreProductRequest` 보강**:
  - 신규 컬럼 검증: `standard_code`, `barcode_gtin`, `generic_name`, `strength`, `unit`, `pack_size`, `drug_type`(enum), `storage_condition`(enum), `nims_item_code`, `replacement_product_id`(존재/자기참조 금지)
  - `insurance_code`에 **unique:products** 적용 (DB unique 제약과 이중 안전장치)
  - `approval_status`는 입력 받지 않음 — **컨트롤러에서 `draft` 강제** (S3에서 적용)
- **`UpdateProductRequest` 보강**:
  - 동일 신규 컬럼 검증 + `insurance_code` unique는 `ignore($id)`
  - `replacement_product_id`에 자기참조 방지 (`Rule::notIn([$id])`)
  - `change_reason`(nullable, 500자) 컬럼 추가
  - **`withValidator` after 규칙**: 마약/향정 관련 제품(`nims_managed=true` 또는 `drug_type ∈ {narcotic, psychotropic}`)이고 핵심 컬럼(`product_name/generic_name/strength/unit/pack_size/manufacturer/drug_type/storage_condition/nims_item_code/replacement_product_id/status`) 변경 시 `change_reason` 필수
- **결정/메모**:
  - `change_reason`은 컬럼 신설 없이 **요청-only 필드**로 운영 (Phase 3 Audit 도입 시 `activity_log`에 기록 예정)
  - `replacement_product_id` 검증: `whereNull('deleted_at')` 적용 — 소프트 삭제된 제품 지정 금지
  - `drug_type` 변경으로 마약/향정으로 전환되는 케이스도 `nims_managed=true` 자동 세팅(S1 모델 훅) + 사유 필수(S2 검증)로 이중 방어
  - 컨트롤러는 본 단계에서 손대지 않음 — 신규 컬럼 저장은 **S3에서 컨트롤러 갱신**과 함께 반영
- **확인 필요(다음 세션 시작 시)**: 없음. S3로 바로 진행 가능.

#### S3 — ✅ 완료 (2026-04-20)
- **신규 파일**:
  - `app/Http/Requests/RejectProductRequest.php` — `reason` (2~500자) 필수
  - `app/Http/Requests/DiscontinueProductRequest.php` — `replacement_product_id`(존재/자기참조 금지), `reason`(선택)
- **수정 파일**:
  - `app/Http/Controllers/ProductController.php`
    - `index`: 검색 컬럼 확장(`standard_code`, `barcode_gtin`, `generic_name`), `approval_status` 필터 추가
    - `store`: `approval_status='draft'` 강제, `reviewed_*`/`approved_*` null 초기화
    - `update`: `change_reason` unset (DB 컬럼 아님)
    - `show`: `reviewer/approver/replacement/files/files.uploader` 즉시 로드, `can.{submit,review,approve,reject,discontinue}` 노출
    - 신규 액션: `submit / review / approve / reject / discontinue`
      - 각 액션은 `Product::APPROVAL_*` / `STATUS_*` 상수 사용
      - `review`/`approve` 시 타임스탬프와 담당자 기록(`reviewed_at`/`reviewed_by`, `approved_at`/`approved_by`)
      - `reject` 시 `reviewed_*`/`approved_*` 초기화 + `remarks`에 `[반려: …] 사유` 부기 (Phase 3 audit 도입 전 임시)
      - `discontinue` 시 `replacement_product_id`/사유는 선택, 사유는 `remarks`에 부기
  - `routes/web.php`
    - 5개 라우트 추가: `POST /products/{product}/{submit|review|approve|reject|discontinue}`
    - 라우트 이름: `products.submit`, `products.review`, `products.approve`, `products.reject`, `products.discontinue`
- **결정/메모**:
  - 반려/단종 사유는 정식 audit 테이블이 아직 없으므로 `remarks`에 임시 부기 → Phase 3에서 `spatie/laravel-activitylog` 도입 후 `properties.reason`으로 이관 예정
  - 라우트는 `Route::resource` **밖**에 배치하여 RESTful CRUD와 분리 (URL 충돌 없음)
  - `update` 메서드는 신규 컬럼을 자동 저장 (Form Request에서 검증된 필드만 통과 → Product의 fillable에 포함됨)
- **확인 필요(다음 세션 시작 시)**: 없음. 곧바로 S4(`ProductFileController`)로 진행 가능.

#### S4 — ✅ 완료 (2026-04-20)
- **신규 파일**:
  - `app/Http/Requests/StoreProductFileRequest.php` — 파일 업로드 검증 (max 10MB, mimes pdf/이미지/오피스/zip, `file_type` enum)
  - `app/Policies/ProductFilePolicy.php` — `viewAny/view`(전 사용자), `create/delete`(admin)
  - `app/Http/Controllers/ProductFileController.php` — `store/destroy/download`
- **수정 파일**:
  - `routes/web.php` — `ProductFileController` import + 3개 라우트 추가
- **추가 라우트**:
  - `POST   /products/{product}/files` → `products.files.store` (업로드, admin)
  - `DELETE /products/{product}/files/{file}` → `products.files.destroy` (삭제, admin)
  - `GET    /products/{product}/files/{file}/download` → `products.files.download` (다운로드, 인증된 모든 사용자)
- **저장 경로 정책**:
  - `storage/app/public/products/{product_id}/files/{uuid}.{ext}`
  - `stored_name` = uuid+ext, `original_name`은 사용자 업로드 원본명 그대로
- **결정/메모**:
  - `ProductFilePolicy`는 Laravel 11 auto-discovery로 자동 매핑 (별도 등록 불필요)
  - `destroy`/`download` 메서드는 `product_id` 일치성을 직접 검증 (URL 변조 방지)
  - SoftDeletes 적용된 `ProductFile`은 삭제 시 실제 파일도 함께 제거 (논리적 복원 시 파일 부재 → 추후 정책 검토 필요)
  - 업로드 응답은 `back()` — 상세 화면에서 모달/탭 재로드를 통해 갱신 (UI는 S6에서 작업)
- **확인 필요(다음 세션 시작 시)**: 없음. 곧바로 S5(상세 UI 상태 뱃지/액션 버튼)로 진행 가능.

#### S5 — ✅ 완료 (2026-04-20)
- **수정 파일**:
  - `resources/js/Pages/Products/Show.vue` (대대적 확장)
- **추가/변경 사항**:
  - `Product` 인터페이스 확장 (모든 신규 컬럼 + `reviewer/approver/replacement` 관계)
  - `Capabilities` 인터페이스에 `submit/review/approve/reject/discontinue` 5종 추가
  - 상단에 **3종 뱃지** 동시 노출: 승인 상태(`approval_status`) / 판매 상태(`status`) / 약품 유형(`drug_type`)
    - NIMS 관리 대상이면 추가로 빨간색 `NIMS 관리` 뱃지 표시
  - `nextStepHint` 안내 메시지 (`Message` 컴포넌트로 다음 단계 안내)
  - **권한별 액션 버튼**: 검수 요청 / 검수 완료 / 최종 승인 / 반려 / 단종 처리
    - 단순 액션 3종(`submit/review/approve`)은 `useConfirm`으로 확인 후 `router.post`
    - 반려는 `Dialog` + `Textarea`로 사유 입력 (필수, 서버 422 응답을 `useForm.errors`로 표시)
    - 단종은 `Dialog` + `InputNumber`(대체품 ID) + `Textarea`(사유)로 입력
  - **기본 정보 그리드 확장**: 표준코드/바코드/성분명/함량·단위/포장수량/보관조건/NIMS 코드/대체품 링크 추가
  - **변경 이력 그리드**: 등록/수정/검수/최종승인 4종 표시 (담당자명 포함)
- **결정/메모**:
  - 대체품 선택은 우선 `InputNumber`(productId 직접 입력)로 단순 구현 — 추후 검색 Dropdown으로 보강 가능
  - 모달 폼은 모두 `useForm`을 사용해 서버 검증 에러를 그대로 표시 (`reason`이 빈 경우 422)
  - `Index.vue`의 `approval_status` 필터 UI는 본 단계에서 미반영 — 후속 보강 항목으로 분류 (필요 시 별도 단계 추가)
- **확인 필요(다음 세션 시작 시)**: 없음. 곧바로 S6(첨부 문서 탭 UI)로 진행 가능.

#### S6 — ✅ 완료 (2026-04-20)
- **신규 파일**:
  - `resources/js/Pages/Products/Partials/ProductFilePanel.vue` — 첨부 파일 관리 패널
- **수정 파일**:
  - `resources/js/Pages/Products/Show.vue` — `ProductFile` 타입 추가, `Product.files` 추가, 하단에 `ProductFilePanel` 카드 마운트
- **UI 구성**:
  - **탭**: `전체` / `허가 문서(license)` / `안전성 자료(safety)` / `카탈로그(catalog)` / `기타(etc)` — 각 탭에 카운트 뱃지
  - **테이블 컬럼**: 종류 뱃지 / 파일명 / 크기 / 업로더 / 업로드 시각 / 액션(다운로드·삭제)
  - **업로드 모달**: 문서 종류 Select + 파일 input (accept 화이트리스트 명시) — `useForm`의 `forceFormData: true`로 multipart 전송
  - **삭제**: `useConfirm`으로 확인 후 `router.delete`
  - **다운로드**: `<a href="..." target="_blank">` + `Storage::disk('public')->download()` 응답
- **결정/메모**:
  - 업로드 권한은 `can.update`(Product update 권한)와 동일하게 부여 → admin 한정
  - 삭제 권한은 `can.delete`와 동일 → admin 한정
  - 일반 사용자(영업)도 다운로드는 가능 (서버 정책: `ProductFilePolicy::view = true`)
  - 탭은 PrimeVue 4 신규 API(`Tabs / TabList / Tab / TabPanels / TabPanel`) 사용 — 5개 패널을 v-for로 정적 렌더링하여 탭 전환 시 DataTable 상태가 분리됨
  - PrimeVue `FileUpload` 대신 native `<input type="file">` 사용 — Inertia useForm과 더 단순하게 통합되고, 디스크 저장 로직이 컨트롤러에 있어 PrimeVue 업로더의 자동 업로드 기능이 불필요
- **확인 필요(다음 세션 시작 시)**: 없음. 곧바로 S7(Pest 테스트)로 진행 가능.

#### S7 — ✅ 완료 (코드 작성 — 2026-04-20)
- **신규 파일**:
  - `tests/Feature/Products/ProductApprovalFlowTest.php` — 11개 케이스
  - `tests/Feature/Products/ProductFileTest.php` — 8개 케이스
  - `tests/Feature/Products/ProductInsuranceCodeUniqueTest.php` — 4개 케이스
- **승인 흐름 테스트 (11건)**:
  - draft → pending 정상 전환
  - rejected → pending 재요청 가능
  - pending 상태에서 다시 submit 시 403
  - sales 의 submit 차단 (403)
  - 전체 흐름(draft → pending → reviewed → approved) + `reviewed_at/by`, `approved_at/by` 기록
  - reviewed 가 아닌 상태에서 approve → 403
  - 반려 시 사유 누락 → 422, 사유 입력 시 rejected + remarks에 사유 부기
  - 단종 처리 시 status/replacement/remarks 반영
  - 자기 자신을 대체품 지정 → 422
  - `drug_type=narcotic`이면 `nims_managed` 자동 true (모델 saving 훅)
  - NIMS 관리 제품의 핵심 컬럼 변경 시 `change_reason` 필수 (Form Request after 규칙)
- **첨부 파일 테스트 (8건)**:
  - admin 업로드 성공 + 디스크 저장 확인
  - sales 업로드 차단 (403)
  - 잘못된 `file_type` → 422
  - 10MB 초과 → 422
  - admin 삭제 시 DB+파일 모두 제거
  - sales 삭제 차단 (403)
  - 다른 제품 ID로 삭제 시도 → 404 (URL 변조 방지)
  - sales의 다운로드 허용 (정책상 view = true)
- **insurance_code 유니크 테스트 (4건)**:
  - 동일 코드 중복 등록 → 422
  - 자기 자신 코드로 update → 통과 (`ignore($id)`)
  - 다른 제품의 코드로 update → 422
  - 신규 등록 시 `approval_status` 입력 무시되고 항상 `draft` 강제
- **실행 명령**:
  - sail: `./vendor/bin/sail test --filter=Products`
  - 또는 `./vendor/bin/sail test tests/Feature/Products`
- **결정/메모**:
  - `RefreshDatabase` 트레이트로 매 테스트마다 마이그레이션이 자동 실행되므로 사용자 환경의 마이그레이션 미실행 여부와 무관하게 통과해야 함
  - 단, `phpunit.xml`의 `DB_DATABASE=testing` MariaDB가 미리 생성되어 있어야 함 (기존 다른 테스트들이 이미 사용 중이므로 이미 존재할 가능성 높음)
  - 본 에이전트 환경(샌드박스)에서는 docker 접근이 제한되어 직접 실행 불가 → 사용자 환경에서 결과 확인 필요
- **확인 필요(다음 세션 시작 시)**:
  - 사용자가 `sail test --filter=Products` 결과 공유 → 실패 케이스가 있다면 핀포인트로 수정 (다음 세션 첫 작업)

#### P1.5-1 — ✅ 완료 (2026-04-20)
- **수정 파일**:
  - `resources/js/Pages/Products/Index.vue` (대대적 갱신)
- **변경 사항**:
  - 필터 UI에 `approval_status` Select 추가 (전체/초안/검수 대기/검수 완료/승인 완료/반려)
  - 검색 placeholder 확장(표준코드/바코드/성분명 명시) — 백엔드 검색 컬럼은 S3에서 이미 확장됨
  - DataTable에 `승인` 컬럼 추가 (승인 단계 뱃지)
  - 제품명 셀에 `drug_type`(general 제외)·`NIMS` 미니 뱃지 추가
  - "필터 초기화" 버튼 추가 (현재 필터가 하나라도 걸려 있을 때만 노출)
- **결정/메모**:
  - 모든 컬럼/필터는 admin/sales 동일하게 노출 (열람 권한은 모두에게 있음)
  - 백엔드 응답에 신규 컬럼 자동 포함 → Eloquent serialize에 의존 (별도 토글 불필요)

#### P1.5-2 — ✅ 완료 (2026-04-20)
- **수정 파일**:
  - `resources/js/Pages/Products/Partials/ProductForm.vue` (대대적 확장)
  - `resources/js/Pages/Products/Create.vue` (useForm 신규 필드 추가)
  - `resources/js/Pages/Products/Edit.vue` (useForm 신규 필드 추가, `change_reason` 포함)
- **추가된 입력 필드**:
  - **식별**: `standard_code`, `barcode_gtin`
  - **약품 상세**: `generic_name`, `strength`, `unit`, `pack_size`, `drug_type`(Select), `storage_condition`(Select)
  - **NIMS**: `nims_item_code` (마약/향정 선택 시에만 섹션 노출)
  - **변경 사유**: `change_reason` (Edit 모드에서만, NIMS 관리 대상이면 `*` + 강조 메시지)
- **UX 디테일**:
  - `drug_type`이 narcotic/psychotropic이면 즉시 “NIMS 관리 자동 등록” 안내 (모델 saving 훅과 일치)
  - Edit 모드에서 status 변경 가능하지만 “단종은 단종 처리 버튼 권장” 안내 표시
  - 모드 분기는 `'change_reason' in form` 또는 `'remove_image' in form` 체크 (별도 prop 불필요)
- **결정/메모**:
  - PrimeVue `Message` 컴포넌트로 NIMS 변경 사유 강조 — 서버 422도 동일 필드에 노출됨
  - 신규 컬럼은 모두 nullable이므로 빈 값 전송 시에도 검증 통과 (Form Request에서 `nullable` 적용 완료)

#### P1.5-3 — ✅ 완료 (2026-04-20)
- **신규 파일**:
  - `resources/js/Pages/Products/Partials/ProductSearchAutoComplete.vue` — 재사용 가능한 제품 검색 AutoComplete
- **수정 파일**:
  - `app/Http/Controllers/ProductController.php` — `search()` 메서드 추가, `JsonResponse` import
  - `routes/web.php` — `GET /products/search` 라우트 추가 (`Route::resource` 위에 배치하여 `/products/{product}` 와 충돌 방지)
  - `resources/js/Pages/Products/Show.vue` — 단종 모달의 `InputNumber` → `ProductSearchAutoComplete` 교체
- **검색 API 사양**:
  - `GET /products/search?q={keyword}&exclude={id}` (인증 + viewAny 권한 필요 — 사실상 전 사용자)
  - 검색 컬럼: `product_name`, `insurance_code`, `product_code`, `standard_code`, `barcode_gtin`, `generic_name`
  - 응답: `[{ id, product_name, insurance_code, product_code, manufacturer }]` (최대 15건)
- **컴포넌트 props**:
  - `modelValue: number | null` (v-model로 productId 바인딩)
  - `excludeId?: number | null` (자기 자신 제외용)
  - `initialLabel?: { product_name, insurance_code }` (수정 폼에서 이미 알고 있는 라벨 즉시 노출)
  - `placeholder?`, `invalid?`
- **결정/메모**:
  - PrimeVue `AutoComplete` 사용 — 디바운스 300ms, 최소 1자
  - 검색은 `fetch`로 직접 호출 (Inertia가 아닌 단순 JSON 엔드포인트), `credentials: 'same-origin'`로 세션 인증 사용
  - 선택 객체는 컴포넌트 내부에 보관 — `update:modelValue`로 ID만 외부에 emit하여 form 데이터 단순화
  - `Show.vue`에서 InputNumber 제거 → import 정리 완료

### 13.3 Phase 2 진행 로그 (P2-S1 ~ P2-S6)

#### P2-S1 — `product_prices` 데이터 모델 (✅ 완료)
- **파일**:
  - `database/migrations/2026_04_20_110000_create_product_prices_table.php`
  - `database/migrations/2026_04_20_110001_seed_sale_prices_from_products_price.php` — 기존 `products.price` → `product_prices(sale)` 1건 시드
  - `app/Models/ProductPrice.php` — 상수, fillable, casts, `product/creator/updater` 관계, `ofType/activeOn` 스코프
  - `app/Models/Product.php` — `prices()` HasMany, `currentPriceOf($type, $on=null)`, `latestPricesByType($on=null)`
  - `database/factories/ProductPriceFactory.php`
- **결정/메모**: unique 제약 `(product_id, price_type, effective_from)`. 인덱스 `(product_id, price_type, effective_from, effective_to)`.

#### P2-S2 — 가격 CRUD 백엔드 (✅ 완료)
- **파일**:
  - `app/Services/Products/ProductPriceService.php` — `register/update/delete`, 등록 시 직전 활성 이력 `effective_to = new_from - 1d` 자동 마감 (트랜잭션)
  - `app/Policies/ProductPricePolicy.php` — admin 한정 (view 는 모두)
  - `app/Http/Requests/StoreProductPriceRequest.php` — `(type, effective_from)` 사전 unique 검사
  - `app/Http/Requests/UpdateProductPriceRequest.php` — amount/source/note/effective_to 만 변경 허용
  - `app/Http/Controllers/ProductPriceController.php`
  - `routes/web.php` — `products.prices.{store,update,destroy}`
  - `app/Http/Controllers/ProductController.php` — `show()`에 `prices` eager load + `current_prices` 응답 + `can.managePrices`
- **결정/메모**: `price_type/effective_from`은 변경 불가 (인접 이력 정합성). 변경 필요 시 삭제 후 재등록.

#### P2-S3 — 상세 화면 “가격 이력” UI (✅ 완료)
- **파일**: `resources/js/Pages/Products/Partials/ProductPricePanel.vue`, `Show.vue` (intf + 카드 추가)
- **기능**: 종류 탭(전체/보험/원가/판매), 현재 적용 가격 카드 3개, 등록 모달(InputNumber + DatePicker), 수정 모달, 삭제 확인 모달, `적용중` 뱃지

#### P2-S4 — 가격 변동 차트 (✅ 완료)
- **파일**: `resources/js/Pages/Products/Partials/ProductPriceChart.vue`
- **기능**: PrimeVue `Chart` (line) + `stepped: 'before'`로 step line. effective_from/effective_to/today 기반 라벨 산출, 종류별 데이터셋 (보험·원가·판매). 데이터 없을 때는 placeholder.

#### P2-S5 — CSV Bulk Upsert (✅ 완료)
- **파일**:
  - `app/Services/Products/ProductImportService.php` — `parseCsv / validateHeaders / analyze / import`
  - `app/Http/Requests/ImportProductsRequest.php` — `file` (≤5MB, csv/txt) 또는 `token` 둘 중 하나 + `mode in [analyze, commit]`
  - `app/Http/Controllers/ProductImportController.php` — `form / handle / sample`
  - `routes/web.php` — `products.import.form / .handle / .sample` (resource 위에 등록)
  - `resources/js/Pages/Products/Import.vue` — 1) Dry-run 업로드 2) 결과 검토 + 확정 적용 (오류만 보기 토글, 가격 변동 뱃지)
  - `resources/js/Pages/Products/Index.vue` — “CSV 일괄 등록” 버튼 추가
- **결정/메모**:
  - **2단계 모델**: analyze → token (uuid) 발급 + storage(local) 임시 저장 → commit 시 token 으로 다시 읽고 import. 토큰 30분 유지.
  - **all-or-nothing**: 한 행이라도 검증 실패면 전체 미적용.
  - **upsert 키 우선순위**: insurance_code → standard_code → barcode_gtin
  - 신규 생성 시 `approval_status=draft` 강제, 기존 행은 `status/approval_status` 보존
  - 가격 컬럼 처리는 `ProductPriceService::register()` 재사용 → 직전 자동 마감 정책이 자동 적용됨
  - **샘플 CSV**: `/products/import/sample` GET — UTF-8 BOM 포함 + 헤더 + 예시 1행

#### P2-S6 — Pest 테스트 (✅ 완료)
- **파일**:
  - `tests/Feature/Products/ProductPriceTest.php` — 11 cases (자동 마감, 종류 독립, `currentPriceOf` 시점, HTTP CRUD, sales 권한, 가격 0, 동일 (type+from) 차단, 부분 수정, 삭제, `latestPricesByType`)
  - `tests/Feature/Products/ProductImportTest.php` — 8 cases (analyze, commit + create + 가격, update 동작, all-or-nothing, 알 수 없는 컬럼, sales 접근 차단, 샘플 다운로드, CSV + 자동 마감)

#### P3-S1 — 거래처 예외 데이터 모델 (✅ 완료, 2026-04-20)
- **변경(추가) 파일**:
  - `database/migrations/2026_04_20_120000_create_company_product_overrides_table.php` (신규) — `company_id/product_id/override_unit_price/override_commission_rate/effective_from/effective_to/reason`, unique `(company,product,effective_from)`
  - `app/Models/CompanyProductOverride.php` (신규) — relations + `scopeActiveOn` + `isActive`
  - `database/factories/CompanyProductOverrideFactory.php` (신규)
  - `app/Policies/CompanyProductOverridePolicy.php` (신규)
- **변경(수정) 파일**:
  - `app/Models/Product.php` — `companyOverrides()` HasMany + `activeOverrideFor()` + `effectiveSalePriceFor(Company,$on)` + `effectiveCommissionRateFor(Company,$on)`
  - `app/Models/Company.php` — `productOverrides()` HasMany
- **결정/메모**:
  - 가격 정책 **단일 진입점** = `Product::effectiveSalePriceFor(Company)` — override → product_prices(sale) → products.price 폴백
  - 수수료율 **단일 진입점** = `Product::effectiveCommissionRateFor(Company)` — override → 등급 매트릭스
  - SoftDelete 적용

#### P3-S2 — 거래처 예외 CRUD 백엔드 (✅ 완료, 2026-04-20)
- **변경(추가) 파일**:
  - `app/Services/Products/CompanyProductOverrideService.php` — `register/update/delete` (직전 자동 마감, 가격과 동일 패턴)
  - `app/Http/Requests/StoreCompanyProductOverrideRequest.php` — 단가/수수료율 둘 다 비어있으면 거부 + (거래처+시작일) 중복 차단
  - `app/Http/Requests/UpdateCompanyProductOverrideRequest.php` — 부분 수정 (회사/시작일 변경 불가)
  - `app/Http/Controllers/CompanyProductOverrideController.php` — `store/update/destroy`
- **변경(수정) 파일**:
  - `routes/web.php` — `products.overrides.{store,update,destroy}` + `companies.search` (자동완성)
  - `app/Http/Controllers/CompanyController.php` — `search()` JsonResponse 추가
  - `app/Http/Controllers/ProductController.php` — `show()`에 `companyOverrides` eager load + `can.manageOverrides`

#### P3-S3 — 거래처 예외 UI (✅ 완료, 2026-04-20)
- **변경(추가) 파일**:
  - `resources/js/Pages/Products/Partials/CompanySearchAutoComplete.vue` — PrimeVue AutoComplete + `companies.search` 호출
  - `resources/js/Pages/Products/Partials/CompanyProductOverridePanel.vue` — DataTable + 등록/수정/삭제 모달 + 적용중 배지
- **변경(수정) 파일**:
  - `resources/js/Pages/Products/Show.vue` — `CompanyOverrideRow` 인터페이스 + `manageOverrides` capability + 패널 카드 통합

#### P3-S4 — Audit 인프라 (✅ 완료, 2026-04-20)
- **설치**: `composer require spatie/laravel-activitylog:^4.8` (4.12.3)
- **변경(추가) 파일**:
  - `database/migrations/2026_04_20_130000_create_activity_log_table.php` — `log_name/description/subject_*/event/causer_*/properties/batch_uuid`
  - `config/activitylog.php` — vendor 기본값 복사
  - `app/Models/ChangeReason.php` — 요청 단위 정적 컨텍스트 (`set/clear/with(callback)`)
- **변경(수정) 파일**:
  - `app/Models/Product.php` — `LogsActivity` + `getActivitylogOptions(useLogName('product'))` + `tapActivity()`로 NIMS 컬럼 변경 또는 NIMS 관리 대상이면 **`nims.product` 채널로 자동 분기** + `ChangeReason::current()` → properties.reason
  - `app/Models/ProductPrice.php` — log_name `product.price` + reason 첨부
  - `app/Models/ProductFile.php` — log_name `product.file`
  - `app/Models/CompanyProductOverride.php` — log_name `product.override` + (모델의 `reason` 컬럼 또는 `ChangeReason::current()`) 첨부

#### P3-S5 — 액션별 Audit 기록 (✅ 완료, 2026-04-20)
- **변경(수정) 파일**:
  - `app/Http/Controllers/ProductController.php`:
    - `update()` — `change_reason`을 `ChangeReason::with()`로 감싸 update → activity log properties.reason 자동 저장
    - `submit/review/approve` — 각각 `ChangeReason::with(라벨)` + 명시적 `activity('product')->event(...)->log('product.{event}')`
    - `reject` — `remarks` 부기 제거, properties.reason 에 사유 영구 저장
    - `discontinue` — `remarks` 부기 제거, properties.reason + replacement_product_id 영구 저장
- **결정/메모**:
  - 임시 `[반려: …]` / `[단종: …]` `remarks` 부기는 **완전히 제거**. 신규 변경부터는 모두 audit log로만 추적됨.
  - 기존 데이터의 `remarks` 부기는 그대로 보존 (마이그레이트 안 함, 새 audit log와 공존).

#### P3-S6 — 변경 이력 UI (✅ 완료, 2026-04-20)
- **변경(추가) 파일**:
  - `resources/js/Pages/Products/Partials/ProductActivityLogPanel.vue` — Tabs (전체/제품/가격/거래처 예외/첨부/NIMS) + DataTable + 행 펼침으로 raw properties JSON
- **변경(수정) 파일**:
  - `app/Http/Controllers/ProductController.php` — `show()`에서 제품 + 자식(가격/파일/override) subject 의 `Activity` 최근 100건을 통합 조회 후 Inertia prop 전달
  - `resources/js/Pages/Products/Show.vue` — `ActivityRow` 인터페이스 + `activities` prop + 패널 카드 추가

#### P3-S7 — Pest 테스트 (✅ 완료 작성, 2026-04-20)
- **파일**:
  - `tests/Feature/Products/CompanyProductOverrideTest.php` — 12 cases:
    1. 신규 등록 시 직전 활성 이력 자동 마감
    2. 다른 거래처 이력 독립
    3. `effectiveSalePriceFor` 우선순위 (override → sale → products.price)
    4. `effectiveCommissionRateFor` 우선순위 (override → 등급 매트릭스)
    5. admin 한정 권한
    6. 단가/수수료율 둘 다 비어있으면 거부
    7. 같은 거래처+시작일 중복 차단
    8. CompanyProductOverride 변경이 `product.override` 채널로 기록
    9. NIMS 컬럼 변경이 `nims.product` 채널로 자동 분리
    10. 승인 액션이 `product.approve` 로 기록 + causer
    11. 반려 사유가 `properties.reason` 으로 영구 저장
    12. 단종 사유 + 대체품이 `properties` 로 기록

### 13.4 다음 세션 인계 메모

> 새 세션에서 이어서 작업할 때 가장 먼저 보는 영역입니다.

- **현재 위치**: 🎉 **Phase 1 (S1~S7) + Phase 1.5 (P1.5-1~P1.5-3) + Phase 2 (P2-S1~P2-S6) + Phase 3 (P3-S1~P3-S7) 모두 완료.**
  - 의약품 마스터 + 4단계 승인 + NIMS + 첨부 + 가격 이력(차트) + CSV 일괄 등록 + 거래처 예외(단가·수수료율) + spatie audit log + NIMS 분리 채널까지 운영 가능.
- **다음 액션 (사용자) — Phase 3 적용 필수 단계**:
  1. **마이그레이션 실행 (필수)** — 신규 테이블 2개:
     - `./vendor/bin/sail artisan migrate`
     - 추가되는 테이블: `company_product_overrides`, `activity_log`
  2. **프론트 빌드** — `./vendor/bin/sail npm run dev` 또는 `build`
  3. **테스트 실행 (권장)** — `./vendor/bin/sail test --filter=Products`
     - 신규: `CompanyProductOverrideTest` (12 cases)
  4. **수동 화면 확인 체크리스트 (Phase 3)**
     - 제품 상세 → “거래처 예외” 카드: 거래처 자동완성 → 등록 → 적용중 표시
     - 같은 거래처에서 두 번째 등록 → 직전 이력 자동 마감 (effective_to)
     - 단가/수수료율 둘 다 비우면 422 안내
     - 제품 상세 → “변경 이력” 카드: 탭별 필터 (제품/가격/거래처 예외/첨부/NIMS) + 행 펼침
     - 마약/향정 약품 유형 변경 시 `NIMS` 채널 탭에 자동 분리되어 표시
     - admin이 검수/승인/반려/단종 시 audit log에 사유/액션 기록 (반려: properties.reason, 단종: properties.reason + replacement_product_id)
- **앞으로의 작업**: 후보 항목과 우선순위는 [`docs/planning/ROADMAP.md`](../../planning/ROADMAP.md) 에서 통합 관리합니다.
  - 본 문서의 “다음 액션” 섹션은 **작성 당시의 인계 메모** 성격이며, 최신 우선순위는 ROADMAP를 기준으로 합니다.
