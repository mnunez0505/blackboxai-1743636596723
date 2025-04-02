<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Get invoice details
    $stmt = $conn->prepare("
        SELECT i.*, c.name as client_name, c.contact, c.email
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

    // Get associated checks
    $stmt = $conn->prepare("
        SELECT id, check_number, amount, status, issue_date
        FROM checks
        WHERE invoice_id = :invoice_id
        ORDER BY issue_date DESC
    ");
    $stmt->bindParam(':invoice_id', $invoiceId);
    $stmt->execute();
    $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate paid amount
    $paidAmount = 0;
    foreach ($checks as $check) {
        if ($check['status'] === 'depositado') {
            $paidAmount += $check['amount'];
        }
    }
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Factura #<?php echo $invoiceId; ?></h2>
        <div>
            <a href="edit-invoice.php?id=<?php echo $invoiceId; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="invoices.php" class="btn btn-secondary">
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
                    <h5 class="mb-0">Información de Factura</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Cliente:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?php echo htmlspecialchars($invoice['client_name']); ?>
                            </p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Monto:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                $<?php echo number_format($invoice['amount'], 2); ?>
                            </p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Pagado:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                $<?php echo number_format($paidAmount, 2); ?>
                            </p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Saldo:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                $<?php echo number_format($invoice['amount'] - $paidAmount, 2); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Detalles Adicionales</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Estado:</label>
                        <div class="col-sm-8">
                            <?php 
                            $statusClass = [
                                'pendiente' => 'bg-warning',
                                'pagada' => 'bg-success',
                                'cancelada' => 'bg-danger'
                            ][$invoice['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?php echo $statusClass; ?>">
                                <?php echo ucfirst($invoice['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Vencimiento:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Descripción:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?php echo $invoice['description'] ? htmlspecialchars($invoice['description']) : 'N/A'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Associated Checks -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Cheques Asociados</h5>
                <a href="add-check.php?invoice_id=<?php echo $invoiceId; ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-plus"></i> Asociar Cheque
                </a>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($checks)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Monto</th>
                            <th>Estado</th>
                            <th>Fecha</th>
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
                            <td><?php echo htmlspecialchars($check['check_number']); ?></td>
                            <td>$<?php echo number_format($check['amount'], 2); ?></td>
                            <td>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($check['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($check['issue_date'])); ?></td>
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
            <?php else: ?>
            <p class="text-center text-muted">No hay cheques asociados a esta factura</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>