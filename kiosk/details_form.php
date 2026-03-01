<?php
/**
 * Step 2: Client Details Form
 */

$tier = $_GET['tier'] ?? 'P4';
$priority = $_GET['priority'] ?? 'General';
$category = $_GET['category'] ?? 'General';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Enter Details - QO-DSS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="text-center mb-4">Enter Your Details</h2>
            
            <div class="alert alert-info text-center mb-4">
                <strong>Transaction Type:</strong> 
                <span class="badge bg-<?= $tier === 'P0' ? 'danger' : ($tier === 'P1' ? 'warning' : 'secondary') ?>">
                    <?= $tier ?>
                </span>
                <?php if ($category !== 'General'): ?>
                    <br><small>Priority Category: <?= $category ?></small>
                <?php endif; ?>
            </div>
            
            <form action="ticket_display.php" method="POST" id="detailsForm">
                <input type="hidden" name="tier" value="<?= htmlspecialchars($tier) ?>">
                <input type="hidden" name="client_category" value="<?= htmlspecialchars($category) ?>">
                
                <div class="mb-3">
                    <label class="form-label">I am a:</label>
                    <select name="client_type" class="form-select form-select-lg" required>
                        <option value="Student">Student</option>
                        <option value="Parent">Parent/Guardian</option>
                        <option value="Faculty">Faculty</option>
                        <option value="Staff">Staff</option>
                        <option value="Visitor">Visitor</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Student ID (if applicable)</label>
                    <input type="text" name="student_id" class="form-control" 
                           placeholder="e.g., 2021-00001">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" name="contact" class="form-control" 
                           placeholder="09XX XXX XXXX">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Transaction Details</label>
                    <textarea name="transaction_details" class="form-control" rows="3" 
                              placeholder="Brief description of your transaction..."></textarea>
                </div>
                
                <?php if (in_array($tier, ['P1', 'P2'])): ?>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="exactCash" name="exact_cash" value="1">
                    <label class="form-check-label" for="exactCash">
                        I have exact amount (no change needed)
                    </label>
                </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Generate Ticket
                    </button>
                    <a href="../index.php" class="btn btn-outline-secondary">← Back</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
