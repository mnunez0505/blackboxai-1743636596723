<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

$checkId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Get check details
    $stmt = $conn->prepare("
        SELECT c.*, cl.name as client_name, i.id as invoice_number
        FROM checks c
        LEFT JOIN clients cl ON c.client_id = cl.id
        LEFT JOIN invoices i ON c.invoice_id = i.id
        WHERE c.id = :id
    ");
    $stmt->bindParam(':id', $checkId);
    $stmt->execute();
    $check = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$check) {
        throw new Exception("Cheque no encontrado");
    }

    // Get check history
    $stmt = $conn->prepare("
        SELECT h.*, u.username 
        FROM check_history h
        LEFT JOIN users u ON h.user_id = u.id
        WHERE h.check_id = :id
        ORDER BY h.change_date DESC
    ");
    $stmt->bindParam(':id', $checkId);
    $stmt->execute();
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Detalle del Cheque</h2>
        <a href="checks.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php else: ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Información Básica</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Número:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($check['check_number']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Beneficiario:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($check['beneficiary']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Cliente:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($check['client_name']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Factura:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">
                                <?php echo $check['invoice_number'] ? '#' . $check['invoice_number'] : 'N/A'; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Detalles Financieros</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Valor:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext">$<?php echo number_format($check['amount'], 2); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Fecha Emisión:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo date('d/m/Y', strtotime($check['issue_date'])); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Estado:</label>
                        <div class="col-sm-8">
                            <?php 
                            $statusClass = [
                                'ingresado' => 'bg-primary',
                                'depositado' => 'bg-success',
                                'protestado' => 'bg-danger'
                            ][$check['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?php echo $statusClass; ?>">
                                <?php echo ucfirst($check['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Historial de Cambios</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Estado Anterior</th>
                            <th>Estado Nuevo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $record): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($record['change_date'])); ?></td>
                            <td><?php echo htmlspecialchars($record['username']); ?></td>
                            <td>
                                <?php if ($record['old_status']): ?>
                                <span class="badge bg-secondary"><?php echo ucfirst($record['old_status']); ?></span>
                                <?php else: ?>
                                <em>Nuevo</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge 
                                    <?php echo [
                                        'ingresado' => 'bg-primary',
                                        'depositado' => 'bg-success',
                                        'protestado' => 'bg-danger'
                                    ][$record['new_status']] ?? 'bg-secondary'; ?>">
                                    <?php echo ucfirst($record['new_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="4" class="text-center">No hay historial registrado</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>