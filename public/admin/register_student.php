<?php
require_once __DIR__ . "/auth.php";
admin_require_login();
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Student</title>
    <link rel="icon" type="image/jpeg" href="../assets/css/logo.jpg">
    <link rel="stylesheet" href="../assets/css/app.css">
    <link rel="stylesheet" href="../assets/css/legacy-theme.css">
    <link rel="stylesheet" href="../assets/css/animations.css">
    <script src="../assets/js/face-api.js"></script>
    <script src="../assets/js/face-model-loader.js"></script>
    <script src="../assets/js/anime.min.js"></script>
    <script src="../assets/js/anime-utils.js"></script>
    <script src="../assets/js/form-utils.js"></script>
</head>
<body class="register-page min-h-screen bg-base-200">
<?php
$headerLogoSrc = "../assets/css/logo.jpg";
$headerHomeHref = "../../src/home.php";
include "../../src/includes/header.php";
?>

<main class="container mx-auto max-w-5xl space-y-4 px-4 py-6">
    <section class="register-hero card border border-base-300 bg-base-100 shadow-lg">
        <div class="card-body flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="field-grid space-y-2">
            <p class="text-xs font-semibold uppercase tracking-widest text-primary">Enrollment</p>
            <h1 class="text-2xl font-bold sm:text-3xl">Register Student Profile</h1>
            <p class="max-w-3xl text-sm opacity-80">Capture student details, verify a live facial descriptor, and automatically generate a QR code for attendance check-ins.</p>
        </div>
        <div class="menu-wrap relative ml-auto">
            <button type="button" id="register-menu-toggle" class="menu-toggle btn btn-square btn-sm relative z-40" aria-label="Open register menu" aria-expanded="false" aria-controls="register-menu">&#9776;</button>
            <div id="register-menu" class="menu-drawer absolute right-0 top-full z-30 mt-2 flex w-56 flex-col gap-2 rounded-box border border-base-300 bg-base-100 p-2 shadow-xl" hidden>
                <a href="dashboard.php" class="action-link btn btn-primary btn-sm">Dashboard</a>
                <a href="manage_students.php" class="action-link btn btn-info btn-sm">Manage Students</a>
                <a href="logout.php" class="action-link btn btn-error btn-sm">Logout</a>
            </div>
        </div>
        </div>
    </section>

    <section class="card border border-base-300 bg-base-100 shadow" id="reg-form-container">
        <div class="card-body p-4 sm:p-6">
        <h2 class="text-xl font-semibold">Student Information</h2>
        <p id="loading-overlay" class="alert alert-info"><span id="loading-circle" class="loading loading-spinner loading-sm" aria-hidden="true"></span><span id="loading-text">Initializing AI Models...</span></p>

        <div id="student-fields" class="space-y-2">
            <label for="student_id">Student ID</label>
            <input class="input input-bordered w-full" id="student_id" type="text" placeholder="e.g. 2024-001">

            <label for="fullname">Full Name</label>
            <input class="input input-bordered w-full" id="fullname" type="text" placeholder="Student full name">

            <label for="grade">Grade & Section</label>
            <input class="input input-bordered w-full" id="grade" type="text" placeholder="e.g. Grade 11 - A">

            <label for="parent_email">Parent Email Address</label>
            <input class="input input-bordered w-full" id="parent_email" type="email" placeholder="parent@example.com">
        </div>

        <div class="camera-wrap mt-4">
            <p class="mb-2 text-sm opacity-70">Keep the student's face centered with good lighting before submitting.</p>
            <video id="video" class="mx-auto w-full max-w-md rounded-box bg-black" autoplay muted></video>
        </div>

        <button class="btn btn-primary mt-4 w-full" id="reg-btn" onclick="register()" disabled>Wait for Models...</button>

        <div id="qr-result" class="hidden rounded-box border border-base-300 p-4 text-center">
            <h3 class="text-lg font-semibold text-success">Registration Complete</h3>
            <p>Use this QR for attendance scanning:</p>
            <img id="qr-image" class="hoverable-media mx-auto mt-2" src="" alt="Student QR Code" width="180">
            <p><small id="qr-val-text"></small></p>
            <button onclick="window.location.reload()" class="btn btn-info mt-2">Register Another Student</button>
        </div>
        </div>
    </section>
</main>

<script>
const video = document.getElementById("video");
const regBtn = document.getElementById("reg-btn");
const MODEL_URL = '../../model/face-api'; 
const csrfToken = <?php echo json_encode($csrfToken); ?>;
const loadingOverlay = document.getElementById('loading-overlay');
const loadingCircle = document.getElementById('loading-circle');
const loadingText = document.getElementById('loading-text');
const MODEL_FILES = [
    'tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1',
    'face_landmark_68_model-weights_manifest.json',
    'face_landmark_68_model-shard1',
    'face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1',
    'face_recognition_model-shard2'
];

function setOverlayStatus(text, isBusy = true, isError = false) {
    if (loadingText) {
        loadingText.innerText = text;
    } else if (loadingOverlay) {
        loadingOverlay.innerText = text;
    }
    if (loadingCircle) {
        loadingCircle.style.display = isBusy ? "inline-block" : "none";
    }
    if (loadingOverlay) {
        loadingOverlay.style.color = isError ? "#b91c1c" : "#2563eb";
        loadingOverlay.style.borderColor = isError ? "#fecaca" : "#bfdbfe";
        loadingOverlay.style.background = isError ? "#fef2f2" : "linear-gradient(90deg, #dbeafe, #cffafe)";
    }
}

const menuToggle = document.getElementById("register-menu-toggle");
const menuDrawer = document.getElementById("register-menu");

if (menuToggle && menuDrawer) {
    const closeMenu = function () {
        menuDrawer.hidden = true;
        menuDrawer.classList.remove("open");
        menuToggle.setAttribute("aria-expanded", "false");
    };

    const openMenu = function () {
        menuDrawer.hidden = false;
        menuDrawer.classList.add("open");
        menuToggle.setAttribute("aria-expanded", "true");
    };

    const isMenuOpen = function () {
        return menuToggle.getAttribute("aria-expanded") === "true" || !menuDrawer.hidden;
    };

    closeMenu();
    window.addEventListener("pageshow", closeMenu);

    menuToggle.addEventListener("click", function (event) {
        event.stopPropagation();
        if (isMenuOpen()) {
            closeMenu();
            return;
        }
        openMenu();
    });

    document.addEventListener("click", function (event) {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }
        if (!target.closest(".menu-wrap")) {
            closeMenu();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeMenu();
        }
    });
}

// 1. Load Models & Start Camera
async function init() {
    try {
        if (!window.FaceModelLoader) {
            throw new Error("Shared face loader utility is not available.");
        }
        const loaded = await FaceModelLoader.loadFaceApiModels({
            modelUrl: MODEL_URL,
            modelFiles: MODEL_FILES,
            loaders: [
                (url) => faceapi.nets.tinyFaceDetector.loadFromUri(url),
                (url) => faceapi.nets.faceLandmark68Net.loadFromUri(url),
                (url) => faceapi.nets.faceRecognitionNet.loadFromUri(url)
            ],
            maxAttempts: 3,
            onAttempt: (attempt, maxAttempts) => {
                if (loadingOverlay) {
                    setOverlayStatus(`Loading AI models (${attempt}/${maxAttempts})...`, true, false);
                }
            }
        });
        if (!loaded) {
            throw new Error("Model loading failed");
        }
        if (loadingOverlay) {
            setOverlayStatus("AI Ready. Position your face.", false, false);
        }
        regBtn.disabled = false;
        regBtn.innerText = "Register & Generate QR";

        const cameraReady = await startRegistrationCamera();
        if (!cameraReady) {
            return;
        }
    } catch (err) {
        const errorName = err && typeof err === "object" ? (err.name || "") : "";
        if (errorName === "NotFoundError" || errorName === "DevicesNotFoundError" || errorName === "NotReadableError") {
            // Camera status already shown by startRegistrationCamera(); avoid masking it.
            console.error(window.FaceModelLoader ? FaceModelLoader.toErrorMessage(err) : err, err);
            return;
        }
        if (loadingOverlay) {
            setOverlayStatus("Model load failed. Check connection and refresh.", false, true);
        }
        console.error(window.FaceModelLoader ? FaceModelLoader.toErrorMessage(err) : err, err);
    }
}

async function startRegistrationCamera() {
    const attempts = [
        { video: { facingMode: "user" } },
        { video: { facingMode: "environment" } },
        { video: true }
    ];

    let lastError = null;
    for (const constraints of attempts) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = stream;
            return true;
        } catch (err) {
            lastError = err;
        }
    }

    const message = window.FaceModelLoader
        ? FaceModelLoader.toErrorMessage(lastError)
        : (lastError && lastError.message ? lastError.message : "Camera unavailable");

    if (loadingOverlay) {
        setOverlayStatus(`Camera unavailable (${message}). Connect/enable a camera, then refresh.`, false, true);
    }
    regBtn.disabled = true;
    regBtn.innerText = "Camera unavailable";
    return false;
}

init();

async function captureFaceDescriptorWithRetry(maxAttempts = 10) {
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        const detectionTiny = await faceapi
            .detectSingleFace(
                video,
                new faceapi.TinyFaceDetectorOptions({
                    inputSize: 224,
                    scoreThreshold: 0.45
                })
            )
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (detectionTiny) {
            return detectionTiny;
        }

        if (loadingOverlay) {
            setOverlayStatus("Face not clear yet... hold still and face the camera", true, false);
        }
        await (window.FaceModelLoader
            ? FaceModelLoader.sleep(250)
            : new Promise(resolve => setTimeout(resolve, 250)));
    }

    return null;
}

// 2. Registration Logic
async function register() {
    const sid = document.getElementById("student_id").value;
    const name = document.getElementById("fullname").value;
    const email = document.getElementById("parent_email").value;
    
    if (!sid || !name || !email) return alert("Please fill in ID, Name, and Email.");

    regBtn.innerText = "Processing Face... Please hold still";
    regBtn.disabled = true;
    setOverlayStatus("Processing face descriptor...", true, false);

    // Capture Face Descriptor with retries + detector fallback
    const detection = await captureFaceDescriptorWithRetry();

    if (!detection) {
        alert("Face not detected. Keep your full face centered, hold still for 2-3 seconds, and ensure good lighting.");
        setOverlayStatus("AI Ready. Position your face.", false, false);
        regBtn.disabled = false;
        regBtn.innerText = "Register & Generate QR";
        return;
    }

    const payload = {
        student_id: sid,
        fullname: name,
        grade: document.getElementById("grade").value,
        parent_email: email,
        descriptor: Array.from(detection.descriptor),
        csrf_token: csrfToken
    };

    // 3. Send to PHP and Handle JSON Response
    try {
        const response = await fetch("../../src/api/register_student.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            // Hide live capture UI once profile is saved
            const fieldGrid = document.getElementById('student-fields');
            const cameraWrap = document.querySelector('.camera-wrap');
            if (fieldGrid) fieldGrid.style.display = "none";
            if (cameraWrap) cameraWrap.style.display = "none";
            regBtn.style.display = "none";
            document.getElementById('loading-overlay').style.display = "none";

            // Show the QR image generated by PHP
            // PHP saves it as qrcodes/STUDENT_ID.png
            document.getElementById("qr-result").style.display = "block";
            document.getElementById("qr-image").src = "../assets/qrcodes/" + encodeURIComponent(sid) + ".png?" + new Date().getTime();
            document.getElementById("qr-val-text").innerText = "Unique ID: " + result.qr_value;
        } else {
            const conflictHint = result.conflict_student_id
                ? `\nMatched Student ID: ${result.conflict_student_id}\nMatched Name: ${result.conflict_fullname || "N/A"}`
                : "";
            alert("Registration Failed: " + (result.message || "Unknown error.") + conflictHint);
            regBtn.disabled = false;
            regBtn.innerText = "Register & Generate QR";
            setOverlayStatus("AI Ready. Position your face.", false, false);
        }
    } catch (err) {
        console.error(err);
        alert("Server error. Check if register_student.php is correct.");
        regBtn.disabled = false;
        setOverlayStatus("Server error. Please try again.", false, true);
    }
}
</script>
<?php include "../../src/includes/footer.php"; ?>
</body>
</html>
