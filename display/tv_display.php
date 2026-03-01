<?php
/**
 * TV Display - Now Serving Screen
 * For HDMI-connected displays
 */

require_once __DIR__ . '/../dbconfig.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Now Serving - EAC-Cavite</title>
    <meta http-equiv="refresh" content="5">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #0a0a0a;
            color: white;
            font-family: 'Segoe UI', Arial, sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(90deg, #1a1a2e 0%, #16213e 100%);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #e94560;
        }
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .clock {
            font-size: 2rem;
            font-family: monospace;
        }
        .main-content {
            display: flex;
            height: calc(100vh - 100px);
        }
        .windows-grid {
            flex: 2;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            padding: 30px;
        }
        .window-box {
            background: #1a1a2e;
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            border: 3px solid #333;
            transition: all 0.3s;
        }
        .window-box.active {
            border-color: #e94560;
            background: #16213e;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 20px rgba(233, 69, 96, 0.5); }
            50% { box-shadow: 0 0 40px rgba(233, 69, 96, 0.8); }
        }
        .window-label {
            font-size: 1.5rem;
            color: #888;
            margin-bottom: 15px;
        }
        .window-ticket {
            font-size: 4rem;
            font-weight: bold;
            color: #e94560;
            margin-bottom: 10px;
        }
        .window-status {
            font-size: 1.2rem;
            color: #0f3460;
        }
        .sidebar {
            flex: 1;
            background: #1a1a2e;
            padding: 30px;
            border-left: 3px solid #333;
        }
        .stats-box {
            margin-bottom: 30px;
        }
        .stats-label {
            color: #888;
            font-size: 1rem;
            text-transform: uppercase;
        }
        .stats-value {
            font-size: 3rem;
            font-weight: bold;
            color: #e94560;
        }
        .queue-preview {
            margin-top: 30px;
        }
        .queue-item {
            background: #16213e;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
        }
        .footer-ticker {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #e94560;
            padding: 15px;
            font-size: 1.2rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🏫 EAC-Cavite Cashier</div>
        <div class="clock" id="clock">--:--</div>
    </div>
    
    <div class="main-content">
        <div class="windows-grid" id="windowsGrid">
            <!-- Populated by JavaScript -->
        </div>
        
        <div class="sidebar">
            <div class="stats-box">
                <div class="stats-label">Now Waiting</div>
                <div class="stats-value" id="waitingCount">--</div>
            </div>
            <div class="stats-box">
                <div class="stats-label">Est. Max Wait</div>
                <div class="stats-value" id="maxWait">--</div>
            </div>
            
            <div class="queue-preview">
                <h4 style="color: #888; margin-bottom: 15px;">Next Tickets</h4>
                <div id="nextQueue"></div>
            </div>
        </div>
    </div>
    
    <div class="footer-ticker">
        🎓 Welcome to Emilio Aguinaldo College - Cavite | Please wait for your number to be called
    </div>
    
    <script>
        function updateClock() {
            const now = new Date();
            document.getElementById('clock').textContent = 
                now.toLocaleTimeString('en-PH', {hour: '2-digit', minute: '2-digit'});
        }
        setInterval(updateClock, 1000);
        updateClock();
        
        // Fetch and display status
        async function updateDisplay() {
            try {
                const response = await fetch('../api/tv_status.php');
                const data = await response.json();
                
                // Update windows
                const grid = document.getElementById('windowsGrid');
                grid.innerHTML = '';
                
                data.windows.forEach(win => {
                    const div = document.createElement('div');
                    div.className = 'window-box ' + (win.current ? 'active' : '');
                    div.innerHTML = `
                        <div class="window-label">WINDOW ${win.id}</div>
                        <div class="window-ticket">${win.current || '---'}</div>
                        <div class="window-status">${win.current ? 'NOW SERVING' : 'AVAILABLE'}</div>
                    `;
                    grid.appendChild(div);
                });
                
                // Update stats
                document.getElementById('waitingCount').textContent = data.total_waiting;
                document.getElementById('maxWait').textContent = data.max_wait + 'm';
                
                // Update next queue preview
                const nextDiv = document.getElementById('nextQueue');
                nextDiv.innerHTML = '';
                if (data.windows[0] && data.windows[0].next) {
                    data.windows[0].next.slice(0, 5).forEach(ticket => {
                        nextDiv.innerHTML += `
                            <div class="queue-item">
                                <span>${ticket}</span>
                                <span style="color: #888;">Waiting</span>
                            </div>
                        `;
                    });
                }
                
            } catch (e) {
                console.error('Failed to update display:', e);
            }
        }
        
        updateDisplay();
        setInterval(updateDisplay, 5000); // Update every 5 seconds
    </script>
</body>
</html>
