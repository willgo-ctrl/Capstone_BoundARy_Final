<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\BoundaryCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $properties = $request->user()->properties()->latest('updated_at')->get();
        $activeProperty = $properties->firstWhere('id', $request->integer('property')) ?? $properties->first();
        $mapData = $properties->map(fn (Property $property): array => [
            'id' => $property->id,
            'name' => $property->name,
            'parcel_reference' => $property->parcel_reference,
            'survey_plan_reference' => $property->survey_plan_reference,
            'tie_latitude' => $property->tie_latitude,
            'tie_longitude' => $property->tie_longitude,
            'courses' => $property->courses,
            'boundary' => $property->boundary,
            'closure_error_meters' => $property->closure_error_meters,
            'ocr_text' => $property->ocr_text,
            'document_name' => $property->document_name,
            'document_url' => $property->survey_document ? route('properties.document', $property) : null,
            'reference_boundary' => $property->reference_boundary,
            'reference_engineer_name' => $property->reference_engineer_name,
            'reference_engineer_license' => $property->reference_engineer_license,
            'reference_deviation_meters' => $property->reference_deviation_meters,
        ])->values();

        return view('properties.index', compact('properties', 'activeProperty', 'mapData'));
    }

    public function store(Request $request, BoundaryCalculator $calculator): RedirectResponse
    {
        $data = $this->validatedSurvey($request);
        $calculation = $calculator->calculate($data['tie_latitude'], $data['tie_longitude'], $data['courses']);
        $reference = $this->referenceComparison($request, $calculation['boundary']);
        $upload = $data['survey_document'] ?? null;
        unset($data['survey_document']);

        if ($upload instanceof UploadedFile) {
            $data['survey_document'] = $upload->store('survey-documents/'.$request->user()->id, 'local');
            $data['document_name'] = $upload->getClientOriginalName();
        }

        $property = $request->user()->properties()->create([...$data, ...$calculation, ...$reference]);

        return redirect()->route('properties.show', $property)->with('status', 'Property saved. Boundary is preliminary and needs engineer review.');
    }

    public function show(Request $request, string $property): RedirectResponse
    {
        $property = $request->user()->properties()->findOrFail($property);

        return redirect()->route('properties.index', ['property' => $property->id]);
    }

    public function update(Request $request, string $property, BoundaryCalculator $calculator): RedirectResponse
    {
        $property = $request->user()->properties()->findOrFail($property);
        $data = $this->validatedSurvey($request);
        $calculation = $calculator->calculate($data['tie_latitude'], $data['tie_longitude'], $data['courses']);
        $reference = $this->referenceComparison($request, $calculation['boundary']);
        $upload = $data['survey_document'] ?? null;
        unset($data['survey_document']);

        if ($upload instanceof UploadedFile) {
            $previousDocument = $property->survey_document;
            $data['survey_document'] = $upload->store('survey-documents/'.$request->user()->id, 'local');
            $data['document_name'] = $upload->getClientOriginalName();
        }

        $property->update([...$data, ...$calculation, ...$reference]);

        if ($upload instanceof UploadedFile && $previousDocument) {
            Storage::disk('local')->delete($previousDocument);
        }

        return redirect()->route('properties.show', $property)->with('status', 'Survey updated. Boundary is preliminary and needs engineer review.');
    }

    public function destroy(Request $request, string $property): RedirectResponse
    {
        $property = $request->user()->properties()->findOrFail($property);

        if ($property->survey_document) {
            Storage::disk('local')->delete($property->survey_document);
        }

        $property->delete();

        return redirect()->route('properties.index')->with('status', 'Property deleted.');
    }

    public function document(Request $request, string $property): StreamedResponse
    {
        $property = $request->user()->properties()->findOrFail($property);
        abort_unless($property->survey_document, 404);

        return Storage::disk('local')->download($property->survey_document, $property->document_name);
    }

    /** @return array<string, mixed> */
    private function validatedSurvey(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'parcel_reference' => ['nullable', 'string', 'max:120'],
            'survey_plan_reference' => ['nullable', 'string', 'max:180'],
            'tie_latitude' => ['required', 'numeric', 'between:-90,90'],
            'tie_longitude' => ['required', 'numeric', 'between:-180,180'],
            'courses' => ['required', 'array', 'min:3', 'max:300'],
            'courses.*.bearing' => ['required', 'numeric', 'between:0,359.999999'],
            'courses.*.distance' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'ocr_text' => ['nullable', 'string', 'max:20000'],
            'reference_coordinates' => ['nullable', 'string', 'max:20000'],
            'reference_engineer_name' => ['required_with:reference_coordinates', 'nullable', 'string', 'max:120'],
            'reference_engineer_license' => ['required_with:reference_coordinates', 'nullable', 'string', 'max:80'],
            'survey_document' => ['nullable', File::types(['jpg', 'jpeg', 'png', 'tif', 'tiff', 'pdf'])->max(10240)],
        ]);
    }

    /** @return array{reference_boundary: ?array, reference_engineer_name: ?string, reference_engineer_license: ?string, reference_deviation_meters: ?float} */
    private function referenceComparison(Request $request, array $boundary): array
    {
        $coordinatesText = trim((string) $request->input('reference_coordinates', ''));

        if ($coordinatesText === '') {
            return [
                'reference_boundary' => null,
                'reference_engineer_name' => null,
                'reference_engineer_license' => null,
                'reference_deviation_meters' => null,
            ];
        }

        $referenceBoundary = [];

        foreach (preg_split('/\r?\n/', $coordinatesText) as $lineNumber => $line) {
            if (trim($line) === '') {
                continue;
            }

            if (! preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $line, $matches)) {
                throw ValidationException::withMessages([
                    'reference_coordinates' => 'Reference coordinates must use one latitude, longitude pair per line.',
                ]);
            }

            $latitude = (float) $matches[1];
            $longitude = (float) $matches[2];

            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                throw ValidationException::withMessages([
                    'reference_coordinates' => 'Reference coordinate '.($lineNumber + 1).' is outside the valid latitude or longitude range.',
                ]);
            }

            $referenceBoundary[] = [round($longitude, 7), round($latitude, 7)];
        }

        if (count($referenceBoundary) < 3) {
            throw ValidationException::withMessages([
                'reference_coordinates' => 'Enter at least three reference coordinates to compare the boundary.',
            ]);
        }

        $earthRadiusMeters = 6_371_008.8;
        $maximumDeviation = 0.0;

        foreach ([[$boundary, $referenceBoundary], [$referenceBoundary, $boundary]] as [$source, $target]) {
            foreach ($source as [$longitude, $latitude]) {
                $nearestDistance = INF;

                foreach ($target as [$targetLongitude, $targetLatitude]) {
                    $latitudeDelta = deg2rad($targetLatitude - $latitude);
                    $longitudeDelta = deg2rad($targetLongitude - $longitude);
                    $latitudeRadians = deg2rad($latitude);
                    $targetLatitudeRadians = deg2rad($targetLatitude);
                    $haversine = sin($latitudeDelta / 2) ** 2
                        + cos($latitudeRadians) * cos($targetLatitudeRadians) * sin($longitudeDelta / 2) ** 2;
                    $distance = 2 * $earthRadiusMeters * asin(min(1, sqrt($haversine)));
                    $nearestDistance = min($nearestDistance, $distance);
                }

                $maximumDeviation = max($maximumDeviation, $nearestDistance);
            }
        }

        return [
            'reference_boundary' => $referenceBoundary,
            'reference_engineer_name' => $request->string('reference_engineer_name')->trim()->toString(),
            'reference_engineer_license' => $request->string('reference_engineer_license')->trim()->toString(),
            'reference_deviation_meters' => round($maximumDeviation, 3),
        ];
    }
}
