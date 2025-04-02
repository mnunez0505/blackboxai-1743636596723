<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

$checkId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Get check details and related data
try {
    // Get check details
    $stmt = $conn->prepare("
        SELECT c.*, cl.name as client_name 
        FROM checks c
        LEFT JOIN clients cl ON c.client_id = cl.id
        WHERE c.id = :id
    ");
    $stmt->bindParam(':id', $checkId);
    $stmt->execute();
    $check = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$check) {
        throw new Exception("Cheque no encontrado");
    }

    // Get clients and invoices
    $stmt = $conn->query("SELECT id, name FROM clients ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("SELECT id, client_id, amount FROM invoices WHERE client_id = :client_id ORDER BY id");
    $stmt->bindParam(':client_id', $check['client_id']);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get status history
    $stmt = $conn->prepare("
        SELECT new_status 
        FROM check_history 
        WHERE check_id = :id 
        ORDER BY change_date DESC 
        LIMIT 1
    ");
    $stmt->bindParam(':id', $checkId);
    $stmt->execute();
    $currentStatus = $stmt->fetchColumn();
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $checkNumber = trim($_POST['check_number']);
    $beneficiary = trim($_POST['beneficiary']);
    $amount = (float)$_POST['amount'];
    $issueDate = $_POST['issue_date'];
    $clientId = (int)$_POST['client_id'];
    $invoiceId = !empty($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : null;
    $newStatus = $_POST['status'];

    // Validate inputs
    if (empty($checkNumber) || empty($beneficiary) || $amount <= 0 || empty($issueDate) || $clientId <= 0) {
        $error = "Todos los campos requeridos deben ser completados correctamente";
    } else {
        try {
            // Begin transaction
            $conn->beginTransaction();

            // Update check details
            $stmt = $conn->prepare("
                UPDATE checks SET
                check_number = :check_number,
                beneficiary = :beneficiary,
                amount = :amount,
                issue_date = :issue_date,
                client_id = :client_id,
                invoice_id = :invoice_id
                WHERE id = :id
            ");
            
            $stmt->bindParam(':check_number', $checkNumber);
            $stmt->bindParam(':beneficiary', $beneficiary);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':issue_date', $issueDate);
            $stmt->bindParam(':client_id', $clientId);
            $stmt->bindParam(':invoice_id', $invoiceId);
            $stmt->bindParam(':id', $checkId);
            $stmt->execute();

            // Check if status changed
            if ($newStatus !== $currentStatus) {
                $stmt = $conn->prepare("
                    UPDATE checks SET status = :status WHERE id = :id
                ");
                $stmt->bindParam(':status', $newStatus);
                $stmt->bindParam(':id', $checkId);
                $stmt->execute();

                // Record status change in history
                $stmt = $conn->prepare("
                    INSERT INTO check_history 
                    (check_id, old_status, new_status, change_date, user_id) 
                    VALUES 
                    (:check_id, :old_status, :new_status, NOW(), :user_id)
                ");
                $stmt->bindParam(':check_id', $checkId);
                $stmt->bindParam(':old_status', $currentStatus);
                $stmt->bindParam(':new_status', $newStatus);
                $stmt->bindParam(':user_id', $_SESSION['user_id']);
                $stmt->execute();
            }

            $conn->commit();
            $success = "Cheque actualizado exitosamente";
            
            // Refresh check data
            $stmt = $conn->prepare("SELECT * FROM checks WHERE id = :id");
            $stmt->bindParam(':id', $checkId);
            $stmt->execute();
            $check = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            $conn->rollBack();
            $error = "Error al actualizar el cheque: " . $e->getMessage();
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Editar Cheque #<?php echo htmlspecialchars($check['check_number']); ?></h2>
        <a href="view-check.php?id=<?php echo $checkId; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (isset($check)): ?>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="check_number" class="form-label">Número de Cheque*</label>
                <input type="text" class="form-control" id="check_number" name="check_number" 
                       value="<?php echo htmlspecialchars($check['check_number']); ?>" required>
            </div>
            
            <div class="col-md-6">
                <label for="beneficiary" class="form-label">Beneficiario*</label>
                <input type="text" class="form-control" id="beneficiary" name="beneficiary" 
                       value="<?php echo htmlspecialchars($check['beneficiary']); ?>" required>
            </div>
            
            <div class="col-md-6">
                <label for="amount" class="form-label">Valor*</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="amount" name="amount" 
                           step="0.01" min="0" value="<?php echo htmlspecialchars($check['amount']); ?>" required>
                </div>
            </div>
            
            <div class="col-md-6">
                <label for="issue_date" class="form-label">Fecha de Emisión*</label>
                <input type="date" class="form-control" id="issue_date" name="issue_date" 
                       value="<?php echo htmlspecialchars($check['issue_date']); ?>" required>
            </div>
            
            <div class="col-md-6">
                <label for="client_id" class="form-label">Cliente*</label>
                <select class="form-select" id="client_id" name="client_id" required>
                    <option value="">Seleccionar cliente</option>
                    <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>" 
                        <?php echo $check['client_id'] == $client['id'] ? 'selected' : ''; ?>>
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
                        <?php echo $check['invoice_id'] == $invoice['id'] ? 'selected' : ''; ?>>
                        #<?php echo $invoice['id']; ?> - $<?php echo number_format($invoice['amount'], 2); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-6">
                <label for="status" class="form-label">Estado*</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="ingresado" <?php echo $check['status'] === 'ingresado' ? 'selected' : ''; ?>>Ingresado</option>
                    <option value="depositado" <?php echo $check['status'] === 'depositado' ? 'selected' : ''; ?>>Depositado</option>
                    <option value="protestado" <?php echo $check['status'] === 'protestado' ? 'selected' : ''; ?>>Protestado</option>
                </select>
            </div>
            
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Guardar Cambios
                </button>
                <a href="view-check.php?id=<?php echo $checkId; ?>" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-times me-1"></i> Cancelar
                </a>
            </div>
        </div>
    </form>

    <script>
    // Dynamic invoice filtering based on selected client
    document.getElementById('client_id').addEventListener('change', function() {
        const clientId = this.value;
        const invoiceSelect = document.getElementById('invoice_id');
        
        if (clientId) {
            fetch(`get-invoices.php?client_id=${clientId}`)
                .then(response => response.json())
                .then(invoices => {
                    invoiceSelect.innerHTML = '<option value="">No asociar a factura</option>';
                    invoices.forEach(invoice => {
                        const option = document.createElement('option');
                        option.value = invoice.id;
                        option.textContent = `#${invoice.id} - $${invoice.amount.toFixed(2)}`;
                        invoiceSelect.appendChild(option);
                    });
                });
        } else {
            invoiceSelect.innerHTML = '<option value="">No asociar a factura</option>';
        }
    });
    </script>

    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>