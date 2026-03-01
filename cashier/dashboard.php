<?php
/**
 * Cashier Main Dashboard
 */

session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../dbconfig.php';

verifyCashierAuth(); // From auth_check.php

$windowId = $_SESSION['window_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Window <?= $windowId ?> - Cashier Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .main-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
        }
        .ticket-number {
            font-size: 5rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .control-btn {
            padding: 20px 40px;
            font-size: 1.3rem;
            border-radius: 15px;
            margin: 10px;
        }
        .queue-list {
            max-height: 400px;
            overflow-y: auto;
        }
        .queue-item {
            padding: 15px;
            border-left: 5px solid;
            margin-bottom: 10px;
            background: white;
            border-radius: 10px;
        }
        .tier-p0 { border-left-color: #dc3545; }
        .tier-p1 { border-left-color: #fd7e14; }
        .tier-p2 { border-left-color: #ffc107; }
        .tier-p3 { border-left-color: #0dcaf0; }
        .tier-p4 { border-left-color: #6c757d; }
        .dss-alert {
            animation: pulse 2s infinite;
            border-radius: 10px;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand">QO-DSS Cashier Interface</span>
            <div class="d-flex align-items-center text-white">
                <span class="me-4">Window <?= $windowId ?></span>
                <span class="me-4"><?= $_SESSION['username'] ?></span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Main Display -->
            <div class="col-md-8">
                <div class="main-display text-center">
                    <h3 class="mb-4">NOW SERVING</h3>
                    <div class="ticket-number" id="currentTicket">---</div>
                    <div id="ticketInfo" class="mt-3">
                        <p class="lead">Waiting for next customer...</p>
                    </div>
                    <div id="serviceTimer" class="mt-3 h4">00:00</div>
                </div>
                
                <!-- Controls -->
                <div class="text-center">
                    <button class="btn btn-success control-btn" onclick="callNext()" id="btnCallNext">
                        📢 Call Next
                    </button>
                    <button class="btn btn-primary control-btn" onclick="completeService()" id="btnComplete" disabled>
                        ✅ Complete
                    </button>
                    <button class="btn btn-warning control-btn" onclick="escalateTicket()" id="btnEscalate" disabled>
                        ⬆️ Escalate
                    </button>
                    <button class="btn btn-danger control-btn" onclick="noShow()" id="btnNoShow" disabled>
                        ❌ No Show
                    </button>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- DSS Alerts -->
                <div id="dssAlerts" class="mb-4"></div>
                
                <!-- Queue Status -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Queue Status</h5>
                    </div>
                    <div class="card-body queue-list" id="queueList">
                        <p class="text-muted">Loading...</p>
                    </div>
                </div>
                
                <!-- My Stats -->
                <div class="card mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">Today's Performance</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h3 mb-0" id="statServed">0</div>
                                <small class="text-muted">Served</small>
                            </div>
                            <div class="col-6">
                                <div class="h3 mb-0" id="statAvgTime">0</div>
                                <small class="text-muted">Avg Time</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Audio for announcements -->
    <audio id="announcementAudio" preload="auto"></audio>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let currentTicketId = null;
        let serviceStartTime = null;
        let timerInterval = null;
        
        // Load initial status
        $(document).ready(function() {
            updateStatus();
            setInterval(updateStatus, 5000); // Refresh every 5 seconds
        });
        
        function updateStatus() {
            $.getJSON('../api/cashier_status.php?window=<?= $windowId ?>', function(data) {
                // Update current ticket
                if (data.current_ticket) {
                    currentTicketId = data.current_ticket.ticket_id;
                    $('#currentTicket').text(currentTicketId);
                    $('#ticketInfo').html(`
                        <span class="badge bg-${getTierColor(data.current_ticket.transaction_category)}">
                            ${data.current_ticket.transaction_category}
                        </span>
                        <p class="mt-2">${data.current_ticket.client_type} | ${data.current_ticket.client_category}</p>
                        <p>Waited: ${data.current_ticket.waiting_time_minutes} min</p>
                    `);
                    $('#btnCallNext').prop('disabled', true);
                    $('#btnComplete, #btnEscalate, #btnNoShow').prop('disabled', false);
                    
                    if (!serviceStartTime) {
                        startTimer();
                    }
                } else {
                    resetDisplay();
                }
                
                // Update queue list
                updateQueueList(data.queue);
                
                // Update stats
                if (data.stats) {
                    $('#statServed').text(data.stats.served_today || 0);
                    $('#statAvgTime').text(Math.round(data.stats.avg_service_time || 0) + 'm');
                }
                
                // Update alerts
                updateAlerts(data.alerts);
            });
        }
        
        function callNext() {
            $.post('../api/dequeue.php', {}, function(response) {
                if (response.ticket) {
                    // Play announcement
                    speakText(response.announcement);
                    updateStatus();
                } else {
                    alert('No tickets available');
                }
            });
        }
        
        function completeService() {
            if (!currentTicketId) return;
            
            $.post('../api/complete_ticket.php', 
                {ticket_id: currentTicketId}, 
                function(response) {
                    if (response.success) {
                        stopTimer();
                        resetDisplay();
                        updateStatus();
                    }
                }
            );
        }
        
        function escalateTicket() {
            if (!currentTicketId) return;
            // Implementation for escalation
        }
        
        function noShow() {
            if (!currentTicketId) return;
            
            $.post('../api/cancel_ticket.php', 
                {
                    ticket_id: currentTicketId,
                    reason: 'No-show'
                }, 
                function(response) {
                    if (response.success) {
                        stopTimer();
                        resetDisplay();
                        updateStatus();
                    }
                }
            );
        }
        
        function startTimer() {
            serviceStartTime = Date.now();
            timerInterval = setInterval(updateTimer, 1000);
        }
        
        function stopTimer() {
            clearInterval(timerInterval);
            serviceStartTime = null;
            $('#serviceTimer').text('00:00');
        }
        
        function updateTimer() {
            if (!serviceStartTime) return;
            const elapsed = Math.floor((Date.now() - serviceStartTime) / 1000);
            const mins = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const secs = (elapsed % 60).toString().padStart(2, '0');
            $('#serviceTimer').text(`${mins}:${secs}`);
        }
        
        function resetDisplay() {
            currentTicketId = null;
            $('#currentTicket').text('---');
            $('#ticketInfo').html('<p class="lead">Waiting for next customer...</p>');
            $('#btnCallNext').prop('disabled', false);
            $('#btnComplete, #btnEscalate, #btnNoShow').prop('disabled', true);
            stopTimer();
        }
        
        function updateQueueList(queue) {
            let html = '';
            if (queue.length === 0) {
                html = '<p class="text-muted text-center">No waiting customers</p>';
            } else {
                queue.forEach((item, index) => {
                    html += `
                        <div class="queue-item tier-${item.transaction_category.toLowerCase()}">
                            <div class="d-flex justify-content-between">
                                <strong>${item.ticket_id}</strong>
                                <span class="badge bg-secondary">#${index + 1}</span>
                            </div>
                            <small class="text-muted">
                                ${item.transaction_category} | 
                                Waiting: ${item.waiting_minutes} min
                            </small>
                        </div>
                    `;
                });
            }
            $('#queueList').html(html);
        }
        
        function updateAlerts(alerts) {
            if (!alerts || alerts.length === 0) {
                $('#dssAlerts').empty();
                return;
            }
            
            let html = '<div class="alert alert-warning dss-alert">';
            html += '<strong>⚠️ System Alert</strong><br>';
            alerts.forEach(alert => {
                html += `${alert.message}<br>`;
            });
            html += '</div>';
            $('#dssAlerts').html(html);
        }
        
        function getTierColor(tier) {
            const colors = {P0: 'danger', P1: 'warning', P2: 'info', P3: 'secondary', P4: 'dark'};
            return colors[tier] || 'secondary';
        }
        
        function speakText(text) {
            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.rate = 0.8;
                utterance.pitch = 1;
                speechSynthesis.speak(utterance);
            }
        }
    </script>
</body>
</html>
