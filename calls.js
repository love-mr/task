// calls.js - WebRTC Voice and Video Calling using PeerJS

let localStream = null;
let peer = null;
let activeCalls = {}; // map of peerId -> mediaConnection
let currentDiscussionId = null;
let currentCallType = null;
let callPollInterval = null;
let declinedCalls = new Set();

let incomingCalls = {}; // Track incoming calls that haven't been answered yet

// Initialize PeerJS
function initPeerJS() {
    if (!window.vyalaMeId) return; // Need user ID
    const peerId = 'vyala_emp_' + window.vyalaMeId;
    
    // We use the public PeerJS cloud server, but add STUN servers to fix NAT traversal issues (video/voice not sharing)
    peer = new Peer(peerId, {
        debug: 2,
        config: {
            'iceServers': [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:global.stun.twilio.com:3478' }
            ]
        }
    });

    peer.on('open', (id) => {
        console.log('My peer ID is: ' + id);
        // Start polling for incoming calls via DB
        startCallPolling();
    });

    peer.on('call', (mediaConnection) => {
        // Someone is calling us directly via PeerJS. 
        console.log('Incoming PeerJS connection from', mediaConnection.peer);
        
        // Track the incoming connection
        incomingCalls[mediaConnection.peer] = mediaConnection;
        
        // If we are currently in an active call, answer it and merge
        if (localStream) {
            mediaConnection.answer(localStream);
            handleMediaConnection(mediaConnection);
        }
    });
    
    peer.on('error', (err) => {
        console.error('PeerJS error:', err);
    });
}

// Start Call (Initiator)
async function startCall(discussionId, type) {
    if (!peer) return alert("Call system not initialized");
    currentDiscussionId = discussionId;
    currentCallType = type;
    
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ 
            video: type === 'video' ? { width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } } : false, 
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } 
        });
        
        showCallUI(type);
        addVideoStream('localVideo', localStream, true);
        
        // Notify server that we started a call
        const fd = new FormData();
        fd.append('discussion_id', discussionId);
        fd.append('type', type);
        await fetch('api.php?action=start_call', { method: 'POST', body: fd });
        
        // Fetch discussion members to call them via PeerJS
        const resp = await fetch('api.php?action=get_discussion_members&discussion_id=' + discussionId);
        const data = await resp.json();
        
        if (data.success) {
            data.members.forEach(member => {
                if (member.id != window.vyalaMeId) {
                    const targetPeerId = 'vyala_emp_' + member.id;
                    const call = peer.call(targetPeerId, localStream);
                    handleMediaConnection(call);
                }
            });
        }
    } catch (err) {
        console.error("Failed to get local stream", err);
        alert("Could not access camera/microphone.");
    }
}

// Answer Call
async function answerCall(callId, discussionId, type) {
    currentDiscussionId = discussionId;
    currentCallType = type;
    
    try {
        localStream = await navigator.mediaDevices.getUserMedia({ 
            video: type === 'video' ? { width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } } : false, 
            audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } 
        });
        
        showCallUI(type);
        addVideoStream('localVideo', localStream, true);
        
        // Notify server
        const fd = new FormData();
        fd.append('call_id', callId);
        await fetch('api.php?action=answer_call', { method: 'POST', body: fd });
        
        // For anyone who already called us, answer them!
        for (let peerId in incomingCalls) {
            const call = incomingCalls[peerId];
            call.answer(localStream);
            handleMediaConnection(call);
        }

        // To mesh into the group, fetch members and call those who haven't called us
        const resp = await fetch('api.php?action=get_discussion_members&discussion_id=' + discussionId);
        const data = await resp.json();
        
        if (data.success) {
            data.members.forEach(member => {
                if (member.id != window.vyalaMeId) {
                    const targetPeerId = 'vyala_emp_' + member.id;
                    if (!incomingCalls[targetPeerId]) {
                        const call = peer.call(targetPeerId, localStream);
                        handleMediaConnection(call);
                    }
                }
            });
        }
    } catch (err) {
        console.error("Failed to get local stream", err);
    }
}

function handleMediaConnection(call) {
    if (!call) return;
    activeCalls[call.peer] = call;
    
    call.on('stream', (remoteStream) => {
        addVideoStream(call.peer, remoteStream, false);
    });
    
    call.on('close', () => {
        removeVideoStream(call.peer);
        delete activeCalls[call.peer];
    });
}

// End Call
async function endCall() {
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
        localStream = null;
    }
    
    for (let peerId in activeCalls) {
        activeCalls[peerId].close();
    }
    activeCalls = {};
    incomingCalls = {};
    
    hideCallUI();
    
    if (currentDiscussionId) {
        const fd = new FormData();
        fd.append('discussion_id', currentDiscussionId);
        await fetch('api.php?action=end_call', { method: 'POST', body: fd });
        currentDiscussionId = null;
    }
}

// Toggle Audio/Video
function toggleMute() {
    if (localStream) {
        const audioTracks = localStream.getAudioTracks();
        if (audioTracks.length > 0) {
            audioTracks[0].enabled = !audioTracks[0].enabled;
            const btn = document.getElementById('btn-toggle-mute');
            if (btn) {
                if (audioTracks[0].enabled) {
                    btn.style.background = 'rgba(255,255,255,0.1)';
                    btn.innerHTML = '<i data-lucide="mic"></i>';
                } else {
                    btn.style.background = '#ef4444';
                    btn.innerHTML = '<i data-lucide="mic-off"></i>';
                }
                if (window.lucide) window.lucide.createIcons();
            }
            return audioTracks[0].enabled;
        }
    }
    return false;
}

function toggleVideo() {
    if (localStream) {
        const videoTracks = localStream.getVideoTracks();
        if (videoTracks.length > 0) {
            videoTracks[0].enabled = !videoTracks[0].enabled;
            const btn = document.getElementById('btn-toggle-video');
            if (btn) {
                if (videoTracks[0].enabled) {
                    btn.style.background = 'rgba(255,255,255,0.1)';
                    btn.innerHTML = '<i data-lucide="video"></i>';
                } else {
                    btn.style.background = '#ef4444';
                    btn.innerHTML = '<i data-lucide="video-off"></i>';
                }
                if (window.lucide) window.lucide.createIcons();
            }
            return videoTracks[0].enabled;
        }
    }
    return false;
}

// Polling for incoming calls
function startCallPolling() {
    if (callPollInterval) clearInterval(callPollInterval);
    callPollInterval = setInterval(async () => {
        // Prevent showing ringing if already in a call
        if (localStream != null) return;
        
        try {
            const resp = await fetch('api.php?action=check_calls');
            const data = await resp.json();
            if (data.success && data.call) {
                if (!declinedCalls.has(data.call.call_id)) {
                    showIncomingCallPopup(data.call);
                }
            } else {
                hideIncomingCallPopup();
            }
        } catch(e) { }
    }, 3000);
}

// ---- UI Helpers ----
function showCallUI(type) {
    const ui = document.getElementById('call-overlay');
    if (ui) ui.style.display = 'flex';
    const grid = document.getElementById('call-video-grid');
    if (grid) grid.innerHTML = ''; // clear
}

function hideCallUI() {
    const ui = document.getElementById('call-overlay');
    if (ui) ui.style.display = 'none';
}

function addVideoStream(id, stream, isLocal) {
    const grid = document.getElementById('call-video-grid');
    if (!grid) return;
    
    let videoEl = document.getElementById('video_' + id);
    if (!videoEl) {
        videoEl = document.createElement('video');
        videoEl.id = 'video_' + id;
        videoEl.autoplay = true;
        videoEl.playsInline = true;
        
        // Add styling for HD video to display properly
        videoEl.style.width = "100%";
        videoEl.style.height = "100%";
        videoEl.style.objectFit = "cover";
        videoEl.style.borderRadius = "12px";
        
        if (isLocal) {
            videoEl.muted = true;
            videoEl.classList.add('local-video');
            videoEl.style.transform = "scaleX(-1)"; // Mirror local video
        }
        
        const wrapper = document.createElement('div');
        wrapper.className = 'video-wrapper';
        wrapper.id = 'wrap_' + id;
        
        // Add styling for wrapper
        wrapper.style.position = "relative";
        wrapper.style.overflow = "hidden";
        wrapper.style.borderRadius = "12px";
        wrapper.style.boxShadow = "0 8px 20px rgba(0,0,0,0.4)";
        wrapper.style.width = isLocal ? "200px" : "400px";
        wrapper.style.height = isLocal ? "150px" : "300px";
        if (isLocal) {
            wrapper.style.position = "absolute";
            wrapper.style.bottom = "120px";
            wrapper.style.right = "40px";
            wrapper.style.zIndex = "20";
        }
        
        wrapper.appendChild(videoEl);
        
        if (isLocal) {
            document.getElementById('call-overlay').appendChild(wrapper);
        } else {
            grid.appendChild(wrapper);
        }
    }
    videoEl.srcObject = stream;
    // Ensure playback starts
    videoEl.play().catch(e => console.warn("Video play error:", e));
}

function removeVideoStream(id) {
    const wrap = document.getElementById('wrap_' + id);
    if (wrap) wrap.remove();
}

function showIncomingCallPopup(call) {
    const popup = document.getElementById('incoming-call-popup');
    if (!popup) return;
    
    document.getElementById('incoming-caller-name').innerText = call.caller_name + ' is calling...';
    const typeLabel = call.type === 'video' ? 'Video Call' : 'Voice Call';
    document.getElementById('incoming-call-type').innerText = typeLabel;
    
    popup.setAttribute('data-call-id', call.call_id);
    popup.setAttribute('data-discussion-id', call.discussion_id);
    popup.setAttribute('data-type', call.type);
    
    popup.style.display = 'flex';
    
    // Play ringtone ideally
}

function hideIncomingCallPopup() {
    const popup = document.getElementById('incoming-call-popup');
    if (popup) popup.style.display = 'none';
}

function declineCall(callId) {
    declinedCalls.add(callId);
    hideIncomingCallPopup();
}

// Bind to window so dashboard.php can use it
window.vyalaCalls = {
    startCall,
    answerCall,
    endCall,
    declineCall,
    toggleMute,
    toggleVideo,
    hideIncomingCallPopup,
    initPeerJS
};
