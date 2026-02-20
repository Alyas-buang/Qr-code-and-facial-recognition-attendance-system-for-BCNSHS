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


<script>
const video = document.getElementById("video");
const readerDiv = document.getElementById("reader");
const verifyDiv = document.getElementById("verify");
const statusMsg = document.getElementById("status");
const infoDiv = document.getElementById("info");

let student = null;
let targetDescriptor = null;
let attendanceToken = null;
let isLocked = false;
let stream = null;

function goBack() {
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
    }
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    window.location.href = "../../src/home.php";
}

/* ---------- LOAD FACE MODELS (ONCE) ---------- */
async function loadModels() {
    await faceapi.nets.tinyFaceDetector.loadFromUri('../../model/face-api');
    await faceapi.nets.faceLandmark68Net.loadFromUri('../../model/face-api');
    await faceapi.nets.faceRecognitionNet.loadFromUri('../../model/face-api');
    console.log("Face models loaded");
}

window.onload = loadModels;

/* ---------- INIT QR SCANNER ---------- */
const qr = new Html5Qrcode("reader");

qr.start(
    { facingMode: "environment" },
    { fps: 15, qrbox: 220 },
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
            statusMsg.innerText = "Attendance recorded!";
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
<?php include "../../src/includes/footer.php"; ?>
</body>
</html>
