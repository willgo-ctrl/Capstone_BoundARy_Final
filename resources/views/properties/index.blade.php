<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Properties | boundARy</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <a class="brand" href="{{ route('properties.index') }}" aria-label="boundARy home">
            <span class="brand-word">bound<span>AR</span>y</span>
        </a>
        <div class="topbar-right">
            <span class="topbar-email">{{ auth()->user()->email }}</span>
            <span class="user-dot" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="quiet-button" type="submit">Sign out</button>
            </form>
        </div>
    </header>

    <main class="dashboard">
        @if (session('status'))
            <div class="notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <ul class="error-list" role="alert">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif

        <div class="page-heading">
            <div>
                <p class="eyebrow">Field records / {{ now()->format('F Y') }}</p>
                <h1>Land records</h1>
                <p class="page-subtitle">Survey plans, parcel boundaries, all in one place.</p>
            </div>
            <button class="primary-button" type="button" data-new-property><span aria-hidden="true">+</span> Add property</button>
        </div>

        <section class="stats-row" aria-label="Property overview">
            <div class="stat-cell"><span class="stat-number">{{ $properties->count() }}</span><span class="stat-label">Properties</span></div>
            <div class="stat-cell"><span class="stat-number">{{ $properties->filter(fn ($property) => $property->closure_error_meters <= 0.25)->count() }}</span><span class="stat-label">Closed traverses</span></div>
            <div class="stat-cell"><span class="stat-number">{{ $properties->filter(fn ($property) => $property->survey_document)->count() }}</span><span class="stat-label">Survey files</span></div>
        </section>

        <div class="workspace">
            <aside class="records-panel" aria-label="Property records">
                <div class="records-head">
                    <h2>Your properties</h2>
                    <span class="count-label">{{ str_pad((string) $properties->count(), 2, '0', STR_PAD_LEFT) }} records</span>
                </div>
                <div class="search-wrap">
                    <label class="sr-only" for="propertySearch">Search properties</label>
                    <input class="search-input" id="propertySearch" type="search" placeholder="Search name or plan reference">
                </div>
                <div class="property-list" id="propertyList">
                    @forelse ($properties as $property)
                        <article class="property-item {{ $activeProperty?->is($property) ? 'is-active' : '' }}" data-property-item>
                            <span class="property-dot" aria-hidden="true"></span>
                            <a class="property-item-copy" href="{{ route('properties.index', ['property' => $property->id]) }}" data-property-search="{{ mb_strtolower($property->name.' '.$property->parcel_reference.' '.$property->survey_plan_reference) }}">
                                <span class="property-item-title">{{ $property->name }}</span>
                                <span class="property-item-meta">{{ $property->parcel_reference ?: 'No parcel reference' }} · {{ $property->courses ? count($property->courses).' courses' : 'No courses' }}</span>
                            </a>
                            <span class="property-item-status">{{ $property->closure_error_meters <= 0.25 ? 'Closed' : 'Check closure' }}</span>
                        </article>
                    @empty
                        <div class="no-properties">No property records yet.</div>
                    @endforelse
                </div>
            </aside>

            <section class="work-panel" aria-label="Selected property workspace">
                @if ($activeProperty)
                    <div class="parcel-toolbar">
                        <div class="parcel-title">
                            <p class="eyebrow">Selected property</p>
                            <h2>{{ $activeProperty->name }}</h2>
                            <p>{{ $activeProperty->parcel_reference ?: 'No parcel reference' }}@if ($activeProperty->survey_plan_reference) · {{ $activeProperty->survey_plan_reference }}@endif</p>
                        </div>
                        <div class="toolbar-actions">
                            <button class="quiet-button" type="button" data-edit-property="{{ $activeProperty->id }}" title="Edit property and survey"> <span aria-hidden="true">✎</span><span>Edit</span></button>
                            <button class="quiet-button" type="button" data-open-ar title="Open camera boundary view"><span aria-hidden="true">⌖</span><span>AR view</span></button>
                            <form method="POST" action="{{ route('properties.destroy', $activeProperty) }}" onsubmit="return confirm('Delete this property and its survey file?')">
                                @csrf
                                @method('DELETE')
                                <button class="quiet-button icon-action danger-button" type="submit" title="Delete property" aria-label="Delete property">×</button>
                            </form>
                        </div>
                    </div>
                    <div class="map-wrap">
                        <div id="boundaryMap" role="region" aria-label="Google map showing the calculated property boundary"></div>
                        <div class="map-status" id="mapStatus" role="status" hidden></div>
                        <div class="map-legend"><span class="legend-key"></span> Calculated boundary <span>·</span> Preliminary</div>
                        <div class="map-controls">
                            <button class="map-control" type="button" data-zoom-in aria-label="Zoom in" title="Zoom in">+</button>
                            <button class="map-control" type="button" data-zoom-out aria-label="Zoom out" title="Zoom out">−</button>
                            <button class="map-control" type="button" data-center-map aria-label="Center map on property" title="Center on property">⌖</button>
                        </div>
                    </div>
                    <div class="survey-meta">
                        <div class="meta-cell"><span class="meta-label">Tie point</span><span class="meta-value">{{ number_format($activeProperty->tie_latitude, 6) }}, {{ number_format($activeProperty->tie_longitude, 6) }}</span></div>
                        <div class="meta-cell"><span class="meta-label">Survey courses</span><span class="meta-value">{{ count($activeProperty->courses) }} bearings & distances</span></div>
                        <div class="meta-cell"><span class="meta-label">Closure error</span><span class="meta-value">{{ number_format($activeProperty->closure_error_meters, 3) }} m</span></div>
                        <div class="meta-cell"><span class="meta-label">Reference deviation</span><span class="meta-value">{{ $activeProperty->reference_deviation_meters !== null ? number_format($activeProperty->reference_deviation_meters, 3).' m max' : 'No reference provided' }}</span></div>
                        <div class="meta-cell"><span class="meta-label">Survey document</span><span class="meta-value">{{ $activeProperty->document_name ?: 'No file uploaded' }}</span></div>
                        <div class="meta-cell"><span class="meta-label">Reference engineer</span><span class="meta-value">{{ $activeProperty->reference_engineer_name ? $activeProperty->reference_engineer_name.' · '.$activeProperty->reference_engineer_license : 'No engineer reference' }}</span></div>
                    </div>
                    <div class="review-banner" role="note">
                        <span aria-hidden="true">!</span>
                        <div><strong>Preliminary boundary, not a legal survey</strong>Coordinate calculations and camera alignment are for visualization only. Verify all measurements and boundary coordinates with a licensed geodetic engineer before relying on them.</div>
                    </div>
                @else
                    <div class="empty-workspace">
                        <div class="empty-survey-art" aria-hidden="true"></div>
                        <p class="eyebrow">Start a field record</p>
                        <h2>Your first parcel, mapped.</h2>
                        <p>Add a property and enter its tie point and survey courses. The calculated boundary will appear here for review.</p>
                        <button class="primary-button" type="button" data-new-property><span aria-hidden="true">+</span> Add first property</button>
                    </div>
                @endif
            </section>
        </div>
    </main>
</div>

<div class="sheet-backdrop" id="propertySheet" data-open-on-load="{{ $errors->any() ? 'true' : 'false' }}" hidden>
    <section class="sheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle">
        <form id="propertyForm" method="POST" action="{{ route('properties.store') }}" enctype="multipart/form-data" data-store-url="{{ route('properties.store') }}" data-update-base="{{ url('/properties') }}">
            @csrf
            <input id="formMethod" type="hidden" name="_method" value="{{ old('_method', 'POST') }}">
            <div class="sheet-header">
                <div><p class="eyebrow">Property record</p><h2 id="sheetTitle">Add property</h2></div>
                <button class="quiet-button icon-action" type="button" data-close-sheet aria-label="Close form">×</button>
            </div>
            <div class="sheet-body">
                <section class="form-section">
                    <div class="section-heading"><h2>Property details</h2><span>01 / Record</span></div>
                    <div class="field-grid">
                        <div class="field full"><label for="propertyName">Property name</label><input id="propertyName" name="name" maxlength="140" required value="{{ old('name', $activeProperty?->name) }}" placeholder="e.g. North Field"></div>
                        <div class="field"><label for="parcelReference">Parcel reference</label><input id="parcelReference" name="parcel_reference" maxlength="120" value="{{ old('parcel_reference', $activeProperty?->parcel_reference) }}" placeholder="Lot or parcel number"></div>
                        <div class="field"><label for="planReference">Survey plan reference</label><input id="planReference" name="survey_plan_reference" maxlength="180" value="{{ old('survey_plan_reference', $activeProperty?->survey_plan_reference) }}" placeholder="Plan / record number"></div>
                    </div>
                </section>
                <section class="form-section">
                    <div class="section-heading"><h2>Tie point</h2><span>02 / Coordinates</span></div>
                    <div class="field-grid">
                        <div class="field"><label for="tieLatitude">Latitude</label><input id="tieLatitude" name="tie_latitude" type="number" min="-90" max="90" step="any" required value="{{ old('tie_latitude', $activeProperty?->tie_latitude) }}" placeholder="e.g. 37.420000"></div>
                        <div class="field"><label for="tieLongitude">Longitude</label><input id="tieLongitude" name="tie_longitude" type="number" min="-180" max="180" step="any" required value="{{ old('tie_longitude', $activeProperty?->tie_longitude) }}" placeholder="e.g. -122.080000"></div>
                    </div>
                    <p class="field-hint">Enter the survey's known starting coordinate in decimal degrees (WGS84).</p>
                </section>
                <section class="form-section">
                    <div class="section-heading"><h2>Survey courses</h2><span>03 / Traverse</span></div>
                    <p class="field-hint">Enter each course as an azimuth from north (0–360°) and distance in metres. Add at least three.</p>
                    <div class="course-table" id="courseRows">
                        @foreach (old('courses', $activeProperty?->courses ?? array_fill(0, 4, ['bearing' => '', 'distance' => ''])) as $index => $course)
                            <div class="course-row">
                                <span class="course-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                                <input name="courses[{{ $index }}][bearing]" type="number" min="0" max="359.999999" step="any" required value="{{ $course['bearing'] }}" placeholder="Azimuth °" aria-label="Course {{ $index + 1 }} azimuth">
                                <input name="courses[{{ $index }}][distance]" type="number" min="0.001" max="100000" step="any" required value="{{ $course['distance'] }}" placeholder="Metres" aria-label="Course {{ $index + 1 }} distance in metres">
                                <button class="remove-course" type="button" data-remove-course aria-label="Remove course {{ $index + 1 }}">×</button>
                            </div>
                        @endforeach
                    </div>
                    <div class="course-actions"><button class="text-button" type="button" data-add-course>+ Add course</button></div>
                </section>
                <section class="form-section">
                    <div class="section-heading"><h2>Survey document</h2><span>04 / Reference</span></div>
                    <div class="upload-row">
                        <input id="surveyDocument" name="survey_document" type="file" accept=".jpg,.jpeg,.png,.tif,.tiff,.pdf,image/*,application/pdf">
                        <button class="quiet-button scan-button" type="button" data-scan-image>Scan image</button>
                    </div>
                    <p class="ocr-status" id="ocrStatus">Images can be read in your browser. PDF documents can be saved as references; review and enter their survey data below.</p>
                    <a class="document-link" id="existingDocument" href="#" hidden></a>
                    <div class="field ocr-field"><label for="ocrText">Extracted text / field notes</label><textarea id="ocrText" name="ocr_text" maxlength="20000" placeholder="Review OCR output and keep useful field notes here.">{{ old('ocr_text', $activeProperty?->ocr_text) }}</textarea></div>
                </section>
                <section class="form-section">
                    <div class="section-heading"><h2>Engineer reference check</h2><span>05 / Comparison</span></div>
                    <p class="field-hint">Add reference boundary coordinates supplied by a licensed geodetic engineer. Enter one latitude, longitude pair per line in WGS84 decimal degrees.</p>
                    <div class="field-grid" style="margin-top: 10px">
                        <div class="field"><label for="referenceEngineerName">Engineer name</label><input id="referenceEngineerName" name="reference_engineer_name" maxlength="120" value="{{ old('reference_engineer_name', $activeProperty?->reference_engineer_name) }}" placeholder="Full name"></div>
                        <div class="field"><label for="referenceEngineerLicense">License / registration</label><input id="referenceEngineerLicense" name="reference_engineer_license" maxlength="80" value="{{ old('reference_engineer_license', $activeProperty?->reference_engineer_license) }}" placeholder="License number"></div>
                        <div class="field full"><label for="referenceCoordinates">Reference coordinates</label><textarea id="referenceCoordinates" name="reference_coordinates" maxlength="20000" placeholder="37.420000, -122.080000&#10;37.420900, -122.080000&#10;37.420900, -122.078900">{{ old('reference_coordinates', $activeProperty?->reference_boundary ? collect($activeProperty->reference_boundary)->map(fn ($coordinate) => number_format($coordinate[1], 7, '.', '').', '.number_format($coordinate[0], 7, '.', ''))->implode("\n") : '') }}</textarea><span class="field-hint">Include the closing coordinate if shown on the engineer's reference.</span></div>
                    </div>
                </section>
            </div>
            <footer class="sheet-footer">
                <button class="quiet-button" type="button" data-close-sheet>Cancel</button>
                <div class="footer-actions"><button class="primary-button" type="submit">Save property</button></div>
            </footer>
        </form>
    </section>
</div>

<div class="ar-overlay" id="arOverlay" hidden>
    <div class="ar-stage">
        <video id="arVideo" autoplay muted playsinline></video>
        <canvas id="arCanvas" aria-label="Preliminary camera boundary overlay"></canvas>
        <header class="ar-topbar"><div><div class="ar-title">Field view · {{ $activeProperty?->name }}</div><div class="ar-state" id="arHeading">Waiting for compass</div></div><button class="quiet-button" type="button" data-close-ar>Close camera</button></header>
        <footer class="ar-bottom"><div class="ar-hud"><strong>Visualization only</strong>GPS and compass accuracy vary by device and surroundings. This camera overlay is not a survey instrument or staking guide.</div><div class="ar-state" id="arLocation">Waiting for location</div></footer>
    </div>
</div>

<script>
    window.boundaryProperties = @js($mapData);
    window.selectedPropertyId = @js($activeProperty?->id);
    window.googleMapsApiKey = @js(config('services.google_maps.key'));
</script>
</body>
</html>