<?php

namespace App\Services;

class BoundaryCalculator
{
    /**
     * @param  list<array{bearing: int|float|string, distance: int|float|string}>  $courses
     * @return array{boundary: list<array{0: float, 1: float}>, closure_error_meters: float}
     */
    public function calculate(float $latitude, float $longitude, array $courses): array
    {
        $earthRadiusMeters = 6_371_008.8;
        $startLatitude = deg2rad($latitude);
        $startLongitude = deg2rad($longitude);
        $currentLatitude = $startLatitude;
        $currentLongitude = $startLongitude;
        $boundary = [[round($longitude, 7), round($latitude, 7)]];

        foreach ($courses as $course) {
            $distanceRadians = ((float) $course['distance']) / $earthRadiusMeters;
            $bearingRadians = deg2rad((float) $course['bearing']);
            $nextLatitude = asin(
                sin($currentLatitude) * cos($distanceRadians)
                + cos($currentLatitude) * sin($distanceRadians) * cos($bearingRadians),
            );
            $nextLongitude = $currentLongitude + atan2(
                sin($bearingRadians) * sin($distanceRadians) * cos($currentLatitude),
                cos($distanceRadians) - sin($currentLatitude) * sin($nextLatitude),
            );

            $currentLatitude = $nextLatitude;
            $currentLongitude = $nextLongitude;
            $boundary[] = [
                round(fmod(fmod(rad2deg($currentLongitude) + 180, 360) + 360, 360) - 180, 7),
                round(rad2deg($currentLatitude), 7),
            ];
        }

        $latitudeDelta = $currentLatitude - $startLatitude;
        $longitudeDelta = $currentLongitude - $startLongitude;
        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($startLatitude) * cos($currentLatitude) * sin($longitudeDelta / 2) ** 2;
        $closureErrorMeters = 2 * $earthRadiusMeters * asin(min(1, sqrt($haversine)));

        if ($closureErrorMeters <= 0.25) {
            $boundary[array_key_last($boundary)] = $boundary[0];
        }

        return [
            'boundary' => $boundary,
            'closure_error_meters' => round($closureErrorMeters, 3),
        ];
    }
}
