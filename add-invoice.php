<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

$error = '';
$success = '';
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

// Get clients for dropdown
try {
    $stmt = $conn->query("SELECT id, name FROM clients ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error al cargar clientes: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = (int)$_POST['client_id'];
    $amount = (float)$_POST['amount'];
    $dueDate = $_POST['due_date'];
    $description = trim($_POST['description']);
    $status = 'pendiente'; // Default status

    // Validate inputs
    if ($clientId <= 0 || $amount <= 0 || empty($dueDate)) {
        $error = "Todos los campos requeridos deben ser completados correctamente";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO invoices 
                (client_id, amount, due_date, description, status)
                VALUES 
                (:client_id, :amount, :due_date, :description, :status)
            ");
            $stmt->bindParam(':client_id', $clientId);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':due_date', $dueDate);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':status', $status);
            
            if ($stmt->execute()) {
                $success = "Factura registrada exitosamente";
                if (isset($_GET['client_id'])) {
                    header("Location: view-client.php?id=$clientId");
                    exit();
                } else {
                    $_POST = []; // Clear form
                }
            }
        } catch(PDOException $e) {
            $error = "Error al registrar factura: " . $e->getMessage();
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
                    <h4 class="mb-0">Registrar Nueva Factura</h4>
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
                                <label for="client_id" class="form-label">Cliente*</label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value="">Seleccionar cliente</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client['id']; ?>"
                                        <?php echo ($clientId == $client['id'] || (isset($_POST['client_id']) && $_POST['client_id'] == $client['id'])) ? 'selected' : ''; ?>>
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
                                           step="0.01" min="0" value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="due_date" class="form-label">Fecha Vencimiento*</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" 
                                       value="<?php echo htmlspecialchars($_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'))); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="status" class="form-label">Estado</label>
                                <select class="form-select" id="status" name="status" disabled>
                                    <option>Pendiente</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="description" name="description" rows="2"><?php 
                                    echo htmlspecialchars($_POST['description'] ?? ''); 
                                ?></textarea>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Factura
                                </button>
                                <a href="<?php echo $clientId ? "view-client.php?id=$clientId" : 'invoices.php'; ?>" 
                                   class="btn btn-secondary ms-2">
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

<?php include 'includes/footer.php'; ?>