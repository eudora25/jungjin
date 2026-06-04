# 병의원 행안부(MOIS) 공공데이터 API 증분 동기화 설계

> 상위: [`CLIENT_MANAGEMENT.md`](CLIENT_MANAGEMENT.md) · 선행: [`HOSPITAL_PUBLIC_DATA.md`](HOSPITAL_PUBLIC_DATA.md)
> 목적: 행정안전부 **공공데이터포털(data.go.kr) 표준데이터 OpenAPI** 로 병의원 인허가 데이터의 **변경분(신규·변경·폐업)** 만 주기적으로 수신해 `hospitals` 마스터에 **건건 upsert** 한다.
> 상태: **🟢 R1~R9 구현 완료** (2026-06-04, 실데이터 검증 통과·이력 UI 포함) — 운영 활성화(`MOIS_SYNC_ENABLED=true`)만 남음
> 결정(2026-06-04): 수신 방식 = **변경분 API 증분 동기화** / 진행 = **설계 문서 우선** → 구현 완료

---

## 0. 검증된 참조 구현 — Pample_renewal_project

> ⚠️ **v0.1 정정**: 초안은 `localdata.go.kr` REST API(authKey·opnSvcId·pageIndex…)를 가정했으나 **실제 API가 다름**. 사내 **Pample_renewal_project(Spring Boot)** 에 동일 기능이 이미 **구현·테스트 완료(62 통과)** 되어 있어, 그 실제 API·필드·매핑을 본 설계의 근거로 채택한다.

- 참조 경로: `/Users/hoony/Desktop/dev-work/Pample_renewal_project`
- 핵심 문서: `docs/medical-institution-sync/README.md`, `backup/plans/plan-mois-clinic-sync.md`, `backup/runtime/mois-clinic-to-medical-institution.md`
- 핵심 소스(Java, 패키지 `*/medicalinstitution/mois/`):
  - `MoisClinicApiAdapter`(RestClient 호출·`cond[]` 파라미터·평탄화), `MoisClinicItemToMedicalInstitutionMapper`(필드 매핑)
  - `IncrementalSyncMoisClinicUseCase` / `BackfillMoisManagementNoUseCase`, `MoisClinicIncrementalSyncScheduler`
  - `Epsg5174ToWgs84Converter`(proj4j), 코드 매퍼 3종(BizStatus/InstitutionType/Municipality)

**Jungjin은 PHP/Laravel** 이므로 코드 포팅이 아니라 **아키텍처·API 사양·매핑 규칙을 차용**해 재구현한다.

### Pample 대비 Jungjin 의 결정적 차이 (작업 단순화)
- Pample 는 기존 행이 심평원 `institution_sign` 키라 `management_no` 가 비어, **이름+주소 퍼지 매칭 백필**이 필요했다.
- **Jungjin 은 이미 `hospitals.hospital_code = 관리번호(MNG_NO)`** 가 CSV(A)로 채워져 있다 → **퍼지 백필 불필요**, 처음부터 **`hospital_code` 기준 직접 upsert** 가능. (미존재 관리번호는 신규 INSERT)

---

## 1. 배경 — 현재 무엇이 있고, 무엇이 없는가

| 구분 | 현황 |
|---|---|
| 행안부 인허가(A) 적재 | ✅ **CSV 파일 업로드** (`HospitalImportService`, 관리번호=`hospital_code` upsert) |
| 심평원(HIRA) 보강(B) 적재 | ✅ Excel 업로드 → 큐 |
| **외부 API 호출 수신** | ❌ 없음 — `Http`/Guzzle 미사용, API 키·엔드포인트 config·.env 없음 |
| **변경분(증분) 처리** | ❌ 없음 — 매번 전체 CSV 수동 내려받아 통째 업로드 |

본 설계는 여기에 **MOIS API 자동 증분 수신** 경로를 더한다. 기존 CSV 업로드 경로는 **유지**(초기 적재·폴백).

### 재사용 자산 (Jungjin 내부)
- **매핑·upsert 키**: `HospitalImportService::buildRow()/updateColumns()/mapStatus()/mapHospitalType()` — 관리번호 기준 upsert. → **공용 매퍼로 추출**해 API 경로와 공유(§3-1).
- **이력 테이블**: `hospital_public_data_imports`(status·report JSON·error) 패턴.
- **큐 잡**: `ProcessHospitalPublicDataImport`(상태 전환·타임아웃).
- **코드값**: `code_definitions`(group_code/code/name) — 영업상태·종별·시군구 매핑에 활용(§4-3).
- **공유 마스터 정책**: `hospitals` 는 platform 소유 공유 마스터(MT-8) → 동기화는 platform 컨텍스트, 테넌트 스코프 없음.

---

## 2. MOIS 공공데이터 API 사양 (Pample 검증값)

- 제공처: **공공데이터포털 data.go.kr** — 행안부 표준데이터(예: "행정안전부_건강_의원 조회서비스").
- Base URL(의원): `https://apis.data.go.kr/1741000/clinics` · 엔드포인트: `GET /info`
- 인증: **`serviceKey`** 쿼리 파라미터(발급 시 이미 URL-encoded). 로그에는 마스킹.

### 2-1. 대상 데이터셋 ID·Base path (업종별 — Pample `application.yml` 검증값)
| 업종 | API ID | Base URL | 검증 totalCount | 비고 |
|---|---|---|---|---|
| **의원** | `15154874` | `https://apis.data.go.kr/1741000/clinics` | ~124,846 | Pample 1차 구현 완료 |
| **병원** | `15154458` | `https://apis.data.go.kr/1741000/hospitals` | 8,276 | 현재 키로 호출 정상 (추가 필드 MNATH/LCTN_AREA 미매핑) |
| **부속의료기관** | `15154643` | `https://apis.data.go.kr/1741000/affiliated_medical_institutions` | 490 | 응답 29필드(의원 30 − `BZSTAT_SE_NM`) |
| 약국 | `15154822` | (pharmacies) | 미확인 | **별도 활용신청 필요**(현재 키 403) — 본 설계 범위 밖(`pharmacies` 후속) |

> ✅ **미결 §6-1 해소**: 업종별 Base path는 `/clinics`·`/hospitals`·`/affiliated_medical_institutions` 로 확정. 공통 엔드포인트는 `/info`.

### 2-2. 요청 파라미터
| 파라미터 | 예 | 설명 |
|---|---|---|
| `serviceKey` | (URL-encoded 키) | 인증키 — `.env` 비밀 |
| `pageNo` | 1~ | 페이지 번호(1부터) |
| `numOfRows` | **≤ 100** | 페이지 크기 (CSV의 500과 다름) |
| `returnType` | `json` | 응답 형식 |
| `cond[LAST_MDFCN_PNT::GTE]` | `yyyyMMddHHmmss` | **최종수정일시 ≥ — 증분 시작** |
| `cond[LAST_MDFCN_PNT::LT]` | `yyyyMMddHHmmss` | 최종수정일시 < — 증분 종료 (반열린 구간) |
| `cond[SALS_STTS_CD::EQ]` | `01`/`03` | (옵션) 영업상태 필터 |
| `cond[OPN_ATMY_GRP_CD::EQ]` | 7자리 | (옵션) 자치단체코드 |
| `cond[LCPMT_YMD::GTE/LT]` | `yyyyMMdd` | (옵션) 인허가일자 범위(백필용) |

- `cond[KEY::OP]=VALUE` 형식. URL에서 `[`/`]` 는 `%5B`/`%5D` 인코딩, KEY·VALUE 도 인코딩.
- 응답 wrapper: `response.header{resultCode,resultMsg}` + `response.body{totalCount,pageNo,numOfRows,items.item[]}`. `resultCode="0"` 만 성공, 그 외 예외.

### 2-3. 응답 필드 → `hospitals` 매핑 (검증된 실제 필드명)
| MOIS 필드 | 의미 | `hospitals` 컬럼 | 처리 |
|---|---|---|---|
| `MNG_NO` | 관리번호 | `hospital_code` (**upsert 키**) | = 기존 CSV 관리번호 |
| `BPLC_NM` | 사업장명 | `hospital_name` | emptyToNull·trim |
| `ROAD_NM_ADDR` → `LOTNO_ADDR` | 도로명/지번주소 | `address` | 도로명 우선 |
| `ROAD_NM_ZIP` → `LCTN_ZIP` | 도로명/소재지우편번호 | `postcode` | 도로명 우선 |
| `TELNO` | 전화번호 | `phone` | |
| `MDLCR_INST_BTP_NM` | 의료기관종별명 | `hospital_type` | `mapHospitalType()` 재사용 |
| `SALS_STTS_NM` → `SALS_STTS_CD` | 영업상태명(우선)/코드(01영업) | `status` | 코드 매핑(§4-3). ⚠️ **R7 정정**: `BZSTAT_SE_NM` 은 영업상태가 아니라 종별명이라 미사용 |
| `LCPMT_YMD` | 인허가일자 | `opened_on` | `yyyy-MM-dd` 파싱 |
| `CLSBIZ_YMD` | 폐업일자 | `closed_on` | |
| `TCBIZ_BGNG_YMD` / `TCBIZ_END_YMD` | 휴업시작/종료 | `suspend_begin_on` / `suspend_end_on` | |
| `MDEXM_SBJCT_CN_NM` | 진료과목명 | `specialty`(레거시 단일) | 첫 과목; 다중은 B(HIRA) |
| `CRD_INFO_X` / `CRD_INFO_Y` | 좌표 | `longitude` / `latitude` | **EPSG:5174→WGS84 변환**(§4-4) |
| `LAST_MDFCN_PNT` | 최종수정시점 | `source_synced_at` + 커서 | 증분 기준. ⚠️ **R7 정정**: 응답은 `yyyy-MM-dd HH:mm:ss`(구분자 포함) → 커서/cond 용 14자리(`yyyyMMddHHmmss`)로 정규화 |
| `DAT_UPDT_SE` | 변경구분 **I/U/D** | (로직) | §5 신규/변경/**폐업** |

> ⚠️ 응답 날짜는 `yyyy-MM-dd`(요청은 `yyyyMMdd`). 빈 문자열은 NULL 대신 빈값으로 오므로 **일괄 `emptyToNull`**.
> ✅ **식별자 일치 해소**: Pample 문서가 "MOIS API와 LOCALDATA CSV 는 같은 행안부 원천"임을 명시 → `MNG_NO == 관리번호 == hospital_code`. v0.1 미결 §6-2 사실상 확정(실데이터 1회 교차 확인만 남음 §9-R7).

---

## 3. 아키텍처 (수신 → 매핑 → 적재 → 이력)

```
[Scheduler 일배치 04:00]─┐
[platform 수동 트리거]   ┘→ SyncHospitalMoisJob (큐)
                              ├─ HospitalMoisSync 이력행(processing)
                              ├─ 커서 조회: 업종별 last_synced_at  (없으면 D-2 lookback)
                              └─ for each 업종(API ID):
                                   HospitalMoisApiClient::fetchPage(id, from, to, pageNo, 100)
                                     │  (Http::retry, cond[LAST_MDFCN_PNT] 범위, totalCount 순회)
                                     ▼
                                   HospitalMoisSyncService::apply(items)
                                     │  HospitalRowMapper (CSV와 공용) + emptyToNull + 좌표변환
                                     │  DAT_UPDT_SE 분기 → I/U=upsert(['hospital_code']) · D=폐업
                                     ▼
                                   커서 전진(max LAST_MDFCN_PNT) + outcome 카운트
                              └─ 이력행 completed + report{업종:{fetched,inserted,updated,closed,skipped,failed}}
```

- **건건 = 행 단위 처리**. 멱등(재실행 동일 결과). 부분 실패 격리(업종/페이지 단위 try-catch → report 기록).
- **D-2 lookback**: 행안부는 D-2 자정 기준 갱신 → `from = (오늘-2일) 00:00`, `to = (오늘-1일) 00:00`(반열린). 첫 실행/커서 없음도 동일 기본.
- 페이징: `numOfRows=100`, `totalCount`로 전 페이지 순회. 페이지 간 `usleep`(쿼터 보호).

---

## 4. 구성 요소

### 4-1. 공용 매퍼 추출 — `HospitalRowMapper` (리팩터링)
- 현 `HospitalImportService` 의 `buildRow/updateColumns/mapStatus/mapHospitalType/parseDate/parseInt/parseDecimal` 을 **`HospitalRowMapper`** 로 이동.
- "논리 키" 연관배열을 입력받게 해 **CSV(한글 헤더)** 와 **API(영문 필드)** 가 각자 어댑터로 논리 키 매핑 후 동일 매퍼 호출 → 상태·일자·타입 규칙 1곳.

### 4-2. `HospitalMoisApiClient` (신규, `app/Services/Clients/`)
- `fetchPage(string $apiId, ?string $from, ?string $to, int $pageNo, int $size=100): array` — `Http::baseUrl()->retry(3,200)->get('/info', [... 'cond[LAST_MDFCN_PNT::GTE]'=>$from ...])` + 평탄화 + `resultCode` 검사.
- Laravel `Http` 은 배열 쿼리를 인코딩하므로 `cond[...]` 키를 문자열 그대로 전달(인코딩 처리 검증). 테스트는 `Http::fake()`.

### 4-3. `HospitalMoisSyncService` (신규)
- `syncAll(?int $userId): HospitalMoisSync` — 업종 순회·페이징·매핑·DAT_UPDT_SE 분기·upsert·커서·report.
- **코드 매핑**(`code_definitions` 활용 권장):
  - `SALS_STTS_CD`(01→영업/03→폐업…) → `status` active/inactive (현 `mapStatus` 보강 또는 code_definitions group `mois_biz_status`)
  - `MDLCR_INST_BTP_NM` → `hospital_type`(현 `mapHospitalType` 재사용)
  - (옵션) `OPN_ATMY_GRP_CD` → 시도/시군구코드
- platform 컨텍스트 실행(테넌트 스코프 없음, 공유 마스터 쓰기 허용).

### 4-4. 좌표 변환 — EPSG:5174 → WGS84
- Pample: `proj4j`(Java). **Jungjin: `proj4php`(`proj4php/proj4php`) 도입** 또는 동등 변환.
- 변환 실패·한반도 범위(위도 33~39°, 경도 124~132°) 밖이면 `latitude/longitude = NULL`(현 CSV 정책과 정합 — A 좌표 미저장과 달리 API는 변환 채움).

### 4-5. `SyncHospitalMoisJob` + Artisan + Scheduler
- 잡: `ProcessHospitalPublicDataImport` 패턴(상태·`finished_at`·error·타임아웃).
- 명령: `hospitals:sync-mois {--since=YYYYMMDD} {--svc=*} {--dry-run}` (`--dry-run`=분류 카운트만).
- Scheduler(`routes/console.php`): 일 1회(예 04:30, HIRA와 시각 분리) 디스패치. 기본 비활성 플래그(`config`) 후 운영 검증 뒤 활성.

### 4-6. Platform UI — 🟢 완료 (R8)
- `/platform/hospitals/mois-sync` — 업종별 커서·스케줄 활성여부·"지금 동기화"(업종 선택·dry-run 토글)·이력 테이블(업종별 +신규/~변경/✕폐업/=스킵). `HospitalMoisSyncController`(index/store, isPlatform 가드) + 큐 디스패치(trigger=manual). 메뉴 "플랫폼 / 병의원 API 동기화(MOIS)".

---

## 5. 신규/변경/폐업 처리 (DAT_UPDT_SE)

| `DAT_UPDT_SE` | 의미 | 처리 |
|---|---|---|
| `I` | 신규 | `hospital_code` 미존재 → INSERT (존재하면 변경감지 후 UPDATE/SKIP) |
| `U` | 변경 | `hospital_code` 매칭 → 변경 필드 비교, 다르면 UPDATE / 같으면 SKIP |
| `D` | 삭제(폐업) | `status=inactive`(폐업) + `closed_on` 채움. **물리/소프트 삭제 금지**(실적 FK 보존) |

- **변경 감지(SKIP 최적화)**: `hospital_name,address,postcode,phone,opened_on,closed_on,suspend_*,latitude,longitude,hospital_type,status` 비교 → 전부 동일이면 SKIP(불필요 쓰기·audit 방지).
- **커서 전진**: 해당 실행에서 본 행들의 `max(LAST_MDFCN_PNT)` 를 업종 커서에 저장. 안전 마진(중복 수신은 upsert로 무해).

---

## 6. 데이터 모델

### 6-1. 동기화 이력 — `hospital_mois_syncs` (신규)
`hospital_public_data_imports` 컨벤션(테이블+모든 컬럼 comment, status enum, report JSON).

| 컬럼 | 타입 | 설명 |
|---|---|---|
| `id` | PK | |
| `created_by` | FK users nullable | 트리거 운영자(스케줄=null) |
| `trigger` | string(20) | `schedule`/`manual` |
| `params` | json nullable | since·업종목록 |
| `status` | string(20) default pending | pending/processing/completed/failed |
| `report` | json nullable | 업종별 fetched/inserted/updated/closed/skipped/failed |
| `error` | text nullable | 실패 메시지 |
| `started_at`/`finished_at` | timestamp nullable | |
| timestamps | | |

### 6-2. 업종별 커서 — `hospital_mois_cursors` (신규)
| 컬럼 | 타입 | 설명 |
|---|---|---|
| `api_id` | string(20) **unique** | 업종 API ID(15154874 등) |
| `last_synced_at` | string(14) nullable | 마지막 반영 `LAST_MDFCN_PNT` |
| `last_sync_id` | FK syncs nullable | 마지막 성공 동기화 |
| timestamps | | |

### 6-3. `hospitals` — 스키마 변경 없음
기존 공공데이터 컬럼 재사용. **마이그레이션 불필요**(매핑만 추가).

---

## 7. 설정 (.env / config) — 인증키 확보됨

✅ **인증키 보유 확인**(2026-06-04, Pample 프로젝트):
- **data.go.kr serviceKey** — Pample `application.yml` 의 `health-affiliated-medical-institution.api-key` 에 **실제 64자 hex 키가 하드코딩**되어 있음. data.go.kr은 **계정당 단일 serviceKey가 활용신청한 모든 API(clinics·hospitals·affiliated)에 공통** 동작 → 이 키를 **clinics/hospitals 동기화에 그대로 재사용 가능**(인코딩/디코딩 형 유의).
- (참고) **localdata.go.kr REST 키** — Pample `.env` `LOCAL_DATA_API_KEY`(44자, 실제값)도 별도 보유. 단 이는 다른 포털(`localdata.go.kr`)용으로 **MOIS data.go.kr API에는 미사용**.
- ⚠️ **보안**: Pample은 키가 yml에 **커밋**되어 있음(노출). **Jungjin은 절대 커밋 금지** — `.env` 비밀로만 주입(OPS-4). 운영 전 **키 회전(재발급) 권장**.

`config/services.php` 또는 신규 `config/mois.php`, 값은 `.env`:
```
MOIS_API_KEY=...                         # data.go.kr serviceKey (Pample 보유키 재사용 또는 재발급)
MOIS_API_NUM_OF_ROWS=100
MOIS_SYNC_LOOKBACK_DAYS=2
MOIS_SYNC_ENABLED=false                  # 검증 후 활성
# 업종별 base는 config 배열로: clinics=15154874, hospitals=15154458, affiliated_medical_institutions=15154643
```

---

## 8. 테스트 계획 (Pest, 테스트 먼저)

- `HospitalMoisApiClientTest` — `Http::fake()` 페이징·`cond[]` 쿼리 구성·`resultCode≠0` 예외.
- `HospitalRowMapperTest` — 한글헤더(CSV)/영문필드(API) 두 입력이 **동일 payload**·`emptyToNull`·날짜 파싱.
- `HospitalMoisSyncServiceTest` — I→insert / U→update / **D→폐업(status=inactive+closed_on)** / 변경없음 SKIP / **멱등(2회 동일)** / 커서 전진(max LAST_MDFCN_PNT) / 업종 부분실패 격리.
- 좌표: EPSG:5174 샘플 → WGS84 한반도 범위 검증, 범위 밖 NULL.
- `hospitals:sync-mois` `--dry-run` upsert 없음 / 정상 이력 completed / `role:platform` 외 차단(UI 채택 시).
- 기존 `HospitalImportService` CSV 테스트 — 매퍼 추출 후 **회귀 그린 유지**.

---

## 9. 작업 목록

- [x] **R1** `HospitalRowMapper` 추출 + `HospitalImportService` 가 사용(CSV 테스트 그린) — `75a04f9`
- [x] **R2** `config/mois.php` + `.env` + `HospitalMoisApiClient`(`Http::fake` 6) — `dda958d`
- [x] **R3** `proj4php` 도입 + `Epsg5174ToWgs84`(5) — `2ad8f22`
- [x] **R4** `hospital_mois_syncs`·`hospital_mois_cursors` 마이그레이션 + 모델(comment, 3) — `3f1da4b`
- [x] **R5** `HospitalMoisSyncService`(업종 순회·페이징·DAT_UPDT_SE·upsert·SKIP·커서·report·격리, 8) — `fffc9b0`
- [x] **R6** `SyncHospitalMoisJob` + `hospitals:sync-mois` + Scheduler(04:30·비활성 플래그, 4) — `9a511b1`
- [x] **R7** 실데이터 증분 검증 — `MNG_NO==hospital_code` 확정(6/2 변경분 97건 중 80 update·17 insert). 상태필드(`SALS_STTS_NM`)·커서 형식 버그 정정(회귀 2) — `13e0d46`
- [x] **R8** Platform 이력·업종 커서·수동 트리거(업종 선택·dry-run) UI(`/platform/hospitals/mois-sync`) + 메뉴 + 권한 테스트 4 — `1a79992`
- [x] **R9** ROADMAP/설계문서/CLIENT_MANAGEMENT 진행 로그 반영

---

## 10. 미결 결정 (해소 현황)

> R1~R7 구현·검증으로 대부분 해소. 운영 활성화·코드 매핑 고도화만 남음.

1. ~~인증키 운영 방침~~ — 🟢 **재사용**(Pample serviceKey, `.env MOIS_API_KEY`, 커밋 금지). R7 실호출 정상.
2. **코드 매핑 저장**(§4-3) — 🟢 현 단계는 `HospitalRowMapper.mapStatus/mapHospitalType` 재사용 + `resolveStatusRaw`(SALS_STTS_NM/CD). `code_definitions` 그룹화는 필요 시 후속.
3. ~~좌표 라이브러리~~ — 🟢 **`proj4php` 채택**, EPSG:5174 정의(towgs84 미적용, Pample 일치) 등록. 골든 케이스 검증.
4. **실행 주기·활성화** — 🟢 스케줄 04:30 등록(HIRA와 분리). ⏳ 운영 검증 후 `MOIS_SYNC_ENABLED=true` (현재 비활성).
5. **부속의료기관 type** — `MDLCR_INST_BTP_NM` → `mapHospitalType` 재사용(의원/병원과 동일 규칙).

### R7 실데이터 검증 결과 (2026-06-04)
- **연결**: clinics 정상(totalCount 5,321/월), 인증키 동작.
- **식별자**: `MNG_NO == hospital_code` **확정** — 기존 master 131,177건이 25자리 PHMA 형식(API와 동일·구조 세그먼트 `041100` 공유). 6/2 변경분 97건 중 **80 update + 17 insert**, 중복 없음.
- **정정된 가정**: ① 영업상태는 `SALS_STTS_NM`(없으면 `SALS_STTS_CD`) — `BZSTAT_SE_NM` 은 종별명이었음. ② `LAST_MDFCN_PNT` 응답은 구분자 포함 → 14자리 정규화.
- **잔여 리스크(낮음)**: 동일 의원이 master/API 간 다른 코드를 갖는 예외는 미발견이나, 최초 운영 수회는 중복(inserted) 비율 모니터링 권장.

---

## 11. ROADMAP 연계

§2.4 공공데이터 적재(HIRA)의 **후속 확장** — "수동 업로드 → API 증분 자동화". 신규 GAP-ID(예 **GAP-12 행안부 MOIS API 증분 동기화**, P2·M) 부여 후 §2.4 등록 예정. cutover(OPS-7) 전 마스터 신선도 확보에 기여. **Pample 검증 구현 차용으로 위험 낮음**.

---

**문서 버전**: 1.1 (R1~R9 구현·실데이터 검증·이력 UI 반영)
**작성일**: 2026-06-04
**상태**: **🟢 R1~R9 구현 완료** — 실데이터 검증 통과(테스트 34, 전체 454 PASS). 운영 활성화(`MOIS_SYNC_ENABLED=true`)만 남음
