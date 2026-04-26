# PrintFlow Call Server

Real-time voice and video call signaling server using Socket.io v4 and WebRTC.

## Important

The main PrintFlow app currently uses the **project-root** signaling server at `../server.js` (events prefixed with `pf-`).
If your browser console shows `ERR_CONNECTION_REFUSED` for `http://127.0.0.1:3000/socket.io/...`, start the server with:

- `..\\start-call-server.bat` (recommended on Windows)
- or `node ..\\server.js`

## 🚀 Quick Start

```bash
# 1. Install dependencies (first time only)
npm install

# 2. Start the server
node server.js

# OR use the batch file
start.bat
```

## ✅ Verify Setup

```bash
verify.bat
```

## 📋 Requirements

- Node.js v14+ (Download: https://nodejs.org/)
- Socket.io v4.8.3 (auto-installed)
- Port 3000 available

## 🔧 Configuration

**Server Port:** Edit `server.js` line 82
```javascript
const PORT = 3000; // Change here
```

**Client URL:** Edit `../public/assets/js/printflow_call.js` line 33
```javascript
this.socket = io('http://localhost:3000', { // Change here
```

## 📚 Documentation

See `SOCKET_IO_GUIDE.md` for:
- Troubleshooting common errors
- Understanding EIO=4 and Socket.io v4
- Testing the call feature
- Advanced configuration

## 🐛 Common Issues

### Server won't start
```bash
# Check if port 3000 is in use
netstat -ano | findstr :3000

# Kill the process if needed
taskkill /PID <PID> /F
```

### Connection errors in browser
1. Make sure server is running
2. Clear browser cache (Ctrl+Shift+Delete)
3. Check browser console for errors
4. Verify Socket.io client version matches server (4.8.3)

## 📁 Files

- `server.js` - Main Socket.io server
- `package.json` - Dependencies
- `start.bat` - Easy startup script
- `verify.bat` - Setup verification
- `SOCKET_IO_GUIDE.md` - Complete documentation

## 🔗 Endpoints

- **Connection:** `http://localhost:3000`
- **Health Check:** `http://localhost:3000/socket.io/?EIO=4&transport=polling`

## 📞 Events

### Client → Server
- `register` - Register user with userId and role
- `callUser` - Initiate a call
- `answerCall` - Answer incoming call
- `iceCandidate` - Exchange ICE candidates
- `rejectCall` - Reject incoming call
- `endCall` - End active call

### Server → Client
- `incomingCall` - Receive call notification
- `callAnswered` - Call was answered
- `callRejected` - Call was rejected
- `callEnded` - Call ended by peer
- `iceCandidate` - Receive ICE candidate

## 🎯 Status

✅ Socket.io v4.8.3  
✅ Engine.IO v4 (EIO=4)  
✅ WebSocket + Polling transports  
✅ CORS configured for localhost  
✅ Auto-reconnection enabled  

---

**Version:** 1.0.0  
**Last Updated:** 2024
