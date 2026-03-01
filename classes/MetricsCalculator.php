<?php
/**
 * Queue Performance Metrics Calculator
 * Implements queuing theory formulas (M/M/s model)
 */

class MetricsCalculator {
    private $pdo;
    
    // Queuing theory constants
    const TARGET_UTILIZATION = 0.75; // 75% optimal
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? getDB();
    }
    
    /**
     * Calculate server utilization (ρ)
     * Formula: ρ = λ / (s × μ)
     */
    public function calculateUtilization($windowId = null, $period = 'today') {
        $whereClause = $windowId ? "AND assigned_window = $windowId" : "";
        
        $sql = "SELECT 
                COUNT(*) as total_served,
                SUM(actual_service_time) as total_service_time,
                TIMESTAMPDIFF(MINUTE, MIN(service_start_time), MAX(service_end_time)) as total_period
                FROM tbl_tickets
                WHERE status = 'Completed'
                AND DATE(service_end_time) = CURDATE()
                $whereClause";
        
        $result = $this->pdo->query($sql)->fetch();
        
        if (!$result || $result['total_period'] == 0) {
            return 0;
        }
        
        // λ (arrival rate) = customers per minute
        $lambda = $result['total_served'] / $result['total_period'];
        
        // μ (service rate) = 1 / average service time
        $avgServiceTime = $result['total_service_time'] / max(1, $result['total_served']);
        $mu = 1 / max(0.1, $avgServiceTime); // Prevent division by zero
        
        // s = number of servers (windows)
        $s = $windowId ? 1 : $this->getActiveWindowCount();
        
        // ρ = λ / (s × μ)
        $rho = $lambda / ($s * $mu);
        
        return min(1.0, max(0, $rho)); // Bound between 0 and 1
    }
    
    /**
     * Calculate average queue length (Lq)
     */
    public function calculateAverageQueueLength() {
        return $this->pdo->query("
            SELECT AVG(queue_length) FROM (
                SELECT DATE_FORMAT(arrival_timestamp, '%H:%i') as time_slot,
                       COUNT(*) as queue_length
                FROM tbl_tickets
                WHERE status = 'Waiting'
                AND arrival_timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY time_slot
            ) as subq
        ")->fetchColumn() ?? 0;
    }
    
    /**
     * Calculate average waiting time in queue (Wq)
     */
    public function calculateAverageWaitingTime($period = 'today') {
        $dateFilter = $period === 'today' ? "DATE(service_start_time) = CURDATE()" : "1=1";
        
        return $this->pdo->query("
            SELECT AVG(actual_wait_time) 
            FROM tbl_tickets 
            WHERE status = 'Completed'
            AND actual_wait_time IS NOT NULL
            AND $dateFilter
        ")->fetchColumn() ?? 0;
    }
    
    /**
     * Calculate throughput (customers per hour)
     */
    public function calculateThroughput($windowId = null) {
        $whereClause = $windowId ? "AND assigned_window = $windowId" : "";
        
        $result = $this->pdo->query("
            SELECT COUNT(*) as count,
                   HOUR(service_end_time) as hour
            FROM tbl_tickets
            WHERE status = 'Completed'
            AND DATE(service_end_time) = CURDATE()
            $whereClause
            GROUP BY HOUR(service_end_time)
        ")->fetchAll();
        
        if (empty($result)) return 0;
        
        $total = array_sum(array_column($result, 'count'));
        $hours = count($result);
        
        return round($total / max(1, $hours), 2);
    }
    
    /**
     * Calculate service level (% served within target time)
     */
    public function calculateServiceLevel($targetMinutes = 15) {
        $total = $this->pdo->query("
            SELECT COUNT(*) FROM tbl_tickets 
            WHERE status = 'Completed' AND DATE(service_end_time) = CURDATE()
        ")->fetchColumn();
        
        if ($total == 0) return 100;
        
        $withinTarget = $this->pdo->query("
            SELECT COUNT(*) FROM tbl_tickets 
            WHERE status = 'Completed' 
            AND DATE(service_end_time) = CURDATE()
            AND actual_wait_time <= $targetMinutes
        ")->fetchColumn();
        
        return round(($withinTarget / $total) * 100, 2);
    }
    
    /**
     * Get comprehensive metrics report
     */
    public function getFullReport() {
        return [
            'utilization_rate' => round($this->calculateUtilization(), 2),
            'avg_queue_length' => round($this->calculateAverageQueueLength(), 2),
            'avg_waiting_time' => round($this->calculateAverageWaitingTime(), 2),
            'throughput_per_hour' => $this->calculateThroughput(),
            'service_level_15min' => $this->calculateServiceLevel(15),
            'service_level_30min' => $this->calculateServiceLevel(30),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getActiveWindowCount() {
        return $this->pdo->query("
            SELECT COUNT(DISTINCT assigned_window) 
            FROM tbl_tickets 
            WHERE DATE(service_start_time) = CURDATE()
        ")->fetchColumn() ?? 3; // Default to 3
    }
}
?>
