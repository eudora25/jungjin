# 월간 보고서 (Monthly Report) — 설계·스펙 문서 (GAP-6)

> 거래처·영업사원·제품 기준 월간 요약 리포트(Excel) 기능의 설계·결정 문서입니다.
> 요구사항 배경은 [`PRODUCT_SPEC.md`](../../planning/PRODUCT_SPEC.md) §4.14, 작업 단위는 [`ROADMAP.md`](../../planning/ROADMAP.md) §2.4 GAP-6 참조.

---

## 0. TL;DR

- admin이 기간을 선택해 **3종 요약 리포트**(거래처별 / 영업사원별 / 제품별)를 화면에서 미리보고 **Excel(1파일 3시트)** 로 내려받는다.
- 집계 기준 데이터는 **승인된 실적**(`performances.status = 'approved'`)뿐이다 — 정산과 동일한 확정 수치.
- 별도 테이블 없이 `performances` 의 가상 컬럼(`subtotal`, `commission_amount`)을 `selectRaw + groupBy` 로 합산한다 (GAP-3 `CommissionSummaryService` 패턴 재사용).

---

## 1. 확정 결정사항 (GAP-6-1, 2026-05-27)

| 항목 | 확정값 | 비고 |
|---|---|---|
| **기준 상태** | `status = 'approved'` 만 | 정산과 동일 기준. 미승인/반려 제외. 영업사원 '승인률' 은 이번 범위에서 제외 |
| **기간 단위** | 단일 월(`YYYY-MM`, 기본) **+** 기간 범위(`from`~`to`) 둘 다 | 기준 컬럼은 `performance_date`. 기본값 = 당월 |
| **Excel 형태** | **1개 파일 + 3개 시트** | 시트: `거래처별`, `영업사원별`, `제품별`. `SettlementExcelExporter` 패턴 확장 |
| **제품 MoM** | 미포함 | 전월 대비 증감은 후속 과제로 보류 (현재 기간 순위만) |

---

## 2. 집계 기준 (3종 공통)

- **대상 모집단**: `performances WHERE status = 'approved' AND performance_date BETWEEN :from AND :to`
- **측정값**:
  | 측정값 | 계산 |
  |---|---|
  | 라인 수 | `COUNT(*)` |
  | 총 수량 | `COALESCE(SUM(quantity), 0)` |
  | 매출액 | `COALESCE(SUM(subtotal), 0)` |
  | 수수료액 | `COALESCE(SUM(commission_amount), 0)` |
  | 평균 수수료율(%) | 매출액 > 0 → `수수료액 / 매출액 × 100`, 아니면 0 (소수 1자리) |
- **합계 행**: 각 시트 하단에 전체 합계. 평균 수수료율은 **합계 매출액 기준으로 재계산**(단순 평균 아님).
- **숫자 포맷**: 금액은 원 단위 정수 + 천단위 콤마, 수수료율은 소수 1자리.

---

## 3. 리포트별 컬럼 정의

### 3.1 거래처별 요약 (시트: `거래처별`)

| # | 컬럼 | 출처 |
|---|---|---|
| 1 | 거래처명 | `company.company_name` |
| 2 | 파트너 유형 | `company.partner_type` (한글 매핑) |
| 3 | 라인 수 | 집계 |
| 4 | 총 수량 | 집계 |
| 5 | 매출액 | 집계 |
| 6 | 수수료액 | 집계 |
| 7 | 평균 수수료율(%) | 집계 |

- group by `company_id` · 정렬: **매출액 desc**

### 3.2 영업사원별 요약 (시트: `영업사원별`)

| # | 컬럼 | 출처 |
|---|---|---|
| 1 | 영업사원명 | `creator.name` (`created_by`) |
| 2 | 라인 수 | 집계 |
| 3 | 총 수량 | 집계 |
| 4 | 매출액 | 집계 |
| 5 | 수수료액 | 집계 |
| 6 | 평균 수수료율(%) | 집계 |

- group by `created_by` (NULL 은 `(미지정)` 으로 묶음) · 정렬: **수수료액 desc**
- ⚠️ '승인률' 컬럼은 approved-only 결정으로 **이번 범위 제외**. 추후 전체 상태 집계 도입 시 추가.

### 3.3 제품별 요약 (시트: `제품별`)

| # | 컬럼 | 출처 |
|---|---|---|
| 1 | 제품명 | `product.product_name` |
| 2 | 보험코드 | `product.insurance_code` |
| 3 | 제조사 | `product.manufacturer` |
| 4 | 라인 수 | 집계 |
| 5 | 총 수량 | 집계 |
| 6 | 매출액 | 집계 |
| 7 | 수수료액 | 집계 |

- group by `product_id` · 정렬: **매출액 desc**
- MoM(전월 대비) 컬럼 미포함.

---

## 4. 시트 공통 헤더 (각 시트 상단)

```
[리포트 제목]            예) 월간 보고서 — 거래처별 요약
기간: 2026-05-01 ~ 2026-05-31   (또는 2026-05)
기준: 승인 완료 실적(approved)
생성일시: 2026-05-27 14:00
생성자: 홍길동(admin)
```

---

## 5. 권한 · 라우트 · Audit

- **권한**: admin 전용. sales 접근 시 403. (`Gate` 또는 라우트 `role:admin` 미들웨어 — GAP-3 패턴 따름)
- **라우트**:
  - `GET /reports/monthly` — Inertia 페이지 + 3종 미리보기 prop
  - `GET /reports/monthly/export.xlsx?from=&to=` — 1파일 3시트 다운로드
- **입력 검증**: `from`/`to` 는 `date`, `from <= to`. 월 입력 시 서버에서 해당 월 1일~말일로 변환. 기본값 = 당월.
- **Audit**: 내보내기 시 activity log 이벤트 `report.monthly.export` 기록 — properties `{ from, to }`.

---

## 6. 화면 구성 (GAP-6-4)

- `/reports/monthly` (admin)
  - 상단 필터: 월 선택(기본) ↔ 기간 직접 입력 토글 + 조회 버튼 + "Excel 다운로드" 버튼
  - 본문: 탭 3개(거래처별 / 영업사원별 / 제품별) — 각 탭에 미리보기 DataTable(합계 행 포함)
  - 미리보기는 페이지 prop 로 서버에서 동일 집계 결과 전달(Excel 과 수치 일치 보장)

---

## 7. 구현 매핑 (작업 단위 → 산출물)

| 단위 | 산출물 | 상태 |
|---|---|---|
| GAP-6-1 | 본 문서 (스펙 확정) | ✅ |
| GAP-6-2 | `App\Services\MonthlyReportService`(집계 3종 + `totals`) + `App\Http\Controllers\ReportsController@monthly` + 라우트 + 미리보기 prop + `MonthlyReportPolicy`/Gate | ✅ |
| GAP-6-3 | `App\Services\MonthlyReportExcelExporter`(1파일 3시트) + `ReportsController@exportMonthly` + activity log `report.monthly.export` | ✅ |
| GAP-6-4 | `resources/js/Pages/Reports/Monthly.vue` (필터 + 합계 카드 + 3탭 미리보기 + 다운로드) + 메뉴 항목 | ✅ |
| GAP-6-5 | `tests/Feature/Reports/MonthlyReportTest.php` (12 cases — 집계 정확성·정렬·합계·미승인 제외·기간 필터·Excel·activity log + admin-only) | ✅ |

---

## 8. 진행 로그

- **2026-05-27**: GAP-6-1 스펙 확정 — 기준 상태 approved만 / 기간 월+범위 둘 다 / Excel 1파일 3시트 / 제품 MoM 미포함. (결정 근거: 정산과 동일 확정 수치 우선, 운영 월말 보고 편의)
- **2026-05-27**: GAP-6-2~6-5 구현 완료 — `MonthlyReportService`/`MonthlyReportExcelExporter`/`ReportsController` + `MonthlyReportPolicy`(Gate `view-monthly-report`/`export-monthly-report`) + `Reports/Monthly.vue`(3탭) + 정산 메뉴 항목. `MonthlyReportTest` 12/12 PASS · 전체 270/270 PASS. 집계는 GAP-3 `CommissionSummaryService` 패턴(FK groupBy 후 이름 별도 로드)을 재사용해 join 컬럼 모호성 회피.
