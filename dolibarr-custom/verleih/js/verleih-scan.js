/**
 * Verleih - Kamera-Barcode-Scanner für loan_scan.php
 * Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * Gleiches Prinzip wie custom/scanproduct/js/scanproduct.js (Quagga2, lazy von CDN
 * geladen): Kamerabild wird nach einem erkannten Barcode automatisch ins vorhandene
 * Eingabefeld geschrieben und das bestehende Formular abgeschickt - der komplette
 * Server-Ablauf (Exemplar suchen, Schüler zuordnen) bleibt unverändert. Unterschied zu
 * scanproduct: unsere Etiketten sind CODE128 (Inventarnummer), keine EAN/UPC-Codes.
 */
(function () {
	'use strict';

	var cameraActive = false;
	var quaggaInitialized = false;

	var cameraToggleBtn = document.getElementById('verleih-scan-camera-toggle');
	var cameraContainer = document.getElementById('verleih-scan-camera-container');
	var cameraStatus = document.getElementById('verleih-scan-camera-status');
	var barcodeInput = document.getElementById('verleih-scan-barcode-input');
	var scanForm = document.getElementById('verleih-scan-form');

	function initCamera() {
		if (!cameraToggleBtn) return;
		cameraToggleBtn.addEventListener('click', function () {
			if (cameraActive) {
				stopCamera();
			} else {
				startCamera();
			}
		});
	}

	function startCamera() {
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			showStatus(verleihScanLang.cameraNotSupported, 'error');
			return;
		}
		if (typeof Quagga === 'undefined') {
			loadQuaggaLibrary(startCameraWithQuagga);
		} else {
			startCameraWithQuagga();
		}
	}

	function loadQuaggaLibrary(callback) {
		var script = document.createElement('script');
		script.src = 'https://cdn.jsdelivr.net/npm/@ericblade/quagga2@1.8.4/dist/quagga.min.js';
		script.onload = callback;
		script.onerror = function () {
			showStatus(verleihScanLang.cameraLibraryError, 'error');
		};
		document.head.appendChild(script);
	}

	function startCameraWithQuagga() {
		cameraContainer.style.display = 'block';
		showStatus(verleihScanLang.cameraInitializing, 'info');

		Quagga.init({
			inputStream: {
				name: 'Live',
				type: 'LiveStream',
				target: cameraContainer,
				constraints: {
					width: { min: 640, ideal: 1280, max: 1920 },
					height: { min: 480, ideal: 720, max: 1080 },
					facingMode: 'environment'
				}
			},
			decoder: {
				// Verleih inventory labels are printed as CODE128 only (see
				// VerleihLabelPdf), so we only need this single reader.
				readers: ['code_128_reader'],
				multiple: false
			},
			locate: true,
			locator: {
				patchSize: 'medium',
				halfSample: true
			},
			numOfWorkers: navigator.hardwareConcurrency || 4,
			frequency: 10
		}, function (err) {
			if (err) {
				showStatus(verleihScanLang.cameraPermissionDenied, 'error');
				cameraContainer.style.display = 'none';
				return;
			}
			Quagga.start();
			cameraActive = true;
			quaggaInitialized = true;
			cameraToggleBtn.textContent = verleihScanLang.disableCamera;
			showStatus(verleihScanLang.cameraActive, 'success');
		});

		Quagga.onDetected(function (result) {
			if (result && result.codeResult && result.codeResult.code) {
				var code = result.codeResult.code.trim();
				if (code.length > 0) {
					stopCamera();
					barcodeInput.value = code;
					showStatus(verleihScanLang.cameraDetected + ': ' + code, 'success');
					setTimeout(function () {
						scanForm.submit();
					}, 300);
				}
			}
		});
	}

	function stopCamera() {
		if (quaggaInitialized) {
			Quagga.stop();
			quaggaInitialized = false;
		}
		cameraActive = false;
		cameraContainer.style.display = 'none';
		if (cameraToggleBtn) {
			cameraToggleBtn.textContent = verleihScanLang.enableCamera;
		}
		showStatus('', '');
	}

	function showStatus(message, type) {
		if (!cameraStatus) return;
		cameraStatus.textContent = message;
		cameraStatus.className = 'vl-scan-status'.concat(type ? ' vl-scan-status-' + type : '');
	}

	function initBarcodeInput() {
		if (!barcodeInput) return;
		barcodeInput.focus();
	}

	document.addEventListener('DOMContentLoaded', function () {
		initCamera();
		initBarcodeInput();
	});

	window.addEventListener('beforeunload', function () {
		if (cameraActive) {
			stopCamera();
		}
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCamera);
	} else {
		initCamera();
	}
})();
