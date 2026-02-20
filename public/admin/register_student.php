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
    <title>Student Registration & QR Generator</title>
    <link rel="icon" type="image/jpeg" href="../assets/css/logo.jpg">
    
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
    <link rel="stylesheet" href="../assets/css/register_student.css">
</head>
<body>
<button class="back-btn" onclick="goBack()">Back</button>


<div class="container" id="reg-form-container">
    <h2>Student Registration</h2>
    <p id="loading-overlay">Initializing AI Models...</p>

    <input id="student_id" type="text" placeholder="Student ID Number (e.g. 2024-001)">
    <input id="fullname" type="text" placeholder="Full Name">
    <input id="grade" type="text" placeholder="Grade & Section">
    <input id="parent_email" type="email" placeholder="Parent Email Address">

    <video id="video" autoplay muted></video>
    
    <button id="reg-btn" onclick="register()" disabled>Wait for Models...</button>

    <div id="qr-result">
        <h3 class="success-title">Success!</h3>
        <p>Registration complete. Use the QR below for attendance:</p>
        <img id="qr-image" src="" alt="Student QR Code" width="180">
        <p><small id="qr-val-text" class="qr-val-text"></small></p>
        <button onclick="window.location.reload()" class="register-another-btn">Register Another</button>
    </div>
</div>

<script>
const video = document.getElementById("video");
const regBtn = document.getElementById("reg-btn");
const MODEL_URL = '../../model/face-api'; 
const csrfToken = <?php echo json_encode($csrfToken); ?>;

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    window.location.href = "../../src/home.php";
}

// 1. Load Models & Start Camera
async function init() {
    try {
        await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
        
        document.getElementById('loading-overlay').innerText = "AI Ready. Position your face.";
        regBtn.disabled = false;
        regBtn.innerText = "Register & Generate QR";
        
        const stream = await navigator.mediaDevices.getUserMedia({ video: {} });
        video.srcObject = stream;
    } catch (err) {
        document.getElementById('loading-overlay').innerText = "Model Load Failed!";
        console.error(err);
    }
}

init();

function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function captureFaceDescriptorWithRetry(maxAttempts = 10) {
    for (let attempt = 1; attempt <= maxAttempts; attempt++) {
        const detectionSsd = await faceapi
            .detectSingleFace(video)
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (detectionSsd) {
            return detectionSsd;
        }

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

        document.getElementById('loading-overlay').innerText =
            "Face not clear yet... hold still and face the camera";
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
            // Hide the video and inputs
            video.style.display = "none";
            document.querySelectorAll('input').forEach(i => i.style.display = 'none');
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
