// e2ee.js - End-to-End Encryption handling using Web Crypto API

// --- RSA Key Management (For Key Exchange) ---
async function generateKeyPair() {
    return await window.crypto.subtle.generateKey(
        {
            name: "RSA-OAEP",
            modulusLength: 2048,
            publicExponent: new Uint8Array([1, 0, 1]),
            hash: "SHA-256",
        },
        true,
        ["encrypt", "decrypt"]
    );
}

async function exportKey(key) {
    const exported = await window.crypto.subtle.exportKey(
        key.type === "private" ? "pkcs8" : "spki",
        key
    );
    const exportedAsString = String.fromCharCode.apply(null, new Uint8Array(exported));
    return btoa(exportedAsString);
}

async function importPublicKey(pem) {
    const binaryDerString = atob(pem);
    const binaryDer = new Uint8Array(binaryDerString.length);
    for (let i = 0; i < binaryDerString.length; i++) {
        binaryDer[i] = binaryDerString.charCodeAt(i);
    }
    return await window.crypto.subtle.importKey(
        "spki",
        binaryDer.buffer,
        {
            name: "RSA-OAEP",
            hash: "SHA-256"
        },
        true,
        ["encrypt"]
    );
}

async function importPrivateKey(pem) {
    const binaryDerString = atob(pem);
    const binaryDer = new Uint8Array(binaryDerString.length);
    for (let i = 0; i < binaryDerString.length; i++) {
        binaryDer[i] = binaryDerString.charCodeAt(i);
    }
    return await window.crypto.subtle.importKey(
        "pkcs8",
        binaryDer.buffer,
        {
            name: "RSA-OAEP",
            hash: "SHA-256"
        },
        true,
        ["decrypt"]
    );
}

// --- AES Key Management (For Message Encryption) ---
async function generateAESKey() {
    return await window.crypto.subtle.generateKey(
        {
            name: "AES-GCM",
            length: 256,
        },
        true,
        ["encrypt", "decrypt"]
    );
}

async function exportAESKey(key) {
    const exported = await window.crypto.subtle.exportKey("raw", key);
    const exportedAsString = String.fromCharCode.apply(null, new Uint8Array(exported));
    return btoa(exportedAsString);
}

async function importAESKey(rawBase64) {
    const binaryDerString = atob(rawBase64);
    const binaryDer = new Uint8Array(binaryDerString.length);
    for (let i = 0; i < binaryDerString.length; i++) {
        binaryDer[i] = binaryDerString.charCodeAt(i);
    }
    return await window.crypto.subtle.importKey(
        "raw",
        binaryDer.buffer,
        { name: "AES-GCM" },
        true,
        ["encrypt", "decrypt"]
    );
}

// --- Encryption / Decryption Functions ---

// Encrypt a message using AES-GCM
async function encryptMessageAES(message, aesKeyBase64) {
    const aesKey = await importAESKey(aesKeyBase64);
    const iv = window.crypto.getRandomValues(new Uint8Array(12));
    const encoded = new TextEncoder().encode(message);
    const ciphertext = await window.crypto.subtle.encrypt(
        { name: "AES-GCM", iv: iv },
        aesKey,
        encoded
    );
    const ivBase64 = btoa(String.fromCharCode(...iv));
    const cipherBase64 = btoa(String.fromCharCode(...new Uint8Array(ciphertext)));
    return JSON.stringify({ iv: ivBase64, ciphertext: cipherBase64 });
}

// Decrypt a message using AES-GCM
async function decryptMessageAES(encryptedPayloadStr, aesKeyBase64) {
    try {
        const payload = JSON.parse(encryptedPayloadStr);
        if (!payload.iv || !payload.ciphertext) return "[Invalid Encrypted Message]";
        
        const aesKey = await importAESKey(aesKeyBase64);
        
        const ivStr = atob(payload.iv);
        const iv = new Uint8Array(ivStr.length);
        for (let i=0; i<ivStr.length; i++) iv[i] = ivStr.charCodeAt(i);
        
        const cipherStr = atob(payload.ciphertext);
        const ciphertext = new Uint8Array(cipherStr.length);
        for (let i=0; i<cipherStr.length; i++) ciphertext[i] = cipherStr.charCodeAt(i);
        
        const decrypted = await window.crypto.subtle.decrypt(
            { name: "AES-GCM", iv: iv },
            aesKey,
            ciphertext
        );
        return new TextDecoder().decode(decrypted);
    } catch(e) {
        console.error("AES Decryption error:", e);
        return "[Decryption Error]";
    }
}

// Encrypt AES key using RSA Public Key
async function encryptAESKeyWithRSA(aesKeyBase64, rsaPublicKeyPem) {
    const rsaPubKey = await importPublicKey(rsaPublicKeyPem);
    const encoded = new TextEncoder().encode(aesKeyBase64);
    const ciphertext = await window.crypto.subtle.encrypt(
        { name: "RSA-OAEP" },
        rsaPubKey,
        encoded
    );
    return btoa(String.fromCharCode(...new Uint8Array(ciphertext)));
}

// Decrypt AES key using RSA Private Key
async function decryptAESKeyWithRSA(encryptedAesKeyBase64) {
    const privKeyStr = localStorage.getItem('e2ee_private_key');
    if (!privKeyStr) throw new Error("No private key found");
    
    const privateKey = await importPrivateKey(privKeyStr);
    const ciphertext = atob(encryptedAesKeyBase64);
    const buffer = new Uint8Array(ciphertext.length);
    for (let i = 0; i < ciphertext.length; i++) buffer[i] = ciphertext.charCodeAt(i);
    
    const decrypted = await window.crypto.subtle.decrypt(
        { name: "RSA-OAEP" },
        privateKey,
        buffer
    );
    return new TextDecoder().decode(decrypted);
}

// Ensure keys exist and are synced
async function initE2EE() {
    let privKeyStr = localStorage.getItem('e2ee_private_key');
    let pubKeyStr = localStorage.getItem('e2ee_public_key');

    if (!privKeyStr || !pubKeyStr) {
        console.log("Generating new E2EE keys...");
        const keyPair = await generateKeyPair();
        privKeyStr = await exportKey(keyPair.privateKey);
        pubKeyStr = await exportKey(keyPair.publicKey);

        localStorage.setItem('e2ee_private_key', privKeyStr);
        localStorage.setItem('e2ee_public_key', pubKeyStr);

        // Upload public key to server
        const formData = new FormData();
        formData.append('public_key', pubKeyStr);
        await fetch('api.php?action=save_public_key', {
            method: 'POST',
            body: formData
        });
        console.log("Public key saved to server.");
    } else {
        // Just sync pub key to server in case it's missing there
        const formData = new FormData();
        formData.append('public_key', pubKeyStr);
        await fetch('api.php?action=save_public_key', {
            method: 'POST',
            body: formData
        });
    }
}

// For legacy/simple RSA-only encryption
async function encryptMessage(message, publicKeyPem) {
    const publicKey = await importPublicKey(publicKeyPem);
    const encoded = new TextEncoder().encode(message);
    const ciphertext = await window.crypto.subtle.encrypt(
        {
            name: "RSA-OAEP"
        },
        publicKey,
        encoded
    );
    return btoa(String.fromCharCode(...new Uint8Array(ciphertext)));
}

async function decryptMessage(ciphertextBase64) {
    const privKeyStr = localStorage.getItem('e2ee_private_key');
    if (!privKeyStr) return "[Decryption Failed: No Private Key]";
    
    try {
        const privateKey = await importPrivateKey(privKeyStr);
        const ciphertext = atob(ciphertextBase64);
        const buffer = new Uint8Array(ciphertext.length);
        for (let i = 0; i < ciphertext.length; i++) {
            buffer[i] = ciphertext.charCodeAt(i);
        }
        
        const decrypted = await window.crypto.subtle.decrypt(
            {
                name: "RSA-OAEP"
            },
            privateKey,
            buffer
        );
        return new TextDecoder().decode(decrypted);
    } catch(e) {
        console.error("Decryption error:", e);
        return "[Decryption Error]";
    }
}

// Auto-init on load
document.addEventListener('DOMContentLoaded', initE2EE);

