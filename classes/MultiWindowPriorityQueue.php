<?php
/**
 * Multi-Window Priority Queue Algorithm
 * Core of QO-DSS - Implements Tables 2.1, 2.4, 2.5 from thesis
 */

require_once __DIR__ . '/../dbconfig.php';

class MultiWindowPriorityQueue {
    private $pdo;
    private $config;
    private $windowConfig;
    
    // Priority base scores (higher = served first)
    const PRIORITY_SCORES = [
        'P0' => 100,  // Mandatory priority (Senior, PWD, Pregnant)
        'P1' => 80,   // Fast lane (Full payment, exact cash)
        'P2' => 60,   // Time-sensitive (Down payment)
        'P3' => 40,   // High complexity (Installment, scholarship)
        'P4' => 20    // General (Documents, misc)
    ];
    
    // Complexity weights (affects service time estimation)
    const COMPLEXITY_WEIGHTS = [
        'P0' => 2,    // Medium
        'P1' => 1,    // Low - fast
        'P2' => 2,    // Medium
        'P3' => 3,    // High - slow
        'P4' => 2     // Medium
    ];
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? getDB();
        $this->loadConfiguration();
    }
    
    /**
     * Load system configuration from database
     */
    private function loadConfiguration() {
        $stmt = $this->pdo->query("SELECT * FROM tbl_settings WHERE setting_id = 1");
        $this->config = $stmt->fetch();
        
        if (!$this->config) {
            // Insert default settings if not exists
            $this->pdo->exec("INSERT INTO tbl_settings (setting_id) VALUES (1)");
            $stmt = $this->pdo->query("SELECT * FROM tbl_settings WHERE setting_id = 1");
            $this->config = $stmt->fetch();
        }
        
        $this->loadWindowConfiguration();
    }
    
    /**
     * Load window eligibility based on current mode (Normal/Peak)
     */
    private function loadWindowConfiguration() {
        $isPeak = ($this->config['current_mode'] ?? 'NORMAL') === 'PEAK';
        
        if ($isPeak) {
            // Table 2.5: Peak-Session Configuration
            $this->windowConfig = [
                1 => ['eligible' => ['P0', 'P1'], 'role' => 'Express'],
                2 => ['eligible' => ['P0', 'P1', 'P2'], 'role' => 'Flexible'],
                3 => ['eligible' => ['P2', 'P3', 'P4'], 'role' => 'General'],
                4 => ['eligible' => ['P2', 'P3', 'P4'], 'role' => 'Tuition Overflow'],
                5 => ['eligible' => ['P2', 'P3', 'P4'], 'role' => 'Tuition Queue'],
                6 => ['eligible' => ['P2', 'P3', 'P4'], 'role' => 'Clearance']
            ];
        } else {
            // Table 2.4: Normal-Session Configuration
            $this->windowConfig = [
                1 => ['eligible' => ['P0', 'P1'], 'role' => 'Express'],
                2 => ['eligible' => ['P0', 'P1', 'P2'], 'role' => 'Flexible'],
                3 => ['eligible' => ['P2', 'P3', 'P4'], 'role' => 'General']
            ];
        }
    }
    
    /**
     * Main enqueue method - Create new ticket
     */
    public function enqueue($data) {
        try {
            $this->pdo->beginTransaction();
            
            // Step 1: Classify transaction tier
            $tier = $this->classifyTransaction($data);
            $complexity = self::COMPLEXITY_WEIGHTS[$tier];
            $basePriority = self::PRIORITY_SCORES[$tier];
            
            // Step 2: Calculate dynamic priority (includes aging if applicable)
            $dynamicPriority = $basePriority;
            
            // Step 3: Estimate service time
            $estServiceTime = $this->estimateServiceTime($tier, $data);
            
            // Step 4: Generate ticket ID
            $ticketId = $this->generateTicketId();
            
            // Step 5: Insert into database
            $sql = "INSERT INTO tbl_tickets (
                ticket_id, client_type, client_category, transaction_category,
                transaction_type, priority_level, complexity_weight, 
                arrival_timestamp, status, estimated_service_time,
                exact_cash, requires_change, details
            ) VALUES (
                :ticket_id, :client_type, :client_category, :tier,
                :trans_type, :priority, :complexity,
                NOW(), 'Waiting', :est_time,
                :exact_cash, :requires_change, :details
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':ticket_id' => $ticketId,
                ':client_type' => $data['client_type'] ?? 'Student',
                ':client_category' => $data['client_category'] ?? 'General',
                ':tier' => $tier,
                ':trans_type' => $data['transaction_type'] ?? 'General',
                ':priority' => $dynamicPriority,
                ':complexity' => $complexity,
                ':est_time' => $estServiceTime,
                ':exact_cash' => $data['exact_cash'] ?? false,
                ':requires_change' => $data['requires_change'] ?? true,
                ':details' => json_encode($data['details'] ?? [])
            ]);
            
            // Step 6: Add to eligible window queues
            $eligibleWindows = $this->getEligibleWindows($tier);
            foreach ($eligibleWindows as $windowId) {
                $this->addToWindowQueue($windowId, $ticketId, $tier);
            }
            
            // Step 7: Log action
            $this->logAction($ticketId, 'CREATED', "Tier: $tier, Windows: " . implode(',', $eligibleWindows));
            
            // Step 8: Check if peak mode should trigger
            $this->checkPeakModeTrigger();
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'ticket_id' => $ticketId,
                'tier' => $tier,
                'tier_name' => $this->getTierName($tier),
                'priority_score' => $dynamicPriority,
                'eligible_windows' => $eligibleWindows,
                'estimated_wait_minutes' => $this->calculateEstimatedWait($ticketId, $tier, $eligibleWindows),
                'estimated_service_minutes' => $estServiceTime,
                'queue_position' => $this->estimateQueuePosition($ticketId, $tier),
                'timestamp' => date('Y-m-d H:i:s'),
                'qr_data' => base64_encode(json_encode(['id' => $ticketId, 'tier' => $tier]))
            ];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Enqueue error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to create ticket'];
        }
    }
    
    /**
     * Dequeue - Get next ticket for specific window
     */
    public function dequeue($windowId) {
        try {
            $this->pdo->beginTransaction();
            
            // Verify window exists
            if (!isset($this->windowConfig[$windowId])) {
                throw new Exception("Invalid window: $windowId");
            }
            
            // Get eligible tiers for this window
            $eligibleTiers = $this->windowConfig[$windowId]['eligible'];
            $tierList = implode("','", $eligibleTiers);
            
            // Find highest priority ticket waiting for this window
            // Non-preemptive: status must be 'Waiting' (not 'Serving')
            $sql = "SELECT t.*, 
                    TIMESTAMPDIFF(MINUTE, t.arrival_timestamp, NOW()) as current_wait_min
                    FROM tbl_tickets t
                    INNER JOIN tbl_window_queues wq ON t.ticket_id = wq.ticket_id
                    WHERE wq.window_id = :window_id 
                    AND t.status = 'Waiting'
                    AND t.transaction_category IN ('$tierList')
                    AND wq.is_eligible = 1
                    ORDER BY 
                        t.priority_level DESC,
                        t.aging_promoted DESC,
                        t.arrival_timestamp ASC
                    LIMIT 1 FOR UPDATE";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':window_id' => $windowId]);
            $ticket = $stmt->fetch();
            
            if (!$ticket) {
                $this->pdo->rollBack();
                return null; // No tickets available
            }
            
            // Mark as serving
            $updateSql = "UPDATE tbl_tickets 
                         SET status = 'Serving', 
                             service_start_time = NOW(),
                             assigned_window = :window,
                             actual_wait_time = TIMESTAMPDIFF(MINUTE, arrival_timestamp, NOW())
                         WHERE ticket_id = :ticket_id";
            
            $this->pdo->prepare($updateSql)->execute([
                ':window' => $windowId,
                ':ticket_id' => $ticket['ticket_id']
            ]);
            
            // Remove from other windows' queues (non-preemptive constraint)
            $this->pdo->prepare("
                DELETE FROM tbl_window_queues 
                WHERE ticket_id = :ticket_id AND window_id != :window
            ")->execute([
                ':ticket_id' => $ticket['ticket_id'],
                ':window' => $windowId
            ]);
            
            // Mark as active in current window
            $this->pdo->prepare("
                UPDATE tbl_window_queues 
                SET is_active = 1, served_at = NOW()
                WHERE ticket_id = :ticket_id AND window_id = :window
            ")->execute([
                ':ticket_id' => $ticket['ticket_id'],
                ':window' => $windowId
            ]);
            
            $this->logAction($ticket['ticket_id'], 'ASSIGNED', "Window: $windowId");
            
            $this->pdo->commit();
            
            return [
                'ticket_id' => $ticket['ticket_id'],
                'tier' => $ticket['transaction_category'],
                'client_type' => $ticket['client_type'],
                'client_category' => $ticket['client_category'],
                'transaction_type' => $ticket['transaction_type'],
                'waiting_time_minutes' => $ticket['current_wait_min'],
                'estimated_service_time' => $ticket['estimated_service_time'],
                'priority_score' => $ticket['priority_level']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Dequeue error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Complete service for current ticket
     */
    public function completeService($ticketId, $windowId) {
        try {
            $sql = "UPDATE tbl_tickets 
                   SET status = 'Completed',
                       service_end_time = NOW(),
                       actual_service_time = TIMESTAMPDIFF(MINUTE, service_start_time, NOW())
                   WHERE ticket_id = :ticket_id AND assigned_window = :window";
            
            $this->pdo->prepare($sql)->execute([
                ':ticket_id' => $ticketId,
                ':window' => $windowId
            ]);
            
            // Clean up window queue
            $this->pdo->prepare("
                DELETE FROM tbl_window_queues WHERE ticket_id = :ticket_id
            ")->execute([':ticket_id' => $ticketId]);
            
            $this->logAction($ticketId, 'COMPLETED', "Window: $windowId");
            
            return true;
        } catch (PDOException $e) {
            error_log("Complete service error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process aging - Promote long-waiting tickets
     * Implements Table 2.1 aging rules
     */
    public function processAging() {
        $promotions = [];
        
        // Table 2.1 Aging Rules
        $agingRules = [
            'P1' => ['threshold' => $this->config['aging_threshold_p1'], 'promote_to' => 'P0', 'bonus' => 20],
            'P2' => ['threshold' => $this->config['aging_threshold_p2'], 'promote_to' => 'P1', 'bonus' => 15],
            'P3' => ['threshold' => $this->config['aging_threshold_p3'], 'promote_to' => 'P2', 'bonus' => 10],
            'P4' => ['threshold' => $this->config['aging_threshold_p4'], 'promote_to' => 'P3', 'bonus' => 5]
        ];
        
        foreach ($agingRules as $currentTier => $rule) {
            // Find tickets exceeding threshold
            $sql = "SELECT ticket_id, priority_level,
                    TIMESTAMPDIFF(MINUTE, arrival_timestamp, NOW()) as wait_min
                    FROM tbl_tickets
                    WHERE transaction_category = :tier 
                    AND status = 'Waiting'
                    AND aging_promoted = FALSE
                    AND TIMESTAMPDIFF(MINUTE, arrival_timestamp, NOW()) >= :threshold";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':tier' => $currentTier,
                ':threshold' => $rule['threshold']
            ]);
            
            while ($row = $stmt->fetch()) {
                // Promote tier and add priority bonus
                $newPriority = self::PRIORITY_SCORES[$rule['promote_to']] + $rule['bonus'];
                
                $updateSql = "UPDATE tbl_tickets 
                             SET transaction_category = :new_tier,
                                 priority_level = :new_priority,
                                 aging_promoted = TRUE,
                                 promotion_reason = 'Aging: waited {$row['wait_min']}min'
                             WHERE ticket_id = :ticket_id";
                
                $this->pdo->prepare($updateSql)->execute([
                    ':new_tier' => $rule['promote_to'],
                    ':new_priority' => $newPriority,
                    ':ticket_id' => $row['ticket_id']
                ]);
                
                $promotions[] = [
                    'ticket_id' => $row['ticket_id'],
                    'from_tier' => $currentTier,
                    'to_tier' => $rule['promote_to'],
                    'wait_minutes' => $row['wait_min']
                ];
                
                $this->logAction($row['ticket_id'], 'PROMOTED', 
                    "Aging: $currentTier -> {$rule['promote_to']} after {$row['wait_min']}min");
                
                // Hard cap check: 60 minutes -> force P0
                $hardCap = $this->config['max_wait_hard_cap'] ?? 60;
                if ($row['wait_min'] >= $hardCap && $rule['promote_to'] !== 'P0') {
                    $this->forcePromoteToP0($row['ticket_id'], $row['wait_min']);
                }
            }
        }
        
        return $promotions;
    }
    
    /**
     * Manual priority override (with audit trail)
     */
    public function overridePriority($ticketId, $newTier, $reason, $performedBy) {
        try {
            $oldTicket = $this->pdo->prepare("
                SELECT transaction_category, priority_level FROM tbl_tickets WHERE ticket_id = ?
            ")->execute([$ticketId])->fetch();
            
            $newPriority = self::PRIORITY_SCORES[$newTier] + 50; // Override bonus
            
            $sql = "UPDATE tbl_tickets 
                   SET transaction_category = :tier,
                       priority_level = :priority,
                       override_code = :reason,
                       updated_at = NOW()
                   WHERE ticket_id = :ticket_id";
            
            $this->pdo->prepare($sql)->execute([
                ':tier' => $newTier,
                ':priority' => $newPriority,
                ':reason' => substr($reason, 0, 20),
                ':ticket_id' => $ticketId
            ]);
            
            $this->logAction($ticketId, 'OVERRIDE', 
                "Manual: {$oldTicket['transaction_category']} -> $newTier by $performedBy. Reason: $reason");
            
            return true;
        } catch (PDOException $e) {
            error_log("Override error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get real-time metrics for DSS dashboard
     */
    public function getMetrics() {
        // Queue length by tier
        $queueByTier = $this->pdo->query("
            SELECT transaction_category, COUNT(*) as count,
                   AVG(TIMESTAMPDIFF(MINUTE, arrival_timestamp, NOW())) as avg_wait
            FROM tbl_tickets 
            WHERE status = 'Waiting'
            GROUP BY transaction_category
        ")->fetchAll();
        
        // Window utilization
        $windowStats = $this->pdo->query("
            SELECT assigned_window,
                   COUNT(*) as served_today,
                   AVG(actual_service_time) as avg_service_time
            FROM tbl_tickets
            WHERE DATE(service_end_time) = CURDATE()
            AND status = 'Completed'
            GROUP BY assigned_window
        ")->fetchAll();
        
        // Current serving
        $nowServing = $this->pdo->query("
            SELECT assigned_window, ticket_id, transaction_category
            FROM tbl_tickets
            WHERE status = 'Serving'
        ")->fetchAll();
        
        // Calculate utilization rate (ρ)
        $totalWindows = count($this->windowConfig);
        $activeWindows = count($nowServing);
        $utilization = $activeWindows / $totalWindows;
        
        return [
            'total_waiting' => array_sum(array_column($queueByTier, 'count')),
            'queue_by_tier' => $queueByTier,
            'window_stats' => $windowStats,
            'now_serving' => $nowServing,
            'utilization_rate' => round($utilization, 2),
            'active_windows' => $activeWindows,
            'total_windows' => $totalWindows,
            'mode' => $this->config['current_mode'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Toggle between Normal and Peak mode
     */
    public function setPeakMode($enable) {
        $mode = $enable ? 'PEAK' : 'NORMAL';
        $this->pdo->prepare("
            UPDATE tbl_settings SET current_mode = :mode WHERE setting_id = 1
        ")->execute([':mode' => $mode]);
        
        $this->config['current_mode'] = $mode;
        $this->loadWindowConfiguration();
        
        $this->logAction('SYSTEM', 'MODE_CHANGE', "Switched to $mode mode");
        
        return $mode;
    }
    
    // ============ PRIVATE HELPER METHODS ============
    
    private function classifyTransaction($data) {
        // Check for mandatory priority groups first
        $category = $data['client_category'] ?? 'General';
        if (in_array($category, ['Senior', 'PWD', 'Pregnant'])) {
            return 'P0';
        }
        
        $type = $data['transaction_type'] ?? '';
        $exactCash = $data['exact_cash'] ?? false;
        $estDuration = $data['estimated_duration'] ?? 999;
        
        // P1: Fast lane transactions
        if ($type === 'Full Payment' || 
            ($type === 'Exact Payment') ||
            ($type === 'Down Payment' && $exactCash && $estDuration <= 2)) {
            return 'P1';
        }
        
        // P2: Time-sensitive enrollment
        if ($type === 'Down Payment' && !$exactCash) {
            return 'P2';
        }
        
        // P3: Complex financial
        if (in_array($type, ['Installment', 'Scholarship', 'Verification', 'Adjustment'])) {
            return 'P3';
        }
        
        // P4: Everything else
        return 'P4';
    }
    
    private function getEligibleWindows($tier) {
        $eligible = [];
        foreach ($this->windowConfig as $windowId => $config) {
            if (in_array($tier, $config['eligible'])) {
                $eligible[] = $windowId;
            }
        }
        return $eligible;
    }
    
    private function estimateServiceTime($tier, $data) {
        $baseTimes = [
            'P0' => 5,   // Priority: medium complexity but expedited
            'P1' => 3,   // Fast lane: 1-5 minutes
            'P2' => 8,   // Down payment: 5-10 minutes
            'P3' => 15,  // Complex: 10-20 minutes
            'P4' => 10   // General: 5-15 minutes
        ];
        
        $base = $baseTimes[$tier] ?? 10;
        
        // Adjust for specific transaction details
        if ($data['transaction_type'] === 'Scholarship') {
            $base += 5; // Additional verification time
        }
        
        return $base;
    }
    
    private function calculateEstimatedWait($ticketId, $tier, $eligibleWindows) {
        // Count people ahead in queue across all eligible windows
        $placeholders = implode(',', array_fill(0, count($eligibleWindows), '?'));
        
        $sql = "SELECT COUNT(*) as ahead
                FROM tbl_tickets
                WHERE status = 'Waiting'
                AND priority_level > (SELECT priority_level FROM tbl_tickets WHERE ticket_id = ?)
                AND transaction_category IN (
                    SELECT transaction_category FROM tbl_tickets 
                    WHERE ticket_id IN (
                        SELECT ticket_id FROM tbl_window_queues 
                        WHERE window_id IN ($placeholders)
                    )
                )";
        
        $params = array_merge([$ticketId], $eligibleWindows);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $ahead = $stmt->fetchColumn();
        
        // Estimate: average service time * position / number of windows
        $avgServiceTime = 8; // minutes
        $estimatedMinutes = ceil(($ahead * $avgServiceTime) / count($eligibleWindows));
        
        return max(1, $estimatedMinutes); // Minimum 1 minute
    }
    
    private function generateTicketId() {
        $date = date('Ymd');
        $prefix = "T-$date-";
        
        $stmt = $this->pdo->prepare("
            SELECT MAX(CAST(SUBSTRING(ticket_id, -4) AS UNSIGNED)) as last_seq
            FROM tbl_tickets 
            WHERE ticket_id LIKE ?
        ");
        $stmt->execute([$prefix . '%']);
        $lastSeq = $stmt->fetchColumn() ?? 0;
        
        $nextSeq = $lastSeq + 1;
        return $prefix . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
    }
    
    private function addToWindowQueue($windowId, $ticketId, $tier) {
        $sql = "INSERT INTO tbl_window_queues (window_id, ticket_id, is_eligible, tier_at_entry)
                VALUES (:window, :ticket, 1, :tier)";
        $this->pdo->prepare($sql)->execute([
            ':window' => $windowId,
            ':ticket' => $ticketId,
            ':tier' => $tier
        ]);
    }
    
    private function forcePromoteToP0($ticketId, $waitMinutes) {
        $this->pdo->prepare("
            UPDATE tbl_tickets 
            SET transaction_category = 'P0',
                priority_level = 999,
                promotion_reason = 'HARD_CAP: forced P0 after {$waitMinutes}min'
            WHERE ticket_id = ?
        ")->execute([$ticketId]);
        
        $this->logAction($ticketId, 'HARD_CAP_PROMOTION', "Forced to P0 after $waitMinutes minutes");
    }
    
    private function checkPeakModeTrigger() {
        $threshold = $this->config['peak_mode_threshold'] ?? 50;
        
        $count = $this->pdo->query("
            SELECT COUNT(*) FROM tbl_tickets WHERE status = 'Waiting'
        ")->fetchColumn();
        
        if ($count > $threshold && $this->config['current_mode'] === 'NORMAL') {
            // Auto-suggest peak mode (don't auto-enable, just alert)
            $this->pdo->prepare("
                INSERT INTO tbl_dss_alerts 
                (alert_type, severity, message, recommended_action, created_at)
                VALUES ('PEAK_SUGGESTION', 'HIGH', 
                        'Queue length ($count) exceeds threshold ($threshold)',
                        'Consider activating Peak Mode to open Windows 4-6',
                        NOW())
            ")->execute();
        }
    }
    
    private function logAction($ticketId, $action, $details) {
        $sql = "INSERT INTO tbl_logs (ticket_id, action_type, details, performed_by)
                VALUES (:ticket, :action, :details, 'SYSTEM')";
        $this->pdo->prepare($sql)->execute([
            ':ticket' => $ticketId,
            ':action' => $action,
            ':details' => $details
        ]);
    }
    
    private function getTierName($tier) {
        $names = [
            'P0' => 'Priority Access',
            'P1' => 'Fast Lane',
            'P2' => 'Time-Sensitive',
            'P3' => 'Standard Processing',
            'P4' => 'General Queue'
        ];
        return $names[$tier] ?? 'Standard';
    }
    
    private function estimateQueuePosition($ticketId, $tier) {
        // Simplified position estimation
        $sql = "SELECT COUNT(*) FROM tbl_tickets 
                WHERE status = 'Waiting' 
                AND arrival_timestamp < (SELECT arrival_timestamp FROM tbl_tickets WHERE ticket_id = ?)
                AND priority_level >= (SELECT priority_level FROM tbl_tickets WHERE ticket_id = ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ticketId, $ticketId]);
        return $stmt->fetchColumn() + 1;
    }
}
?>
