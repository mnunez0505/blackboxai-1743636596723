<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Get client details
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = :id");
    $stmt->bindParam(':id', $clientId);
    $stmt->execute();
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        throw new Exception("Cliente no encontrado");
    }

    // Get client's invoices
    $stmt = $conn->prepare("
        SELECT id, amount, due_date 
        FROM invoices 
        WHERE client_id = :client_id
        ORDER BY due_date DESC
    ");
    $stmt->bindParam(':client_id', $clientId);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get client's checks
    $stmt = $conn->prepare("
        SELECT c.id, c.check_number, c.amount, c.status, c.issue_date
        FROM checks c
        WHERE c.client_id = :client_id
        ORDER BY c.issue_date DESC
        LIMIT 5
    ");
    $stmt->bindParam(':client_id', $clientId);
    $stmt->execute();
    $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Detalles del Cliente</h2>
        <div>
            <a href="edit-client.php?id=<?php echo $clientId; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="clients.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php else: ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Información del Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Nombre:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($client['name']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Contacto:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($client['contact']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Email:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($client['email']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Dirección:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($client['address']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Resumen Financiero</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-6 col-form-label">Total Facturas:</label>
                        <div class="col-sm-6">
                            <p class="form-control-plaintext"><?php echo count($invoices); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-6 col-form-label">Total Cheques:</label>
                        <div class="col-sm-6">
                            <p class="form-control-plaintext"><?php echo count($checks); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Últimas Facturas</h5>
                        <a href="add-invoice.php?client_id=<?php echo $clientId; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-plus"></i> Nueva
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($invoices)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Monto</th>
                                    <th>Vencimiento</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?php echo $invoice['id']; ?></td>
                                    <td>$<?php echo number_format($invoice['amount'], 2); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                                    <td>
                                        <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end">
                        <a href="invoices.php?client_id=<?php echo $clientId; ?>" class="btn btn-sm btn-primary">
                            Ver todas las facturas
                        </a>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted">No hay facturas registradas</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Últimos Cheques</h5>
                        <a href="add-check.php?client_id=<?php echo $clientId; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-plus"></i> Nuevo
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($checks)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checks as $check): 
                                    $statusClass = [
                                        'ingresado' => 'bg-primary',
                                        'depositado' => 'bg-success',
                                        'protestado' => 'bg-danger'
                                    ][$check['status']] ?? 'bg-secondary';
                                ?>
                                <tr>
                                    <td><?php echo $check['check_number']; ?></td>
                                    <td>$<?php echo number_format($check['amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($check['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-check.php?id=<?php echo $check['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end">
                        <a href="checks.php?client_id=<?php echo $clientId; ?>" class="btn btn-sm btn-primary">
                            Ver todos los cheques
                        </a>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted">No hay cheques registrados</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>