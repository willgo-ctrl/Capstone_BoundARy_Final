const properties = window.boundaryProperties ?? [];
const selectedProperty = properties.find((property) => Number(property.id) === Number(window.selectedPropertyId));
const sheet = document.querySelector('#propertySheet');
const form = document.querySelector('#propertyForm');
const courseRows = document.querySelector('#courseRows');

function addCourse(course = {}) {
	const index = courseRows.querySelectorAll('.course-row').length;
	const row = document.createElement('div');
	row.className = 'course-row';
	row.innerHTML = `<span class="course-number">${String(index + 1).padStart(2, '0')}</span>
		<input name="courses[${index}][bearing]" type="number" min="0" max="359.999999" step="any" required placeholder="Azimuth °" aria-label="Course ${index + 1} azimuth">
		<input name="courses[${index}][distance]" type="number" min="0.001" max="100000" step="any" required placeholder="Metres" aria-label="Course ${index + 1} distance in metres">
		<button class="remove-course" type="button" data-remove-course aria-label="Remove course ${index + 1}">×</button>`;
	row.querySelector(`[name="courses[${index}][bearing]"]`).value = course.bearing ?? '';
	row.querySelector(`[name="courses[${index}][distance]"]`).value = course.distance ?? '';
	courseRows.append(row);
}

function fillCourses(courses) {
	courseRows.replaceChildren();
	(courses.length ? courses : Array.from({ length: 4 }, () => ({}))).forEach(addCourse);
}

function openSheet(property = null) {
	if (!sheet || !form) return;
	form.reset();
	form.querySelector('#surveyDocument').value = '';
	const method = form.querySelector('#formMethod');
	const existingDocument = form.querySelector('#existingDocument');

	if (property) {
		form.action = `${form.dataset.updateBase}/${property.id}`;
		method.value = 'PUT';
		form.querySelector('#sheetTitle').textContent = 'Edit property';
		form.querySelector('[name="name"]').value = property.name;
		form.querySelector('[name="parcel_reference"]').value = property.parcel_reference ?? '';
		form.querySelector('[name="survey_plan_reference"]').value = property.survey_plan_reference ?? '';
		form.querySelector('[name="tie_latitude"]').value = property.tie_latitude;
		form.querySelector('[name="tie_longitude"]').value = property.tie_longitude;
		form.querySelector('[name="ocr_text"]').value = property.ocr_text ?? '';
		form.querySelector('[name="reference_engineer_name"]').value = property.reference_engineer_name ?? '';
		form.querySelector('[name="reference_engineer_license"]').value = property.reference_engineer_license ?? '';
		form.querySelector('[name="reference_coordinates"]').value = (property.reference_boundary ?? [])
			.map(([longitude, latitude]) => `${Number(latitude).toFixed(7)}, ${Number(longitude).toFixed(7)}`)
			.join('\n');
		existingDocument.hidden = !property.document_url;
		existingDocument.href = property.document_url ?? '#';
		existingDocument.textContent = property.document_name ? `Download current file · ${property.document_name}` : '';
		fillCourses(property.courses ?? []);
	} else {
		form.action = form.dataset.storeUrl;
		method.value = 'POST';
		form.querySelector('#sheetTitle').textContent = 'Add property';
		existingDocument.hidden = true;
		existingDocument.href = '#';
		existingDocument.textContent = '';
		fillCourses([]);
	}

	sheet.hidden = false;
	document.body.style.overflow = 'hidden';
	sheet.querySelector('input[name="name"]').focus();
}

function closeSheet() {
	if (!sheet) return;
	sheet.hidden = true;
	document.body.style.overflow = '';
}

document.querySelectorAll('[data-new-property]').forEach((button) => button.addEventListener('click', () => openSheet()));
document.querySelectorAll('[data-edit-property]').forEach((button) => {
	button.addEventListener('click', () => openSheet(properties.find((property) => Number(property.id) === Number(button.dataset.editProperty))));
});
document.querySelectorAll('[data-close-sheet]').forEach((button) => button.addEventListener('click', closeSheet));
sheet?.addEventListener('click', (event) => {
	if (event.target === sheet) closeSheet();
});
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') {
		closeSheet();
		closeArView();
	}
});
document.querySelector('[data-add-course]')?.addEventListener('click', () => addCourse());
courseRows?.addEventListener('click', (event) => {
	const remove = event.target.closest('[data-remove-course]');
	if (!remove) return;
	if (courseRows.querySelectorAll('.course-row').length <= 3) {
		remove.closest('.course-row').querySelectorAll('input').forEach((input) => { input.value = ''; });
		return;
	}
	remove.closest('.course-row').remove();
	courseRows.querySelectorAll('.course-row').forEach((row, index) => {
		row.querySelector('.course-number').textContent = String(index + 1).padStart(2, '0');
		row.querySelectorAll('input').forEach((input, inputIndex) => {
			const field = inputIndex === 0 ? 'bearing' : 'distance';
			input.name = `courses[${index}][${field}]`;
			input.setAttribute('aria-label', `Course ${index + 1} ${inputIndex === 0 ? 'azimuth' : 'distance in metres'}`);
		});
	});
});

if (sheet?.dataset.openOnLoad === 'true') {
	sheet.hidden = false;
	document.body.style.overflow = 'hidden';
	if (form.querySelector('#formMethod').value === 'PUT' && selectedProperty) {
		form.action = `${form.dataset.updateBase}/${selectedProperty.id}`;
		form.querySelector('#sheetTitle').textContent = 'Edit property';
	}
}

document.querySelector('#propertySearch')?.addEventListener('input', (event) => {
	const query = event.target.value.trim().toLocaleLowerCase();
	document.querySelectorAll('[data-property-item]').forEach((item) => {
		item.hidden = !item.querySelector('[data-property-search]').dataset.propertySearch.includes(query);
	});
});

let ocrLoader;
document.querySelector('[data-scan-image]')?.addEventListener('click', async () => {
	const file = document.querySelector('#surveyDocument').files[0];
	const status = document.querySelector('#ocrStatus');
	const imageFile = file && file.type.startsWith('image/');
	if (!imageFile) {
		status.textContent = 'Choose a survey image first. PDF files can be saved as references, but image OCR is not available for PDFs.';
		return;
	}

	const button = document.querySelector('[data-scan-image]');
	button.disabled = true;
	status.textContent = 'Loading browser OCR. The selected image stays on this device until you submit the form.';
	try {
		ocrLoader ??= new Promise((resolve, reject) => {
			const script = document.createElement('script');
			script.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
			script.onload = resolve;
			script.onerror = reject;
			document.head.append(script);
		});
		await ocrLoader;
		const result = await window.Tesseract.recognize(file, 'eng', {
			logger: (event) => {
				if (event.status === 'recognizing text') status.textContent = `Reading image · ${Math.round(event.progress * 100)}%`;
			},
		});
		const text = result.data.text.trim();
		document.querySelector('#ocrText').value = text;
		const courses = parseCourses(text);
		if (courses.length >= 3) fillCourses(courses);
		status.textContent = courses.length
			? `${courses.length} possible course${courses.length === 1 ? '' : 's'} found. Check and correct every value before saving.`
			: 'Text extracted. No clear azimuth-and-distance rows were detected; review the text and enter courses manually.';
	} catch {
		status.textContent = 'OCR could not load. Check your internet connection or enter the survey courses manually.';
	} finally {
		button.disabled = false;
	}
});

function parseCourses(text) {
	const courses = [];
	for (const line of text.split(/\r?\n/)) {
		const quadrant = line.match(/^\s*([NS])\s*(\d{1,2}(?:\.\d+)?)\s*(?:°|deg)?\s*([EW])\s*[,;\s]+([\d.]+)/i);
		const azimuth = line.match(/^\s*(\d{1,3}(?:\.\d+)?)\s*(?:°|deg)?\s*[,;/\s]+\s*([\d.]+)\s*(?:m(?:eters?)?)?\s*$/i);
		if (quadrant) {
			const angle = Number(quadrant[2]);
			const bearing = quadrant[1].toUpperCase() === 'N'
				? (quadrant[3].toUpperCase() === 'E' ? angle : 360 - angle)
				: (quadrant[3].toUpperCase() === 'E' ? 180 - angle : 180 + angle);
			courses.push({ bearing: Number(bearing.toFixed(4)), distance: Number(quadrant[4]) });
		} else if (azimuth && Number(azimuth[1]) <= 360) {
			courses.push({ bearing: Number(azimuth[1]), distance: Number(azimuth[2]) });
		}
	}
	return courses.filter((course) => Number.isFinite(course.distance) && course.distance > 0);
}

const mapElement = document.querySelector('#boundaryMap');
const mapStatus = document.querySelector('#mapStatus');
let googleMap;

function showMapStatus(message) {
	if (!mapStatus) return;
	mapStatus.textContent = message;
	mapStatus.hidden = false;
}

function centerOnProperty() {
	if (!googleMap || !selectedProperty) return;
	const tiePoint = {
		lat: Number(selectedProperty.tie_latitude),
		lng: Number(selectedProperty.tie_longitude),
	};
	const coordinates = (selectedProperty.boundary ?? []).map(([longitude, latitude]) => ({
		lat: Number(latitude),
		lng: Number(longitude),
	}));

	if (coordinates.length < 2) {
		googleMap.setCenter(tiePoint);
		googleMap.setZoom(17);
		return;
	}

	const bounds = new google.maps.LatLngBounds();
	coordinates.forEach((coordinate) => bounds.extend(coordinate));
	googleMap.fitBounds(bounds, { top: 58, right: 58, bottom: 48, left: 58 });
}

async function initializeGoogleMap() {
	if (!mapElement) return;
	if (!window.googleMapsApiKey) {
		showMapStatus('Add GOOGLE_MAPS_API_KEY to your .env file to enable Google Maps.');
		return;
	}

	try {
		const script = document.createElement('script');
		const callbackName = 'boundaryGoogleMapsLoaded';
		const parameters = new URLSearchParams({
			key: window.googleMapsApiKey,
			loading: 'async',
			v: 'weekly',
			auth_referrer_policy: 'origin',
			callback: callbackName,
		});
		script.src = `https://maps.googleapis.com/maps/api/js?${parameters}`;
		script.async = true;
		await new Promise((resolve, reject) => {
			window[callbackName] = () => {
				delete window[callbackName];
				resolve();
			};
			script.onerror = () => {
				delete window[callbackName];
				reject(new Error('Google Maps failed to load.'));
			};
			document.head.append(script);
		});

		const { Circle, Map, Polygon } = await window.google.maps.importLibrary('maps');
		const tiePoint = {
			lat: Number(selectedProperty.tie_latitude),
			lng: Number(selectedProperty.tie_longitude),
		};
		googleMap = new Map(mapElement, {
			center: tiePoint,
			zoom: 17,
			mapTypeId: 'roadmap',
			zoomControl: false,
			mapTypeControl: false,
			streetViewControl: false,
			fullscreenControl: false,
		});

		const boundary = (selectedProperty.boundary ?? []).map(([longitude, latitude]) => ({
			lat: Number(latitude),
			lng: Number(longitude),
		}));
		if (boundary.length) {
			new Polygon({
				map: googleMap,
				paths: boundary,
				strokeColor: '#e45b3f',
				strokeOpacity: 0.95,
				strokeWeight: 3,
				fillColor: '#e45b3f',
				fillOpacity: selectedProperty.closure_error_meters <= 0.25 ? 0.17 : 0,
			});
		}
		new Circle({
			map: googleMap,
			center: tiePoint,
			radius: 5,
			fillColor: '#21483a',
			fillOpacity: 1,
			strokeColor: '#fff',
			strokeWeight: 2,
		});
		mapStatus.hidden = true;
		centerOnProperty();
	} catch {
		showMapStatus('Google Maps could not load. Check the API key, billing, and Maps JavaScript API settings.');
	}
}

window.gm_authFailure = () => showMapStatus('Google Maps rejected the API key. Check its website restrictions and API settings.');
document.querySelector('[data-zoom-in]')?.addEventListener('click', () => {
	if (googleMap) googleMap.setZoom(Math.min(21, (googleMap.getZoom() ?? 17) + 1));
});
document.querySelector('[data-zoom-out]')?.addEventListener('click', () => {
	if (googleMap) googleMap.setZoom(Math.max(2, (googleMap.getZoom() ?? 17) - 1));
});
document.querySelector('[data-center-map]')?.addEventListener('click', centerOnProperty);
void initializeGoogleMap();

const arOverlay = document.querySelector('#arOverlay');
const arVideo = document.querySelector('#arVideo');
const arCanvas = document.querySelector('#arCanvas');
let cameraStream;
let gpsWatch;
let currentLocation;
let compassHeading;

function closeArView() {
	if (!arOverlay) return;
	arOverlay.hidden = true;
	if (cameraStream) cameraStream.getTracks().forEach((track) => track.stop());
	if (gpsWatch !== undefined) navigator.geolocation?.clearWatch(gpsWatch);
	window.removeEventListener('deviceorientation', updateHeading);
	cameraStream = undefined;
	gpsWatch = undefined;
	document.body.style.overflow = '';
}

function updateHeading(event) {
	if (typeof event.webkitCompassHeading === 'number') {
		compassHeading = event.webkitCompassHeading;
	} else if (typeof event.alpha === 'number') {
		compassHeading = (360 - event.alpha + (window.screen.orientation?.angle ?? 0)) % 360;
	}
	document.querySelector('#arHeading').textContent = Number.isFinite(compassHeading)
		? `Approx. compass ${compassHeading.toFixed(0)}° · device heading`
		: 'Compass heading unavailable';
	drawArBoundary();
}

function drawArBoundary() {
	if (!arCanvas || !currentLocation || !Number.isFinite(compassHeading) || !selectedProperty?.boundary) return;
	const rect = arCanvas.getBoundingClientRect();
	const ratio = window.devicePixelRatio || 1;
	arCanvas.width = Math.round(rect.width * ratio);
	arCanvas.height = Math.round(rect.height * ratio);
	const context = arCanvas.getContext('2d');
	context.setTransform(ratio, 0, 0, ratio, 0, 0);
	context.clearRect(0, 0, rect.width, rect.height);
	const fieldOfView = 70;
	const screenPoints = selectedProperty.boundary.map(([longitude, latitude]) => {
		const lat1 = currentLocation.latitude * Math.PI / 180;
		const lat2 = Number(latitude) * Math.PI / 180;
		const deltaLongitude = (Number(longitude) - currentLocation.longitude) * Math.PI / 180;
		const y = Math.sin(deltaLongitude) * Math.cos(lat2);
		const x = Math.cos(lat1) * Math.sin(lat2) - Math.sin(lat1) * Math.cos(lat2) * Math.cos(deltaLongitude);
		const bearing = (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
		const relativeBearing = ((bearing - compassHeading + 540) % 360) - 180;
		if (Math.abs(relativeBearing) > 85) return null;
		const screenX = rect.width / 2 + Math.tan(relativeBearing * Math.PI / 180) / Math.tan(fieldOfView * Math.PI / 360) * rect.width / 2;
		return { x: screenX, y: rect.height * 0.68 };
	});
	context.beginPath();
	screenPoints.forEach((point, index) => {
		if (!point) return;
		const priorPoints = screenPoints.slice(0, index).reverse();
		if (index === 0 || !priorPoints.some((prior) => prior)) context.moveTo(point.x, point.y);
		else context.lineTo(point.x, point.y);
	});
	context.setLineDash([7, 6]);
	context.lineWidth = 3;
	context.strokeStyle = '#f1c55c';
	context.stroke();
	context.setLineDash([]);
	screenPoints.forEach((point, index) => {
		if (!point) return;
		context.beginPath();
		context.arc(point.x, point.y, 5, 0, Math.PI * 2);
		context.fillStyle = '#f1c55c';
		context.fill();
		if (index < selectedProperty.courses.length) {
			context.fillStyle = '#fff';
			context.font = '11px sans-serif';
			context.fillText(String(index + 1), point.x + 8, point.y - 9);
		}
	});
}

document.querySelector('[data-open-ar]')?.addEventListener('click', async () => {
	const status = document.querySelector('#arLocation');
	if (!navigator.mediaDevices?.getUserMedia || !navigator.geolocation) {
		status.textContent = 'Camera and location need a supported mobile browser on HTTPS.';
		arOverlay.hidden = false;
		return;
	}
	arOverlay.hidden = false;
	document.body.style.overflow = 'hidden';
	try {
		if ('DeviceOrientationEvent' in window && typeof window.DeviceOrientationEvent.requestPermission === 'function') {
			const permission = await window.DeviceOrientationEvent.requestPermission();
			if (permission !== 'granted') throw new Error('Compass permission is required to align the view.');
		}
		cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
		arVideo.srcObject = cameraStream;
		await arVideo.play();
		window.addEventListener('deviceorientation', updateHeading, true);
		gpsWatch = navigator.geolocation.watchPosition((position) => {
			currentLocation = { latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: position.coords.accuracy };
			document.querySelector('#arLocation').textContent = `GPS ±${Math.round(position.coords.accuracy)} m`;
			drawArBoundary();
		}, (error) => {
			status.textContent = error.code === 1 ? 'Allow location access to position the preview.' : 'Location unavailable. Move to an open area and retry.';
		}, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
	} catch (error) {
		status.textContent = error.message || 'Camera permission was denied or unavailable.';
	}
});
document.querySelector('[data-close-ar]')?.addEventListener('click', closeArView);
