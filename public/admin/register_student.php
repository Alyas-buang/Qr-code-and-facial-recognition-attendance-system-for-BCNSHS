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
    
    <script src="../assets/js/face-api.js"></script>
    <link rel="stylesheet" href="../assets/css/register_student.css">
</head>
<body>
<?php
$headerLogoSrc = "../assets/css/logo.jpg";
$headerHomeHref = "../../src/home.php";
include "../../src/includes/header.php";
?>

<main class="register-shell">
    <section class="intro-panel">
        <div>
            <p class="eyebrow">Enrollment</p>
            <h1>Register Student Profile</h1>
            <p class="intro-text">Capture student details, verify a live facial descriptor, and automatically generate a QR code for attendance check-ins.</p>
        </div>
        <div class="menu-wrap">
            <button type="button" id="register-menu-toggle" class="menu-toggle" aria-label="Open register menu" aria-expanded="false" aria-controls="register-menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <div id="register-menu" class="menu-drawer" hidden>
                <a href="dashboard.php" class="action-link action-link-dashboard">Dashboard</a>
                <a href="register_student.php" class="action-link action-link-register">Register Student</a>
                <a href="manage_students.php" class="action-link action-link-manage">Manage Students</a>
                <a href="logout.php" class="action-link action-link-logout">Logout</a>
            </div>
        </div>
    </section>

    <section class="container" id="reg-form-container">
        <h2>Student Information</h2>
        <p id="loading-overlay">Initializing AI Models...</p>

        <div class="field-grid">
            <label for="student_id">Student ID</label>
            <input id="student_id" type="text" placeholder="e.g. 2024-001">

            <label for="fullname">Full Name</label>
            <input id="fullname" type="text" placeholder="Student full name">

            <label for="grade">Grade & Section</label>
            <input id="grade" type="text" placeholder="e.g. Grade 11 - A">

            <label for="parent_email">Parent Email Address</label>
            <input id="parent_email" type="email" placeholder="parent@example.com">
        </div>

        <div class="camera-wrap">
            <p class="camera-note">Keep the student's face centered with good lighting before submitting.</p>
            <video id="video" autoplay muted></video>
        </div>

        <button id="reg-btn" onclick="register()" disabled>Wait for Models...</button>

        <div id="qr-result">
            <h3 class="success-title">Registration Complete</h3>
            <p>Use this QR for attendance scanning:</p>
            <img id="qr-image" src="" alt="Student QR Code" width="180">
            <p><small id="qr-val-text" class="qr-val-text"></small></p>
            <button onclick="window.location.reload()" class="register-another-btn">Register Another Student</button>
        </div>
    </section>
</main>

<script>
const video = document.getElementById("video");
const regBtn = document.getElementById("reg-btn");
const MODEL_URL = '../../model/face-api'; 
const csrfToken = <?php echo json_encode($csrfToken); ?>;
const loadingOverlay = document.getElementById('loading-overlay');
const MODEL_FILES = [
    'tiny_face_detector_model-weights_manifest.json',
    'tiny_face_detector_model-shard1',
    'face_landmark_68_model-weights_manifest.json',
    'face_landmark_68_model-shard1',
    'face_recognition_model-weights_manifest.json',
    'face_recognition_model-shard1',
    'face_recognition_model-shard2'
];

const menuToggle = document.getElementById("register-menu-toggle");
const menuDrawer = document.getElementById("register-menu");

if (menuToggle && menuDrawer) {
    const closeMenu = function () {
        menuDrawer.hidden = true;
        menuDrawer.classList.remove("open");
        menuToggle.setAttribute("aria-expanded", "false");
    };

    menuToggle.addEventListener("click", function (event) {
        event.stopPropagation();
        const willOpen = menuDrawer.hidden;
        menuDrawer.hidden = !willOpen;
        menuDrawer.classList.toggle("open", willOpen);
        menuToggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
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
    await Promise.all(
        MODEL_FILES.map(file => fetchWithRetry(`${MODEL_URL}/${file}`, 3))
    );
}

async function loadModelsWithRetry(maxAttempts = 3) {
    let lastError = null;
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
            if (loadingOverlay) {
                loadingOverlay.innerText = `Loading AI models (${attempt}/${maxAttempts})...`;
            }
            await warmModelAssets();
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
            ]);
            return;
        } catch (err) {
            lastError = err;
            await sleep(500 * attempt);
        }
    }
    throw lastError || new Error('Model loading failed');
}

// 1. Load Models & Start Camera
async function init() {
    try {
        await loadModelsWithRetry(3);
        if (loadingOverlay) {
            loadingOverlay.innerText = "AI Ready. Position your face.";
        }
        regBtn.disabled = false;
        regBtn.innerText = "Register & Generate QR";
        
        const stream = await navigator.mediaDevices.getUserMedia({ video: {} });
        video.srcObject = stream;
    } catch (err) {
        if (loadingOverlay) {
            loadingOverlay.innerText = "Model load failed. Check connection and refresh.";
        }
        console.error(err);
    }
}

init();

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

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
            loadingOverlay.innerText =
            "Face not clear yet... hold still and face the camera";
        }
        await wait(250);
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

    // Capture Face Descriptor with retries + detector fallback
    const detection = await captureFaceDescriptorWithRetry();

    if (!detection) {
        alert("Face not detected. Keep your full face centered, hold still for 2-3 seconds, and ensure good lighting.");
        document.getElementById('loading-overlay').innerText = "AI Ready. Position your face.";
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
            const fieldGrid = document.querySelector('.field-grid');
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
            alert("Registration Failed: " + result.message);
            regBtn.disabled = false;
            regBtn.innerText = "Register & Generate QR";
        }
    } catch (err) {
        console.error(err);
        alert("Server error. Check if register_student.php is correct.");
        regBtn.disabled = false;
    }
}
</script>
<?php include "../../src/includes/footer.php"; ?>
</body>
</html>
