# 의약품 도메인 재설계 — 공유 마스터 + 제약사 취급품 (GAP-11)

> 단일 `products`(테넌트별 복제) 구조를, **공유 의약품 마스터 + 제약사별 취급품** 구조로 재설계하는 설계 문서.
> 참조: `Pample_renewal_project`(Spring/DDD) 의 `DrugApproval → DrugProduct → CompanyDrugProduct` 분리 모델.
> 규모 **XL** — 실적·정산까지 참조 재배선. 단계(DR) 분할 + 병행 운영 후 cutover.
> 진행/백로그: [`docs/planning/ROADMAP.md`](../../planning/ROADMAP.md) **GAP-11**. 멀티테넌시 설계: [`MULTI_TENANCY.md`](../tenancy/MULTI_TENANCY.md).

---

## 0. 배경 / 목적

현재 정진팜은 의약품을 단일 `products` 테이블로 관리하고, GAP-10 MT-4에서 **`tenant_id`를 붙여 제약사별로 격리**했다. 그 결과 동일 의약품("타이레놀 500mg")이 **제약사 수만큼 행이 중복**된다. 마스터 갱신(약가·허가 변경) 시 모든 복제본을 따로 고쳐야 하고 일관성이 깨진다.

Pample(renewal)은 이를 **3단 분리**로 해결한다: 전국 허가 마스터(공유) → 보험코드 단위 제품 → 회사별 취급품(+수수료). 이 구조는 **멀티테넌시에 정합적**이다(마스터 1벌 공유, 테넌트는 "취급 선택"만).

**목표**: Pample의 핵심 분리를 정진팜에 **경량 차용**해, 의약품 중복을 없애고 platform/제약사 책임을 명확히 한다. (식약처/심평원 동기화 파이프라인 등 무거운 부분은 차용하지 않음 — 정진팜엔 `health_individual_drugs` 심평원 참조가 이미 존재.)

---

## 1. 현행 vs 목표

### 1.1 현행 (정진팜)
```
products (tenant_id 부착 — 제약사별 복제)
  ├─ product_prices            (price_type: insurance/cost/sale, effective_from~to)  ← 기간형 이력 ✓
  ├─ product_commission_rates  (제품×등급 A~E 매트릭스, base_month, effective range) ← 등급 기반 ✓
  ├─ company_product_overrides (거래처별 예외 단가/수수료)
  ├─ product_files / approval_status 워크플로 / NIMS
performances.product_id → products (실적 스냅샷이 product 참조)
settlements ← performances 집계
health_individual_drugs (심평원 개별의약품 참조 — 공유, 별개)
```

### 1.2 목표 (경량 차용)
```
drug_products  ← 공유 의약품 마스터 (platform 관리, tenant_id 없음)
  └─ drug_product_prices  ← 약가 이력 (기존 product_prices 재사용, 보험약가 중심)
company_drug_products  ← 제약사(tenant)별 "취급 선택" (tenant_id + drug_product_id 유니크)
  ├─ company_drug_product_commission_rates  ← 제약사별 수수료 (등급 매트릭스 유지, D-2)
  └─ (거래처 예외단가는 company_drug_product 기준으로 재배치, D-3)
performances.company_drug_product_id → company_drug_products  (또는 drug_product_id, D-4)
health_individual_drugs → drug_products 시드/동기화 원천
```

**핵심 전환**: `products`(테넌트 복제) → `drug_products`(공유 1벌) + `company_drug_products`(테넌트 취급 매핑).

---

## 2. 책임 분담 (멀티테넌시 정합)

| 엔티티 | 소유/관리 | tenant_id |
|---|---|---|
| `drug_products` (의약품 마스터) | **platform** (정진팜) — 중앙 카탈로그 | 없음(공유) |
| `drug_product_prices` (약가) | platform | 없음(공유) |
| `company_drug_products` (취급품) | **제약사(pharma)** — 마스터에서 선택 | **있음** |
| `company_drug_product_commission_rates` | 제약사 | (company_drug_product 통해 상속) |

→ **platform = 공유 의약품 마스터 관리**, **제약사 = 취급 제품 선택 + 자사 수수료**. (GAP-10에서 미뤄둔 "platform vs pharma 의약품 소유권" 이 여기서 해소됨.)

---

## 3. 정진팜 자산 재사용 매핑

| 현행 | 재배치 | 비고 |
|---|---|---|
| `products` (마스터성 컬럼: insurance_code, product_name, generic_name, manufacturer, drug_type, nims_* …) | → `drug_products` (tenant_id 제거, 공유) | 마스터 컬럼은 거의 그대로 |
| `product_prices` | → `drug_product_prices` | 약가는 마스터 귀속 (공유) |
| `product_commission_rates` (등급 A~E) | → `company_drug_product_commission_rates` | **수수료는 제약사 귀속** (D-2) |
| `company_product_overrides` | → company_drug_product 기준 예외 | 거래처×취급품 (D-3) |
| `health_individual_drugs` | drug_products **시드/매칭 원천** | 심평원 데이터로 마스터 채움 |
| `performances.product_id` | → company_drug_product_id (D-4) | **최대 영향 지점** |

---

## 4. 영향 범위 (대형)

`products`를 직접 참조하는 모든 곳을 재배선해야 한다:
- **`performances.product_id`** (실적 원장 — 스냅샷). 가장 핵심.
- `settlements` / `SettlementBuilder` / `PerformanceResolver` (가격·수수료 스냅샷 해석)
- `company_product_overrides`, `product_prices`, `product_commission_rates`
- `ProductController`(+가격/수수료/파일/override 패널), `Platform\ProductController`, `MasterData`
- 마스터 관리/플랫폼 메뉴·Vue 페이지, CSV import, 월간 보고서(제품별 집계)
- 다수 테스트

→ **GAP-10(멀티테넌시) 안정화 후** 별도 대형 작업으로 진행. 병행 테이블 + 점진 cutover 권장.

---

## 5. 단계 분할 (DR) — 초안

| 단계 | 내용 | 비고 |
|---|---|---|
| DR-0 | 설계·결정 확정 (본 문서, D-1~D-5) | |
| DR-1 | `drug_products` + `drug_product_prices` 신설 + 기존 products/prices 데이터 이관(마스터 dedup) | 병행 |
| DR-2 | `company_drug_products` + `company_drug_product_commission_rates` 신설 + 제약사별 취급/수수료 이관 | 병행 |
| DR-3 | 읽기 경로 전환 (목록/상세/검색 = 새 테이블), 구 products 읽기 중단 | |
| DR-4 | **실적/정산 참조 재배선** (`performances` → company_drug_product), Resolver/Builder 수정 | 최고 위험 |
| DR-5 | 거래처 예외단가 재배치 + CSV import/Excel/보고서 갱신 | |
| DR-6 | 회귀 테스트(이관 정합성·실적 스냅샷 동등성) + 구 `products` 계열 폐기 | |

> 각 단계 `sail test` 통과 + 데이터 이관은 dry-run→검증→commit.

---

## 6. 결정 (전체 확정 — 2026-05-29)

- **D-1 마스터 출처**: ✅ **(a) 기존 `products` 마스터 컬럼 기반 + `health_individual_drugs`(심평원)로 보강**. 운영 데이터 보존 + 표준코드/허가정보 심평원 매칭.
- **D-2 수수료 모델**: ✅ **등급 매트릭스(A~E) 유지 + 소유를 `company_drug_product`로 이전**. 정진팜은 거래처 등급 기반 정산이라 매트릭스 필수(단일율 전환 시 정산 로직 붕괴). `company_drug_product_commission_rates`가 등급별 율(rate_a~e)·base_month·effective range 보유.
- **D-3 거래처 예외단가**: ✅ **`company_product_overrides` → (company_id, `company_drug_product_id`) 재배치, 기능 유지**. 거래처×취급품 단위.
- **D-4 실적 참조 단위**: ✅ **(a) `performances.company_drug_product_id`** (제약사 취급품 참조). tenant_id 도 여기서 도출, 수수료 연결 자연스러움. (스냅샷 가격/수수료는 기존대로 실적 행에 고정.)
- **D-5 착수 시점**: ✅ **GAP-10 멀티테넌시 잔여(MT-8/마스터 CRUD/NOT NULL) 이후 착수**. products tenant_id 작업과 충돌 방지.

---

## 7. 진행 현황 (Progress Log)

| 단계 | 상태 | 비고 |
|---|---|---|
| DR-0 설계 | 🟢 완료 | 2026-05-29. D-1~D-5 전체 확정(§6) |
| DR-1~6 | ⚪ 대기 | **GAP-10 안정화 후** 착수 (D-5) |

---

**문서 버전**: 1.0
**작성일**: 2026-05-29
**상태**: 🟢 설계 확정 (D-1~D-5) — 착수는 **GAP-10 멀티테넌시 잔여 완료 후**(D-5).
