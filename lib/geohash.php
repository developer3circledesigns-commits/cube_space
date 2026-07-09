<?php
class GeoHash {
    private static $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';

    public static function encode($lat, $lng, $precision = 8): string {
        $latRange = [-90, 90];
        $lngRange = [-180, 180];
        $bits = [];
        $bitCount = $precision * 5;
        for ($i = 0; $i < $bitCount; $i++) {
            if ($i % 2 === 0) {
                $mid = ($lngRange[0] + $lngRange[1]) / 2;
                if ($lng > $mid) {
                    $bits[] = 1;
                    $lngRange[0] = $mid;
                } else {
                    $bits[] = 0;
                    $lngRange[1] = $mid;
                }
            } else {
                $mid = ($latRange[0] + $latRange[1]) / 2;
                if ($lat > $mid) {
                    $bits[] = 1;
                    $latRange[0] = $mid;
                } else {
                    $bits[] = 0;
                    $latRange[1] = $mid;
                }
            }
        }
        $geohash = '';
        for ($i = 0; $i < $precision; $i++) {
            $chunk = 0;
            for ($j = 0; $j < 5; $j++) {
                $chunk = ($chunk << 1) | ($bits[$i * 5 + $j] ?? 0);
            }
            $geohash .= self::$base32[$chunk];
        }
        return $geohash;
    }

    public static function neighbors($geohash): array {
        $neighbors = [];
        $latLng = self::decode($geohash);
        $prec = strlen($geohash);
        $dlat = 180 / (1 << (int)floor($prec * 5 / 2));
        $dlng = 360 / (1 << (int)ceil($prec * 5 / 2));
        $offsets = [[-1,0],[1,0],[0,-1],[0,1],[-1,-1],[-1,1],[1,-1],[1,1]];
        foreach ($offsets as $offset) {
            $neighbors[] = self::encode(
                $latLng['lat'] + $offset[0] * $dlat,
                $latLng['lng'] + $offset[1] * $dlng,
                $prec
            );
        }
        return $neighbors;
    }

    public static function decode($geohash): array {
        $base32 = self::$base32;
        $bits = [];
        $len = strlen($geohash);
        for ($i = 0; $i < $len; $i++) {
            $idx = strpos($base32, $geohash[$i]);
            for ($j = 4; $j >= 0; $j--) {
                $bits[] = ($idx >> $j) & 1;
            }
        }
        $latRange = [-90, 90];
        $lngRange = [-180, 180];
        for ($i = 0; $i < count($bits); $i++) {
            if ($i % 2 === 0) {
                $mid = ($lngRange[0] + $lngRange[1]) / 2;
                if ($bits[$i]) $lngRange[0] = $mid;
                else $lngRange[1] = $mid;
            } else {
                $mid = ($latRange[0] + $latRange[1]) / 2;
                if ($bits[$i]) $latRange[0] = $mid;
                else $latRange[1] = $mid;
            }
        }
        return [
            'lat' => ($latRange[0] + $latRange[1]) / 2,
            'lng' => ($lngRange[0] + $lngRange[1]) / 2,
        ];
    }

    public static function boundingBox($geohash): array {
        $decoded = self::decode($geohash);
        $prec = strlen($geohash);
        $dlat = 180 / (1 << (int)floor($prec * 5 / 2));
        $dlng = 360 / (1 << (int)ceil($prec * 5 / 2));
        return [
            'minLat' => $decoded['lat'] - $dlat / 2,
            'maxLat' => $decoded['lat'] + $dlat / 2,
            'minLng' => $decoded['lng'] - $dlng / 2,
            'maxLng' => $decoded['lng'] + $dlng / 2,
        ];
    }
}
