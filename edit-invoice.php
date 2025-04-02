<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Get invoice details
try {
    $stmt = $conn->prepare("
        SELECT i.*, c.name as client_name 
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.id = :id
    ");
    $stmt->bindParam(':id', $invoiceId);
    $stmt->execute();
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception("Factura no encontrada");
    }

    // Get clients for dropdown
    $stmt = $conn->query("SELECT id, name FROM clients ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int)$_POST['client_id'];
    $amount = (float)$_POST['amount'];
    $dueDate = $_POST['due_date'];
    $description = trim($_POST['description']);
    $status = $_POST['status'];

    // Validate inputs
    if ($clientId <= 0 || $amount <= 0 || empty($dueDate)) {
        $error = "Todos los campos requeridos deben ser completados correctamente";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE invoices SET
                client_id = :client_id,
                amount = :amount,
                due_date = :due_date,
                description = :description,
                status = :status
                WHERE id = :id
            ");
            $stmt->bindParam(':client_id', $clientId);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':due_date', $dueDate);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $invoiceId);
            
            if ($stmt->execute()) {
                $success = "Factura actualizada exitosamente";
                // Refresh invoice data
                $stmt = $conn->prepare("SELECT * FROM invoices WHERE id = :id");
                $stmt->bindParam(':id', $invoiceId);
                $stmt->execute();
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch(PDOException $e) {
            $error = "Error al actualizar factura: " . $e->getMessage();
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
                    <h4 class="mb-0">Editar Factura #<?php echo $invoiceId; ?></h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if (isset($invoice)): ?>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="client_id" class="form-label">Cliente*</label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value="">Seleccionar cliente</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>"
                                        <?php echo $invoice['client_id'] == $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="amount" class="form-label">Monto*</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           step="0.01" min="0" value="<?php echo htmlspecialchars($invoice['amount']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="due_date" class="form-label">Fecha Vencimiento*</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" 
                                       value="<?php echo htmlspecialchars($invoice['due_date']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="status" class="form-label">Estado*</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="pendiente" <?php echo $invoice['status'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                                    <option value="pagada" <?php echo $invoice['status'] === 'pagada' ? 'selected' : ''; ?>>Pagada</option>
                                    <option value="cancelada" <?php echo $invoice['status'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="description" name="description" rows="2"><?php 
                                    echo htmlspecialchars($invoice['description']); 
                                ?></textarea>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cambios
                                </button>
                                <a href="view-invoice.php?id=<?php echo $invoiceId; ?>" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>