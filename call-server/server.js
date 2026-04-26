const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');

const app = express();
app.use(cors());

const server = http.createServer(app);

// Socket.io v4 with proper CORS for localhost XAMPP
const io = new Server(server, {
    cors: {
        origin: "*",
        methods: ["GET", "POST"]
    },
    allowEIO3: false, // Force Engine.IO v4
    transports: ['websocket', 'polling']
});

// User mapping: userId -> { socketId, role }
const users = new Map();

io.on('connection', (socket) => {
    console.log(`✅ [Connected] Socket: ${socket.id}`);

    // Register user
    socket.on('register', (data) => {
        const { userId, role } = data;
        if (userId) {
            users.set(userId.toString(), { socketId: socket.id, role: role });
            socket.userId = userId.toString();
            console.log(`👤 [Registered] User: ${userId} (${role}) - Socket: ${socket.id}`);
        }
    });

    // Call Initiation
    socket.on('callUser', (data) => {
        const { to, from, offer, callType, orderId, name, avatar } = data;
        const targetUser = users.get(to.toString());
        
        if (targetUser) {
            console.log(`📞 [Call] ${from} → ${to} (${callType})`);
            io.to(targetUser.socketId).emit('incomingCall', {
                from,
                offer,
                callType,
                orderId,
                name,
                avatar
            });
        } else {
            console.log(`❌ [Call Failed] Target ${to} is offline`);
            socket.emit('callRejected', { reason: 'User is offline' });
        }
    });

    // Answer Call
    socket.on('answerCall', (data) => {
        const { to, answer } = data;
        const targetUser = users.get(to.toString());
        if (targetUser) {
            console.log(`✅ [Answer] Forwarding to ${to}`);
            io.to(targetUser.socketId).emit('callAnswered', { answer });
        }
    });

    // WebRTC: ICE Candidate
    socket.on('iceCandidate', (data) => {
        const { to, candidate } = data;
        const targetUser = users.get(to.toString());
        if (targetUser) {
            io.to(targetUser.socketId).emit('iceCandidate', { candidate });
        }
    });

    // Reject Call
    socket.on('rejectCall', (data) => {
        const { to } = data;
        const targetUser = users.get(to.toString());
        if (targetUser) {
            console.log(`🚫 [Rejected] Call to ${to}`);
            io.to(targetUser.socketId).emit('callRejected', { from: socket.userId });
        }
    });

    // End Call
    socket.on('endCall', (data) => {
        const { to } = data;
        const targetUser = users.get(to.toString());
        if (targetUser) {
            console.log(`📴 [Ended] Call with ${to}`);
            io.to(targetUser.socketId).emit('callEnded');
        }
    });

    socket.on('disconnect', () => {
        if (socket.userId) {
            users.delete(socket.userId);
            console.log(`👋 [Disconnected] User: ${socket.userId}`);
        } else {
            console.log(`👋 [Disconnected] Anonymous: ${socket.id}`);
        }
    });
});

const PORT = 3000;
server.listen(PORT, '0.0.0.0', () => {
    console.log(`
╔═══════════════════════════════════════════════╗
║  🚀 PrintFlow Call Server (Socket.io v4)     ║
╠═══════════════════════════════════════════════╣
║  Port:        ${PORT}                              ║
║  URL:         http://localhost:${PORT}            ║
║  Engine.IO:   v4 (EIO=4)                      ║
║  Transports:  WebSocket, Polling              ║
╚═══════════════════════════════════════════════╝
    `);
});
