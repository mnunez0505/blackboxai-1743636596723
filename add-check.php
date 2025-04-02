<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

// Initialize variables
$error = '';
$success = '';
$clients = [];
$invoices = [];

// Get clients and invoices for dropdowns
try {
    $stmt = $conn->query("SELECT id, name FROM clients ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->query("SELECT id, client_id, amount FROM invoices ORDER BY id");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkNumber = trim($_POST['check_number']);
    $beneficiary = trim($_POST['beneficiary']);
    $amount = (float)$_POST['amount'];
    $issueDate = $_POST['issue_date'];
    $clientId = (int)$_POST['client_id'];
    $invoiceId = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
    $status = 'ingresado'; // Default status

    // Validate inputs
    if (empty($checkNumber) || empty($beneficiary) || $amount <= 0 || empty($issueDate) || $clientId <= 0) {
        $error = "Todos los campos requeridos deben ser completados correctamente";
    } else {
        try {
            // Insert check
            $stmt = $conn->prepare("
                INSERT INTO checks 
                (check_number, beneficiary, amount, issue_date, client_id, invoice_id, status) 
                VALUES 
                (:check_number, :beneficiary, :amount, :issue_date, :client_id, :invoice_id, :status)
            ");
            
            $stmt->bindParam(':check_number', $checkNumber);
            $stmt->bindParam(':beneficiary', $beneficiary);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':issue_date', $issueDate);
            $stmt->bindParam(':client_id', $clientId);
            $stmt->bindParam(':invoice_id', $invoiceId);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $checkId = $conn->lastInsertId();
                
                // Record initial status in history
                $stmt = $conn->prepare("
                    INSERT INTO check_history 
                    (check_id, old_status, new_status, change_date, user_id) 
                    VALUES 
                    (:check_id, '', :status, NOW(), :user_id)
                ");
                $stmt->bindParam(':check_id', $checkId);
                $stmt->bindParam(':status', $status);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                
                $success = "Cheque registrado exitosamente";
                $_POST = []; // Clear form
            }
        } catch(PDOException $e) {
            $error = "Error al registrar el cheque: " . $e->getMessage();
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Registrar Nuevo Cheque</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="check_number" class="form-label">Número de Cheque*</label>
                                <input type="text" class="form-control" id="check_number" name="check_number" 
                                       value="<?php echo htmlspecialchars($_POST['check_number'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="beneficiary" class="form-label">Beneficiario*</label>
                                <input type="text" class="form-control" id="beneficiary" name="beneficiary" 
                                       value="<?php echo htmlspecialchars($_POST['beneficiary'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="amount" class="form-label">Valor*</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="issue_date" class="form-label">Fecha de Emisión*</label>
                                <input type="date" class="form-control" id="issue_date" name="issue_date" 
                                       value="<?php echo htmlspecialchars($_POST['issue_date'] ?? date('Y-m-d')); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="client_id" class="form-label">Cliente*</label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value="">Seleccionar cliente</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>" 
                                        <?php echo ($_POST['client_id'] ?? '') == $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="invoice_id" class="form-label">Factura (Opcional)</label>
                                <select class="form-select" id="invoice_id" name="invoice_id">
                                    <option value="">No asociar a factura</option>
                                    <?php foreach ($invoices as $invoice): ?>
                                    <option value="<?php echo $invoice['id']; ?>" 
                                        <?php echo ($_POST['invoice_id'] ?? '') == $invoice['id'] ? 'selected' : ''; ?>>
                                        #<?php echo $invoice['id']; ?> - $<?php echo number_format($invoice['amount'], 2); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cheque
                                </button>
                                <a href="checks.php" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Dynamic invoice filtering based on selected client
document.getElementById('client_id').addEventListener('change', function() {
    const clientId = this.value;
    const invoiceSelect = document.getElementById('invoice_id');
    
    // Reset invoice select
    invoiceSelect.innerHTML = '<option value="">No asociar a factura</option>';
    
    if (clientId) {
        // Filter invoices for selected client
        <?php 
        echo "const invoices = " . json_encode($invoices) . ";\n";
        ?>
        
        const clientInvoices = invoices.filter(invoice => invoice.client_id == clientId);
        
        clientInvoices.forEach(invoice => {
            const option = document.createElement('option');
            option.value = invoice.id;
            option.textContent = `#${invoice.id} - $${invoice.amount.toFixed(2)}`;
            invoiceSelect.appendChild(option);
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>