# 기준정보 마스터 관리 (병의원·약국·의약품) — 설계 문서

> **GAP-9**. 병의원·약국·의약품을 **거래처(Company)와 독립된 기준정보 마스터**로 명확히 분리해 admin이 단독 관리하도록 정비하는 작업의 단일 설계 문서입니다.
> 진행/백로그는 [`docs/planning/ROADMAP.md`](../../planning/ROADMAP.md) **GAP-9** 로 통합 관리합니다.
> 관련 기존 문서: [`CLIENT_MANAGEMENT.md`](../client/CLIENT_MANAGEMENT.md)(약국·병원 마스터), [`PRODUCT_MANAGEMENT.md`](../product/PRODUCT_MANAGEMENT.md)(의약품/제품 마스터).

---

## 0. 배경 — 왜 분리하는가

현재 병의원·약국·의약품 마스터는 기능(테이블·CRUD·Policy·Import)이 이미 구현되어 있으나, **화면 구조(IA)와 메뉴 배치가 거래처와 뒤섞여** 있어 "독립 기준정보"라는 성격이 드러나지 않는다.

| 마스터 | **이전** 메뉴 위치 (GAP-9 이전) | 라우트 | 거래처 종속 여부 |
|---|---|---|---|
| 의약품(제품) | `기준 정보 > 제품 관리` | `/products` | 독립 (company 무관) |
| 약국 | `기준 정보 > 클라이언트 > 약국` | `/pharmacies` | 독립 (`company_id` nullable·선택 링크) |
| 병의원 | `기준 정보 > 클라이언트 > 병원` | `/hospitals` | 독립 (`company_id` nullable·선택 링크) |
| (참고) 거래처/업체 | `기준 정보 > 업체 관리` | `/companies` | — (실적·정산의 정산 단위) |

**문제 인식**
- 약국·병원이 "클라이언트" 하위에 묶여 있어 거래처(업체)와의 경계가 모호.
- 의약품(제품)은 별도 위치에 있어 "마스터 3종"이 한 묶음으로 보이지 않음.
- admin이 기준정보를 한 곳에서 관리한다는 운영 동선이 없음.

**목표**
- 병의원·약국·의약품을 **"기준정보 마스터"** 라는 단일 묶음으로 분리·노출.
- 각 마스터는 **거래처와 독립**임을 코드·문서·UI에서 명확히.
- admin 전용 관리 동선 확립 (조회는 기존 권한 유지).

---

## 1. 핵심 설계 원칙

1. **독립 마스터 우선**: 병의원·약국·의약품은 그 자체로 완결적인 기준정보다. 거래처(`companies`)가 없어도 등록·관리된다.
2. **거래처와의 연결은 선택적 링크일 뿐**: 약국·병원의 `company_id`(nullable, unique 1:1)와 `hospital_company_assignments` 등은 **통합/이관/실적 연동을 위한 옵션 링크**이며, 마스터 관리의 전제 조건이 아니다. → 제거하지 않고 "선택 링크"로 명문화.
3. **URL 안정성 유지**: 기존 라우트(`/products`, `/pharmacies`, `/hospitals`)는 **변경하지 않는다**(북마크·테스트·import 안정성). 변경은 IA(메뉴 그룹)와 진입 동선 중심.
4. **권한 일관성**: 쓰기(create/update/delete/import)는 admin 전용 유지. 조회는 기존 정책 유지(로그인 사용자).

---

## 2. 범위 (Scope)

### 2.1 포함 (In)

- **IA 재구성**: 사이드바에 **"기준정보 마스터"** 그룹 신설 — 의약품 / 약국 / 병의원을 1급 항목으로 배치.
  - 기존 "클라이언트" 그룹에서 약국·병원을 이 그룹으로 이동.
  - 영업사원(`/clients/sales`)은 사용자(`users`) 기반 read-only 뷰이므로 **마스터 그룹에 두지 않고** 관리(admin) 영역 또는 별도 유지.
- **마스터 허브(선택)**: `기준정보 마스터` 진입 시 3종 마스터의 건수·바로가기를 보여주는 admin 랜딩 카드(`MasterData/Index.vue`). (경량, 후속 보강 가능)
- **독립성 명문화**: 각 마스터 화면/문서에서 "거래처와 독립" 성격 표기. 약국·병원 상세에 연결된 거래처(있을 때만) "선택 링크"로 표시.
- **문서화**: 본 문서 + ROADMAP GAP-9 + 기존 모듈 문서 상호 링크.

### 2.2 제외 (Out — 별도 항목)

- 약국·병원 CSV/레거시 import 고도화 → 기존 Import 컨트롤러 + OPS-7.
- 실적이 약국·병원을 직접 참조하는 다형 연동(`partner_type`) → CLIENT_MANAGEMENT §5, 도메인 검토 후.
- 의약품 마스터 신규 필드/워크플로 변경 → PRODUCT_MANAGEMENT.
- `company_id` 컬럼 물리 제거 → **하지 않음**(원칙 2).

---

## 3. IA / 메뉴 변경안 (결정 반영)

```
마스터 관리                  ← 신설 그룹 (admin 쓰기 / 로그인 조회)
  ├─ 마스터 홈        /master-data   ← 허브 랜딩(건수·바로가기), admin
  ├─ 의약품 관리      /products
  ├─ 약국 관리        /pharmacies
  └─ 병의원 관리      /hospitals

거래처                       ← 기존 "기준 정보" 그룹을 거래처 중심으로 정리
  └─ 업체 관리        /companies

관리 (admin)
  ├─ 사용자 관리      /users
  ├─ 목표 관리        /sales-quotas
  ├─ 영업사원(조회)   /clients/sales   ← 마스터 그룹에서 이동(사용자 기반 read-only)
  └─ 설정             /profile
```

> 그룹명 **"마스터 관리"** 확정(D-1). "클라이언트" 하위 그룹은 약국·병원 이동 + 영업사원 이동으로 비워지므로 제거.

---

## 4. 영향 파일 (예상)

- `resources/js/layout/AppMenu.vue` — 그룹 재배치 (의약품/약국/병의원 → "기준정보 마스터").
- (선택) `app/Http/Controllers/MasterDataController.php` + `resources/js/Pages/MasterData/Index.vue` — 마스터 허브 랜딩(3종 건수·바로가기).
- (선택) `routes/web.php` — `GET /master-data` (admin) 라우트.
- 약국/병원 `Show.vue` — 연결된 거래처(있을 때) "선택 링크" 표기 보강.
- 기존 컨트롤러·라우트·모델은 **변경 없음**(원칙 3).

---

## 5. 결정 (확정 — 2026-05-29)

- **D-1 메뉴 그룹명**: ✅ **"마스터 관리"**.
- **D-2 마스터 허브 화면**: ✅ **신설** — `GET /master-data`(admin) + 3종 건수·바로가기 카드.
- **D-3 영업사원(조회) 위치**: ✅ **관리(admin) 영역으로 이동**. 마스터 그룹 미포함.
- **D-4 거래처 링크 표기**: ✅ **읽기 표시만** — 약국·병원 상세에서 연결 거래처가 있을 때만 이름 표시(연결/해제 UI 없음).

---

## 6. 진행 현황 (Progress Log)

| 단계 | 제목 | 상태 | 비고 |
|---|---|---|---|
| GAP-9-1 | 설계·범위 확정 (본 문서) | 🟢 완료 | 2026-05-29. D-1~D-4 확정(§5) |
| GAP-9-2 | 메뉴 IA 재구성 (`AppMenu.vue`) | 🟢 완료 | "마스터 관리" 그룹(마스터 홈·의약품·약국·병의원) + "거래처" 그룹 + 영업사원 관리 이동 |
| GAP-9-3 | 마스터 허브 랜딩 (`/master-data`) | 🟢 완료 | `MasterDataController` + `MasterData/Index.vue` 3종 건수·바로가기, admin |
| GAP-9-4 | 약국·병원 상세 거래처 읽기 표시 | 🟢 완료 | show에서 `company` 로드 + 연결 시에만 읽기 링크(선택 링크 안내) |
| GAP-9-5 | 테스트(권한·허브 prop) | 🟢 완료 | `MasterDataHubTest` 3 cases — admin 건수 / sales 403 / 비로그인 redirect. 전체 276 PASS |

### 변경 파일 요약

- [controller] `app/Http/Controllers/MasterDataController.php` (신규)
- [route] `routes/web.php` — `GET /master-data` (admin 그룹) + import
- [menu] `resources/js/layout/AppMenu.vue` — "마스터 관리"/"거래처" 그룹 재편, 영업사원(조회) 관리 영역 이동
- [page] `resources/js/Pages/MasterData/Index.vue` (신규)
- [controller] `Pharmacy/HospitalController::show` — `company:id,company_name` loadMissing
- [page] `Clients/{Pharmacies,Hospitals}/Show.vue` — 연결 거래처 읽기 표시
- [test] `tests/Feature/MasterData/MasterDataHubTest.php` (신규, 3 cases)

---

**문서 버전**: 1.0
**작성일**: 2026-05-29
**최종 갱신**: 2026-05-29 (GAP-9-1~5 완료 — 전체 276/276 PASS)
**상태**: 🟢 완료
