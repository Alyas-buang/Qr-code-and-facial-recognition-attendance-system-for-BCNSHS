<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Scanner | BCNSHS</title>
    <link rel="icon" type="image/jpeg" href="../assets/css/logo.jpg">
    <script src="../assets/js/face-api.js"></script>
    <script src="../assets/js/html5-qrcode.min.js"></script>
    <link rel="stylesheet" href="../assets/css/scanner_style.css">
</head>
<body>
<button class="back-btn" onclick="goBack()">Back</button>

<div class="app-container">
    <div class="scanner-card">
        <div class="header">
            <img src="../assets/css/logo.jpg" alt="Logo" class="logo-small">
            <div>
                <h2>BCNSHS Scanner</h2>
                <div id="step-indicator" class="step-text">Step 1: Scan QR Code</div>
            </div>
        </div>

        <div class="progress-container">
            <div id="progress-bar" class="progress-fill"></div>
        </div>

        <div id="reader-wrapper">
            <div id="reader"></div>
            <p class="hint">Center your student QR code in the box</p>
        </div>

        <div id="verify" class="hidden">
            <div id="info" class="student-card"></div>
            
            <div class="video-container">
                <video id="video" autoplay muted playsinline></video>
                <div class="face-overlay"></div>
            </div>
            
            <div class="status-box">
                <div id="status-spinner" class="spinner"></div>
                <p id="status">Initializing Face Recognition...</p>
            </div>
        </div>
        
        <button class="btn-cancel" onclick="location.reload()">Reset Scanner</button>
    </div>
</div>

<div id="attendance-modal" class="attendance-modal" aria-live="polite" aria-hidden="true" style="position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.35);z-index:2000;">
    <div class="attendance-modal-card" style="width:min(90vw,360px);background:#fff;border-radius:14px;padding:18px 16px;text-align:center;box-shadow:0 18px 40px rgba(0,0,0,.25);">
        <h3 id="attendance-modal-title" style="margin:0;color:#16a34a;font-size:1.2rem;">Attendance logged</h3>
        <p id="attendance-modal-text" style="margin:8px 0 0;color:#14532d;font-size:.9rem;"></p>
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

let student = null;
let targetDescriptor = null;
let attendanceToken = null;
let isLocked = false;
let stream = null;
let modalTimer = null;
const MODEL_URL = '../../model/face-api';
const MODEL_FILES = [
    'tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1',
    'face_landmark_68_model-weights_manifest.json',
    'face_landmark_68_model-shard1',
    'face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1',
    'face_recognition_model-shard2'
];

function showAttendanceModal(studentName, guardianInformed) {
    if (!attendanceModal || !attendanceModalText) return;

    if (modalTimer) {
        clearTimeout(modalTimer);
    }

    attendanceModalText.textContent = guardianInformed
        ? `guardian/parent of "${studentName}" has been informed`
        : "";
    attendanceModal.style.display = "flex";
    attendanceModal.setAttribute("aria-hidden", "false");

    modalTimer = setTimeout(() => {
        attendanceModal.style.display = "none";
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

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function fetchWithRetry(url, attempts = 3) {
    let lastError = null;
    for (let i = 1; i <= attempts; i++) {
        try {
            const res = await fetch(url, { cache: 'force-cache' });
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            return true;
        } catch (err) {
            lastError = err;
            await sleep(250 * i);
        }
    }
    throw lastError || new Error('Failed to fetch model file');
}

async function warmModelAssets() {
    await Promise.all(MODEL_FILES.map(file => fetchWithRetry(`${MODEL_URL}/${file}`, 3)));
}

/* ---------- LOAD FACE MODELS (ONCE) ---------- */
async function loadModels(maxAttempts = 3) {
    let lastError = null;
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            statusMsg.innerText = `Loading face models (${attempt}/${maxAttempts})...`;
            await warmModelAssets();
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
            ]);
            console.log("Face models loaded");
            statusMsg.innerText = "Models loaded. Ready to scan QR.";
            return true;
        } catch (err) {
            lastError = err;
            await sleep(500 * attempt);
        }
    }

    console.error('Model loading failed:', lastError);
    statusMsg.style.color = "red";
    statusMsg.innerText = "Model load failed. Check connection and refresh.";
    return false;
}

window.onload = loadModels;

/* ---------- INIT QR SCANNER ---------- */
const qr = new Html5Qrcode("reader");
const readerWrapper = document.getElementById("reader-wrapper");

function computeQrBox() {
    const wrapperWidth = readerWrapper ? readerWrapper.clientWidth : window.innerWidth;
    const size = Math.round(Math.max(220, Math.min(420, wrapperWidth * 0.72)));
    return { width: size, height: size };
}

qr.start(
    { facingMode: "environment" },
    { fps: 15, qrbox: computeQrBox() },
    async (code) => {
        // FULLY stop QR camera
        await qr.stop();
        await qr.clear();

        // SMALL HARDWARE RELEASE DELAY (CRITICAL)
        await new Promise(r => setTimeout(r, 500));

        readerDiv.style.display = "none";
        verifyDiv.style.display = "block";
        handleStudent(code);
    }
);

/* ---------- FETCH STUDENT ---------- */
async function handleStudent(code) {
    statusMsg.innerText = "Loading student data...";

    const res = await fetch("../../src/api/get_student.php?code=" + encodeURIComponent(code));
    const raw = await res.text();

    try {
        student = JSON.parse(raw);
    } catch (e) {
        console.error("Invalid JSON from get_student.php:", raw);
        statusMsg.style.color = "red";
        statusMsg.innerText = "Server returned invalid response";
        return;
    }

    if (!student.success) {
        alert("Student not found");
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

    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: "user",
                width: 640,
                height: 480
            }
        });
    } catch (err) {
        alert("Camera is busy. Please refresh the page.");
        console.error(err);
        return;
    }

    video.srcObject = stream;

    video.onplay = () => {
        const timer = setInterval(async () => {
            if (isLocked) return;

            const result = await faceapi
                .detectSingleFace(
                    video,
                    new faceapi.TinyFaceDetectorOptions({
                        inputSize: 224,
                        scoreThreshold: 0.5
                    })
                )
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!result) return;

            const distance = faceapi.euclideanDistance(
                result.descriptor,
                targetDescriptor
            );

            if (distance < 0.48) {
                isLocked = true;
                clearInterval(timer);
                statusMsg.innerText = "Face matched. Saving...";
                saveAttendance();
            }
        }, 700);
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
            setTimeout(() => location.reload(), 2000);
            return;
        }

        if (resData.success) {
            statusMsg.style.color = "green";
            statusMsg.innerText = "Attendance logged";
            const guardianInformed = typeof resData.message === "string" && resData.message.toLowerCase().includes("email sent");
            showAttendanceModal(student.fullname || "student", guardianInformed);
        } else {
            statusMsg.style.color = "red";
            statusMsg.innerText = "Failed to save attendance";
        }
    } catch (err) {
        console.error(err);
        statusMsg.innerText = "Server error";
    }

setTimeout(() => location.reload(), 2000);
}
</script>

</body>
</html>
