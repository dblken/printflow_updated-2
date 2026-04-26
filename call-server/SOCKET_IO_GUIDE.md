# Socket.io v4 Setup & Troubleshooting Guide

## ✅ What Was Fixed

### 1. Server Configuration
- ✅ Created proper `call-server/server.js` with Socket.io v4.8.3
- ✅ Added correct CORS origins for localhost XAMPP
- ✅ Forced Engine.IO v4 (`allowEIO3: false`)
- ✅ Enabled both WebSocket and polling transports

### 2. Client Configuration
- ✅ Updated client to Socket.io v4.8.3 CDN
- ✅ Added proper connection options (transports, reconnection)
- ✅ Fixed timeout and reconnection settings

---

## 🚀 How to Start the Server

### Option 1: Using the Batch File (Easiest)
```bash
cd C:\xampp\htdocs\printflow\call-server
start.bat
```

### Option 2: Manual Start
```bash
cd C:\xampp\htdocs\printflow\call-server
npm install
node server.js
```

You should see:
```
╔═══════════════════════════════════════════════╗
║  🚀 PrintFlow Call Server (Socket.io v4)     ║
╠═══════════════════════════════════════════════╣
║  Port:        3000                            ║
║  URL:         http://localhost:3000           ║
║  Engine.IO:   v4 (EIO=4)                      ║
║  Transports:  WebSocket, Polling              ║
╚═══════════════════════════════════════════════╝
```

---

## 🔍 How to Verify Everything is Working

### 1. Check Server Version
```bash
cd C:\xampp\htdocs\printflow\call-server
npm list socket.io
```
Should show: `socket.io@4.8.3`

### 2. Check Client Version
Open your browser console on any page with the call feature:
```javascript
console.log(io.version);
```
Should show: `4.8.3`

### 3. Test Connection
Open browser console and run:
```javascript
const testSocket = io('http://localhost:3000');
testSocket.on('connect', () => console.log('✅ Connected!'));
testSocket.on('connect_error', (err) => console.log('❌ Error:', err));
```

---

## 🐛 Common Errors & Solutions

### Error: `GET http://localhost:3000/socket.io/?EIO=4 400 (Bad Request)`

**Cause:** Version mismatch or server not running

**Solutions:**
1. Make sure server is running: `cd call-server && node server.js`
2. Check versions match:
   - Server: `npm list socket.io` → should be 4.8.3
   - Client: Check `<script src="https://cdn.socket.io/4.8.3/socket.io.min.js">`
3. Clear browser cache (Ctrl+Shift+Delete)

---

### Error: `WebSocket is closed before the connection is established`

**Cause:** CORS or transport issues

**Solutions:**
1. Server CORS is now configured for localhost
2. Client now uses both transports: `['websocket', 'polling']`
3. Restart server after changes

---

### Error: `io is not defined`

**Cause:** Socket.io client script not loaded

**Solution:**
Make sure this is in your HTML `<head>`:
```html
<script src="https://cdn.socket.io/4.8.3/socket.io.min.js"></script>
```

---

## 📚 Understanding EIO=4 and Socket.io v4

### What is EIO?
- **EIO** = Engine.IO protocol version
- Engine.IO is the low-level transport layer used by Socket.io
- **EIO=4** means Engine.IO protocol version 4

### Version Mapping
| Socket.io Version | Engine.IO Version | URL Parameter |
|-------------------|-------------------|---------------|
| Socket.io v2.x    | Engine.IO v3     | `?EIO=3`      |
| Socket.io v3.x    | Engine.IO v4     | `?EIO=4`      |
| Socket.io v4.x    | Engine.IO v4     | `?EIO=4`      |

### Why Version Mismatch Causes 400 Error
1. Client sends `?EIO=4` in the connection URL
2. If server is running Socket.io v2 (which uses EIO=3), it doesn't understand EIO=4
3. Server responds with **400 Bad Request** because the protocol version is incompatible
4. Connection fails immediately

### Our Setup (Correct)
- ✅ **Server:** Socket.io v4.8.3 → Engine.IO v4 → Accepts `?EIO=4`
- ✅ **Client:** Socket.io v4.8.3 → Engine.IO v4 → Sends `?EIO=4`
- ✅ **Result:** Perfect match, connection succeeds

---

## 🧪 Testing the Call Feature

1. **Start the server:**
   ```bash
   cd C:\xampp\htdocs\printflow\call-server
   node server.js
   ```

2. **Open two browser windows:**
   - Window 1: Login as Customer
   - Window 2: Login as Staff

3. **Navigate to chat page** in both windows

4. **Initiate a call** from one window

5. **Check server console** for logs:
   ```
   ✅ [Connected] Socket: abc123
   👤 [Registered] User: 1 (Customer) - Socket: abc123
   📞 [Call] 1 → 2 (voice)
   ```

---

## 📝 Files Modified

1. ✅ `call-server/server.js` - New proper Socket.io v4 server
2. ✅ `call-server/start.bat` - Easy startup script
3. ✅ `public/assets/js/printflow_call.js` - Updated connection options
4. ✅ `customer/chat.php` - Updated to Socket.io v4.8.3 CDN

---

## 🔧 Advanced Configuration

### Change Server Port
Edit `call-server/server.js`:
```javascript
const PORT = 3000; // Change to your desired port
```

Also update client in `printflow_call.js`:
```javascript
this.socket = io('http://localhost:3000', { // Update port here
```

### Enable Debug Logging
**Server side:**
```javascript
const io = new Server(server, {
    // ... existing config
    connectionStateRecovery: {
        maxDisconnectionDuration: 2 * 60 * 1000,
    }
});
```

**Client side:**
```javascript
this.socket = io('http://localhost:3000', {
    // ... existing config
    debug: true
});
```

---

## ✅ Checklist

- [ ] Node.js installed
- [ ] Server dependencies installed (`npm install`)
- [ ] Server running on port 3000
- [ ] Client script tag uses Socket.io v4.8.3
- [ ] Browser cache cleared
- [ ] No firewall blocking port 3000
- [ ] XAMPP Apache running on port 80

---

## 🆘 Still Having Issues?

1. **Check server logs** for error messages
2. **Check browser console** for connection errors
3. **Verify port 3000 is not in use:**
   ```bash
   netstat -ano | findstr :3000
   ```
4. **Test server directly:**
   ```bash
   curl http://localhost:3000/socket.io/?EIO=4&transport=polling
   ```
   Should return: `0{"sid":"...","upgrades":["websocket"],...}`

---

**Last Updated:** 2024
**Socket.io Version:** 4.8.3
**Engine.IO Version:** 4
