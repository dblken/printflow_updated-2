const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');
const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');

const app = express();

// Diagnostic Route
app.get('/', (req, res) => {
    res.send('PrintFlow Advanced Signaling Server is LIVE on Port 3000');
});

// Explicit CORS for XAMPP compatibility
app.use(cors({
    origin: '*',
    methods: ['GET', 'POST']
}));

const server = http.createServer(app);
const io = new Server(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"],
        credentials: true
    },
    transports: ['polling', 'websocket'],
    allowEIO3: true,
    pingTimeout: 60000,
    pingInterval: 25000
});

// DB Config
const dbConfig = { host: 'localhost', user: 'root', password: 'root_password_placeholder', database: 'printflow_4' };
const loadEnv = () => {
    try {
        const p = path.join(__dirname, '.env');
        if (fs.existsSync(p)) {
            const c = fs.readFileSync(p, 'utf8');
            c.split('\n').forEach(l => {
                const [k, v] = l.split('=');
                if (k && v) {
                    const val = v.trim().replace(/^[\"']|[\"']$/g, '');
                    if (k.trim() === 'PRINTFLOW_DB_HOST') dbConfig.host = val;
                    if (k.trim() === 'PRINTFLOW_DB_USER') dbConfig.user = val;
                    if (k.trim() === 'PRINTFLOW_DB_PASS') dbConfig.password = val;
                    if (k.trim() === 'PRINTFLOW_DB_NAME') dbConfig.database = val;
                }
            });
        }
    } catch(e){}
};
loadEnv();
if (dbConfig.password === 'root_password_placeholder') dbConfig.password = '122704';

let db;
async function connectDB() {
    try {
        db = await mysql.createPool({...dbConfig, waitForConnections: true, connectionLimit: 5});
        console.log(`[DB] Connected to ${dbConfig.database}`);
    } catch (e) {
        console.error('[DB] Connection Failed:', e.message);
    }
}
connectDB();

async function updateStatus(uId, uType, status) {
    if (!db) return;
    try {
        const table = uType === 'Customer' ? 'customers' : 'users';
        const idCol  = uType === 'Customer' ? 'customer_id' : 'user_id';
        let s = 'offline';
        if (status === 'online') s = 'online';
        if (status === 'in-call') s = 'in-call';
        const q = `UPDATE ${table} SET online_status = ?, last_seen = NOW() WHERE ${idCol} = ?`;
        await db.execute(q, [s, uId]);
    } catch(e){}
}

const activeUsers = new Map();

/**
 * activeCalls — tracks who is in an active or pending call.
 * Key: `${userId}_${userType}`
 * Value: `{ partnerId, partnerType, pending: bool }`
 */
const activeCalls = new Map();

function _callKey(id, type) { return `${id}_${type}`; }

io.on('connection', (socket) => {
    console.log(`[PFCall] Socket received connection: ${socket.id}`);

    // ── Auto-register from query params IMMEDIATELY on connect ──────
    // This ensures the user is trackable the instant the socket opens,
    // before the explicit 'register' event fires (which carries name/avatar).
    const qId   = socket.handshake.query.userId;
    const qType = socket.handshake.query.userType;
    if (qId && qType) {
        const key = `${qId}_${qType}`;
        socket.userId   = qId;
        socket.userType = qType;
        socket.userKey  = key;
        if (!activeUsers.has(key)) {
            activeUsers.set(key, { sockets: new Set(), userId: qId, userType: qType, name: '', avatar: '' });
        }
        activeUsers.get(key).sockets.add(socket.id);
        console.log(`[PFCall] Auto-registered from query: ${qId}(${qType}) — sockets: ${activeUsers.get(key).sockets.size}`);
    }

    // ── Explicit register: updates name/avatar + marks DB online ────
    socket.on('register', async (data) => {
        const { userId, userType, name, avatar } = data;
        if (!userId || !userType) return;
        const key = `${userId}_${userType}`;
        socket.userId   = userId;
        socket.userType = userType;
        socket.userKey  = key;

        console.log(`[PFCall][REG] Registering ${key} (${name})`);

        if (!activeUsers.has(key)) {
            activeUsers.set(key, { sockets: new Set(), userId, userType, name, avatar });
        } else {
            const u = activeUsers.get(key);
            if (name)   u.name   = name;
            if (avatar) u.avatar = avatar;
        }
        activeUsers.get(key).sockets.add(socket.id);
        await updateStatus(userId, userType, 'online');
        io.emit('user-online', { userId, userType, name, avatar });

        // ── CALL RECOVERY ──
        // Check if there is an active or pending call for this user
        for (const [cKey, call] of activeCalls.entries()) {
            const partnerKey = `${call.partnerId}_${call.partnerType}`;
            if (partnerKey === key) {
                // This user is the target of a call from cKey
                const [callerId, callerType] = cKey.split('_');
                const caller = activeUsers.get(cKey);
                
                if (call.pending) {
                    console.log(`[PFCall][RECOVERY] Pending call found for ${key}. Resending incomingCall.`);
                    socket.emit('incomingCall', {
                        fromUserId:   callerId,
                        fromUserType: callerType,
                        fromName:     caller ? caller.name : 'Unknown',
                        fromAvatar:   caller ? caller.avatar : '',
                        callType:     call.callType || 'voice',
                        orderId:      call.orderId
                    });
                } else {
                    console.log(`[PFCall][RECOVERY] Active call found for ${key}. Syncing state.`);
                    socket.emit('pf-call-sync', {
                        partnerId: callerId,
                        partnerType: callerType,
                        callType: call.callType || 'voice',
                        startTime: call.startTime
                    });
                }
                break;
            }
        }

        console.log(`[PFCall][REG] Success: ${key}. Total online: ${activeUsers.size}`);
    });

    socket.on('check-online', (data, callback) => {
        const key = `${data.userId}_${data.userType}`;
        const isOnline = activeUsers.has(key);
        if (typeof callback === 'function') callback({ isOnline });
    });

    const _relay = (toId, toType, event, data) => {
        const key = `${toId}_${toType}`;
        const target = activeUsers.get(key);
        if (target) {
            console.log(`[PFCall][RELAY] ${event} to ${key} (${target.sockets.size} sockets)`);
            target.sockets.forEach(sid => io.to(sid).emit(event, data));
            return true;
        }
        console.warn(`[PFCall][RELAY] FAILED: ${key} is offline for event ${event}`);
        return false;
    };

    // ── callUser: initiate a call ────────────────────────────────────────
    socket.on('callUser', async (d) => {
        const targetId   = d.receiverId || d.toUserId;
        const targetType = d.toUserType;
        const callerKey  = _callKey(socket.userId, socket.userType);
        const targetKey  = _callKey(targetId, targetType);
        const orderId    = d.orderId || null;

        console.log(`[PFCall][CALL] ${callerKey} -> ${targetKey} (${d.type}) | Order: ${orderId}`);

        // Reject if target is busy
        if (activeCalls.has(targetKey)) {
            console.log(`[PFCall][CALL] BUSY: ${targetKey}`);
            socket.emit('pf-call-busy', { message: 'User is currently on another call.' });
            return;
        }

        // Reject if caller is already in a call
        if (activeCalls.has(callerKey)) {
            console.log(`[PFCall][CALL] ALREADY_IN_CALL: ${callerKey}`);
            socket.emit('pf-call-error', { message: 'You are already in a call.' });
            return;
        }

        const ok = _relay(targetId, targetType, 'incomingCall', {
            fromUserId:   socket.userId,
            fromUserType: socket.userType,
            fromName:     d.fromName,
            fromAvatar:   d.fromAvatar,
            callType:     d.type || d.callType || 'voice',
            orderId:      orderId
        });

        // Messenger behavior: even if offline (ok=false), we let the caller "ring"
        if (!ok) {
            console.log(`[PFCall][CALL] TARGET_OFFLINE: ${targetKey}. Triggering Push Notification.`);
            // Trigger Push via PHP
            try {
                const data = JSON.stringify({
                    receiverId: targetId,
                    receiverType: targetType,
                    callerName: d.fromName,
                    callType: d.type || 'voice',
                    orderId: orderId
                });
                
                const req = http.request({
                    hostname: 'localhost',
                    port: 80,
                    path: '/printflow/public/api/push/send_call_push.php',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Content-Length': data.length
                    }
                }, (res) => {
                    res.on('data', () => {}); // Consume response
                });
                
                req.on('error', (e) => console.error('[PFCall][PUSH] Request Error:', e.message));
                req.write(data);
                req.end();
            } catch (e) {
                console.error('[PFCall][PUSH] Error:', e.message);
            }
            
            socket.emit('pf-call-ringing-offline', { userId: targetId });
        }
        
        // Mark caller as pending (waiting for answer)
        activeCalls.set(callerKey, { 
            partnerId: targetId, 
            partnerType: targetType, 
            pending: true,
            orderId: orderId,
            callType: d.type || 'voice',
            startTime: Date.now()
        });

        // Auto-timeout for missed call (35 seconds)
        setTimeout(async () => {
            const call = activeCalls.get(callerKey);
            if (call && call.pending && call.partnerId == targetId) {
                console.log(`[PFCall][TIMEOUT] Call ${callerKey} -> ${targetKey} missed.`);
                
                // Notify caller
                const targetSocket = activeUsers.get(callerKey);
                if (targetSocket) {
                    targetSocket.sockets.forEach(sid => io.to(sid).emit('pf-call-missed', { partnerId: targetId }));
                }
                
                // Log Missed Call to DB
                if (db && orderId) {
                    try {
                        const msg = `Missed ${call.callType} call`;
                        await db.execute(
                            'INSERT INTO order_messages (order_id, sender, sender_id, message, message_type) VALUES (?, ?, ?, ?, ?)',
                            [orderId, socket.userType, socket.userId, msg, 'call_log']
                        );
                        console.log(`[PFCall][DB] Missed call logged for order #${orderId}`);
                    } catch(e) {
                        console.error('[PFCall][DB] Missed call log failed:', e.message);
                    }
                }

                activeCalls.delete(callerKey);
                activeCalls.delete(targetKey);
            }
        }, 35000);
    });

    socket.on('pf-accept-call', (d) => {
        const callerKey   = _callKey(d.toUserId, d.toUserType);
        const receiverKey = _callKey(socket.userId, socket.userType);

        // Mark both as in an active call
        activeCalls.set(callerKey,   { partnerId: socket.userId,  partnerType: socket.userType, pending: false, callType: d.callType || 'voice', startTime: Date.now() });
        activeCalls.set(receiverKey, { partnerId: d.toUserId,     partnerType: d.toUserType,    pending: false, callType: d.callType || 'voice', startTime: Date.now() });

        updateStatus(socket.userId, socket.userType, 'in-call');
        updateStatus(d.toUserId, d.toUserType, 'in-call');

        io.emit('user-status-change', { userId: socket.userId, userType: socket.userType, status: 'in-call' });
        io.emit('user-status-change', { userId: d.toUserId,     userType: d.toUserType,    status: 'in-call' });

        _relay(d.toUserId, d.toUserType, 'pf-call-accepted', { fromUserId: socket.userId });
    });

    // ── pf-reject-call ───────────────────────────────────────────────────
    socket.on('pf-reject-call', (d) => {
        const callerKey   = _callKey(d.toUserId, d.toUserType);
        const receiverKey = _callKey(socket.userId, socket.userType);
        activeCalls.delete(callerKey);
        activeCalls.delete(receiverKey);
        _relay(d.toUserId, d.toUserType, 'pf-call-rejected', { fromUserId: socket.userId });
    });

    // ── pf-end-call ──────────────────────────────────────────────────────
    socket.on('pf-end-call', (d) => {
        const callerKey   = _callKey(d.toUserId, d.toUserType);
        const receiverKey = _callKey(socket.userId, socket.userType);
        activeCalls.delete(callerKey);
        activeCalls.delete(receiverKey);

        updateStatus(socket.userId, socket.userType, 'online');
        updateStatus(d.toUserId, d.toUserType, 'online');

        io.emit('user-status-change', { userId: socket.userId, userType: socket.userType, status: 'online' });
        io.emit('user-status-change', { userId: d.toUserId,     userType: d.toUserType,    status: 'online' });

        _relay(d.toUserId, d.toUserType, 'pf-call-ended', { fromUserId: socket.userId });
    });

    // ── WebRTC signaling (pass-through) ─────────────────────────────────
    socket.on('pf-webrtc-offer',  (d) => _relay(d.toUserId, d.toUserType, 'pf-webrtc-offer',  { fromUserId: socket.userId, offer:     d.offer }));
    socket.on('pf-webrtc-answer', (d) => _relay(d.toUserId, d.toUserType, 'pf-webrtc-answer', { fromUserId: socket.userId, answer:    d.answer }));
    socket.on('pf-ice-candidate', (d) => _relay(d.toUserId, d.toUserType, 'pf-ice-candidate', { fromUserId: socket.userId, candidate: d.candidate }));

    // ── disconnect ───────────────────────────────────────────────────────
    socket.on('disconnect', async () => {
        console.log(`[PFCall] Disconnected: ${socket.userId || 'Anonymous'} (${socket.id})`);

        if (socket.userKey) {
            // Clean up call state if this user was in a call
            const callState = activeCalls.get(socket.userKey);
            if (callState) {
                // Notify partner that call ended
                _relay(callState.partnerId, callState.partnerType, 'pf-call-ended', { fromUserId: socket.userId });
                activeCalls.delete(_callKey(callState.partnerId, callState.partnerType));
                activeCalls.delete(socket.userKey);
            }

            if (activeUsers.has(socket.userKey)) {
                const u = activeUsers.get(socket.userKey);
                u.sockets.delete(socket.id);
                if (u.sockets.size === 0) {
                    await updateStatus(socket.userId, socket.userType, 'offline');
                    activeUsers.delete(socket.userKey);
                    io.emit('user-offline', { userId: socket.userId, userType: socket.userType });
                }
            }
        }
    });
});

const PORT = 3000;
server.listen(PORT, '0.0.0.0', () => {
    console.log(`
    --------------------------------------------------
    PrintFlow Signaling Server - ACTIVE on Port ${PORT}
    --------------------------------------------------
    Diagnostic URL: http://localhost:${PORT}/
    Environment: Node.js + Express + Socket.IO v4
    Binding: 0.0.0.0 (All Interfaces)
    --------------------------------------------------
    `);
});
