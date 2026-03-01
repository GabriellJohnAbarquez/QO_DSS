<?php
/**
 * Decision Support System Analytics
 * Forecasting, trend analysis, and intelligent recommendations
 */

require_once __DIR__ . '/../dbconfig.php';

class DSSAnalytics {
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo ?? getDB();
    }
    
    /**
     * Generate short-term forecast for next 2 hours
     * Based on historical patterns
     */
    public function generateForecast() {
        $forecasts = [];
        $currentHour = date('H');
        
        // Get historical averages for same day/hour
        for ($i = 0; $i < 2; $i++) { // Next 2 hours
            $targetHour = ($currentHour + $i) % 24;
            $dayOfWeek = date('N'); // 1-7
            
            $sql = "SELECT 
                    AVG(arrival_count) as avg_arrivals,
                    AVG(avg_wait_time) as avg_wait,
                    AVG(utilization) as avg_util
                    FROM tbl_hourly_stats
                    WHERE hour_of_day = ? AND day_of_week = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$targetHour, $dayOfWeek]);
            $historical = $stmt->fetch();
            
            // Adjust based on current trend
            $currentTrend = $this->getCurrentTrend();
            $adjustedArrivals = ($historical['avg_arrivals'] ?? 20) * $currentTrend;
            
            $forecasts[] = [
                'hour' => $targetHour,
                'predicted_arrivals' => round($adjustedArrivals),
                'predicted_avg_wait' => round($historical['avg_wait'] ?? 10),
                'predicted_utilization' => min(1.0, ($historical['avg_util'] ?? 0.7) * $currentTrend),
                'confidence' => $this->calculateConfidence($targetHour, $dayOfWeek)
            ];
        }
        
        return $forecasts;
    }
    
    /**
     * Analyze current trend (increasing, decreasing, stable)
     */
    private function getCurrentTrend() {
        // Compare last 30 minutes to previous 30 minutes
        $sql = "SELECT 
                SUM(CASE WHEN arrival_timestamp >= DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN 1 ELSE 0 END) as recent,
                SUM(CASE WHEN arrival_timestamp BETWEEN DATE_SUB(NOW(), INTERVAL 60 MINUTE) AND DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN 1 ELSE 0 END) as previous
                FROM tbl_tickets
                WHERE arrival_timestamp >= DATE_SUB(NOW(), INTERVAL 60 MINUTE)";
        
        $result = $this->pdo->query($sql)->fetch();
        
        if ($result['previous'] == 0) return 1.0;
        
        $ratio = $result['recent'] / $result['previous'];
        
        // Smooth the ratio
        if ($ratio > 1.5) return 1.3;      // Rapidly increasing
        if ($ratio > 1.2) return 1.15;     // Increasing
        if ($ratio < 0.7) return 0.8;      // Decreasing
        if ($ratio < 0.9) return 0.95;     // Slightly decreasing
        return 1.0;                         // Stable
    }
    
    /**
     * Generate staffing recommendations
     */
    public function getStaffingRecommendations() {
        $metrics = $this->getCurrentMetrics();
        $recommendations = [];
        
        // Rule 1: High utilization + long queues
        if ($metrics['utilization'] > 0.85 && $metrics['queue_length'] > 20) {
            $recommendations[] = [
                'type' => 'STAFFING',
                'priority' => 'HIGH',
                'message' => 'All windows at high utilization with significant queue',
                'action' => 'Open additional window immediately',
                'estimated_impact' => 'Reduce wait time by ~40%'
            ];
        }
        
        // Rule 2: Imbalanced workload
        $utilizationStdDev = $this->calculateUtilizationVariance();
        if ($utilizationStdDev > 0.2) {
            $recommendations[] = [
                'type' => 'REBALANCE',
                'priority' => 'MEDIUM',
                'message' => 'Uneven distribution across windows detected',
                'action' => 'Reassign tier eligibility or redirect customers',
                'affected_windows' => $this->getImbalancedWindows()
            ];
        }
        
        // Rule 3: Predicted surge
        $forecast = $this->generateForecast();
        if ($forecast[0]['predicted_arrivals'] > 30) {
            $recommendations[] = [
                'type' => 'PREDICTIVE',
                'priority' => 'MEDIUM',
                'message' => 'High arrival volume predicted in next hour',
                'action' => 'Pre-position staff, activate peak mode readiness',
                'predicted_arrivals' => $forecast[0]['predicted_arrivals']
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get bottleneck analysis
     */
    public function analyzeBottlenecks() {
        $bottlenecks = [];
        
        // Service time bottleneck
        $slowTransactions = $this->pdo->query("
            SELECT transaction_category, 
                   AVG(actual_service_time) as avg_time,
                   COUNT(*) as frequency
            FROM tbl_tickets
            WHERE DATE(service_end_time) = CURDATE()
            AND status = 'Completed'
            GROUP BY transaction_category
            HAVING avg_time > 15
            ORDER BY avg_time DESC
        ")->fetchAll();
        
        if (!empty($slowTransactions)) {
            $bottlenecks[] = [
                'type' => 'SERVICE_TIME',
                'description' => 'Slow transaction types detected',
                'details' => $slowTransactions,
                'recommendation' => 'Consider pre-processing or dedicated windows for complex transactions'
            ];
        }
        
        // Window-specific bottleneck
        $windowPerformance = $this->pdo->query("
            SELECT assigned_window,
                   AVG(actual_wait_time) as avg_wait,
                   COUNT(*) as served
            FROM tbl_tickets
            WHERE DATE(service_end_time) = CURDATE()
            GROUP BY assigned_window
            HAVING avg_wait > 20
        ")->fetchAll();
        
        if (!empty($windowPerformance)) {
            $bottlenecks[] = [
                'type' => 'WINDOW_PERFORMANCE',
                'description' => 'Specific windows showing delays',
                'details' => $windowPerformance
            ];
        }
        
        return $bottlenecks;
    }
    
    private function getCurrentMetrics() {
        $waiting = $this->pdo->query("
            SELECT COUNT(*) FROM tbl_tickets WHERE status = 'Waiting'
        ")->fetchColumn();
        
        $serving = $this->pdo->query("
            SELECT COUNT(*) FROM tbl_tickets WHERE status = 'Serving'
        ")->fetchColumn();
        
        $totalWindows = $this->pdo->query("
            SELECT COUNT(DISTINCT window_id) FROM tbl_window_queues
        ")->fetchColumn();
        
        return [
            'queue_length' => $waiting,
            'active_service' => $serving,
            'total_windows' => max(1, $totalWindows),
            'utilization' => $serving / max(1, $totalWindows)
        ];
    }
    
    private function calculateUtilizationVariance() {
        // Calculate standard deviation of window utilization
        $windows = $this->pdo->query("
            SELECT window_id, 
                   COUNT(*) as ticket_count,
                   AVG(TIMESTAMPDIFF(MINUTE, added_to_queue, COALESCE(served_at, NOW()))) as avg_time
            FROM tbl_window_queues
            WHERE DATE(added_to_queue) = CURDATE()
            GROUP BY window_id
        ")->fetchAll();
        
        if (count($windows) < 2) return 0;
        
        $utilizations = array_column($windows, 'ticket_count');
        $mean = array_sum($utilizations) / count($utilizations);
        
        $variance = 0;
        foreach ($utilizations as $val) {
            $variance += pow($val - $mean, 2);
        }
        
        return sqrt($variance / count($utilizations));
    }
    
    private function getImbalancedWindows() {
        // Implementation for identifying which windows are overloaded/underloaded
        return []; // Placeholder
    }
    
    private function calculateConfidence($hour, $day) {
        // Calculate confidence based on historical data availability
        $count = $this->pdo->prepare("
            SELECT COUNT(*) FROM tbl_hourly_stats WHERE hour_of_day = ? AND day_of_week = ?
        ")->execute([$hour, $day])->fetchColumn();
        
        if ($count > 10) return 'HIGH';
        if ($count > 5) return 'MEDIUM';
        return 'LOW';
    }
}
?>
