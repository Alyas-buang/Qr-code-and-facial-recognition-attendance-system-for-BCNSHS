<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Scanner | BCNSHS</title>
    <link rel="icon" type="image/jpeg" href="../assets/css/logo.jpg">
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/legacy-theme.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <script src="../assets/js/face-api.js"></script>
    <script src="../assets/js/face-model-loader.js"></script>
    <script src="../assets/js/html5-qrcode.min.js"></script>
    <script src="../assets/js/anime.min.js"></script>
    <script src="../assets/js/anime-utils.js"></script>
    <script src="../assets/js/form-utils.js"></script>
</head>
<body class="scanner-page min-h-screen bg-base-200 p-4">
<div class="app-container container mx-auto mt-6 max-w-2xl">
    <div class="mb-3 flex items-center">
        <button class="btn btn-sm btn-neutral border border-base-300 shadow-md" onclick="goBack()">Back</button>
    </div>
    <div class="card border border-base-300 bg-base-100 shadow-xl">
        <div class="card-body p-4 sm:p-6">
        <div class="mb-3 flex items-center gap-3">
            <img src="../assets/css/logo.jpg" alt="Logo" class="logo-small hoverable-media h-12 w-12 rounded-full object-cover">
            <div>
                <h2 class="text-xl font-bold">BCNSHS Scanner</h2>
                <div id="step-indicator" class="step-text text-sm text-base-content opacity-70">Step 1: Scan QR Code</div>
            </div>
        </div>

        <div class="mb-4 w-full rounded-box bg-base-300">
            <div id="progress-bar" class="h-2 w-0 rounded-box bg-primary"></div>
        </div>

        <div id="reader-wrapper" class="space-y-2">
            <div id="reader" class="mx-auto overflow-hidden rounded-box border border-base-300"></div>
            <p class="hint mt-2 text-sm text-base-content opacity-70">Center your student QR code in the box</p>
            <p id="qr-status" class="hint mt-2 text-sm text-base-content opacity-70"></p>
        </div>

        <div id="verify" class="hidden">
            <div id="info" class="alert mt-3"></div>
            
            <div>
                <video id="video" class="mx-auto mt-3 w-full rounded-box bg-black" autoplay muted playsinline></video>
                <div class="face-overlay"></div>
            </div>
            
            <div class="mt-3 flex items-center gap-2">
                <span id="status-spinner" class="loading loading-spinner loading-sm hidden"></span>
                <p id="status" class="text-sm">Initializing Face Recognition...</p>
            </div>
        </div>
        
        <button class="btn-cancel btn btn-error mt-4" onclick="location.reload()">Reset Scanner</button>
        </div>
    </div>
</div>

<div id="attendance-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-30 p-4" aria-live="polite" aria-hidden="true">
    <div class="card w-full max-w-sm bg-base-100 shadow-2xl">
        <div class="card-body text-center">
        <h3 id="attendance-modal-title" class="text-xl font-bold text-success">Attendance logged</h3>
        <p id="attendance-modal-text" class="text-sm opacity-80"></p>
        </div>
    </div>
</div>


<script>
const video = document.getElementById("video");
const readerDiv = document.getElementById("reader");
const verifyDiv = document.getElementById("verify");
const statusMsg = document.getElementById("status");
const infoDiv = document.getElementById("info");
const attendanceModal = document.getElementById("attendance-modal");
const attendanceModalText = document.getElementById("attendance-modal-text");
const stepIndicator = document.getElementById("step-indicator");
const progressBar = document.getElementById("progress-bar");
const statusSpinner = document.getElementById("status-spinner");

let student = null;
let targetDescriptor = null;
let attendanceToken = null;
let isLocked = false;
let stream = null;
let modalTimer = null;
let isQrRunning = false;
let faceModelsReady = false;
let modelsLoadPromise = null;
const MODEL_URL = '../../model/face-api';
const MODEL_FILES = [
    'tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1',
    'face_landmark_68_tiny_model-weights_manifest.json',
    'face_landmark_68_tiny_model-shard1',
    'face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1',
    'face_recognition_model-shard2'
];

function setProgress(percent, isError = false) {
    if (!progressBar) return;
    const clamped = Math.max(0, Math.min(100, Number(percent) || 0));
    progressBar.style.width = `${clamped}%`;
    progressBar.style.background = isError
        ? "linear-gradient(90deg, #ef4444, #f97316)"
        : "linear-gradient(90deg, #2563eb, #0ea5e9, #22c55e)";
}

function setLoadingState(isLoading) {
    if (!statusSpinner) return;
    statusSpinner.classList.toggle("hidden", !isLoading);
}

function showAttendanceModal(studentName, guardianInformed) {
    if (!attendanceModal || !attendanceModalText) return;

    if (modalTimer) {
        clearTimeout(modalTimer);
    }

    attendanceModalText.textContent = guardianInformed
        ? `guardian/parent of "${studentName}" has been informed`
        : "";
    attendanceModal.classList.remove("hidden");
    attendanceModal.classList.add("flex");
    attendanceModal.setAttribute("aria-hidden", "false");

    modalTimer = setTimeout(() => {
        attendanceModal.classList.remove("flex");
        attendanceModal.classList.add("hidden");
        attendanceModal.setAttribute("aria-hidden", "true");
    }, 1500);
}

function goBack() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    const current = new URL(window.location.href);
    const referrer = document.referrer ? new URL(document.referrer, window.location.origin) : null;
    const canUseHistory =
        window.history.length > 1 &&
        referrer &&
        referrer.origin === current.origin &&
        referrer.pathname !== current.pathname;

    if (canUseHistory) {
        window.history.back();
        return;
    }
    window.location.replace("../../src/home.php");
}

function toErrorMessage(err) {
    if (window.FaceModelLoader) {
        return FaceModelLoader.toErrorMessage(err);
    }
    if (err && typeof err === "object") {
        if (typeof err.message === "string" && err.message.trim() !== "") {
            return err.message;
        }
        if (typeof err.name === "string" && err.name.trim() !== "") {
            return err.name;
        }
    }
    if (typeof err === "string" && err.trim() !== "") {
        return err;
    }
    return "Unknown error";
}

/* ---------- LOAD FACE MODELS (ONCE) ---------- */
async function loadModels(maxAttempts = 3) {
    if (!window.FaceModelLoader) {
        statusMsg.style.color = "red";
        statusMsg.innerText = "Shared model loader is missing. Tap Reset Scanner and try again.";
        setProgress(100, true);
        setLoadingState(false);
        return false;
    }

    let lastError = null;
    setLoadingState(true);
    const loaded = await FaceModelLoader.loadFaceApiModels({
        modelUrl: MODEL_URL,
        modelFiles: MODEL_FILES,
        loaders: [
            (url) => faceapi.nets.tinyFaceDetector.loadFromUri(url),
            (url) => faceapi.nets.faceLandmark68TinyNet.loadFromUri(url),
            (url) => faceapi.nets.faceRecognitionNet.loadFromUri(url)
        ],
        maxAttempts,
        onAttempt: (attempt, totalAttempts) => {
            statusMsg.innerText = `Loading face models (${attempt}/${totalAttempts})...`;
            setProgress(22 + Math.round((attempt / totalAttempts) * 28));
        },
        onSuccess: () => {
            console.log("Face models loaded");
            statusMsg.innerText = "Models loaded. Ready to scan QR.";
            setProgress(55);
            setLoadingState(false);
        },
        onFailure: (err) => {
            lastError = err ?? new Error("Model load attempt failed");
        }
    });
    if (loaded) {
        return true;
    }

    console.error('Model loading failed:', toErrorMessage(lastError), lastError);
    statusMsg.style.color = "red";
    statusMsg.innerText = "Model load failed. Check connection and refresh.";
    setProgress(100, true);
    setLoadingState(false);
    return false;
}

/* ---------- INIT QR SCANNER ---------- */
const qr = new Html5Qrcode("reader");
const readerWrapper = document.getElementById("reader-wrapper");
const qrStatus = document.getElementById("qr-status");

function setQrStatus(text, isError = false) {
    if (!qrStatus) return;
    qrStatus.innerText = text;
    qrStatus.style.color = isError ? "#b91c1c" : "";
}

function computeQrBox() {
    const wrapperWidth = readerWrapper ? readerWrapper.clientWidth : window.innerWidth;
    const size = Math.round(Math.max(220, Math.min(420, wrapperWidth * 0.72)));
    return { width: size, height: size };
}

async function stopQrScannerIfRunning() {
    if (!isQrRunning) return;
    try {
        await qr.stop();
    } catch (err) {
        console.warn("QR stop warning:", toErrorMessage(err));
    }
    try {
        await qr.clear();
    } catch (err) {
        console.warn("QR clear warning:", toErrorMessage(err));
    }
    isQrRunning = false;
}

async function startQrScanner() {
    if (stepIndicator) {
        stepIndicator.innerText = "Step 1: Scan QR Code";
    }
    statusMsg.style.color = "";
    statusMsg.innerText = "Loading face verification models...";
    setLoadingState(true);
    setProgress(15);
    setQrStatus("Requesting camera access...");

    try {
        await qr.start(
            { facingMode: "environment" },
            { fps: 15, qrbox: computeQrBox() },
            async (code) => {
                await stopQrScannerIfRunning();
                await (window.FaceModelLoader
                    ? FaceModelLoader.sleep(120)
                    : new Promise(resolve => setTimeout(resolve, 120)));

                readerDiv.style.display = "none";
                verifyDiv.style.display = "block";
                if (stepIndicator) {
                    stepIndicator.innerText = "Step 2: Face Verification";
                }
                setProgress(70);
                if (modelsLoadPromise && !faceModelsReady) {
                    statusMsg.style.color = "";
                    statusMsg.innerText = "Finalizing face models...";
                    await modelsLoadPromise;
                }
                if (!faceModelsReady) {
                    statusMsg.style.color = "red";
                    statusMsg.innerText = "Face models failed to load. Tap Reset Scanner and try again.";
                    setProgress(100, true);
                    setLoadingState(false);
                    return;
                }
                handleStudent(code);
            }
        );
        isQrRunning = true;
        setQrStatus("QR scanner ready.");
    } catch (err) {
        const message = toErrorMessage(err);
        console.error("QR scanner start failed:", message, err);
        if (message.includes("NotAllowedError")) {
            setQrStatus("Camera permission denied/dismissed. Allow camera then tap Reset Scanner.", true);
            setProgress(100, true);
            setLoadingState(false);
            return;
        }
        setQrStatus(`Unable to start QR camera (${message}). Tap Reset Scanner and try again.`, true);
        setProgress(100, true);
        setLoadingState(false);
    }
}

async function initScanner() {
    await startQrScanner();

    modelsLoadPromise = loadModels(3)
        .then((ok) => {
            faceModelsReady = !!ok;
            if (!ok) {
                setQrStatus("QR ready, but face models failed to load. Tap Reset Scanner after checking connection.", true);
            }
            return ok;
        })
        .catch((err) => {
            faceModelsReady = false;
            console.error("Face model load fatal:", toErrorMessage(err), err);
            setQrStatus("QR ready, but face models failed to load. Tap Reset Scanner.", true);
            return false;
        });
}

document.addEventListener("DOMContentLoaded", () => {
    initScanner().catch((err) => {
        console.error("Scanner init failed:", toErrorMessage(err), err);
        statusMsg.style.color = "red";
        statusMsg.innerText = "Scanner initialization failed. Refresh and try again.";
    });
});

/* ---------- FETCH STUDENT ---------- */
async function handleStudent(code) {
    statusMsg.innerText = "Loading student data...";
    setLoadingState(true);
    setProgress(78);

    const res = await fetch("../../src/api/get_student.php?code=" + encodeURIComponent(code));
    const raw = await res.text();

    try {
        student = JSON.parse(raw);
    } catch (e) {
        console.error("Invalid JSON from get_student.php:", raw);
        statusMsg.style.color = "red";
        statusMsg.innerText = "Server returned invalid response";
        setProgress(100, true);
        setLoadingState(false);
        return;
    }

    if (!student.success) {
        alert(student.message || "Student not found");
        location.reload();
        return;
    }

    infoDiv.style.display = "block";
    infoDiv.textContent = "";
    const nameEl = document.createElement("h3");
    nameEl.textContent = student.fullname || "";
    const idEl = document.createElement("p");
    idEl.textContent = `ID: ${student.student_id || ""}`;
    const sectionEl = document.createElement("p");
    sectionEl.textContent = `Section: ${student.grade_section || ""}`;
    infoDiv.appendChild(nameEl);
    infoDiv.appendChild(idEl);
    infoDiv.appendChild(sectionEl);

    targetDescriptor = new Float32Array(student.descriptor);
    attendanceToken = student.attendance_token || null;

    startFaceCheck();
}

/* ---------- FACE VERIFICATION ---------- */
async function startFaceCheck() {
    statusMsg.innerText = "Align your face to the camera";
    setProgress(85);
    setLoadingState(true);

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: "user",
                width: { ideal: 480 },
                height: { ideal: 360 },
                frameRate: { ideal: 24, max: 30 }
            }
        });
    } catch (err) {
        alert("Camera is busy. Please refresh the page.");
        console.error(err);
        return;
    }

    video.srcObject = stream;

    video.onplay = () => {
        const detectorOptions = new faceapi.TinyFaceDetectorOptions({
            inputSize: 160,
            scoreThreshold: 0.45
        });
        const minDetectionGapMs = 220;
        let lastDetectionAt = 0;
        let isDetecting = false;
        let consecutiveMatches = 0;

        const detectLoop = async () => {
            if (isLocked) return;

            const now = performance.now();
            if (isDetecting || now - lastDetectionAt < minDetectionGapMs) {
                requestAnimationFrame(detectLoop);
                return;
            }

            isDetecting = true;
            lastDetectionAt = now;

            try {
                const result = await faceapi
                    .detectSingleFace(video, detectorOptions)
                    .withFaceLandmarks(true)
                    .withFaceDescriptor();

                if (!result) {
                    consecutiveMatches = 0;
                } else {
                    const distance = faceapi.euclideanDistance(
                        result.descriptor,
                        targetDescriptor
                    );

                    if (distance < 0.48) {
                        consecutiveMatches += 1;
                        if (consecutiveMatches >= 2) {
                            isLocked = true;
                            statusMsg.innerText = "Face matched. Saving...";
                            setProgress(92);
                            setLoadingState(true);
                            saveAttendance();
                            return;
                        }
                    } else {
                        consecutiveMatches = 0;
                    }
                }
            } catch (err) {
                console.error("Face detection error:", err);
                consecutiveMatches = 0;
            } finally {
                isDetecting = false;
            }

            requestAnimationFrame(detectLoop);
        };

        requestAnimationFrame(detectLoop);
    };
}


/* ---------- SAVE ATTENDANCE ---------- */
async function saveAttendance() {
    const canvas = document.createElement("canvas");
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    const ctx = canvas.getContext("2d");
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    const photo = canvas.toDataURL("image/jpeg", 0.7);

    // Stop camera AFTER capture
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }

    statusMsg.innerText = "Saving attendance...";
    setProgress(96);
    setLoadingState(true);

    try {
        const response = await fetch("../../src/api/log_attendance.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
                student_id: student.student_id,
                attendance_token: attendanceToken,
                photo: photo
            })
        });

        const raw = await response.text();
        let resData;
        try {
            resData = JSON.parse(raw);
        } catch (e) {
            console.error("Invalid JSON from log_attendance.php:", raw);
            statusMsg.style.color = "red";
            statusMsg.innerText = "Server returned invalid response";
            setProgress(100, true);
            setLoadingState(false);
            setTimeout(() => location.reload(), 2000);
            return;
        }

        if (resData.success) {
            statusMsg.style.color = "green";
            statusMsg.innerText = "Attendance logged";
            setProgress(100);
            setLoadingState(false);
            const guardianInformed = typeof resData.message === "string" && resData.message.toLowerCase().includes("email sent");
            showAttendanceModal(student.fullname || "student", guardianInformed);
        } else {
            statusMsg.style.color = "red";
            statusMsg.innerText = "Failed to save attendance";
            setProgress(100, true);
            setLoadingState(false);
        }
    } catch (err) {
        console.error(err);
        statusMsg.innerText = "Server error";
        setProgress(100, true);
        setLoadingState(false);
    }

setTimeout(() => location.reload(), 2000);
}

setProgress(10);
setLoadingState(true);
</script>

</body>
</html>
