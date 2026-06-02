# 병의원 공공데이터 적재 설계 — 인허가(A) 베이스 + 심평원(B) 보강

> 상위: [`CLIENT_MANAGEMENT.md`](CLIENT_MANAGEMENT.md) (병원 마스터 M4)
> 목적: `docs/data/samples` 의 공공데이터를 **병의원 마스터의 베이스 데이터**로 정규화 적재한다.
> 상태: **구현 1차 완료** (스키마·모델·import 서비스·커맨드·테스트) — 실데이터 적재(대용량 실행)·UI 표시는 후속. (2026-06-01)

---

## 0. 결정 사항 (2026-06-01)

| 항목 | 결정 | 비고 |
|---|---|---|
| 베이스 소스 | **A(인허가 CSV)** 를 마스터로, **B(심평원 Excel)** 로 보강 | 하이브리드 |
| 적재 범위 | **전체 정규화** — B의 12종 Excel 모두 연관 테이블로 적재 | |
| 진행 | **설계 문서 우선** → 리뷰 후 구현 | 본 문서 |

---

## 1. 두 데이터 소스

### 소스 A — 행정안전부 지방행정 인허가 (CSV, EUC-KR)
`docs/data/samples/건강_*.csv`

| 파일 | 행수 | 키 | 비고 |
|---|---|---|---|
| `건강_병원.csv` | 8,316 | `관리번호` (`PHMA…`) | 종합/병원급 + `소재지면적`·`관리주체` 추가 컬럼 |
| `건강_의원.csv` | 124,676 | `관리번호` | 의원/치과/한의원 |
| `건강_부속의료기관.csv` | 490 | `관리번호` | 기업부속의원 등 |
| `건강_약국.csv` | 70,145 | `관리번호` | **약국은 별도(`PharmacyImportService`)** — 본 설계 범위 밖 |

공통 컬럼(병원/의원): `개방자치단체코드, 관리번호, 인허가일자, 인허가취소일자, 영업상태명, 폐업일자,
휴업시작/종료일자, 소재지/도로명우편번호, 사업장명, 업태구분명, 데이터갱신구분/시점, 도로명주소,
병상수, 상세영업상태명/코드, 영업상태코드, 의료기관종별명, 의료인수, 입원실수, 전화번호,
좌표정보(X)(Y), 지번주소, 진료과목내용(코드), 진료과목내용명, 총면적, 최종수정시점`

- **장점**: 인허가/폐업·휴업 일자, 영업상태, 사업장 기준 식별.
- **현재 처리**: `HospitalImportService` 가 A를 import하지만 일부 필드만 저장
  (`관리번호→hospital_code, 사업장명, 종별, 진료과목 첫개, 우편, 주소, 전화, 상태`).
  병상수·의료인수·입원실수·좌표·인허가일자 등은 **버려지고 있음** → 보강 대상.

### 소스 B — 건강보험심사평가원(HIRA) 공공데이터 (Excel, 12종)
`docs/data/samples/전국 병의원 및 약국 현황 2026.3 2/*.xlsx`
**공통 키 = `암호화요양기호`** (base64 형태, 12개 파일을 가로지르는 조인키)

| # | 파일 | 시트 | 행수 | 관계 | 핵심 컬럼 |
|---|---|---|---|---|---|
| 1 | 병원정보서비스 | hospBasisList | 79,562 | **1:1(기본)** | 종별/시도/시군구코드, 개설일자, 의사수(일반/인턴/레지던트/전문 × 의·치·한), 조산사수, 좌표, 홈페이지 |
| 2 | 약국정보서비스 | parmacyBasisList | 25,688 | (약국) | 범위 밖 |
| 3 | 시설정보_01 | …_01 | 105,251 | **1:1** | 설립구분, 병상 상세(상급/일반/중환자/분만/수술/응급/물리치료/정신과/격리/무균) |
| 4 | 세부정보_02 | …_02 | 25,016 | **1:1** | 위치/주차, 휴진(일·공휴일), 응급실 주·야간, 점심/접수시간, **요일별 진료 시작·종료** |
| 5 | 진료과목정보_03 | …_03 | 433,338 | **1:N** | 진료과목코드/명, 과목별 전문의수, 선택진료 의사수 |
| 6 | 교통정보_04 | …_04 | 40,526 | **1:N** | 교통편/노선/하차지점/방향/거리 |
| 7 | 의료장비정보_05 | …_05 | 62,784 | **1:N** | 장비코드/명, 장비대수 |
| 8 | 식대가산정보_06 | …_06 | 15,665 | **1:N** | 유형코드/명, 일반식 가산여부, 산정인원수, 치료식 등급 |
| 9 | 간호등급정보_07 | …_07 | 13,167 | **1:N** | 유형(건보/의료급여), 간호등급 |
| 10 | 특수진료정보_08 | …_08 | 64,630 | **1:N** | 검색코드/명 (HPV 등 사업 참여) |
| 11 | 전문병원지정분야_09 | …_09 | 111 | **1:N** | 지정분야 코드/명 |
| 12 | 기타인력정보_10 | …_10 | 43,966 | **1:N** | 기타인력코드/명/수(약사 등) |

- **장점**: 안정적 키 + 진료과목·장비·병상·진료시간 등 **관계형 디테일**.
- **약점**: 사업자등록번호·인허가/폐업 상태 없음 (→ A가 보완).

### 🔑 핵심 제약: A·B 사이 직접 조인키가 없다
`관리번호`(A) ↔ `암호화요양기호`(B) 를 직접 잇는 컬럼이 **없음**.
→ 하이브리드 연결은 **기관명 + 주소(또는 좌표) 정규화 매칭**으로 수행. (정확도·미매칭 처리 필요 — §4)

---

## 2. `hospitals` 테이블 — 추가 컬럼 (1:1 보강)

기존(유지): `company_id, hospital_code(=관리번호), hospital_name, business_registration_number,
hospital_type, specialty(단일·레거시), representative_name, postcode, address, phone,
contact_*, email, remarks, status, created_by/updated_by`

**신규 마이그레이션** `add_public_data_to_hospitals_table` (모두 nullable, `->after()` 위치 지정):

| 컬럼 | 타입 | 출처 | 설명 |
|---|---|---|---|
| `ykiho` | string(40) unique nullable | B-1 암호화요양기호 | B 연관 데이터 조인키. 매칭 성공 시만 채움 |
| `clazz_code` | string(10) | B-1 종별코드 / A 종별명 | 종별 (의원31/병원 등) |
| `sido_code` | string(10) | B-1 시도코드 | 지역 타겟팅 |
| `sigungu_code` | string(10) | B-1 시군구코드 | |
| `eupmyeondong` | string(50) | B-1 읍면동 | |
| `opened_on` | date | A 인허가일자 / B 개설일자 | A 우선 |
| `closed_on` | date | A 폐업일자 | status 보강 |
| `suspend_begin_on` / `suspend_end_on` | date | A 휴업시작/종료 | |
| `doctor_count` | unsignedInteger | A 의료인수 / B 총의사수 | |
| `bed_count` | unsignedInteger | A 병상수 / B 시설정보 합 | |
| `inpatient_room_count` | unsignedInteger | A 입원실수 | |
| `latitude` / `longitude` | decimal(10,7) | B 좌표 우선, A 보조 | 지도·동선 |
| `homepage` | string | B-1 병원홈페이지 | |
| `total_area` | decimal(12,2) | A 총면적 | |
| `license_authority_code` | string(10) | A 개방자치단체코드 | |
| `source_synced_at` | timestamp | A 데이터갱신시점 | 재적재 비교용 |

> `specialty`(단일 문자열) 컬럼은 레거시 호환 위해 유지하되, 다중 과목은 §3-`hospital_specialties` 사용.

---

## 3. 연관 테이블 (1:N / 1:1) — B 전체 정규화

모두 `hospital_id` FK(`hospitals.id`, cascade)로 연결. (ykiho 가 아니라 내부 PK로 연결 → 적재 시 ykiho→hospital_id 해석)
테이블 comment 는 테이블 레벨만(컨벤션 §5).

| 테이블 | 소스 | 관계 | 주요 컬럼 |
|---|---|---|---|
| `hospital_specialties` | B-5 | 1:N | `dept_code, dept_name, specialist_count, selective_doctor_count` — `unique(hospital_id, dept_code)` |
| `hospital_equipments` | B-7 | 1:N | `equipment_code, equipment_name, quantity` — `unique(hospital_id, equipment_code)` |
| `hospital_facilities` | B-3 | 1:1 | 설립구분 + 병상 상세 13종(상급/일반/성인·소아·신생아중환자/분만/수술/응급/물리치료/정신과 4종/격리/무균) |
| `hospital_hours` | B-4 | 1:1 | 위치·주차·휴진·응급실(주/야간)·점심·접수 + 요일별 진료 시작/종료(JSON 또는 컬럼 14개) |
| `hospital_transports` | B-6 | 1:N | `transport_type, route_no, stop_name, direction, distance, remarks` |
| `hospital_nursing_grades` | B-9 | 1:N | `insurance_type_code/name, nursing_grade` |
| `hospital_meal_surcharges` | B-8 | 1:N | `type_code/name, general_meal_surcharge, calc_headcount, therapeutic_meal_grade` |
| `hospital_special_treatments` | B-10 | 1:N | `search_code, search_name` |
| `hospital_specialized_fields` | B-11 | 1:N | `field_code, field_name` (전문병원) |
| `hospital_other_staff` | B-12 | 1:N | `staff_code, staff_name, staff_count` |

> 적재량이 큰 1:N(진료과목 433k, 장비 63k)은 batch upsert + `chunk` 사용(컨벤션: `all()` 금지).

---

## 4. 적재 전략 (하이브리드)

### 4-1. 단계
1. **A(CSV) 적재** — 기존 `HospitalImportService` 확장. `hospital_code(=관리번호)` upsert 키 유지.
   추가 흡수 컬럼: 인허가/폐업/휴업일자·병상수·의료인수·입원실수·좌표·총면적·자치단체코드·갱신시점.
   `firstSpecialty()` 는 유지(레거시 `specialty`)하되, 다중 과목은 B에서 채움.
2. **B-1(병원정보) 적재 → ykiho 마스터화** — 신규 `HiraInstitutionImportService`(가칭).
   기관명+주소 정규화 매칭으로 A의 `hospitals` 행에 `ykiho` 및 B-1 보강 컬럼 부착.
   - 매칭 규칙: `정규화(기관명)` + `정규화(주소 앞부분/시군구)` 일치. 좌표 근접 보조.
   - **미매칭 처리**: A에 없는 B 기관은 (옵션) 신규 `hospitals` 행 생성 또는 `unmatched` 리포트로 보류 → §6 결정 필요.
3. **B-3~12 연관 테이블 적재** — ykiho→hospital_id 해석 후 각 child 테이블 batch upsert.
   ykiho 미해석 행은 스킵 + 카운트 리포트.
4. **코드 시드** — 종별/시도/시군구/진료과목/장비 코드 → `code_definitions`(§5).

### 4-2. 멱등성·재실행
- 모든 import 는 upsert(키: A=`hospital_code`, child=`unique(hospital_id, *_code)`).
- 1회성 정규화가 아니라 **반복 적재 가능**하도록 서비스로 구현(시드 아님). CI/배포 동일 실행은 아님(대용량) → artisan command 로 수동/큐 실행.

### 4-3. 성능
- xlsx 파싱: PhpSpreadsheet `ReadFilter` + read-only, 또는 사전 변환(xlsx→CSV) 후 스트리밍.
  진료과목 433k·시설 105k 고려 → **xlsx→CSV 사전 변환 + fgetcsv 스트리밍** 권장.
- batch 500 upsert, `DB::transaction` 청크 단위.

---

## 5. 코드값 → `code_definitions` 재사용

별도 코드 테이블 신설 대신 기존 `code_definitions(group_code, code, name, …)` 활용:

| group_code | 출처 | 예 |
|---|---|---|
| `hospital_clazz` | B 종별코드 | 31=의원 |
| `sido` / `sigungu` | B 시도/시군구 | 230000=대구 |
| `medical_dept` | B-5 진료과목코드 | 01=내과 |
| `med_equipment` | B-7 장비코드 | B302=초음파영상진단기 |
| `nursing_insurance_type`, `meal_type`, `special_treatment`, `specialized_field`, `other_staff` | B-8~12 | |

기존 `hospital_type`(general_hospital/clinic/…) enum 은 유지하고, `clazz_code` 는 원천 코드 보존용으로 병행.

---

## 6. 미결 결정 (구현 착수 전 확인)

1. **B 미매칭 기관 처리**: A에 없는 B-1 기관을 (a) 신규 `hospitals` 생성 / (b) `unmatched` 리포트 보류 / (c) ykiho-only 행 생성. → 권장: **(b) 1차 보류 + 매칭률 리포트**, 매칭률 확인 후 정책 확정.
2. **진료시간(B-4) 저장 형태**: 요일별 14컬럼 vs JSON 1컬럼. → 권장: **JSON**(조회 위주, 정규화 이득 적음).
3. **부속의료기관(490)** 포함 여부: 일반 병의원과 동일 테이블 + `hospital_type=other` 로 흡수 → 권장 포함.
4. **테넌시**: `hospitals` 는 공유 마스터(플랫폼 소유)인지 테넌트별인지 — 기존 정책(MASTER_DATA / MT-8 공유 마스터) 따름. 공공데이터는 **공유 마스터(platform 적재)** 로 가정.

---

## 7. 구현 작업 목록

- [x] `add_public_data_to_hospitals_table` 마이그레이션 + `Hospital` fillable/cast 확장
- [x] 연관 테이블 10종 마이그레이션(`create_hospital_detail_tables`) + 모델 + `Hospital` hasMany/hasOne 관계
- [x] `HospitalImportService` 확장(A 추가 컬럼 흡수: 일자·병상수·의료인수·입원실수·면적·자치단체코드·갱신시점) + 테스트
- [x] `HiraInstitutionImportService`(B-1 + 기관명+우편번호 ykiho 매칭) + 매칭률·미매칭 리포트 + 테스트
- [x] `HiraDetailImportService`(B-3~12 child 적재, 1:N hospital별 교체 / 1:1 upsert) + 테스트
- [x] `XlsxRowReader`(openspout 스트리밍, 대용량 xlsx) — PhpSpreadsheet 대신 openspout 채택
- [x] artisan command: `hospitals:import-public-data {--dir=} {--step=a|b1|details|all}` + 스모크 테스트
- [x] **platform UI 보강 업로드** — `/platform/hospitals/public-data` (super_admin이 Excel 업로드 → 큐 적재). `HospitalPublicDataController` + `StoreHospitalPublicDataImportRequest`(`extensions:xlsx`) + `ProcessHospitalPublicDataImport` 잡 + `hospital_public_data_imports` 이력 테이블 + `Platform/Hospitals/PublicData.vue` + 메뉴. 테스트 6.
- [x] 실데이터 B-1 **매칭률 측정 완료** — 79,562건 중 78,278 매칭 **98.4%** (미매칭 1,284, 대부분 동명+동일우편 모호) → §6-1 "보류+리포트" 정책 유지 확정.
- [ ] `code_definitions` 코드 시드(종별/지역/진료과목/장비…) — **후속**. 각 child 테이블에 `*_name` 비정규화 저장으로 조회는 가능. 필터 UI 시 시드.
- [x] B 상세(B-3~12) **실데이터 대용량 적재 완료** (아래 결과)
- [x] (UI) 병의원 상세에 보강 정보(진료과목·장비·병상·진료시간·기타) 표시 — `Platform/Hospitals/Show.vue` + 컨트롤러 eager load + 테스트

### 실데이터 상세 적재 결과 (2026-06-01)
| 테이블 | 적재 행수 | 연결률(연결/총) |
|---|---|---|
| hospital_specialties | 425,214 | 425,214 / 433,337 |
| hospital_equipments | 61,847 | 61,847 / 62,783 |
| hospital_facilities | 78,272 | 78,272 / 105,250 |
| hospital_hours | 23,300 | 23,300 / 25,015 |
| hospital_transports | 38,154 | 38,154 / 40,525 |
| hospital_nursing_grades | 12,646 | 12,646 / 13,166 |
| hospital_meal_surcharges | 15,276 | 15,276 / 15,664 |
| hospital_special_treatments | 56,246 | 63,418 / 64,629 (중복 ~7천 무시) |
| hospital_specialized_fields | 108 | 108 / 110 |
| hospital_other_staff | 17,742 | 17,742 / 43,965 |

스킵 = ykiho 미매칭 기관(병원정보 B-1 에 없거나 매칭 실패). other_staff 스킵률이 높은 건 약국 등 비매칭 기관 비중 때문.

### 실데이터에서 발견·수정한 이슈
- **날짜 셀**: 엑셀 `개설일자`가 DateTimeImmutable → `XlsxRowReader::stringify()` 추가.
- **ykiho 길이**: 실제 ~76자(base64) → `ykiho varchar(191)`.
- **자유 텍스트 길이**: `hospital_hours`/`transports`/`equipments`/`special_treatments` 의 위치·방향·장비명 등 varchar 길이 초과 → 넉넉히 확대(255~500).
- **모델 테이블명**: `HospitalEquipment`(equipment 불가산) → `hospital_equipment` 로 오추론 → `$table` 명시. `HospitalOtherStaff` 도 명시.
- **원천 중복 행**: 동일 (기관,코드) 중복 존재 → 1:N 적재를 `insertOrIgnore` 로 변경.
- 커맨드 `--only=` 옵션 추가(특정 상세 유형만 재적재).

### 운영 흐름 (super_admin)
1. `/platform/hospitals/import` — 인허가 CSV 등록(A)
2. `/platform/hospitals/public-data` — ① 병원정보(B-1) 업로드 → ykiho 매칭, ② 상세 유형별 Excel 업로드 → 정규화 적재. 처리는 큐(백그라운드), 같은 화면 이력에서 상태·결과 확인.
   - ⚠️ 큐 워커 필요: 운영은 supervisor `queue:work`, 로컬은 `./vendor/bin/sail artisan queue:work`. (테스트 env 는 `sync`)
   - 서버 디스크 파일로 일괄 적재하려면 artisan `hospitals:import-public-data` 사용.

### 구현 메모 (실제와의 차이)
- B import 의 보강 갱신은 `upsert(['id'])` 가 INSERT 행 NOT NULL 검증에 걸려, **id 기준 부분 UPDATE** 로 구현.
- A(인허가)의 좌표는 TM 좌표계라 위경도에 저장하지 않음 — `latitude/longitude` 는 **B(WGS84)에서만** 채움(`좌표(Y)`=위도, `좌표(X)`=경도).
- B 보강은 `ykiho/종별/시도/시군구/읍면동/홈페이지/좌표`만 갱신, `opened_on/doctor_count` 등은 A 값 보존(클로버 방지).
