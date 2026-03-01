<?php
/**
 * Ticket Generation Utilities
 * QR codes, barcodes, formatting
 */

class TicketGenerator {
    
    /**
     * Generate QR code data for ticket
     */
    public static function generateQRData($ticketId, $tier) {
        $data = [
            'id' => $ticketId,
            'tier' => $tier,
            'generated' => time(),
            'checksum' => self::generateChecksum($ticketId)
        ];
        return base64_encode(json_encode($data));
    }
    
    /**
     * Verify ticket QR data
     */
    public static function verifyQRData($qrString) {
        try {
            $data = json_decode(base64_decode($qrString), true);
            if (!$data) return false;
            
            $expectedChecksum = self::generateChecksum($data['id']);
            return $data['checksum'] === $expectedChecksum;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Format ticket ID for display
     */
    public static function formatForDisplay($ticketId) {
        // T-20240301-0001 -> T-0301-0001 (shorter for display)
        return preg_replace('/^T-\d{4}/', 'T', $ticketId);
    }
    
    /**
     * Generate audio announcement text
     */
    public static function generateAnnouncement($ticketId, $windowId) {
        $shortId = self::formatForDisplay($ticketId);
        $numbers = str_split(preg_replace('/[^0-9]/', '', $shortId));
        $spelledNumbers = implode(' ', $numbers);
        
        return "Ticket $spelledNumbers, please proceed to window $windowId";
    }
    
    private static function generateChecksum($ticketId) {
        return substr(md5($ticketId . 'EAC_SECRET_KEY'), 0, 8);
    }
}
?>
