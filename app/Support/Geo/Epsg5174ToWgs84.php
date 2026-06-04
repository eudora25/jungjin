<?php

namespace App\Support\Geo;

use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;
use Throwable;

/**
 * 행안부 좌표(EPSG:5174 — Bessel 중부원점 TM) → WGS84(EPSG:4326) 변환기.
 *
 * MOIS API 응답의 CRD_INFO_X/Y 를 hospitals.latitude/longitude(WGS84)로 변환한다.
 * Pample 검증 구현(proj4j 내장 EPSG:5174 정의 = towgs84 미적용)과 동일하게 정의해,
 * 변환 결과가 한반도 범위(위도 33~39°, 경도 124~132°)를 벗어나면 null 을 반환한다(안전망).
 *
 * 설계: docs/modules/client/HOSPITAL_LOCALDATA_API_SYNC.md §4-4
 */
class Epsg5174ToWgs84
{
    private const KOREA_LAT_MIN = 33.0;

    private const KOREA_LAT_MAX = 39.0;

    private const KOREA_LNG_MIN = 124.0;

    private const KOREA_LNG_MAX = 132.0;

    /** Pample(proj4j createFromName("EPSG:5174"))와 동일한 towgs84 미적용 정의. */
    private const EPSG_5174 = '+proj=tmerc +lat_0=38 +lon_0=127.0028902777778 +k=1 +x_0=200000 +y_0=500000 +ellps=bessel +units=m +no_defs';

    private readonly Proj4php $proj4;

    private readonly Proj $source;

    private readonly Proj $target;

    public function __construct()
    {
        $this->proj4 = new Proj4php;
        $this->proj4->addDef('EPSG:5174', self::EPSG_5174);
        $this->source = new Proj('EPSG:5174', $this->proj4);
        $this->target = new Proj('EPSG:4326', $this->proj4);
    }

    /**
     * EPSG:5174 좌표(X, Y)를 WGS84 (longitude, latitude)로 변환.
     * 입력이 비었거나 숫자가 아니거나, 변환 결과가 한반도 범위를 벗어나면 null.
     *
     * @return array{longitude: float, latitude: float}|null
     */
    public function convert(mixed $x, mixed $y): ?array
    {
        $sx = $this->toFloat($x);
        $sy = $this->toFloat($y);
        if ($sx === null || $sy === null) {
            return null;
        }

        try {
            $point = new Point($sx, $sy, $this->source);
            $result = $this->proj4->transform($this->target, $point);
        } catch (Throwable) {
            return null;
        }

        $lng = (float) $result->x;
        $lat = (float) $result->y;

        if (! $this->withinKorea($lng, $lat)) {
            return null;
        }

        return [
            'longitude' => round($lng, 7),
            'latitude' => round($lat, 7),
        ];
    }

    private function toFloat(mixed $v): ?float
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    private function withinKorea(float $lng, float $lat): bool
    {
        return $lng >= self::KOREA_LNG_MIN && $lng <= self::KOREA_LNG_MAX
            && $lat >= self::KOREA_LAT_MIN && $lat <= self::KOREA_LAT_MAX;
    }
}
