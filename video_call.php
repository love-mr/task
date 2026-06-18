<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['employee_id'])) {
    die("Unauthorized.");
}

$discId = (int)($_GET['discussion_id'] ?? 0);
$type = $_GET['type'] ?? 'video'; // 'audio' or 'video'

if (!$discId) {
    die("Discussion ID is required.");
}

$meId = $_SESSION['employee_id'];

// Get user info
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar FROM employees WHERE id = ?");
$stmt->execute([$meId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
$myName = $me ? ($me['first_name'] . ' ' . $me['last_name']) : 'User ' . $meId;
$myInitials = $me['avatar'] ?: strtoupper(substr($me['first_name'] ?? 'U', 0, 1) . substr($me['last_name'] ?? '', 0, 1));

// Generate a random room ID based on the discussion ID
// In a real app, this should be synced or retrieved from the server so all members join the same room.
$roomID = "discussion_" . $discId;

// ZEGOCLOUD App ID and Server Secret (Placeholders)
// The user will need to replace these with their actual ZegoCloud credentials
$appID = 0; // Replace with your App ID
$serverSecret = "YOUR_SERVER_SECRET"; // Replace with your Server Secret

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($type); ?> Call - Discussion #<?php echo $discId; ?></title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            width: 100vw;
            height: 100vh;
            background: #0f172a;
            font-family: 'Outfit', sans-serif;
            color: #fff;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #root {
            width: 100vw;
            height: 100vh;
        }
        .loading-screen {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            width: 100vw;
            position: absolute;
            top: 0;
            left: 0;
            background: #0f172a;
            z-index: 10;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-left-color: #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        
        .setup-warning {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            padding: 20px;
            border-radius: 8px;
            max-width: 500px;
            text-align: center;
            display: none;
            position: absolute;
            z-index: 20;
        }
        .setup-warning h3 { margin-top: 0; color: #f87171; }
        .setup-warning code { background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="loading-screen" id="loading-screen">
        <div class="spinner"></div>
        <div style="font-size: 1.2rem; font-weight: 500;">Connecting to Secure <?php echo ucfirst($type); ?> Call...</div>
        <div style="font-size: 0.9rem; color: #94a3b8; margin-top: 8px;">End-to-End Encrypted via WebRTC</div>
    </div>

    <div class="setup-warning" id="setup-warning">
        <h3>ZegoCloud Credentials Required</h3>
        <p>To enable real-time audio/video calls, you must configure your ZegoCloud App ID and Server Secret.</p>
        <p>1. Create an account at <a href="https://zegocloud.com" target="_blank" style="color:#60a5fa;">ZegoCloud</a></p>
        <p>2. Create a project and get your <code>AppID</code> and <code>ServerSecret</code></p>
        <p>3. Open <code>video_call.php</code> and update lines 26 and 27.</p>
    </div>

    <div id="root"></div>

    <script src="https://unpkg.com/@zegocloud/zego-uikit-prebuilt/zego-uikit-prebuilt.js"></script>
    <script>
        const appID = <?php echo $appID; ?>;
        const serverSecret = "<?php echo $serverSecret; ?>";
        const roomID = "<?php echo $roomID; ?>";
        const userID = "user_" + <?php echo $meId; ?>;
        const userName = "<?php echo addslashes($myName); ?>";
        const callType = "<?php echo $type; ?>"; // 'audio' or 'video'

        window.onload = function () {
            const loadingScreen = document.getElementById('loading-screen');
            const setupWarning = document.getElementById('setup-warning');

            if (appID === 0 || serverSecret === "YOUR_SERVER_SECRET") {
                loadingScreen.style.display = 'none';
                setupWarning.style.display = 'block';
                return;
            }

            // Generate Kit Token
            // In a production app, the token should be generated on the server for better security.
            const kitToken = ZegoUIKitPrebuilt.generateKitTokenForTest(
                appID, 
                serverSecret, 
                roomID, 
                userID, 
                userName
            );

            // Initialize ZegoUIKitPrebuilt
            const zp = ZegoUIKitPrebuilt.create(kitToken);

            loadingScreen.style.display = 'none';

            // Join the room
            zp.joinRoom({
                container: document.querySelector("#root"),
                sharedLinks: [{
                    name: 'Discussion Link',
                    url: window.location.href,
                }],
                scenario: {
                    mode: ZegoUIKitPrebuilt.GroupCall, // Group call mode for conference
                },
                turnOnMicrophoneWhenJoining: true,
                turnOnCameraWhenJoining: callType === 'video',
                showMyCameraToggleButton: true,
                showMyMicrophoneToggleButton: true,
                showAudioVideoSettingsButton: true,
                showScreenSharingButton: true, // Allow screen sharing in group discussions
            });
        };
    </script>
</body>
</html>
